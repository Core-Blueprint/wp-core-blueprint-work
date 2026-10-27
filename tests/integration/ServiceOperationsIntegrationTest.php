<?php
declare(strict_types=1);

use CB\Work\Capabilities;
use CB\Work\Content\PostTypes;
use CB\Work\Content\ProjectMeta;
use CB\Work\Database\Schema;
use CB\Work\PublicApi\ServiceOperations as ReadApi;
use CB\Work\PublicApi\ServiceBillingActions;
use CB\Work\Repository\RecurrenceRules;
use CB\Work\ServiceOperations\Engagements;
use CB\Work\ServiceOperations\Periods;
use CB\Work\ServiceOperations\ServiceBilling;

/** Real WordPress/MariaDB behavior: isolated Work test DB only. */
final class ServiceOperationsIntegrationTest extends WP_UnitTestCase {
    private int $actor;
    private int $project;
    private int $service;

    public function set_up(): void {
        if (!defined('DB_NAME') || DB_NAME !== 'core_blueprint_work_test'
            || !defined('DB_HOST') || DB_HOST !== '127.0.0.1:3307') {
            self::fail('Service Operations tests require the canonical isolated Work test DB.');
        }
        parent::set_up();
        // Base's signed Privileged Access Protection restricts newly created
        // administrators until independently approved. Delegated Work managers
        // need the explicit application cap, not administrator privileges.
        $this->actor=self::factory()->user->create(['role'=>'subscriber']);
        $operator=get_userdata($this->actor);
        self::assertInstanceOf(WP_User::class,$operator);
        self::assertFalse(\CoreBlueprint\Core\Permissions\PrivilegedAccessPolicy::is_privileged($operator),
            'A delegated Work manager must not inherit administrator authority.');
        $operator->add_cap(Capabilities::MANAGE);
        wp_set_current_user($this->actor);
        self::assertTrue(\cb_work_runtime_ready(), 'Work and Base contracts must be available.');
        self::assertTrue(current_user_can(Capabilities::MANAGE),
            'An explicitly authorized, non-privileged Work manager must be allowed.');
        update_option(Schema::OPTION,CB_WORK_SCHEMA_VERSION);
        $this->project=self::factory()->post->create([
            'post_type'=>PostTypes::PROJECT,'post_status'=>'publish','post_title'=>'Client Project'
        ]);
        self::assertTrue(ProjectMeta::save($this->project,[
            'work_context'=>'customer','customer_provider'=>'crm','customer_type'=>'organization',
            'customer_id'=>'SO-CUSTOMER-101','starts_on'=>'2026-10-01'
        ]));
        ProjectMeta::mark_initialized($this->project);
        $this->service=self::factory()->post->create([
            'post_type'=>PostTypes::SERVICE,'post_status'=>'publish','post_title'=>'Periodic Security Reviews'
        ]);
        self::assertGreaterThan(0,$this->project);
        self::assertGreaterThan(0,$this->service);
    }

    private function engagement(string $start='2026-10-01'):int {
        $id=Engagements::create([
            'project_id'=>$this->project,'service_id'=>$this->service,
            'starts_on'=>$start,'label'=>'Monthly review'
        ],$this->actor);
        self::assertIsInt($id);
        self::assertGreaterThan(0,$id);
        return $id;
    }

    private function rule(string $start='2026-10-05'):int {
        $id=RecurrenceRules::create([
            'title'=>'Security audit', 'project_id'=>$this->project,'service_id'=>$this->service,
            'frequency'=>'monthly','interval_count'=>1,'start_on'=>$start,
            'is_active'=>true,'created_by'=>$this->actor,'updated_by'=>$this->actor
        ]);
        self::assertGreaterThan(0,$id);
        return $id;
    }

    public function test_schema_persistence_and_unique_binding():void {
        $engagement=$this->engagement();
        $rule=$this->rule();
        self::assertTrue(Engagements::bind_rule($engagement,$rule,'2026-10-01',$this->actor));
        self::assertTrue(Engagements::bind_rule($engagement,$rule,'2026-10-01',$this->actor));
        $other=$this->engagement();
        self::assertWPError(Engagements::bind_rule($other,$rule,'2026-10-01',$this->actor));
        self::assertCount(1,Engagements::rules($engagement));
        $listing=ReadApi::search(['project_id'=>$this->project]);
        self::assertIsArray($listing);
        self::assertSame(2,$listing['total']);
        $rule_edit=RecurrenceRules::update($rule,['service_id'=>0]);
        self::assertFalse($rule_edit,'Bound rule context cannot silently be reassigned.');
    }

    public function test_period_reports_missing_as_overdue_not_completed():void {
        $engagement=$this->engagement();
        $rule=$this->rule();
        self::assertTrue(Engagements::bind_rule($engagement,$rule,'2026-10-01',$this->actor));
        $period=Periods::resolve(Engagements::get($engagement),'2026-10','2026-11-02');
        self::assertIsArray($period);
        self::assertSame('overdue',$period['status']);
        self::assertSame(1,$period['scheduled']);
        self::assertSame(1,$period['missing']);
        self::assertSame(0,$period['completed']);
        self::assertSame('2026-11-05',$period['next_execution']);
    }

    public function test_quarterly_billing_independent_of_monthly_execution_and_cas():void {
        $engagement=$this->engagement();
        $rule=$this->rule();
        self::assertTrue(Engagements::bind_rule($engagement,$rule,'2026-10-01',$this->actor));
        $terms=ServiceBilling::create_terms($engagement,'quarterly','2026-10-01','advance',$this->actor);
        self::assertIsInt($terms);
        $oct=ServiceBilling::for_month($engagement,'2026-10');
        $dec=ServiceBilling::for_month($engagement,'2026-12');
        self::assertSame('2026-10-01',$oct['period_start']);
        self::assertSame($oct['period_start'],$dec['period_start']);
        self::assertSame('2027-01-01',$oct['period_end_exclusive']);
        $before=Periods::resolve(Engagements::get($engagement),'2026-10','2026-11-02');
        $created=ServiceBillingActions::record($engagement,'2026-10','invoiced','2026-10-02','INV-123',0);
        self::assertIsArray($created);
        self::assertSame('saved',$created['outcome']);
        self::assertSame(1,$created['registration']['revision']);
        $read=ServiceBilling::for_month($engagement,'2026-12');
        self::assertSame('invoiced',$read['state']);
        self::assertSame('INV-123',$read['registration']['external_reference']);
        $reused=ServiceBillingActions::record($engagement,'2026-10','invoiced','2026-10-02','INV-123',1);
        self::assertSame('reused',$reused['outcome']);
        $stale=ServiceBillingActions::record($engagement,'2026-10','not_recorded','', '',0);
        self::assertWPError($stale);
        self::assertSame('work_service_registration_stale',$stale->get_error_code());
        $corrected=ServiceBillingActions::record($engagement,'2026-10','not_recorded','', '',1);
        self::assertSame(2,$corrected['registration']['revision']);
        $after=Periods::resolve(Engagements::get($engagement),'2026-10','2026-11-02');
        self::assertSame($before['status'],$after['status']);
        global $wpdb;
        self::assertSame(2,(int)$wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM '.Schema::service_billing_events_table().' WHERE registration_id = %d',
            $created['registration']['id']
        )));
    }

    public function test_unapproved_privileged_identity_is_restricted_by_base():void {
        // Base's fail-closed signed-approval boundary applies before Work's
        // own capability check. Never fake approval or bypass a guard in tests.
        self::assertTrue(\CoreBlueprint\Core\Permissions\PrivilegedAccessPolicy::enforces_approval());
        $admin_id=self::factory()->user->create(['role'=>'administrator']);
        $admin=get_userdata($admin_id);
        self::assertInstanceOf(WP_User::class,$admin);
        $admin->add_cap(Capabilities::MANAGE);
        self::assertTrue(\CoreBlueprint\Core\Permissions\PrivilegedAccessPolicy::is_privileged($admin));
        self::assertFalse(\CoreBlueprint\Core\Permissions\PrivilegedAccessRegistry::is_approved($admin));
        wp_set_current_user($admin_id);
        self::assertFalse(current_user_can(Capabilities::MANAGE),
            'An administrator pending Base approval must not gain Work authority.');
        $result=ReadApi::search();
        self::assertWPError($result);
        self::assertSame('work_service_forbidden',$result->get_error_code());
    }

    public function test_first_run_guidance_blocks_creation_without_customer_project():void {
        wp_delete_post($this->project,true);
        wp_delete_post($this->service,true);
        ob_start();
        try {
            \CB\Work\Admin\ServiceOperations::render();
        } finally {
            $html=(string)ob_get_clean();
        }
        self::assertStringContainsString('cb-work-so-onboarding',$html);
        self::assertStringContainsString('post-new.php?post_type=cb_work_service',$html);
        self::assertStringNotContainsString('id="cb-work-so-add"',$html);
        self::assertStringNotContainsString('cb-work-so-cards',$html);
        self::assertStringNotContainsString('cb-work-so-table',$html);
        // An unavailable customer provider must not offer a dead-end
        // customer-Project creation action.
        if (!\CB\Work\Integration\CRMCustomers::available()) {
            self::assertStringContainsString('Customer selection is unavailable.',$html);
            self::assertStringNotContainsString('post-new.php?post_type=cb_work_project',$html);
        }
    }

    public function test_first_run_ready_and_filtered_zero_are_distinct():void {
        // The real WordPress posts seeded in set_up provide the prerequisites,
        // but no recurring service has been registered yet.
        ob_start();
        try {
            \CB\Work\Admin\ServiceOperations::render();
        } finally {
            $first=(string)ob_get_clean();
        }
        self::assertStringContainsString('cb-work-so-onboarding',$first);
        self::assertStringContainsString('id="cb-work-so-add"',$first);
        self::assertStringContainsString('data-cb-so-open',$first);
        self::assertStringNotContainsString('cb-work-so-filters',$first);

        $prior=$_GET;
        try {
            $_GET['cb_search']='no-service-matches-this-filter';
            ob_start();
            try {
                \CB\Work\Admin\ServiceOperations::render();
            } finally {
                $filtered=(string)ob_get_clean();
            }
        } finally {
            $_GET=$prior;
        }
        self::assertStringContainsString('cb-work-so-filters',$filtered);
        self::assertStringContainsString('Clear filters',$filtered);
        self::assertStringNotContainsString('cb-work-so-onboarding',$filtered);
    }

    public function test_manager_only_contracts_and_no_provider_dependencies():void {
        $engagement=$this->engagement();
        self::assertIsArray(ReadApi::period($engagement,'2026-10'));
        $user=self::factory()->user->create(['role'=>'subscriber']);
        wp_set_current_user($user);
        self::assertFalse(current_user_can(Capabilities::MANAGE),
            'A non-operator must not inherit the Work capability.');
        $search=ReadApi::search();
        self::assertWPError($search);
        self::assertSame('work_service_forbidden',$search->get_error_code());
        $period=ReadApi::period($engagement,'2026-10');
        self::assertWPError($period);
        self::assertSame('work_service_forbidden',$period->get_error_code());
        $mutation=ServiceBillingActions::record($engagement,'2026-10','invoiced','', '',0);
        self::assertWPError($mutation);
        self::assertSame('work_service_forbidden',$mutation->get_error_code());
    }
}

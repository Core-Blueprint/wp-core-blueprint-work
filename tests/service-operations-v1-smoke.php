<?php
declare(strict_types=1);

/** SO-P1..P5 source contract: no WordPress fixture or destructive provisioning. */
$root = dirname(__DIR__);
$read = static fn(string $path):string => (string)file_get_contents($root.'/'.$path);
$schema=$read('src/Database/Schema.php');
$entry=$read('core-blueprint-work.php');
$engagement=$read('src/ServiceOperations/Engagements.php');
$periods=$read('src/ServiceOperations/Periods.php');
$billing=$read('src/ServiceOperations/ServiceBilling.php');
$menu=$read('src/Admin/Menu.php');
$admin=$read('src/Admin/ServiceOperations.php');
$actions=$read('src/Admin/ServiceOperationsActions.php');
$api=$read('src/PublicApi/ServiceOperations.php');
$billingApi=$read('src/PublicApi/ServiceBillingActions.php');
$plugin=$read('src/Plugin.php');
$tests=[
    'rc1 product version is preserved' => str_contains($entry,"CB_WORK_VERSION', '1.0.0-rc1'"),
    'additive schema v2.4' => str_contains($entry,"CB_WORK_SCHEMA_VERSION', '2.4'"),
    'stable Engagement identity' => str_contains($schema,'cb_work_service_engagements'),
    'one rule cannot join two Engagements' => str_contains($schema,'PRIMARY KEY  (rule_id)'),
    'separate immutable billing terms' => str_contains($schema,'cb_work_service_billing_terms'),
    'coverage uniqueness' => str_contains($schema,'UNIQUE KEY term_period (billing_terms_id,period_start)'),
    'append-only correction history' => str_contains($schema,'UNIQUE KEY registration_revision (registration_id,revision)'),
    'billing units unchanged' => str_contains($schema,'UNIQUE KEY source_unit (unit_type,unit_id)'),
    'Project/Service references guarded' => str_contains($engagement,'Services::get') && str_contains($engagement,'Projects::get'),
    'recurrence relation effective date explicit' => str_contains($engagement,'effective_from'),
    'idempotent binding' => str_contains($engagement,'work_service_rule_already_bound'),
    'ledger source identity is validated' => str_contains($periods,'recurrence_occurrence') && str_contains($periods,'$source_valid'),
    'inactive rule does not assert missing history was scheduled' => str_contains($periods,'$potential') && str_contains($periods,'$unknown = true'),
    'period resolver is read-only' => !str_contains($periods,'wp_insert_post(') && !str_contains($periods,'::reserve_next_occurrence('),
    'period status considers missing' => str_contains($periods,"$"."counts['missing']"),
    'period status considers blocked and canceled' => str_contains($periods,'WorkItemStatus::BLOCKED') && str_contains($periods,'WorkItemStatus::CANCELLED'),
    'status never assumes missing is completed' => str_contains($periods,"$"."counts['completed'] === $"."counts['scheduled']"),
    'independent quarterly billing' => str_contains($billing,"'quarterly' === $"."freq"),
    'CAS protects correction' => str_contains($billing,'work_service_registration_stale') && str_contains($billing,'FOR UPDATE'),
    'historical journal transaction' => str_contains($billing,'WorkSqlTransaction::run') && str_contains($billing,'service_billing_events_table'),
    'no coupling to external provider' => !str_contains($engagement.$periods.$billing,'hub_site_id') && !str_contains($engagement.$periods.$billing,'snelstart_invoice_id'),
    'public reads manager-only' => str_contains($api,'Capabilities::MANAGE'),
    'public mutations manager-only' => str_contains($billingApi,'Capabilities::MANAGE'),
    'wp-admin CSRF and AJAX controls' => str_contains($actions,'check_admin_referer') && str_contains($actions,'check_ajax_referer'),
    'fallback admin-post form' => str_contains($actions,'admin_post_cb_work_service_invoice_save'),
    'entry route registered' => str_contains($menu,"SERVICE_OPERATIONS_SLUG") && str_contains($plugin,'ServiceOperationsAdmin::init()'),
    'table has combined filters' => str_contains($admin,'cb_project') && str_contains($admin,'cb_service')
        && str_contains($admin,'cb_billing') && str_contains($admin,'cb_status') && str_contains($admin,'cb_customer'),
    'bounded scan fails explicitly' => str_contains($admin,'work_service_scan_limit'),
    'inline registration is progressively enhanced' => str_contains($read('assets/service-operations.js'),'event.preventDefault()'),
    'six PO sources present' => count(glob($root.'/languages/core-blueprint-work-*.po'))===6,
    'P1 filters are optional and first-run state is filter aware' =>
        str_contains($admin, 'private static function has_filters')
        && str_contains($admin, "0 === $"."report['total'] && !self::has_filters($"."filters)"),
    'P1 create is gated on eligible customer Projects and Services' =>
        str_contains($admin, "WorkContext::CUSTOMER ===")
        && str_contains($admin, "'' !== (string)($"."p['customer_provider']")
        && str_contains($admin, "'' !== (string)($"."p['customer_id']")
        && str_contains($admin, '$can_create = [] !== $customer_projects && [] !== $services')
        && str_contains($admin, 'self::create_form($customer_projects,$services)'),
    'P1 onboarding gives real WordPress entry points' =>
        str_contains($admin, 'self::onboarding($customer_projects,$services)')
        && str_contains($admin, "post-new.php?post_type=cb_work_project")
        && str_contains($admin, "post-new.php?post_type=cb_work_service"),
    'P1 missing customer provider is not silently fabricated' =>
        str_contains($admin, '!CRMCustomers::available()')
        && str_contains($admin, 'Customer selection is unavailable.')
        && !str_contains($admin, "'customer_provider'=>'manual'"),
    'P1 primary CTA has explicit native details target' =>
        str_contains($admin, 'data-cb-so-open')
        && str_contains($admin, 'id="cb-work-so-add"')
        && str_contains($read('assets/service-operations.js'), 'details.open = true'),
    'P1 compact default status summary preserves all status categories' =>
        str_contains($admin, "['completed','overdue','attention']")
        && str_contains($admin, "['planned','in_progress','blocked','not_delivered','not_scheduled']")
        && str_contains($admin, 'data-cb-so-billing-count="invoiced"')
        && str_contains($admin, 'data-cb-so-billing-count="not_recorded"'),
    'P1 advanced filters retain all old dimensions' =>
        str_contains($admin, 'cb-work-so-filters-more')
        && str_contains($admin, "cb_project")
        && str_contains($admin, "cb_service")
        && str_contains($admin, "cb_customer")
        && str_contains($admin, "cb_billing")
        && str_contains($admin, "cb_status"),
    'P1 localizations available' =>
        str_contains($read('languages/core-blueprint-work.pot'), 'msgid "Getting started"')
        && str_contains($read('languages/core-blueprint-work-nl_NL.po'), 'msgstr "Aan de slag"'),
];
foreach($tests as $name=>$pass){if(!$pass){fwrite(STDERR,"Service Operations SOURCE FAIL: ".$name."\n");exit(1);}}
echo "Service Operations v1 source contract: PASS (".count($tests)." checks)\n";

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
    'additive schema v2.3' => str_contains($entry,"CB_WORK_SCHEMA_VERSION', '2.3'"),
    'stable Engagement identity' => str_contains($schema,'cb_work_service_engagements'),
    'one rule cannot join two Engagements' => str_contains($schema,'PRIMARY KEY  (rule_id)'),
    'separate immutable billing terms' => str_contains($schema,'cb_work_service_billing_terms'),
    'coverage uniqueness' => str_contains($schema,'UNIQUE KEY term_period (billing_terms_id,period_start)'),
    'append-only correction history' => str_contains($schema,'UNIQUE KEY registration_revision (registration_id,revision)'),
    'billing units unchanged' => str_contains($schema,'UNIQUE KEY source_unit (unit_type,unit_id)'),
    'Project/Service references guarded' => str_contains($engagement,'Services::get') && str_contains($engagement,'Projects::get'),
    'recurrence relation effective date explicit' => str_contains($engagement,'effective_from'),
    'idempotent binding' => str_contains($engagement,'work_service_rule_already_bound'),
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
];
foreach($tests as $name=>$pass){if(!$pass){fwrite(STDERR,"Service Operations SOURCE FAIL: ".$name."\n");exit(1);}}
echo "Service Operations v1 source contract: PASS (".count($tests)." checks)\n";

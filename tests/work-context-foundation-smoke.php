<?php
declare(strict_types=1);

$root = dirname( __DIR__ );

$domain      = file_get_contents( $root . '/src/Domain/WorkContext.php' );
$projectMeta = file_get_contents( $root . '/src/Content/ProjectMeta.php' );
$itemMeta    = file_get_contents( $root . '/src/Content/WorkItemMeta.php' );
$projects    = file_get_contents( $root . '/src/Repository/Projects.php' );
$items       = file_get_contents( $root . '/src/Repository/WorkItems.php' );
$rules       = file_get_contents( $root . '/src/Repository/RecurrenceRules.php' );
$schema      = file_get_contents( $root . '/src/Database/Schema.php' );
$query       = file_get_contents( $root . '/src/Query/WorkItemQuery.php' );
$viewState   = file_get_contents( $root . '/src/Admin/WorkItemViewState.php' );
$projectAdmin= file_get_contents( $root . '/src/Admin/Projects.php' );
$itemAdmin   = file_get_contents( $root . '/src/Admin/WorkItems.php' );
$recurrence  = file_get_contents( $root . '/src/Admin/Recurrence.php' );
$quickAdd    = file_get_contents( $root . '/src/Admin/QuickAdd.php' );
$workspace   = file_get_contents( $root . '/src/Admin/ProjectWorkspace.php' );
$billing     = file_get_contents( $root . '/src/Billing/SnapshotBuilder.php' );
$bootstrap   = file_get_contents( $root . '/core-blueprint-work.php' );

$checks = [
	'Work context domain has exactly Internal and Customer explicit states' =>
		str_contains( $domain, "public const INTERNAL = 'internal';" )
		&& str_contains( $domain, "public const CUSTOMER = 'customer';" )
		&& str_contains( $domain, 'return [ self::INTERNAL, self::CUSTOMER ];' ),

	'Work context foundation uses schema 1.9' =>
		str_contains( $bootstrap, "CB_WORK_SCHEMA_VERSION', '1.9'" ),

	'Projects and Work Items persist Work context as owned post meta' =>
		str_contains( $projectMeta, "_cb_work_project_context" )
		&& str_contains( $itemMeta, "_cb_work_item_context" )
		&& str_contains( $projects, "'work_context'" )
		&& str_contains( $items, "'work_context'" ),

	'legacy migration only promotes complete customer-linked records' =>
		str_contains( $schema, 'backfill_work_contexts' )
		&& str_contains( $schema, 'backfill_post_context' )
		&& str_contains( $schema, 'WorkContext::CUSTOMER' ),

	'Recurring Work persists and projects the same Work context' =>
		str_contains( $schema, "work_context varchar(16) NOT NULL DEFAULT ''" )
		&& str_contains( $rules, "'work_context'        => $normalized['work_context']" )
		&& str_contains( $rules, "'work_context'        => $rule['work_context']" ),

	'Project-linked Work Items take authoritative Project context' =>
		str_contains( $items, "$context = WorkContext::sanitize( $project['work_context'] ?? '' );" )
		&& str_contains( $items, "'provider' => (string) ( $project['customer_provider'] ?? '' )" ),

	'Internal Work is forced non-billable at the write boundary' =>
		str_contains( $items, '$billing  = BillingDisposition::NON_BILLABLE;' )
		&& str_contains( $rules, '$billing  = BillingDisposition::NON_BILLABLE;' ),

	'billing snapshot creation fails closed unless Work is Customer context' =>
		str_contains( $billing, 'WorkContext::CUSTOMER !== $context' )
		&& str_contains( $billing, 'work_billing_internal' )
		&& str_contains( $billing, 'work_billing_context_required' ),

	'Project reclassification synchronizes active Work Items and recurrence templates' =>
		str_contains( $items, 'public static function sync_project_context' )
		&& str_contains( $rules, 'public static function sync_project_context' )
		&& str_contains( $projectAdmin, 'WorkItems::sync_project_context( $post_id )' )
		&& str_contains( $projectAdmin, 'RecurrenceRules::sync_project_context( $post_id )' ),

	'Work Items expose canonical context filtering through shared query state' =>
		str_contains( $query, "'work_context'         => $context" )
		&& str_contains( $viewState, "'work_context'   => $context" )
		&& str_contains( $viewState, "'work_context'   => 'work_context'" ),

	'Project, Work Item and Recurrence editors expose Work context explicitly' =>
		str_contains( $projectAdmin, 'cb-work-project-context' )
		&& str_contains( $itemAdmin, 'cb-work-item-context' )
		&& str_contains( $recurrence, 'cb-work-recurrence-context' ),

	'Quick Add classifies standalone capture as Internal while repository keeps Project authority' =>
		str_contains( $quickAdd, "'work_context' => WorkContext::INTERNAL" ),

	'Project operational surfaces expose and filter Work context' =>
		str_contains( $projectAdmin, 'cb_work_context' )
		&& str_contains( $workspace, "'Context', 'core-blueprint-work'" ),
];

$failed = false;
foreach ( $checks as $label => $passed ) {
	if ( ! $passed ) {
		fwrite( STDERR, "Work context foundation smoke failed: {$label}\n" );
		$failed = true;
	}
}

exit( $failed ? 1 : 0 );

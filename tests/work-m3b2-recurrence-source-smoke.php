<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$api = (string) file_get_contents( $root . '/src/Transport/Rest/WorkspaceEngagementRulesController.php' );
$panel = (string) file_get_contents( $root . '/src/Admin/WorkspaceEngagementsPanel.php' );
$js = (string) file_get_contents( $root . '/assets/js/workspace/engagement-rules.js' );
$parent_js = (string) file_get_contents( $root . '/assets/js/workspace/engagements.js' );
$plugin = (string) file_get_contents( $root . '/src/Plugin.php' );
$assets = (string) file_get_contents( $root . '/src/Admin/Assets.php' );
$stage = (string) file_get_contents( $root . '/tools/prepare-workspace-modules.py' );
$check = (string) file_get_contents( $root . '/tools/check' );
$golden = (string) file_get_contents( $root . '/src/Admin/Operations.php' );

$checks = [
	'REST route is GET/POST only with existing manager permission and callback checks' =>
		str_contains( $api, "public const ROUTE = '/workspace/engagements/" )
		&& str_contains( $api, 'WP_REST_Server::READABLE' )
		&& str_contains( $api, 'WP_REST_Server::CREATABLE' )
		&& str_contains( $api, "[ WorkspaceEngagementsController::class, 'can_manage' ]" )
		&& str_contains( $api, 'self::authorized( $request )' ),
	'Read uses original bounded recurrence and execution projection without creating status or scheduling' =>
		str_contains( $api, 'RecurrenceRules::search_page(' )
		&& str_contains( $api, 'ServiceOperations::period(' )
		&& str_contains( $api, 'Engagements::rules(' )
		&& str_contains( $api, 'self::matches( $rule, $engagement )' )
		&& str_contains( $api, "'status' => 'active'" )
		&& str_contains( $api, "'project_id' => (int) \$engagement['project_id']" )
		&& ! str_contains( $api, 'Scheduler::' )
		&& ! str_contains( $api, 'WorkItems::create' ),
	'Binding delegates to canonical audited provider and uses server-owned context and effective date' =>
		str_contains( $api, 'ServiceOperationsActions::bind_rule(' )
		&& str_contains( $api, 'ProjectMeta::sanitize_date( $raw_date )' )
		&& str_contains( $api, 'RecurrenceRules::get(' )
		&& ! str_contains( $api, 'Engagements::bind_rule(' )
		&& ! str_contains( $api, 'Audit::record(' ),
	'No customer ID or tokens are projected into REST or UI; queries fail closed' =>
		str_contains( $api, 'customer_id' )
		&& ! str_contains( $api, "'customer_id' =>" )
		&& ! str_contains( $api, "'customer_provider' =>" )
		&& str_contains( $api, 'work_service_period_unavailable' )
		&& str_contains( $api, 'work_service_rules_unavailable' ),
	'Base Modal and canonical status labels available through scoped progressive Work surface' =>
		str_contains( $panel, "ServiceOperations::statuses()" )
		&& str_contains( $panel, 'data-cb-work-m3-rule-endpoint' )
		&& str_contains( $parent_js, "from './engagement-rules.js'" )
		&& str_contains( $parent_js, 'ruleDialog.open(item, action)' )
		&& str_contains( $js, 'browser.cbCore?.modal?.show' )
		&& str_contains( $js, 'textContent' )
		&& ! str_contains( $js, 'innerHTML' )
		&& str_contains( $js, 'onConfirm: async () =>' ),
	'REST template works with both pretty permalinks and index.php query-string routes' =>
		str_contains( $panel, "rest_url( 'cb-work/v1/workspace/engagements/__id__/rules' )" )
		&& str_contains( $js, "endpoint.replace('__id__', String(id))" )
		&& str_contains( $js, 'Cross-origin Work REST' ),
	'Registered in plugin, asset fail-closed check and immutable package graph' =>
		str_contains( $plugin, 'WorkspaceEngagementRulesController::init();' )
		&& str_contains( $assets, 'is_file( $engagement_rules )' )
		&& str_contains( $stage, '"engagement-rules.js"' ),
	'Level 1 includes source, real PHP fixture and interactive modal regression' =>
		str_contains( $check, 'work-m3b2-recurrence-source-smoke.php' )
		&& str_contains( $check, 'work-m3b2-recurrence-runtime.php' )
		&& str_contains( $check, 'work-m3b2-recurrence-runtime.mjs' ),
	'Protected renderer remains exact Git blob' =>
		sha1( 'blob ' . strlen( $golden ) . "\0" . $golden ) === '92a4814f24b43f96d16e3d96ce61a49ee9f21500',
];
foreach ( $checks as $name => $pass ) {
	if ( ! $pass ) {
		fwrite( STDERR, "Work M3-B2 source FAIL: {$name}\n" );
		exit( 1 );
	}
}
echo "Work M3-B2 recurrence source: PASS (canonical Periods, manager, binding, immutable modules, safe UI).\n";

<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$api = (string) file_get_contents( $root . '/src/Transport/Rest/WorkspaceEngagementBillingController.php' );
$ui = (string) file_get_contents( $root . '/assets/js/workspace/engagement-billing.js' );
$parent = (string) file_get_contents( $root . '/assets/js/workspace/engagements.js' );
$panel = (string) file_get_contents( $root . '/src/Admin/WorkspaceEngagementsPanel.php' );
$plugin = (string) file_get_contents( $root . '/src/Plugin.php' );
$assets = (string) file_get_contents( $root . '/src/Admin/Assets.php' );
$compiler = (string) file_get_contents( $root . '/tools/prepare-workspace-modules.py' );
$css = (string) file_get_contents( $root . '/assets/css/unified-workspace.css' );
$checker = (string) file_get_contents( $root . '/tools/check' );
$ops = (string) file_get_contents( $root . '/src/Admin/Operations.php' );

$checks = [
	'Billing REST only GET/POST manager checked at route and callback' =>
		str_contains( $api, 'WP_REST_Server::READABLE' )
		&& str_contains( $api, 'WP_REST_Server::CREATABLE' )
		&& str_contains( $api, "[ WorkspaceEngagementsController::class, 'can_manage' ]" )
		&& str_contains( $api, 'self::authorized( $request )' )
		&& str_contains( $api, "'permission_callback' => \$permission" ),
	'Read takes authoritative service coverage and never guesses invoicing from execution status' =>
		str_contains( $api, 'ServiceBilling::coverage(' )
		&& str_contains( $api, 'ServiceOperations::engagement(' )
		&& str_contains( $api, 'Periods::bounds(' )
		&& ! str_contains( $api, 'ServiceOperations::period(' )
		&& ! str_contains( $api, 'Periods::resolve(' ),
	'Only canonical invoicing writers own CAS and append-only audit journal' =>
		str_contains( $api, 'ServiceBillingActions::record(' )
		&& str_contains( $api, 'ServiceBillingActions::create_terms(' )
		&& ! str_contains( $api, 'BillingRepository::record(' )
		&& ! str_contains( $api, 'WorkSqlTransaction::run(' )
		&& ! str_contains( $api, 'Audit::record(' )
		&& ! str_contains( $api, 'Scheduler::' ),
	'Internal services refuse both record and term creation at server boundary' =>
		str_contains( $api, "'customer' !== (string) \$engagement['work_context']" )
		&& str_contains( $api, "'internal' === \$context && 'not_applicable' !== \$coverage['state']" )
		&& str_contains( $api, 'work_service_registration_not_applicable' )
		&& str_contains( $api, 'work_service_billing_not_applicable' ),
	'In-place payload omits customers, terms identifiers and audit actors' =>
		! str_contains( $api, "'customer_id' =>" )
		&& ! str_contains( $api, "'customer_provider' =>" )
		&& ! str_contains( $api, "'recorded_by' =>" )
		&& ! str_contains( $api, "'terms_id' => (int) \$coverage" )
		&& str_contains( $api, "'revision' => max( 0, (int) \$registration['revision'] )" ),
	'Client never reports failed CAS or unreadable coverage as successful' =>
		str_contains( $ui, 'expected_revision: expectedRevision' )
		&& str_contains( $ui, 'return false;' )
		&& str_contains( $ui, "updated?.outcome" )
		&& str_contains( $ui, "showError()" )
		&& str_contains( $ui, "browser.cbCore?.modal" )
		&& str_contains( $ui, 'textContent' )
		&& ! str_contains( $ui, 'innerHTML' ),
	'Original Work screen and native fallback retained' =>
		str_contains( $parent, "from './engagement-billing.js'" )
		&& str_contains( $parent, 'createEngagementBillingDialog(' )
		&& str_contains( $parent, "item.context === 'internal'" )
		&& str_contains( $panel, "ServiceOperations::billing_statuses()" )
		&& str_contains( $panel, 'data-cb-work-m3-billing-terms-endpoint' )
		&& str_contains( $panel, 'Menu::SERVICE_OPERATIONS_SLUG' ),
	'Content-addressed ESM, fail-closed assets, Base style scoping' =>
		str_contains( $plugin, 'WorkspaceEngagementBillingController::init();' )
		&& str_contains( $assets, 'is_file( $engagement_billing )' )
		&& str_contains( $compiler, '"engagement-billing.js"' )
		&& str_contains( $css, '.cb-work-m3-billing__form' ),
	'Golden renderer untouched and all three new checks in canonical Level 1' =>
		sha1( 'blob ' . strlen( $ops ) . "\0" . $ops ) === '92a4814f24b43f96d16e3d96ce61a49ee9f21500'
		&& str_contains( $checker, 'work-m3b3-billing-source-smoke.php' )
		&& str_contains( $checker, 'work-m3b3-billing-runtime.php' )
		&& str_contains( $checker, 'work-m3b3-billing-runtime.mjs' ),
];
foreach ( $checks as $name => $passed ) {
	if ( ! $passed ) {
		fwrite( STDERR, "Work M3-B3 source FAIL: {$name}\n" );
		exit( 1 );
	}
}
echo "Work M3-B3 billing source: PASS (authoritative coverage, CAS, role segregation, Base Modal, ESM).\n";

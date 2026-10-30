<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$controller = (string) file_get_contents( $root . '/src/Transport/Rest/WorkspaceEngagementsController.php' );
$render = (string) file_get_contents( $root . '/src/Admin/WorkspaceEngagementsPanel.php' );
$operations = (string) file_get_contents( $root . '/src/Admin/WorkspaceOperationsPanel.php' );
$app = (string) file_get_contents( $root . '/assets/js/workspace/app.js' );
$script = (string) file_get_contents( $root . '/assets/js/workspace/engagements.js' );
$assets = (string) file_get_contents( $root . '/src/Admin/Assets.php' );
$stage = (string) file_get_contents( $root . '/tools/prepare-workspace-modules.py' );
$plugin = (string) file_get_contents( $root . '/src/Plugin.php' );
$style = (string) file_get_contents( $root . '/assets/css/unified-workspace.css' );
$check = (string) file_get_contents( $root . '/tools/check' );
$golden = (string) file_get_contents( $root . '/src/Admin/Operations.php' );

$checks = [
	'REST API is manager-only, WordPress-native, method constrained, with callback recheck' =>
		str_contains( $controller, 'WP_REST_Server::READABLE' )
		&& str_contains( $controller, 'WP_REST_Server::CREATABLE' )
		&& str_contains( $controller, 'ServiceOperations::search(' )
		&& str_contains( $controller, 'ServiceOperationsActions::create(' )
		&& str_contains( $controller, 'self::can_manage( $request )' )
		&& str_contains( $controller, 'current_user_can( Capabilities::MANAGE )' )
		&& str_contains( $controller, 'get_current_user_id() > 0' )
		&& str_contains( $controller, 'rest_authorization_required_code()' ),
	'Service creation uses canonical Project context/audit, never client billing or scheduler' =>
		str_contains( $controller, 'ProjectMeta::sanitize_date( $starts_on )' )
		&& str_contains( $controller, 'project_id' )
		&& str_contains( $controller, 'service_id' )
		&& str_contains( $controller, 'starts_on' )
		&& ! str_contains( $controller, 'use CB\\Work\\ServiceOperations\\Engagements;' )
		&& ! str_contains( $controller, 'ServiceBilling::' )
		&& ! str_contains( $controller, 'Scheduler::' )
		&& ! str_contains( $controller, 'customer_provider' ),
	'Bounded list returns only safe values and separate paging metadata' =>
		str_contains( $controller, 'private const MAX_PAGE_SIZE = 25;' )
		&& str_contains( $controller, 'Projects::get(' )
		&& str_contains( $controller, 'Services::get(' )
		&& str_contains( $controller, "'items' => \$items" ),
	'In-place list and Base Modal preserve native operations fallback' =>
		str_contains( $operations, 'data-cb-work-m3-service-link' )
		&& str_contains( $operations, 'WorkspaceEngagementsPanel::render();' )
		&& str_contains( $render, 'data-cb-work-m3-engagements' )
		&& str_contains( $render, 'data-cb-work-m3-engagements-add' )
		&& str_contains( $render, 'Menu::SERVICE_OPERATIONS_SLUG' )
		&& str_contains( $render, 'data-cb-work-m3-nonce' ),
	'DOM behavior is safe, actual Base Modal use and POST with WP nonce' =>
		str_contains( $script, 'browser.cbCore?.modal' )
		&& str_contains( $script, 'modal.show' )
		&& str_contains( $script, 'textContent' )
		&& str_contains( $script, 'replaceChildren' )
		&& str_contains( $script, "method: 'POST'" )
		&& str_contains( $script, "'X-WP-Nonce': nonce" )
		&& str_contains( $script, "credentials: 'same-origin'" )
		&& ! str_contains( $script, 'innerHTML' ),
	'New module is in the immutable graph and required by PHP Assets' =>
		str_contains( $plugin, 'WorkspaceEngagementsController::init();' )
		&& str_contains( $app, "from './engagements.js'" )
		&& str_contains( $app, 'mountWorkspaceEngagements(root, browser, () => operationsSummary?.refresh());' )
		&& str_contains( $stage, '"engagements.js"' )
		&& str_contains( $assets, 'is_file( $engagements )' ),
	'Only scoped, keyboard friendly Work CSS and unchanged protected Golden renderer' =>
		str_contains( $style, '.cb-work-unified .cb-work-m3-engagements' )
		&& str_contains( $style, '@media screen and (max-width: 782px)' )
		&& '92a4814f24b43f96d16e3d96ce61a49ee9f21500' ===
			sha1( 'blob ' . strlen( $golden ) . "\0" . $golden ),
	'M3-B tests are part of canonical Level 1' =>
		str_contains( $check, 'work-m3b-engagements-source-smoke.php' )
		&& str_contains( $check, 'work-m3b-engagements-runtime.php' )
		&& str_contains( $check, 'work-m3b-engagements-runtime.mjs' ),
];
foreach ( $checks as $label => $success ) {
	if ( ! $success ) {
		fwrite( STDERR, "Work M3-B1 source FAIL: {$label}\n" );
		exit( 1 );
	}
}
echo "Work M3-B1 source: PASS (canonical create, read boundary, modal, progressive fallback and protected renderer).\n";

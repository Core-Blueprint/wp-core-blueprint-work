<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$controller = (string) file_get_contents( $root . '/src/Transport/Rest/WorkspaceReadController.php' );
$plugin = (string) file_get_contents( $root . '/src/Plugin.php' );
$check = (string) file_get_contents( $root . '/tools/check' );
$workspace = (string) file_get_contents( $root . '/src/Admin/UnifiedWorkspace.php' );
$menu = (string) file_get_contents( $root . '/src/Admin/Menu.php' );
$operations = (string) file_get_contents( $root . '/src/Admin/Operations.php' );

$checks = [
	'Work REST controller is booted for all requests, not gated by is_admin' =>
		str_contains( $plugin, 'WorkspaceReadController::init();' )
		&& strpos( $plugin, 'WorkspaceReadController::init();' ) < strpos( $plugin, 'if ( is_admin() ) {' ),
	'GET-only private Work namespace without mutations' =>
		str_contains( $controller, "public const NAMESPACE = 'cb-work/v1';" )
		&& str_contains( $controller, "'methods' => \\WP_REST_Server::READABLE" )
		&& str_contains( $controller, "'permission_callback' => [ self::class, 'can_read' ]" )
		&& ! str_contains( $controller, '::create(' )
		&& ! str_contains( $controller, '::update(' )
		&& ! str_contains( $controller, '::delete(' ),
	'manager-only authorization and runtime readiness' =>
		str_contains( $controller, 'current_user_can( Capabilities::MANAGE )' )
		&& str_contains( $controller, '\\cb_work_runtime_ready()' )
		&& str_contains( $controller, 'rest_authorization_required_code()' )
		&& str_contains( $controller, 'self::can_read( $request )' ),
	'all three sections, bounded pagination and validated inputs' =>
		str_contains( $controller, "work-items|projects|services" )
		&& str_contains( $controller, 'private const MAX_PAGE_SIZE = 100;' )
		&& str_contains( $controller, "'maximum' => self::MAX_PAGE_SIZE" )
		&& str_contains( $controller, 'WorkItemStatus::all()' )
		&& str_contains( $controller, 'WorkItemPriority::all()' )
		&& str_contains( $controller, "'minimum' => 1" ),
	'canonical Work Item repository, no duplicate query' =>
		str_contains( $controller, 'WorkItems::search( $criteria )' )
		&& ! str_contains( $controller, 'PostTypes::WORK_ITEM, $page' ),
	'canonical Project and Service record readers' =>
		str_contains( $controller, 'Projects::get( $id )' )
		&& str_contains( $controller, 'Services::get( $id )' ),
	'private data cannot escape through list projection' =>
		! str_contains( $controller, "'customer_id' =>" )
		&& ! str_contains( $controller, "'customer_provider' =>" )
		&& ! str_contains( $controller, "'description' =>" )
		&& ! str_contains( $controller, "'relations' =>" )
		&& ! str_contains( $controller, "'pricing' =>" ),
	'existing FIELD stable shell and menu stay untouched' =>
		str_contains( $workspace, 'WorkItems::all( 25 )' )
		&& str_contains( $menu, 'UNIFIED_WORKSPACE_SLUG' )
		&& ! str_contains( $operations, 'WorkspaceReadController' ),
	'Level 1 and Level 2 test registration' =>
		str_contains( $check, 'tests/work-uwe-03b1-rest-smoke.php' )
		&& is_file( $root . '/tests/integration/WorkspaceReadRestIntegrationTest.php' ),
];
foreach ( $checks as $label => $passed ) {
	if ( ! $passed ) {
		fwrite( STDERR, "UWE-03B.1 REST source smoke FAILED: {$label}\n" );
		exit( 1 );
	}
}
echo "UWE-03B.1 REST source smoke passed.\n";

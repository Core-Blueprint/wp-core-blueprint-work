<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$menu = (string) file_get_contents( $root . '/src/Admin/Menu.php' );
$assets = (string) file_get_contents( $root . '/src/Admin/Assets.php' );
$shell = (string) file_get_contents( $root . '/src/Admin/UnifiedWorkspace.php' );
$operations = (string) file_get_contents( $root . '/src/Admin/Operations.php' );

$checks = [
	'new Work-only route registered with management capability' =>
		str_contains( $menu, "UNIFIED_WORKSPACE_SLUG = 'core-blueprint-work-workspace'" )
		&& str_contains( $menu, "Capabilities::MANAGE, self::UNIFIED_WORKSPACE_SLUG, [ UnifiedWorkspace::class, 'render' ]" ),
	'preview route hidden, existing menus remain accessible' =>
		str_contains( $menu, 'remove_submenu_page( self::TOP_LEVEL_SLUG, self::UNIFIED_WORKSPACE_SLUG )' )
		&& str_contains( $menu, 'self::WORK_ITEMS_SLUG, [ Operations::class' ),
	'workspace context registered with Base Admin Theme' =>
		str_contains( $menu, 'CONTEXT_UNIFIED_WORKSPACE' )
		&& str_contains( $menu, "add_action( 'current_screen', [ self::class, 'register_admin_theme_screen' ] )" ),
	'read-only authorization and bounded canonical data reads' =>
		str_contains( $shell, 'current_user_can( Capabilities::MANAGE )' )
		&& str_contains( $shell, 'WorkItems::all( 25 )' )
		&& str_contains( $shell, 'Projects::all( 25 )' )
		&& str_contains( $shell, 'Services::all( 25 )' )
		&& ! str_contains( $shell, '::create(' )
		&& ! str_contains( $shell, '::update(' ),
	'accessible links and server-rendered direct URL fallback' =>
		str_contains( $shell, 'role="tablist"' )
		&& str_contains( $shell, 'role="tabpanel"' )
		&& str_contains( $shell, 'aria-selected=' )
		&& str_contains( $shell, "add_query_arg( [ 'page' => Menu::UNIFIED_WORKSPACE_SLUG" ),
	'Work-scoped assets only' =>
		str_contains( $assets, 'enqueue_unified_workspace' )
		&& str_contains( $assets, 'wp_enqueue_script_module(' )
		&& str_contains( $assets, "'assets/js/workspace/app.js'" )
		&& str_contains( $assets, "'assets/css/unified-workspace.css'" ),
	'workspace CSS resolves to real packaged asset and correct URL' =>
		is_file( $root . '/assets/css/unified-workspace.css' )
		&& str_contains( $assets, "CB_WORK_DIR . 'assets/css/unified-workspace.css'" )
		&& str_contains( $assets, "CB_WORK_URL . 'assets/css/unified-workspace.css'" )
		&& ! str_contains( $assets, "'assets/unified-workspace.css'" ),
	'workspace JavaScript entry and relative router are packaged' =>
		is_file( $root . '/assets/js/workspace/app.js' )
		&& is_file( $root . '/assets/js/workspace/router.js' )
		&& str_contains( (string) file_get_contents( $root . '/assets/js/workspace/app.js' ), "from './router.js'" )
		&& str_contains( $assets, "CB_WORK_DIR . 'assets/js/workspace/app.js'" )
		&& str_contains( $assets, "CB_WORK_URL . 'assets/js/workspace/app.js'" ),
	'protected Operations renderer unchanged by this patch' =>
		! str_contains( $operations, 'UnifiedWorkspace' ),
];

foreach ( $checks as $label => $pass ) {
	if ( ! $pass ) {
		fwrite( STDERR, "UWE-03A shell source gate failed: {$label}\n" );
		exit( 1 );
	}
}

echo "UWE-03A shell source smoke passed.\n";

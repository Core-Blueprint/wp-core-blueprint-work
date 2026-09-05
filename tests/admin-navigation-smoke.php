<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$menu = file_get_contents( $root . '/src/Admin/Menu.php' );
$page = file_get_contents( $root . '/src/Admin/Page.php' );
$postTypes = file_get_contents( $root . '/src/Content/PostTypes.php' );
$plugin = file_get_contents( $root . '/src/Plugin.php' );
$taxActions = file_get_contents( $root . '/src/Admin/TaxRateActions.php' );
$suite = file_get_contents( $root . '/src/Integration/Suite.php' );

$checks = [
	'Work owns a normal top-level WP Admin menu' => str_contains( $menu, 'add_menu_page(' ) && str_contains( $menu, "TOP_LEVEL_SLUG = 'core-blueprint-work'" ),
	'Work operational menu mounts Work Items' => str_contains( $menu, "WORK_ITEMS_SLUG = 'core-blueprint-work-items'" ),
	'Work operational menu mounts Projects' => str_contains( $menu, "PROJECTS_SLUG = 'core-blueprint-work-projects'" ),
	'Work operational menu mounts Services' => str_contains( $menu, 'add_submenu_page(' ) && str_contains( $menu, "'edit.php?post_type=' . PostTypes::SERVICE" ),
	'Service editor stays highlighted under Work' => str_contains( $menu, "add_filter( 'parent_file'" ) && str_contains( $menu, "add_filter( 'submenu_file'" ) && str_contains( $menu, 'CONTEXT_SERVICES' ),
	'WordPress does not auto-own the Service menu' => str_contains( $postTypes, "'show_in_menu'        => false" ),
	'Core Blueprint Work page is settings-only and has a distinct slug' => str_contains( $page, "SLUG = 'core-blueprint-work-settings'" ) && ! str_contains( $page, 'render_services' ) && ! str_contains( $page, 'VIEW_SERVICES' ),
	'Core Blueprint Work settings do not expose operational nav tabs' => ! str_contains( $page, 'nav-tab-wrapper' ) && ! str_contains( $page, "'nav-tabs'" ),
	'Work boot owns operational actions and both navigation surfaces' => str_contains( $plugin, 'Menu::init();' ) && str_contains( $plugin, 'Page::init();' ) && str_contains( $plugin, 'OperationalActions::init();' ),
	'VAT actions return only to Work settings' => str_contains( $taxActions, "'page'           => Page::SLUG" ) && ! str_contains( $taxActions, "'view'" ),
	'Suite links open operational Work rather than settings' => str_contains( $suite, 'Menu::TOP_LEVEL_SLUG' ) && ! str_contains( $suite, 'Page::SLUG' ),
];

$failed = false;
foreach ( $checks as $label => $passed ) {
	if ( ! $passed ) {
		fwrite( STDERR, "Admin navigation smoke failed: {$label}\n" );
		$failed = true;
	}
}

exit( $failed ? 1 : 0 );

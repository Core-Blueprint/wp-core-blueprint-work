<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$menu = file_get_contents( $root . '/src/Admin/Menu.php' );
$page = file_get_contents( $root . '/src/Admin/Page.php' );
$postTypes = file_get_contents( $root . '/src/Content/PostTypes.php' );
$plugin = file_get_contents( $root . '/src/Plugin.php' );
$taxActions = file_get_contents( $root . '/src/Admin/TaxRateActions.php' );
$suite = file_get_contents( $root . '/src/Integration/Suite.php' );
$operations = file_get_contents( $root . '/src/Admin/Operations.php' );
$projects = file_get_contents( $root . '/src/Admin/Projects.php' );
$actions = file_get_contents( $root . '/src/Admin/OperationalActions.php' );

$checks = [
	'Work owns a normal top-level WP Admin menu' => str_contains( $menu, 'add_menu_page(' ) && str_contains( $menu, "TOP_LEVEL_SLUG   = 'core-blueprint-work'" ),
	'Work operational menu mounts Work Items' => str_contains( $menu, "WORK_ITEMS_SLUG  = 'core-blueprint-work-items'" ),
	'Projects use native CPT admin routing under Work' => str_contains( $menu, "PostTypes::PROJECT" ) && str_contains( $menu, "'edit.php?post_type='" ) && ! str_contains( $menu, 'PROJECTS_SLUG' ),
	'Services use native CPT admin routing under Work' => str_contains( $menu, 'PostTypes::SERVICE' ),
	'Work Types are operational under Work' => str_contains( $menu, "WORK_TYPES_SLUG  = 'core-blueprint-work-types'" ) && str_contains( $menu, 'render_work_types' ),
	'Project and Service editors stay highlighted under Work' => str_contains( $menu, 'CONTEXT_PROJECTS' ) && str_contains( $menu, 'CONTEXT_SERVICES' ) && str_contains( $menu, "add_filter( 'parent_file'" ),
	'WordPress does not auto-own Project or Service menu placement' => substr_count( $postTypes, "'show_in_menu'        => false" ) >= 2,
	'Core Blueprint Work page is settings-only and has a distinct slug' => str_contains( $page, "SLUG = 'core-blueprint-work-settings'" ) && ! str_contains( $page, 'render_services' ) && ! str_contains( $page, 'VIEW_SERVICES' ),
	'Core Blueprint Work settings do not expose operational nav tabs' => ! str_contains( $page, 'nav-tab-wrapper' ) && ! str_contains( $page, "'nav-tabs'" ),
	'Work boot owns native Project admin and picker integration' => str_contains( $plugin, 'Projects::init();' ) && str_contains( $plugin, 'Pickers::init();' ),
	'native Project editor has contextual Work Items' => str_contains( $projects, 'WorkItems::for_project' ) && str_contains( $projects, 'View all Work Items' ),
	'global Work Items provides create and edit flows' => str_contains( $operations, 'Add Work Item' ) && str_contains( $operations, 'Edit Work Item' ) && str_contains( $actions, 'cb_work_update_work_item' ),
	'raw technical relation controls are absent from normal Work Item UX' => ! str_contains( $operations, 'reference_fields' ) && ! str_contains( $operations, 'External/source relation' ),
	'VAT actions return only to Work settings' => str_contains( $taxActions, "'page'           => Page::SLUG" ) && ! str_contains( $taxActions, "'view'" ),
	'Suite Project shortcut opens native Project list' => str_contains( $suite, 'Menu::projects_url()' ),
	'Suite links core extension/status to operational Work rather than settings' => str_contains( $suite, 'Menu::TOP_LEVEL_SLUG' ) && ! str_contains( $suite, 'Page::SLUG' ),
];

foreach ( $checks as $label => $passed ) {
	if ( ! $passed ) {
		fwrite( STDERR, "Admin navigation smoke failed: {$label}\n" );
		exit( 1 );
	}
}

echo "Admin navigation smoke passed.\n";

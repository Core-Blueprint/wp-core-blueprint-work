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
$workItems = file_get_contents( $root . '/src/Admin/WorkItems.php' );
$actions = file_get_contents( $root . '/src/Admin/OperationalActions.php' );

$checks = [
	'Work owns a normal top-level WP Admin menu' => str_contains( $menu, 'add_menu_page(' ) && 1 === preg_match( "/const\s+TOP_LEVEL_SLUG\s*=\s*'core-blueprint-work'/", $menu ),
	'Work operational menu mounts Work Items workspace' => 1 === preg_match( "/const\s+WORK_ITEMS_SLUG\s*=\s*'core-blueprint-work-items'/", $menu ),
	'Work operational menu mounts Recurring Work' => 1 === preg_match( "/const\s+RECURRENCE_SLUG\s*=\s*'core-blueprint-work-recurrence'/", $menu ) && str_contains( $menu, "__( 'Recurring Work'" ),
	'Projects use native CPT admin routing under Work' => str_contains( $menu, 'PostTypes::PROJECT' ) && str_contains( $menu, "'edit.php?post_type='" ) && ! str_contains( $menu, 'PROJECTS_SLUG' ),
	'Services use native CPT admin routing under Work' => str_contains( $menu, 'PostTypes::SERVICE' ),
	'Work Types are operational under Work' => 1 === preg_match( "/const\s+WORK_TYPES_SLUG\s*=\s*'core-blueprint-work-types'/", $menu ) && str_contains( $menu, 'render_work_types' ),
	'Project Service Work Item and Recurrence screens stay highlighted under Work' => str_contains( $menu, 'CONTEXT_PROJECTS' ) && str_contains( $menu, 'CONTEXT_SERVICES' ) && str_contains( $menu, 'CONTEXT_RECURRENCE' ) && str_contains( $menu, 'PostTypes::WORK_ITEM === (string) $screen->post_type' ) && str_contains( $menu, "add_filter( 'parent_file'" ),
	'WordPress does not auto-own Service Project or Work Item menu placement' => substr_count( $postTypes, "'show_in_menu'          => false" ) >= 2 && str_contains( $postTypes, "WORK_ITEM = 'cb_work_item'" ),
	'Core Blueprint Work page is settings-only and has a distinct slug' => str_contains( $page, "SLUG = 'core-blueprint-work-settings'" ) && ! str_contains( $page, 'render_services' ) && ! str_contains( $page, 'VIEW_SERVICES' ),
	'Core Blueprint Work settings do not expose operational nav tabs' => ! str_contains( $page, 'nav-tab-wrapper' ) && ! str_contains( $page, "'nav-tabs'" ),
	'Work boot owns native Project and Work Item admin plus picker integration' => str_contains( $plugin, 'Projects::init();' ) && str_contains( $plugin, 'WorkItemsAdmin::init();' ) && str_contains( $plugin, 'Pickers::init();' ),
	'native Project editor has contextual Work Items' => str_contains( $projects, 'WorkItems::for_project' ) && str_contains( $projects, 'View all Work Items' ) && str_contains( $projects, 'Menu::new_work_item_url' ),
	'global Work Items workspace creates and edits through native Gutenberg' => str_contains( $operations, 'Add Work Item' ) && str_contains( $operations, 'Menu::new_work_item_url' ) && str_contains( $operations, 'Menu::edit_work_item_url' ) && str_contains( $workItems, 'Work Item Details' ),
	'custom Work Item CRUD admin-post handlers are removed while governed status action remains' => ! str_contains( $actions, 'cb_work_create_work_item' ) && ! str_contains( $actions, 'cb_work_update_work_item' ) && str_contains( $actions, 'cb_work_transition_work_item' ),
	'raw technical relation controls are absent from normal Work Item UX' => ! str_contains( $workItems, 'source_provider' ) && ! str_contains( $workItems, 'source_type' ) && ! str_contains( $workItems, 'source_id' ),
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

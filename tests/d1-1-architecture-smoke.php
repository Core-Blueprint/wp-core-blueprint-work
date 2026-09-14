<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$postTypes = file_get_contents( $root . '/src/Content/PostTypes.php' );
$projectMeta = file_get_contents( $root . '/src/Content/ProjectMeta.php' );
$workItemMeta = file_get_contents( $root . '/src/Content/WorkItemMeta.php' );
$projectRepo = file_get_contents( $root . '/src/Repository/Projects.php' );
$workItems = file_get_contents( $root . '/src/Repository/WorkItems.php' );
$schema = file_get_contents( $root . '/src/Database/Schema.php' );
$operations = file_get_contents( $root . '/src/Admin/Operations.php' );
$projectsAdmin = file_get_contents( $root . '/src/Admin/Projects.php' );
$workItemsAdmin = file_get_contents( $root . '/src/Admin/WorkItems.php' );
$pickers = file_get_contents( $root . '/src/Admin/Pickers.php' );
$crm = file_get_contents( $root . '/src/Integration/CRMCustomers.php' );
$projectRest = file_get_contents( $root . '/src/Content/ProjectRestController.php' );
$workItemRest = file_get_contents( $root . '/src/Content/WorkItemRestController.php' );

$checks = [
	'Projects use canonical cb_work_project CPT' => str_contains( $postTypes, "PROJECT   = 'cb_work_project'" ),
	'Project CPT remains private but Gutenberg-capable' => str_contains( $postTypes, 'ProjectRestController::class' ) && preg_match_all( "/'publicly_queryable'\s*=>\s*false/", $postTypes ) >= 2,
	'Project REST reads stay capability-gated' => str_contains( $projectRest, 'current_user_can( Capabilities::MANAGE )' ),
	'Project customer and dates are registered Work-owned post meta' => str_contains( $projectMeta, '_cb_work_project_customer_provider' ) && str_contains( $projectMeta, '_cb_work_project_starts_on' ) && str_contains( $projectMeta, '_cb_work_project_due_on' ),
	'Project repository is CPT-backed rather than SQL-backed' => str_contains( $projectRepo, 'new \\WP_Query' ) && str_contains( $projectRepo, 'get_post(' ) && str_contains( $projectRepo, 'wp_insert_post(' ) && ! str_contains( $projectRepo, 'Schema::' ) && ! str_contains( $projectRepo, '$wpdb' ),
	'pre-v1 Project relational schema is fully absent' => ! str_contains( $schema, 'cb_work_projects' ) && ! str_contains( $schema, 'projects_table' ) && ! str_contains( $schema, 'DROP TABLE IF EXISTS' ),
	'Work Item Project reference remains optional registered post meta' => str_contains( $workItemMeta, '_cb_work_item_project_id' ) && str_contains( $workItemMeta, 'self::write_id( $work_item_id, self::PROJECT_ID' ),
	'Work Item Project references validate through canonical Project repository' => str_contains( $workItems, 'Projects::get( $project_id )' ),
	'projectless Work Items remain supported' => str_contains( $workItems, "'project_id'          => \$project_id" ),
	'Project customer may be inherited by a Work Item' => str_contains( $workItems, "\$project['customer_provider']" ) && str_contains( $workItems, "'customer_provider'   => \$customer['provider']" ),
	'Project edit screen exposes contextual Work Items' => str_contains( $projectsAdmin, 'WorkItems::for_project' ) && str_contains( $projectsAdmin, 'Menu::new_work_item_url( $project_id )' ) && str_contains( $projectsAdmin, 'Menu::edit_work_item_url' ),
	'global Work Items remains the canonical management surface' => str_contains( $projectsAdmin, 'Menu::WORK_ITEMS_SLUG' ) && str_contains( $operations, 'render_work_items' ),
	'normal Work Item UX has no raw provider type id inputs' => ! str_contains( $workItemsAdmin, 'source_provider' ) && ! str_contains( $workItemsAdmin, 'source_type' ) && ! str_contains( $workItemsAdmin, 'source_id' ),
	'normal Work Item UX uses human customer and assignee pickers' => str_contains( $workItemsAdmin, 'Pickers::customer' ) && str_contains( $workItemsAdmin, 'Pickers::assignees' ),
	'CRM adapter uses only documented frontend query namespaces' => str_contains( $crm, '\\CB\\CRM\\Frontend\\Queries\\Contacts' ) && str_contains( $crm, '\\CB\\CRM\\Frontend\\Queries\\Organizations' ) && ! str_contains( $crm, 'Repository' ) && ! str_contains( $crm, '$wpdb' ),
	'CRM integration remains fail-soft and Work-readiness gated' => str_contains( $crm, 'public static function available' ) && str_contains( $crm, 'class_exists' ) && str_contains( $crm, 'cb_work_runtime_ready' ),
	'Base Object Picker backs async human selection' => str_contains( $pickers, 'CB\\Core\\UI\\ObjectPicker' ) && str_contains( $pickers, 'Assets::enqueue_object_picker' ),
	'assignee picker persists multiple IDs without jQuery' => str_contains( $pickers, 'self::render_user_picker( $name, $id, $user_ids, true );' ) && str_contains( $workItems, "explode( ',', (string) \$raw )" ),
	'Work Item Gutenberg REST reads stay capability-gated' => str_contains( $workItemRest, 'current_user_can( Capabilities::MANAGE )' ) && str_contains( $workItemRest, 'get_items_permissions_check' ),
];

foreach ( $checks as $label => $passed ) {
	if ( ! $passed ) {
		fwrite( STDERR, "D1.1/D1.2 architecture smoke failed: {$label}\n" );
		exit( 1 );
	}
}

echo "D1.1/D1.2 architecture smoke passed.\n";

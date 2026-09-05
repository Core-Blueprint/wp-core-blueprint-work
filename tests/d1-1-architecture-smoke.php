<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$postTypes = file_get_contents( $root . '/src/Content/PostTypes.php' );
$projectMeta = file_get_contents( $root . '/src/Content/ProjectMeta.php' );
$projectRepo = file_get_contents( $root . '/src/Repository/Projects.php' );
$workItems = file_get_contents( $root . '/src/Repository/WorkItems.php' );
$schema = file_get_contents( $root . '/src/Database/Schema.php' );
$operations = file_get_contents( $root . '/src/Admin/Operations.php' );
$projectsAdmin = file_get_contents( $root . '/src/Admin/Projects.php' );
$pickers = file_get_contents( $root . '/src/Admin/Pickers.php' );
$crm = file_get_contents( $root . '/src/Integration/CRMCustomers.php' );

$checks = [
	'Projects use canonical cb_work_project CPT' => str_contains( $postTypes, "PROJECT = 'cb_work_project'" ),
	'Project CPT remains private and admin-only by default' => substr_count( $postTypes, "'publicly_queryable'  => false" ) >= 2 && substr_count( $postTypes, "'show_in_rest'        => false" ) >= 2,
	'Project customer and dates are registered Work-owned post meta' => str_contains( $projectMeta, '_cb_work_project_customer_provider' ) && str_contains( $projectMeta, '_cb_work_project_starts_on' ) && str_contains( $projectMeta, '_cb_work_project_due_on' ),
	'Project repository is CPT-backed rather than SQL-backed' => str_contains( $projectRepo, 'new \\WP_Query' ) && str_contains( $projectRepo, 'get_post(' ) && str_contains( $projectRepo, 'wp_insert_post(' ) && ! str_contains( $projectRepo, 'Schema::' ) && ! str_contains( $projectRepo, '$wpdb' ),
	'transitional Project table is only destructively removed' => 1 === substr_count( $schema, 'cb_work_projects' ) && str_contains( $schema, 'DROP TABLE IF EXISTS' ) && ! str_contains( $schema, 'projects_table' ),
	'Work Item project_id remains relational and nullable' => str_contains( $schema, 'project_id bigint(20) unsigned NULL' ),
	'Work Item Project references validate through canonical Project repository' => str_contains( $workItems, 'Projects::get( $project_id )' ),
	'projectless Work Items remain supported' => str_contains( $workItems, "'project_id'          =>" ) && str_contains( $workItems, "? \$normalized['project_id'] : null" ),
	'Project customer may be inherited by a Work Item' => str_contains( $workItems, "\$project['customer_provider']" ) && str_contains( $workItems, "'customer'            => \$customer" ),
	'Project edit screen exposes contextual Work Items' => str_contains( $projectsAdmin, 'WorkItems::for_project' ) && str_contains( $projectsAdmin, "'create' => '1'" ) && str_contains( $projectsAdmin, "'project_id' => \$project_id" ),
	'global Work Items remains the canonical management surface' => str_contains( $projectsAdmin, 'Menu::WORK_ITEMS_SLUG' ) && str_contains( $operations, 'render_work_items' ),
	'normal Work Item UX has no raw provider type id inputs' => ! str_contains( $operations, 'reference_fields' ) && ! str_contains( $operations, 'source_provider' ) && ! str_contains( $operations, 'source_type' ) && ! str_contains( $operations, 'source_id' ),
	'normal Work Item UX uses human customer and assignee pickers' => str_contains( $operations, 'Pickers::customer' ) && str_contains( $operations, 'Pickers::assignees' ),
	'CRM adapter uses only documented frontend query namespaces' => str_contains( $crm, '\\CB\\CRM\\Frontend\\Queries\\Contacts' ) && str_contains( $crm, '\\CB\\CRM\\Frontend\\Queries\\Organizations' ) && ! str_contains( $crm, 'Repository' ) && ! str_contains( $crm, '$wpdb' ),
	'CRM integration remains fail-soft' => str_contains( $crm, 'public static function available' ) && str_contains( $crm, 'class_exists' ),
	'Base Object Picker backs async human selection' => str_contains( $pickers, 'CB\\Core\\UI\\ObjectPicker' ) && str_contains( $pickers, 'Assets::enqueue_object_picker' ),
	'assignee picker persists multiple IDs without jQuery' => str_contains( $pickers, "'multiple'      => true" ) && str_contains( $workItems, "explode( ',', (string) \$raw )" ),
];

foreach ( $checks as $label => $passed ) {
	if ( ! $passed ) {
		fwrite( STDERR, "D1.1 architecture smoke failed: {$label}\n" );
		exit( 1 );
	}
}

echo "D1.1 architecture smoke passed.\n";

<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$bootstrap = file_get_contents( $root . '/core-blueprint-work.php' );
$schema = file_get_contents( $root . '/src/Database/Schema.php' );
$postTypes = file_get_contents( $root . '/src/Content/PostTypes.php' );
$projectMeta = file_get_contents( $root . '/src/Content/ProjectMeta.php' );
$workItemMeta = file_get_contents( $root . '/src/Content/WorkItemMeta.php' );
$projects = file_get_contents( $root . '/src/Repository/Projects.php' );
$workItems = file_get_contents( $root . '/src/Repository/WorkItems.php' );

$checks = [
	'current Work schema is 1.7 after public source identity foundation' => str_contains( $bootstrap, "CB_WORK_SCHEMA_VERSION', '1.7'" ),
	'Project storage remains cb_work_project' => str_contains( $postTypes, "PROJECT   = 'cb_work_project'" ) && str_contains( $projectMeta, 'register_post_meta( PostTypes::PROJECT' ),
	'Work Item storage moves to cb_work_item' => str_contains( $postTypes, "WORK_ITEM = 'cb_work_item'" ) && str_contains( $workItemMeta, 'register_post_meta( PostTypes::WORK_ITEM' ),
	'transitional Project and Work Item tables are not registered' => ! str_contains( $schema, 'projects_table' ) && ! str_contains( $schema, 'work_items_table' ),
	'transitional Project and Work Item tables are removed explicitly' => str_contains( $schema, "DROP TABLE IF EXISTS ' . \$wpdb->prefix . 'cb_work_projects'" ) && str_contains( $schema, "DROP TABLE IF EXISTS ' . \$wpdb->prefix . 'cb_work_items'" ),
	'Work Type table remains Work-owned' => str_contains( $schema, "'cb_work_types'" ),
	'assignments remain relational many-to-many rows keyed by Work Item post ID' => str_contains( $schema, "'cb_work_item_assignments'" ) && str_contains( $schema, 'PRIMARY KEY  (work_item_id,user_id)' ),
	'external relations remain generic many-to-many integration metadata' => str_contains( $schema, "'cb_work_item_relations'" ) && str_contains( $schema, 'provider varchar(64)' ) && str_contains( $schema, 'relation_type varchar(64)' ) && str_contains( $schema, 'external_id varchar(191)' ) && str_contains( $schema, 'UNIQUE KEY relation (work_item_id,provider,relation_type,external_id)' ),
	'canonical source identity is separate from ordinary relations' => str_contains( $schema, "'cb_work_item_sources'" ) && str_contains( $schema, 'UNIQUE KEY source_identity (provider,source_type,external_id)' ),
	'cross-plugin SQL foreign keys remain forbidden' => ! str_contains( strtoupper( $schema ), 'FOREIGN KEY' ),
	'completion metadata remains separate from billing classification' => str_contains( $workItemMeta, '_cb_work_item_completed_at' ) && str_contains( $workItemMeta, '_cb_work_item_billing_disposition' ),
	'default Work Types seed idempotently by stable code' => str_contains( $schema, 'seed_default_work_types' ) && str_contains( $schema, "'development'    => 'Development'" ),
	'Project repository remains WordPress-native' => ! str_contains( $projects, 'Schema::' ) && ! str_contains( $projects, '$wpdb' ),
	'Work Item repository uses WordPress post storage plus relational child tables' => str_contains( $workItems, 'PostTypes::WORK_ITEM' ) && str_contains( $workItems, 'wp_insert_post(' ) && str_contains( $workItems, 'Schema::assignments_table()' ) && ! str_contains( $workItems, 'Schema::work_items_table' ),
	'Work Item creation validates optional Project Service and Work Type references' => str_contains( $workItems, 'Projects::get' ) && str_contains( $workItems, 'Services::get' ) && str_contains( $workItems, 'WorkTypes::get' ),
	'Work Item lifecycle emits public post-persistence hooks' => str_contains( $workItems, "do_action( 'cb_work_work_item_created'" ) && str_contains( $workItems, "do_action( 'cb_work_work_item_updated'" ) && str_contains( $workItems, "do_action( 'cb_work_work_item_status_changed'" ),
];

foreach ( $checks as $label => $passed ) {
	if ( ! $passed ) {
		fwrite( STDERR, "D1.2 schema smoke failed: {$label}\n" );
		exit( 1 );
	}
}

echo "D1.2 schema smoke passed.\n";

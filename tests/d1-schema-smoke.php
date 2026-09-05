<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$bootstrap = file_get_contents( $root . '/core-blueprint-work.php' );
$schema = file_get_contents( $root . '/src/Database/Schema.php' );
$workItems = file_get_contents( $root . '/src/Repository/WorkItems.php' );
$projects = file_get_contents( $root . '/src/Repository/Projects.php' );

$checks = [
	'D1 advances Work schema to 1.1' => str_contains( $bootstrap, "CB_WORK_SCHEMA_VERSION', '1.1'" ),
	'Project table is Work-owned' => str_contains( $schema, "'cb_work_projects'" ),
	'Work Type table is Work-owned' => str_contains( $schema, "'cb_work_types'" ),
	'Work Item table is Work-owned' => str_contains( $schema, "'cb_work_items'" ),
	'assignments are relational many-to-many rows' => str_contains( $schema, "'cb_work_item_assignments'" ) && str_contains( $schema, 'PRIMARY KEY  (work_item_id,user_id)' ),
	'external relations are generic soft references' => str_contains( $schema, "'cb_work_item_relations'" ) && str_contains( $schema, 'provider varchar(64)' ) && str_contains( $schema, 'relation_type varchar(64)' ) && str_contains( $schema, 'external_id varchar(191)' ),
	'cross-plugin SQL foreign keys remain forbidden' => ! str_contains( strtoupper( $schema ), 'FOREIGN KEY' ),
	'completion metadata is persisted separately from billing classification' => str_contains( $schema, 'completed_at datetime NULL' ) && str_contains( $schema, 'billing_disposition varchar(32)' ),
	'default Work Types seed idempotently by stable code' => str_contains( $schema, 'seed_default_work_types' ) && str_contains( $schema, "'development'    => 'Development'" ),
	'Project creation accepts optional soft customer references' => str_contains( $projects, 'customer_provider' ) && str_contains( $projects, 'customer_type' ) && str_contains( $projects, 'customer_id' ),
	'Work Item creation validates Work-owned project service and Work Type references' => str_contains( $workItems, 'Projects::get' ) && str_contains( $workItems, 'Services::get' ) && str_contains( $workItems, 'WorkTypes::get' ),
	'Work Item lifecycle emits public post-persistence hooks' => str_contains( $workItems, "do_action( 'cb_work_work_item_created'" ) && str_contains( $workItems, "do_action( 'cb_work_work_item_status_changed'" ),
];

foreach ( $checks as $label => $passed ) {
	if ( ! $passed ) {
		fwrite( STDERR, "D1 schema smoke failed: {$label}\n" );
		exit( 1 );
	}
}

echo "D1 schema smoke passed.\n";

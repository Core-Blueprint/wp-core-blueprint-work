<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$postTypes = file_get_contents( $root . '/src/Content/PostTypes.php' );
$meta = file_get_contents( $root . '/src/Content/WorkItemMeta.php' );
$rest = file_get_contents( $root . '/src/Content/WorkItemRestController.php' );
$repo = file_get_contents( $root . '/src/Repository/WorkItems.php' );
$admin = file_get_contents( $root . '/src/Admin/WorkItems.php' );
$operations = file_get_contents( $root . '/src/Admin/Operations.php' );
$projects = file_get_contents( $root . '/src/Admin/Projects.php' );
$menu = file_get_contents( $root . '/src/Admin/Menu.php' );
$schema = file_get_contents( $root . '/src/Database/Schema.php' );
$arch = file_get_contents( $root . '/docs/ARCHITECTURE.md' );

$checks = [
	'Work Item canonical CPT is cb_work_item' => 1 === preg_match( "/const\s+WORK_ITEM\s*=\s*'cb_work_item'/", $postTypes ),
	'Work Item CPT stays private but enables Gutenberg REST' => preg_match_all( "/'publicly_queryable'\s*=>\s*false/", $postTypes ) >= 2 && 1 === preg_match( "/'show_in_rest'\s*=>\s*true/", $postTypes ) && str_contains( $postTypes, 'WorkItemRestController::class' ),
	'Work Item REST reads require Work management capability' => str_contains( $rest, 'current_user_can( Capabilities::MANAGE )' ) && str_contains( $rest, 'get_items_permissions_check' ) && str_contains( $rest, 'get_item_permissions_check' ),
	'Work Item state is registered Work-owned post meta' => str_contains( $meta, 'register_post_meta( PostTypes::WORK_ITEM' ) && str_contains( $meta, '_cb_work_item_project_id' ) && str_contains( $meta, '_cb_work_item_status' ) && str_contains( $meta, '_cb_work_item_billing_disposition' ),
	'Work Item repository is CPT-backed' => str_contains( $repo, 'new \\WP_Query' ) && str_contains( $repo, 'get_post( $id )' ) && str_contains( $repo, 'wp_insert_post(' ) && str_contains( $repo, 'wp_update_post(' ) && ! str_contains( $repo, 'Schema::work_items_table' ),
	'Work Item repository validates Project Service and Work Type references' => str_contains( $repo, 'Projects::get( $project_id )' ) && str_contains( $repo, 'Services::get( $service_id )' ) && str_contains( $repo, 'WorkTypes::get( $work_type_id )' ),
	'projectless Work Items remain supported' => str_contains( $repo, "'project_id'          => \$project_id" ) && str_contains( $meta, 'self::write_id( $work_item_id, self::PROJECT_ID' ),
	'Project customer may still be inherited' => str_contains( $repo, "\$project['customer_provider']" ) && str_contains( $repo, "'customer_provider'   => \$customer['provider']" ),
	'assignments and integration relations stay relational child rows' => str_contains( $schema, "'cb_work_item_assignments'" ) && str_contains( $schema, "'cb_work_item_relations'" ) && str_contains( $repo, 'Schema::assignments_table()' ) && str_contains( $repo, 'Schema::relations_table()' ),
	'transitional relational Work Item table is destroyed not registered' => ! str_contains( $schema, 'work_items_table' ) && str_contains( $schema, "DROP TABLE IF EXISTS ' . \$wpdb->prefix . 'cb_work_items'" ),
	'pre-v1 orphan assignment relation rows are cleared on 1.3 conversion' => str_contains( $schema, "version_compare( \$previous_version, '1.3', '<' )" ) && str_contains( $schema, "'DELETE FROM ' . self::assignments_table()" ) && str_contains( $schema, "'DELETE FROM ' . self::relations_table()" ),
	'D1.2 destructive cleanup fails closed so Base cannot advance the schema marker after a failed cleanup' => str_contains( $schema, "if ( false === \$wpdb->query( 'DROP TABLE IF EXISTS '" ) && str_contains( $schema, "if ( false === \$wpdb->query( 'DELETE FROM ' . self::assignments_table() ) )" ),
	'Gutenberg Work Item editor owns the human details UX' => str_contains( $admin, 'add_meta_boxes_' ) && str_contains( $admin, "__( 'Work Item Details'" ) && str_contains( $admin, 'Pickers::customer' ) && str_contains( $admin, 'Pickers::assignees' ),
	'operational status remains separate from WordPress publish state' => str_contains( $admin, "'Operational status'" ) && str_contains( $admin, 'WorkItemStatus::transitions_from' ) && str_contains( strtolower( $arch ), 'publishing state is editor persistence state' ),
	'global Work Items remains the canonical operational workspace' => str_contains( $operations, 'render_work_items' ) && str_contains( $operations, 'Menu::new_work_item_url' ) && str_contains( $operations, 'Menu::edit_work_item_url' ) && ! str_contains( $operations, 'render_work_item_form' ),
	'Project context creates and opens native Work Item editor' => str_contains( $projects, 'Menu::new_work_item_url( $project_id )' ) && str_contains( $projects, 'Menu::edit_work_item_url' ),
	'Work menu stays selected while native Work Item editor is open' => str_contains( $menu, 'PostTypes::WORK_ITEM === (string) $screen->post_type' ) && str_contains( $menu, 'CONTEXT_WORK_ITEMS' ),
	'raw integration metadata stays out of normal Work Item editor' => ! str_contains( $admin, 'source_provider' ) && ! str_contains( $admin, 'source_type' ) && ! str_contains( $admin, 'source_id' ),
	'D2 D3 D4 ownership remains unchanged' => str_contains( $arch, '**D2 — Operational Views Foundation:**' ) && str_contains( $arch, '**D3 — Builder-neutral Frontend Resource Contracts:**' ) && str_contains( $arch, '**D4 — Bricks Adapter:**' ),
];

foreach ( $checks as $label => $passed ) {
	if ( ! $passed ) {
		fwrite( STDERR, "D1.2 Work Item CPT smoke failed: {$label}\n" );
		exit( 1 );
	}
}

echo "D1.2 Work Item CPT smoke passed.\n";

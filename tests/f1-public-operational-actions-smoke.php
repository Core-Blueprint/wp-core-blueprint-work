<?php
declare(strict_types=1);

$root       = dirname( __DIR__ );
$bootstrap  = file_get_contents( $root . '/core-blueprint-work.php' );
$schema     = file_get_contents( $root . '/src/Database/Schema.php' );
$sources    = file_get_contents( $root . '/src/Repository/WorkItemSources.php' );
$relations  = file_get_contents( $root . '/src/Repository/WorkItemRelations.php' );
$reads      = file_get_contents( $root . '/src/PublicApi/WorkItems.php' );
$actions    = file_get_contents( $root . '/src/PublicApi/WorkItemActions.php' );
$events     = file_get_contents( $root . '/src/Governance/Events.php' );
$plugin     = file_get_contents( $root . '/src/Plugin.php' );

$checks = [
	'F1 remains present while Work schema advances to 1.9 and plugin remains rc1' => str_contains( $bootstrap, "CB_WORK_VERSION', '1.0.0-rc1'" ) && str_contains( $bootstrap, "CB_WORK_SCHEMA_VERSION', '1.9'" ),
	'canonical external source identities have dedicated Work-owned storage' => str_contains( $schema, "'cb_work_item_sources'" ) && str_contains( $schema, 'source_type varchar(64)' ),
	'external source identity is database-unique independent of ordinary relations' => str_contains( $schema, 'UNIQUE KEY source_identity (provider,source_type,external_id)' ) && str_contains( $schema, 'UNIQUE KEY work_item_source (work_item_id)' ),
	'ordinary relations remain many-to-many per Work Item instead of globally unique' => str_contains( $schema, 'UNIQUE KEY relation (work_item_id,provider,relation_type,external_id)' ) && ! str_contains( $schema, 'UNIQUE KEY relation (provider,relation_type,external_id)' ),
	'source claims have bounded stale takeover state' => str_contains( $sources, 'claimed_at' ) && str_contains( $sources, 'stale_before' ) && str_contains( $sources, "'status'       => 'busy'" ),
	'normal source replay avoids expected duplicate-key database errors' => str_contains( $sources, 'INSERT IGNORE INTO' ) && str_contains( $sources, 'if ( 1 === $ok )' ),
	'source creation has a recovery marker for interrupted post-to-source attachment' => str_contains( $sources, "RECOVERY_PROVIDER = 'core-blueprint-work'" ) && str_contains( $sources, "RECOVERY_TYPE     = 'source_identity'" ) && str_contains( $actions, 'recovery_work_item_ids' ),
	'internal source recovery marker is reserved from public source and relation input' => str_contains( $actions, 'is_reserved_reference' ) && str_contains( $actions, "'work_source_reserved'" ) && str_contains( $actions, "'work_relation_reserved'" ),
	'internal source recovery metadata is filtered out of public Work Item projections' => str_contains( $reads, 'private static function project' ) && str_contains( $reads, 'is_reserved_relation' ),
	'one source replay reuses the existing canonical Work Item' => str_contains( $actions, "'outcome' => 'reused'" ) && str_contains( $actions, "'outcome' => \$outcome" ),
	'direct public create cannot smuggle private source fields around the source contract' => str_contains( $actions, 'contains_source_fields' ) && str_contains( $actions, "'work_source_contract_required'" ),
	'public mutations require canonical Work management authorization' => str_contains( $actions, 'current_user_can( Capabilities::MANAGE )' ),
	'public mutations delegate to the canonical repository instead of duplicating Work Item persistence' => str_contains( $actions, 'WorkItemRepository::create' ) && str_contains( $actions, 'WorkItemRepository::update' ) && str_contains( $actions, 'WorkItemRepository::transition_status' ),
	'public read API resolves Work Items by source and by many-to-many relation' => str_contains( $reads, 'get_by_source' ) && str_contains( $reads, 'by_relation' ) && str_contains( $reads, 'WorkItemRelations::find_work_item_ids' ),
	'reverse relation lookup is provider-neutral and bounded' => str_contains( $relations, 'provider = %s AND relation_type = %s AND external_id = %s' ) && str_contains( $relations, 'min( 500, $limit )' ) && str_contains( $relations, 'LIMIT %d' ) && str_contains( $reads, 'int $limit = 100' ),
	'source and relation mutations are observable through governance and lifecycle hooks' => str_contains( $events, 'work.item.source.attached' ) && str_contains( $events, 'work.item.relation.added' ) && str_contains( $actions, "do_action( 'cb_work_work_item_source_attached'" ) && str_contains( $actions, "do_action( 'cb_work_work_item_relation_added'" ),
	'deleting a canonical Work Item removes its source identity' => str_contains( $plugin, 'WorkItemSources::init();' ) && str_contains( $sources, "add_action( 'before_delete_post'" ) && str_contains( $sources, "Schema::sources_table(), [ 'work_item_id' => \$post_id ]" ),
	'F1 remains sibling-neutral and does not hard-code CRM Helpdesk Contracts or Invoice and Quotes namespaces' => ! str_contains( $actions . $sources . $relations, 'CB\\CRM\\' ) && ! str_contains( $actions . $sources . $relations, 'CB\\Helpdesk\\' ) && ! str_contains( $actions . $sources . $relations, 'CB\\Contracts\\' ) && ! str_contains( $actions . $sources . $relations, 'Invoice' ),
	'F1 adds no cross-plugin SQL foreign keys' => ! str_contains( strtoupper( $schema ), 'FOREIGN KEY' ),
];

foreach ( $checks as $label => $passed ) {
	if ( ! $passed ) {
		fwrite( STDERR, "F1 public operational actions smoke failed: {$label}\n" );
		exit( 1 );
	}
}

echo "F1 public operational actions smoke passed.\n";

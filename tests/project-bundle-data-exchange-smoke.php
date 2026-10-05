<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$bootstrap = file_get_contents( $root . '/core-blueprint-work.php' );
$schema = file_get_contents( $root . '/src/Database/Schema.php' );
$portable = file_get_contents( $root . '/src/Repository/PortableIdentities.php' );
$integration = file_get_contents( $root . '/src/Integration/DataExchange.php' );
$entity = file_get_contents( $root . '/src/Integration/DataExchange/ProjectBundleEntity.php' );
$admin = file_get_contents( $root . '/src/Admin/ProjectDataExchange.php' );
$projectActions = file_get_contents( $root . '/src/PublicApi/ProjectActions.php' );
$projects = file_get_contents( $root . '/src/Repository/Projects.php' );
$plugin = file_get_contents( $root . '/src/Plugin.php' );
$menu = file_get_contents( $root . '/src/Admin/Menu.php' );
$fixturePath = $root . '/docs/examples/acquisition-sprint-v1.cb-work.json';
$fixtureRaw = file_get_contents( $fixturePath );
$fixture = is_string( $fixtureRaw ) ? json_decode( $fixtureRaw, true ) : null;

$uuid = static fn( mixed $value ): bool =>
	is_string( $value )
	&& 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $value );

$fixtureValid = is_array( $fixture )
	&& 'core-blueprint-data-exchange' === ( $fixture['format'] ?? null )
	&& 1 === ( $fixture['format_version'] ?? null )
	&& 'core-blueprint-work' === ( $fixture['extension_id'] ?? null )
	&& 'project-bundle' === ( $fixture['entity'] ?? null )
	&& 1 === ( $fixture['schema_version'] ?? null )
	&& isset( $fixture['records'][0] )
	&& is_array( $fixture['records'][0] )
	&& $uuid( $fixture['records'][0]['portable_key'] ?? null )
	&& 'internal' === ( $fixture['records'][0]['work_context'] ?? null )
	&& isset( $fixture['records'][0]['work_items'] )
	&& is_array( $fixture['records'][0]['work_items'] )
	&& 18 === count( $fixture['records'][0]['work_items'] );

if ( $fixtureValid ) {
	$seen = [ $fixture['records'][0]['portable_key'] => true ];
	foreach ( $fixture['records'][0]['work_items'] as $item ) {
		$key = $item['portable_key'] ?? null;
		if (
			! $uuid( $key )
			|| isset( $seen[ $key ] )
			|| ! in_array( $item['status'] ?? '', [ 'planned', 'in_progress', 'blocked' ], true )
		) {
			$fixtureValid = false;
			break;
		}
		$seen[ $key ] = true;
	}
}

$checks = [
	'Work schema 2.0 owns portable identity registry' =>
		str_contains( $bootstrap, "CB_WORK_SCHEMA_VERSION', '2.0'" )
		&& str_contains( $schema, "'cb_work_portable_identities'" )
		&& str_contains( $schema, 'UNIQUE KEY entity_local (entity_type,local_id)' )
		&& str_contains( $schema, 'UNIQUE KEY portable_key (portable_key)' ),
	'portable identity repository uses immutable UUIDv4 identity instead of titles or local IDs' =>
		str_contains( $portable, "public const PROJECT   = 'project'" )
		&& str_contains( $portable, "public const WORK_ITEM = 'work_item'" )
		&& str_contains( $portable, 'wp_generate_uuid4()' )
		&& str_contains( $portable, 'work_portable_identity_immutable' )
		&& str_contains( $portable, 'work_portable_identity_collision' ),
	'Project bundle is a JSON-only Base Data Exchange entity' =>
		str_contains( $integration, "PROJECT_BUNDLE_ENTITY = 'project-bundle'" )
		&& str_contains( $integration, 'ProjectBundleEntity' )
		&& str_contains( $integration, 'Foundation::SUPPORT_JSON' )
		&& str_contains( $entity, 'implements EntityInterface' )
		&& str_contains( $entity, 'private const SCHEMA_VERSION = 1;' ),
	'Project bundle v1 is deliberately bounded to internal active planning data' =>
		str_contains( $entity, 'WorkContext::INTERNAL' )
		&& str_contains( $entity, 'WorkItemStatus::active()' )
		&& ! str_contains( $entity, "'service_id'" )
		&& ! str_contains( $entity, "'work_type_id'" )
		&& ! str_contains( $entity, "'assigned_user_ids'" )
		&& ! str_contains( $entity, "'completed_at'" ),
	'Project bundle resolves portable identities and never matches by title' =>
		str_contains( $entity, 'PortableIdentities::local_id' )
		&& str_contains( $entity, 'PortableIdentities::claim' )
		&& ! str_contains( $entity, 'get_page_by_title' ),
	'Project mutation contract is governed and canonical' =>
		str_contains( $projectActions, 'current_user_can( Capabilities::MANAGE )' )
		&& str_contains( $projectActions, 'ProjectRepository::create' )
		&& str_contains( $projectActions, 'ProjectRepository::update' )
		&& str_contains( $projects, 'public static function update( int $id, array $input ): bool' ),
	'Project Data Exchange uses preview fingerprint apply and capability/nonce boundaries' =>
		str_contains( $admin, 'Engine::preview_json' )
		&& str_contains( $admin, 'Engine::apply_json' )
		&& str_contains( $admin, 'check_ajax_referer' )
		&& str_contains( $admin, 'check_admin_referer' )
		&& str_contains( $admin, 'current_user_can( Capabilities::MANAGE )' ),
	'Project import/export is integrated into Work admin runtime' =>
		str_contains( $plugin, 'ProjectDataExchange::init();' )
		&& str_contains( $menu, "PROJECT_IMPORT_SLUG    = 'core-blueprint-work-project-import'" )
		&& str_contains( $menu, 'ProjectDataExchange::class' ),
	'Acquisition Sprint fixture is valid portable schema v1 with 18 active Work Items' => $fixtureValid,
];

foreach ( $checks as $label => $passed ) {
	if ( ! $passed ) {
		fwrite( STDERR, "Project bundle Data Exchange smoke failed: {$label}\n" );
		exit( 1 );
	}
}

echo "Project bundle Data Exchange smoke passed.\n";

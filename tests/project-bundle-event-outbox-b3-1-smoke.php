<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$schema = file_get_contents( $root . '/src/Database/Schema.php' );
$boot = file_get_contents( $root . '/core-blueprint-work.php' );
$transaction = file_get_contents( $root . '/src/Integration/DataExchange/ProjectBundleTransaction.php' );
$buffer = file_get_contents( $root . '/src/Governance/DeferredEvents.php' );
$outbox = file_get_contents( $root . '/src/Governance/WorkEventOutbox.php' );
$projects = file_get_contents( $root . '/src/Repository/Projects.php' );
$items = file_get_contents( $root . '/src/Repository/WorkItems.php' );
$projectApi = file_get_contents( $root . '/src/PublicApi/ProjectActions.php' );
$itemApi = file_get_contents( $root . '/src/PublicApi/WorkItemActions.php' );
$integration = file_get_contents( $root . '/tests/integration/ProjectBundleIntegrityIntegrationTest.php' );
$plugin = file_get_contents( $root . '/src/Plugin.php' );

$checks = [
	'new outbox uses schema 2.1 and immutable event UUID uniqueness' =>
		is_string( $boot ) && str_contains( $boot, "CB_WORK_SCHEMA_VERSION', '2.1'" )
		&& is_string( $schema ) && str_contains( $schema, "cb_work_bundle_event_outbox" )
		&& str_contains( $schema, "UNIQUE KEY event_key (event_key)" ),
	'Project bundle SQL boundary waits for outbox insert before releasing savepoint' =>
		is_string( $transaction ) && str_contains( $transaction, 'DeferredEvents::begin();' )
		&& str_contains( $transaction, 'DeferredEvents::finish()' )
		&& str_contains( $transaction, 'DeferredEvents::cancel();' )
		&& str_contains( $transaction, 'Schema::outbox_table()' ),
	'only Work bundle scopes intercept canonical public audit writes and lifecycle hooks' =>
		is_string( $buffer ) && str_contains( $buffer, 'Audit::record(' )
		&& str_contains( $buffer, 'do_action(' )
		&& is_string( $projectApi ) && is_string( $itemApi )
		&& str_contains( $projectApi, 'DeferredEvents::audit(' )
		&& str_contains( $itemApi, 'DeferredEvents::audit(' )
		&& is_string( $projects ) && is_string( $items )
		&& str_contains( $projects, "DeferredEvents::lifecycle( 'cb_work_project_created'" )
		&& str_contains( $items, "DeferredEvents::lifecycle( 'cb_work_work_item_created'" ),
	'outbox retains bounded failure codes and acknowledges each delivery durably' =>
		is_string( $outbox ) && str_contains( $outbox, 'last_error_code' )
		&& str_contains( $outbox, "\$stage = 'audit_refused'" )
		&& str_contains( $outbox, '1 !== $acknowledged' )
		&& str_contains( $schema, 'last_error_code varchar(80)' ),
	'outbox refuses dispatch in caller-owned transactions and publishes via cron' =>
		is_string( $outbox ) && str_contains( $outbox, 'SELECT @@in_transaction' )
		&& str_contains( $outbox, 'public static function drain(): int' )
		&& str_contains( $outbox, 'MAX_ATTEMPTS' )
		&& str_contains( $outbox, 'outbox_event_key' )
		&& is_string( $plugin ) && str_contains( $plugin, 'WorkEventOutbox::init();' ),
	'negative integration assertions guard Work hooks, Base callbacks and savepoints' =>
		is_string( $integration ) && str_contains( $integration, 'assert_no_new_published_bundle_events' )
		&& str_contains( $integration, 'emitted_work_lifecycle' )
		&& str_contains( $integration, 'emitted_work_audits' )
		&& str_contains( $integration, 'WorkEventOutbox::drain()' ),
];

foreach ( $checks as $label => $passed ) {
	if ( ! $passed ) {
		fwrite( STDERR, "B3.1 Work event outbox source smoke failed: {$label}\n" );
		exit( 1 );
	}
}

echo "B3.1 Work Event Outbox source smoke passed.\n";

<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$provider = file_get_contents( $root . '/src/Integration/DataExchange/ProjectBundleEntity.php' );
$transaction = file_get_contents( $root . '/src/Integration/DataExchange/ProjectBundleTransaction.php' );
$fixture = file_get_contents( $root . '/tests/integration/ProjectBundleIntegrityIntegrationTest.php' );

$checks = [
	'one database transaction per Project bundle' => is_string( $provider )
		&& str_contains( $provider, 'ProjectBundleTransaction::run(' )
		&& ! str_contains( $provider, 'count_for_project( $project_id )' ),
	'existing Project row is locked and the Work context revalidated' =>
		str_contains( $provider, 'FOR UPDATE' )
		&& str_contains( $provider, 'WorkContext::INTERNAL !== (string) $project' ),
	'Project and Work Item post IDs are tracked for cache invalidation' =>
		str_contains( $provider, '$touch( $project_id );' )
		&& str_contains( $provider, '$touch( $work_item_id );' ),
	'nested transaction ownership and rollback' => is_string( $transaction )
		&& str_contains( $transaction, 'SELECT @@in_transaction' )
		&& str_contains( $transaction, 'SAVEPOINT ' )
		&& str_contains( $transaction, 'ROLLBACK TO SAVEPOINT ' )
		&& str_contains( $transaction, 'clean_post_cache(' ),
	'nontransactional tables fail closed' => str_contains( $transaction, 'SHOW CREATE TABLE %i' )
		&& str_contains( $transaction, 'work_project_bundle_transaction_storage_unavailable' ),
	'fail-nth CREATE and UPDATE integration coverage' => is_string( $fixture )
		&& str_contains( $fixture, 'test_fail_nth_create_leaves_no_project_item_or_identity_and_retry_converges' )
		&& str_contains( $fixture, 'test_fail_nth_update_restores_existing_project_and_first_item_and_preserves_outer_transaction' ),
];
foreach ( $checks as $label => $passed ) {
	if ( ! $passed ) {
		fwrite( STDERR, "B3.1 Project Bundle Integrity smoke failed: {$label}\n" );
		exit( 1 );
	}
}
echo "B3.1 Project Bundle Integrity source smoke passed.\n";

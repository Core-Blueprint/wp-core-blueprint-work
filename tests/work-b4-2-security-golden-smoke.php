<?php
declare(strict_types=1);

/** B4.2 source boundary regression, supplements real WordPress/MariaDB tests. */
$root = dirname( __DIR__ );
$read = static function ( string $path ) use ( $root ): string {
	$value = file_get_contents( $root . '/' . $path );
	return is_string( $value ) ? $value : '';
};

$scheduler = $read( 'src/Recurrence/Scheduler.php' );
$trash = $read( 'src/Repository/RecurrenceRules.php' );
$tx = $read( 'src/Repository/WorkSqlTransaction.php' );
$outbox = $read( 'src/Governance/WorkEventOutbox.php' );
$lifecycle = $read( 'src/Lifecycle.php' );
$bootstrap = $read( 'core-blueprint-work.php' );
$operations = $read( 'src/Admin/Operations.php' );
$phpunit = $read( 'tests/integration/WorkB42LockAndAccessIntegrationTest.php' );
$calibration = $read( 'tools/audit-b4-phpstan-constants' );

$locked_start = strpos( $scheduler, 'WorkSqlTransaction::run(' );
$row_lock = strpos( $scheduler, "'SELECT id, is_active, trashed_at, created_by FROM ' . \$table . ' WHERE id = %d FOR UPDATE'" );
$gate = strpos( $scheduler, "\$claimed['claim_token'] !== \$token" );
$create = strpos( $scheduler, 'WorkItems::create( $input )' );
$attach = strpos( $scheduler, 'RecurrenceOccurrences::attach_work_item( $occurrence_id, $created_id, $token )' );
$closure_end = strpos( $scheduler, 'return $created_id;' );

$checks = [
	'new WordPress Work Item creation takes the same row lock as Trash inside savepoint-aware transaction' =>
		false !== $locked_start && false !== $row_lock && false !== $gate
		&& false !== $create && false !== $attach && false !== $closure_end
		&& $locked_start < $row_lock && $row_lock < $gate
		&& $gate < $create && $create < $attach && $attach < $closure_end,
	'permanent Trash mutation retains its own database row lock' =>
		str_contains( $trash, 'public static function trash(' )
		&& str_contains( $trash, "'SELECT id FROM ' . \$table . ' WHERE id = %d FOR UPDATE'" ),
	'claim token must be checked immediately before creating the Work Item' =>
		str_contains( $scheduler, "(string) \$claimed['claim_token'] !== \$token" ),
	'failed transaction returns without a post-Trash creation attempt' =>
		str_contains( $scheduler, "empty( \$after['is_active'] ) || ! empty( \$after['is_trashed'] )" ),
	'WorkSqlTransaction uses savepoints instead of committing parent transactions' =>
		str_contains( $tx, "'SAVEPOINT ' . \$savepoint" )
		&& str_contains( $tx, "'RELEASE SAVEPOINT ' . \$savepoint" ),
	'outbox cron hook adapts int delivery count to void while direct callers retain integer' =>
		str_contains( $outbox, "add_action( self::HOOK, [ self::class, 'drain_cron' ] )" )
		&& str_contains( $outbox, 'public static function drain_cron(): void' )
		&& str_contains( $outbox, 'public static function drain(): int' ),
	'WordPress wp_die arguments escape both link URL and label' =>
		str_contains( $lifecycle, "esc_url( admin_url( 'plugins.php' ) )" )
		&& str_contains( $lifecycle, "esc_html__( 'Plugins' )" )
		&& str_contains( $bootstrap, "esc_url( admin_url( 'plugins.php' ) )" )
		&& str_contains( $bootstrap, "esc_html__( 'Plugins', 'core-blueprint-work' )" ),
	'immutable G0 Operations source is preserved with an exact one-error local PHPStan exception' =>
		str_contains( $operations, "/** @param int[] \$ids */\n\tprivate static function work_item_date_label( string \$date ): string" )
		&& str_contains( $calibration, 'identifier: parameter.notFound' )
		&& str_contains( $calibration, 'count: 1' )
		&& str_contains( $calibration, 'work-phpstan-unfiltered.json' ),
	'real security regression suite exercises two DB sessions and actor, REST, nonce permissions' =>
		str_contains( $phpunit, 'test_two_connections_serialize_creation_before_trash' )
		&& str_contains( $phpunit, 'test_private_project_and_work_item_rest_reads_fail_closed' )
		&& str_contains( $phpunit, 'test_manager_with_invalid_nonce_cannot_mutate_work' )
		&& str_contains( $phpunit, 'test_unprivileged_admin_post_mutations_fail_closed' ),
];
foreach ( $checks as $label => $ok ) {
	if ( ! $ok ) {
		fwrite( STDERR, "Work B4.2 Security Golden FAILED: {$label}\n" );
		exit( 1 );
	}
}
echo "Work B4.2 Security Golden source contracts: PASS\n";

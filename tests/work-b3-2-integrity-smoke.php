<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$tx = file_get_contents( $root . '/src/Repository/WorkSqlTransaction.php' );
$recurrence = file_get_contents( $root . '/src/Repository/RecurrenceRules.php' );
$billing = file_get_contents( $root . '/src/Repository/BillingUnits.php' );
$frontend = file_get_contents( $root . '/src/Frontend/Actions/WorkItems.php' );
$public = file_get_contents( $root . '/src/PublicApi/WorkItemActions.php' );
$read = file_get_contents( $root . '/src/Repository/WorkItems.php' );
$gate = file_get_contents( $root . '/tools/check' );
/**
 * Validate transaction ownership at the method boundary. A strict total count
 * would block safely adding more guarded repository operations in the future.
 */
$uses_transaction = static function ( string $source, string $method ): bool {
	$signature = 'public static function ' . $method . '(';
	$start = strpos( $source, $signature );
	if ( false === $start ) {
		return false;
	}
	$end = strpos( $source, "\n\t}", $start );
	return false !== $end
		&& str_contains( substr( $source, $start, $end - $start ), 'WorkSqlTransaction::run(' );
};

$checks = [
	'B3.2 uses existing transaction state and savepoints' => str_contains( $tx, 'SELECT @@in_transaction' ) && str_contains( $tx, "'SAVEPOINT '" ) && str_contains( $tx, "'ROLLBACK TO SAVEPOINT '" ),
	'B3.2 checks the finish statement' => str_contains( $tx, 'false === $wpdb->query( $finish )' ),
	'B3.2 recurrence create and update retain transaction ownership' =>
		$uses_transaction( $recurrence, 'create' )
		&& $uses_transaction( $recurrence, 'update' )
		&& ! str_contains( $recurrence, "'START TRANSACTION'" )
		&& ! str_contains( $recurrence, "'COMMIT'" ),
	'New recurrence lifecycle operations preserve shared transaction ownership' =>
		$uses_transaction( $recurrence, 'trash' )
		&& $uses_transaction( $recurrence, 'restore' )
		&& $uses_transaction( $recurrence, 'delete_permanently' )
		&& $uses_transaction( $recurrence, 'reserve_next_occurrence' ),
	'B3.2 billing critical multirow writes use transaction ownership' => substr_count( $billing, 'WorkSqlTransaction::run(' ) === 2 && ! str_contains( $billing, "'START TRANSACTION'" ) && ! str_contains( $billing, "'COMMIT'" ),
	'B3.2 frontend requires client expected status and reports conflict' => str_contains( $frontend, "'work_transition_precondition_required'" ) && str_contains( $frontend, "'work_transition_conflict'" ) && str_contains( $frontend, '$expected_from' ),
	'B3.2 public PHP action carries expected source into locked repository' => str_contains( $public, '$expected_from ?? $from' ),
	'B3.2 canonical repository CAS remains in place' => str_contains( $read, 'WorkItemMutationTransaction::run' ) && str_contains( $read, '$expected_from' ),
	'B3.2 source smoke integrated into Level 1' => str_contains( $gate, 'work-b3-2-integrity-smoke.php' ),
];
foreach ( $checks as $label => $pass ) {
	if ( ! $pass ) {
		fwrite( STDERR, "B3.2 source smoke failed: {$label}\n" );
		exit( 1 );
	}
}
echo "B3.2 source smoke passed.\n";

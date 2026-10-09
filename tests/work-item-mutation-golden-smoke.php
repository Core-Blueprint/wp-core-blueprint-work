<?php
declare(strict_types=1);

// A1 source+transaction behavior smoke. No WordPress boot or database access.
define( 'ABSPATH', '/tmp/cb-work-a1/' );
define( 'ARRAY_A', 'ARRAY_A' );

require dirname( __DIR__ ) . '/src/Content/PostTypes.php';
require dirname( __DIR__ ) . '/src/Repository/WorkItemMutationTransaction.php';

use CB\Work\Repository\WorkItemMutationTransaction;

final class WorkMutationFakeDatabase {
	public string $posts = 'wp_posts';
	public int $in_transaction = 0;
	public bool $row_exists = true;
	public bool $fail_commit = false;
	public array $queries = [];

	public function get_var( string $sql ): string {
		if ( 'SELECT @@in_transaction' !== $sql ) {
			throw new RuntimeException( 'Unexpected query.' );
		}
		return (string) $this->in_transaction;
	}

	public function prepare( string $sql, mixed ...$args ): string {
		return sprintf( str_replace( [ '%d', '%s' ], [ '%d', "'%s'" ], $sql ), ...$args );
	}

	public function query( string $sql ): int|false {
		$this->queries[] = $sql;
		return $this->fail_commit && 'COMMIT' === $sql ? false : 0;
	}

	public function get_row( string $sql, string $format ): ?array {
		if ( 'ARRAY_A' !== $format || ! str_contains( $sql, 'FOR UPDATE' ) ) {
			throw new RuntimeException( 'Post lock must be selected.' );
		}
		return $this->row_exists ? [ 'ID' => 123 ] : null;
	}
}

function clean_post_cache( int $id ): void {
	if ( 123 !== $id ) {
		throw new RuntimeException( 'Unexpected cached post ID.' );
	}
}
function wp_cache_delete( int $id, string $group ): bool {
	return 123 === $id && 'post_meta' === $group;
}

$root = dirname( __DIR__ );
$repo = (string) file_get_contents( $root . '/src/Repository/WorkItems.php' );
$meta = (string) file_get_contents( $root . '/src/Content/WorkItemMeta.php' );
$board = (string) file_get_contents( $root . '/src/Admin/WorkItemBoardActions.php' );
$client = (string) file_get_contents( $root . '/assets/work-items-reorder.js' );
$checks = [
	'status uses locked transaction and expected snapshot' =>
		str_contains( $repo, 'WorkItemMutationTransaction::run( $id' )
		&& str_contains( $repo, '$from !== $expected_from' )
		&& str_contains( $repo, 'WorkItemMeta::set_status( $id, $to, $actor_user_id, $previous_raw )' ),
	'completion metadata is verified inside transaction' =>
		str_contains( $meta, 'public static function set_status(' )
		&& str_contains( $meta, "false === update_post_meta( \$work_item_id, self::STATUS" )
		&& str_contains( $meta, 'wp_cache_delete( $work_item_id' )
		&& str_contains( $meta, "null === \$actual['completed_at']" ),
	'Work-owned field verification follows metadata writes' =>
		str_contains( $repo, 'WorkItemMeta::details_match( $id, $normalized )' )
		&& str_contains( $meta, 'function details_match(' ),
	'assignments never begin a nested SQL transaction' =>
		str_contains( $repo, 'private static function write_assignments(' )
		&& ! str_contains( $repo, "\$wpdb->query( 'START TRANSACTION' )" ),
	'Board posts snapshot and rejects stale requests' =>
		str_contains( $client, "body.set('expected_status', card.dataset.cbWorkStatus || '')" )
		&& str_contains( $board, "'expected_status'" )
		&& str_contains( $board, '409' ),
];

foreach ( $checks as $message => $passed ) {
	if ( ! $passed ) {
		fwrite( STDERR, "Work mutation A1 smoke FAILED: {$message}\n" );
		exit( 1 );
	}
}

global $wpdb;
$wpdb = new WorkMutationFakeDatabase();
if ( ! WorkItemMutationTransaction::run( 123, static fn(): bool => true )
	|| $wpdb->queries[0] !== 'START TRANSACTION'
	|| end( $wpdb->queries ) !== 'COMMIT' ) {
	throw new RuntimeException( 'New transaction must commit.' );
}

$wpdb = new WorkMutationFakeDatabase();
$wpdb->in_transaction = 1;
if ( ! WorkItemMutationTransaction::run( 123, static fn(): bool => true )
	|| ! str_starts_with( $wpdb->queries[0], 'SAVEPOINT cb_work_a1_' )
	|| ! str_starts_with( (string) end( $wpdb->queries ), 'RELEASE SAVEPOINT cb_work_a1_' )
	|| in_array( 'COMMIT', $wpdb->queries, true )
	|| in_array( 'START TRANSACTION', $wpdb->queries, true ) ) {
	throw new RuntimeException( 'Ambient transaction must use an owned savepoint.' );
}

$wpdb = new WorkMutationFakeDatabase();
$wpdb->in_transaction = 1;
if ( WorkItemMutationTransaction::run( 123, static fn(): bool => false )
	|| ! str_starts_with( $wpdb->queries[1], 'ROLLBACK TO SAVEPOINT cb_work_a1_' ) ) {
	throw new RuntimeException( 'Failed nested mutation must roll back its savepoint.' );
}

$wpdb = new WorkMutationFakeDatabase();
$wpdb->row_exists = false;
if ( WorkItemMutationTransaction::run( 123, static fn(): bool => true )
	|| ! in_array( 'ROLLBACK', $wpdb->queries, true ) ) {
	throw new RuntimeException( 'Absent Work Item row must fail closed.' );
}

$wpdb = new WorkMutationFakeDatabase();
try {
	WorkItemMutationTransaction::run( 123, static function (): bool {
		throw new RuntimeException( 'simulated write failure' );
	} );
	throw new RuntimeException( 'Exception must propagate.' );
} catch ( RuntimeException $e ) {
	if ( 'simulated write failure' !== $e->getMessage()
		|| ! in_array( 'ROLLBACK', $wpdb->queries, true ) ) {
		throw $e;
	}
}

echo "Work Item Mutation A1 Golden smoke passed (locks, root/nested transactions, rollback, source contracts).\n";

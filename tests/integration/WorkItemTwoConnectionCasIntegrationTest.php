<?php
declare(strict_types=1);

/**
 * Optional A1 SQL-CAS cross-session fixture, patterned after WT-G-006.
 *
 * It models the status/meta compare-and-swap predicate on one shared InnoDB
 * row; it does NOT claim to execute WordPress' update_post_meta() in parallel.
 * Only the local Docker wordpress_test database is permitted. Unlike a
 * TEMPORARY table, the uniquely named fixture is visible to TWO connections.
 */
final class WorkItemTwoConnectionCasIntegrationTest extends WP_UnitTestCase {
	private ?wpdb $connection_a = null;
	private ?wpdb $connection_b = null;
	private string $shared_table = '';
	private bool $owns_table = false;

	public function set_up(): void {
		if ( ! defined( 'DB_NAME' ) || 'wordpress_test' !== (string) DB_NAME
			|| ! defined( 'DB_HOST' ) || '127.0.0.1:3307' !== (string) DB_HOST ) {
			self::fail( 'Work A1 two-connection fixture requires local wordpress_test at 127.0.0.1:3307.' );
		}
		parent::set_up();
	}

	public function tear_down(): void {
		try {
			if ( $this->owns_table && $this->connection_a instanceof wpdb
				&& 1 === preg_match( '/^cb_wa1_status_[a-f0-9]{16}$/D', $this->shared_table ) ) {
				$deleted = mysqli_query( $this->connection_a->dbh, 'DROP TABLE `' . $this->shared_table . '`' );
				if ( true !== $deleted ) {
					throw new RuntimeException( 'Work A1 shared fixture cleanup failed: ' . mysqli_error( $this->connection_a->dbh ) );
				}
				$this->owns_table = false;
			}
		} finally {
			if ( $this->connection_b instanceof wpdb ) {
				$this->connection_b->close();
			}
			if ( $this->connection_a instanceof wpdb ) {
				$this->connection_a->close();
			}
			parent::tear_down();
		}
	}

	public function test_two_sessions_reject_a_stale_status_after_completion_and_reopen(): void {
		$this->connection_a = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$this->connection_b = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$a = $this->connection_a;
		$b = $this->connection_b;
		self::assertInstanceOf( mysqli::class, $a->dbh );
		self::assertInstanceOf( mysqli::class, $b->dbh );
		self::assertTrue( mysqli_select_db( $a->dbh, DB_NAME ) );
		self::assertTrue( mysqli_select_db( $b->dbh, DB_NAME ) );
		self::assertNotSame( (int) $a->get_var( 'SELECT CONNECTION_ID()' ), (int) $b->get_var( 'SELECT CONNECTION_ID()' ) );

		$this->shared_table = 'cb_wa1_status_' . bin2hex( random_bytes( 8 ) );
		self::assertMatchesRegularExpression( '/^cb_wa1_status_[a-f0-9]{16}$/D', $this->shared_table );
		$table = '`' . $this->shared_table . '`';
		$created = mysqli_query( $a->dbh, 'CREATE TABLE ' . $table . ' (
			id bigint unsigned NOT NULL PRIMARY KEY,
			status varchar(24) NOT NULL,
			completed_at datetime NULL,
			completed_by bigint unsigned NULL
		) ENGINE=InnoDB' );
		self::assertTrue( $created, 'Local shared A1 table creation failed: ' . mysqli_error( $a->dbh ) );
		$this->owns_table = true;

		self::assertSame( 1, $a->insert( $this->shared_table, [
			'id' => 1, 'status' => 'planned',
			'completed_at' => null, 'completed_by' => null,
		], [ '%d', '%s', '%s', '%d' ] ) );

		$read = static fn( wpdb $db ): array => (array) $db->get_row(
			'SELECT status, completed_at, completed_by FROM ' . $table . ' WHERE id = 1',
			ARRAY_A
		);
		$first = $read( $a );
		$second = $read( $b );
		self::assertSame( 'planned', $first['status'] );
		self::assertSame( $first, $second );

		// A wins. B's action still carries the same old 'planned' snapshot.
		$finished_at = gmdate( 'Y-m-d H:i:s' );
		$cas = static fn( wpdb $db, string $from, string $to, ?string $at, ?int $actor ): int|false =>
			$db->query( $db->prepare(
				'UPDATE ' . $table . ' SET status = %s, completed_at = %s, completed_by = %d WHERE id = %d AND status = %s',
				$to, $at, $actor ?? 0, 1, $from
			) );
		self::assertSame( 1, $cas( $a, $first['status'], 'completed', $finished_at, 101 ) );
		self::assertSame( 0, $cas( $b, $second['status'], 'blocked', null, null ) );
		$after = $read( $b );
		self::assertSame( 'completed', $after['status'] );
		self::assertSame( $finished_at, $after['completed_at'] );
		self::assertSame( '101', (string) $after['completed_by'] );

		// Only a fresh expected snapshot may reopen the completed record.
		self::assertSame( 1, $cas( $b, $after['status'], 'planned', null, null ) );
		$reopened = $read( $a );
		self::assertSame( 'planned', $reopened['status'] );
		self::assertNull( $reopened['completed_at'] );
		self::assertSame( '0', (string) $reopened['completed_by'] );
	}
}

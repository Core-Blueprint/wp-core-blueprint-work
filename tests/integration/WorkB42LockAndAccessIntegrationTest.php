<?php
declare(strict_types=1);

use CB\Work\Capabilities;
use CB\Work\Content\PostTypes;
use CB\Work\Content\ProjectRestController;
use CB\Work\Content\WorkItemRestController;
use CB\Work\Frontend\Access as FrontendAccess;

/**
 * B4.2 real WordPress permission boundaries and two-session InnoDB serialization.
 * Dedicated disposable Work database only. Never touches production tables.
 */
final class WorkB42LockAndAccessIntegrationTest extends WP_UnitTestCase {
	private ?wpdb $connection_a = null;
	private ?wpdb $connection_b = null;
	private string $table = '';
	private bool $owns_table = false;

	public function set_up(): void {
		if ( ! defined( 'DB_NAME' ) || 'core_blueprint_work_test' !== (string) DB_NAME
			|| ! defined( 'DB_HOST' ) || '127.0.0.1:3307' !== (string) DB_HOST ) {
			self::fail( 'B4.2 integration requires the isolated Work test database.' );
		}
		parent::set_up();
	}

	public function tear_down(): void {
		try {
			if ( $this->owns_table && $this->connection_a instanceof wpdb
				&& 1 === preg_match( '/^cb_wb42_lock_[a-f0-9]{16}$/D', $this->table ) ) {
				$removed = mysqli_query( $this->connection_a->dbh, 'DROP TABLE `' . $this->table . '`' );
				if ( true !== $removed ) {
					throw new RuntimeException( 'B4.2 shared fixture cleanup failed: ' . mysqli_error( $this->connection_a->dbh ) );
				}
				$this->owns_table = false;
			}
		} finally {
			$this->connection_a?->close();
			$this->connection_b?->close();
			$this->connection_a = null;
			$this->connection_b = null;
			$this->table = '';
			parent::tear_down();
		}
	}

	/**
	 * Exercises the exact InnoDB row-lock ordering used by Scheduler's critical
	 * WorkSqlTransaction closure and RecurrenceRules::trash() on independent
	 * MariaDB sessions. The live WordPress post/ledger workflow is separately
	 * protected by the Level 1 source contract and existing recurrence fixtures.
	 */
	public function test_two_connections_serialize_creation_before_trash(): void {
		$this->connection_a = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$this->connection_b = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$a = $this->connection_a;
		$b = $this->connection_b;
		self::assertInstanceOf( mysqli::class, $a->dbh );
		self::assertInstanceOf( mysqli::class, $b->dbh );
		self::assertTrue( mysqli_select_db( $a->dbh, DB_NAME ) );
		self::assertTrue( mysqli_select_db( $b->dbh, DB_NAME ) );
		self::assertTrue( mysqli_autocommit( $a->dbh, true ) );
		self::assertTrue( mysqli_autocommit( $b->dbh, true ) );
		self::assertSame( DB_NAME, (string) $a->get_var( 'SELECT DATABASE()' ) );
		self::assertSame( DB_NAME, (string) $b->get_var( 'SELECT DATABASE()' ) );
		self::assertNotSame( (int) $a->get_var( 'SELECT CONNECTION_ID()' ), (int) $b->get_var( 'SELECT CONNECTION_ID()' ) );
		$this->table = 'cb_wb42_lock_' . bin2hex( random_bytes( 8 ) );
		self::assertMatchesRegularExpression( '/^cb_wb42_lock_[a-f0-9]{16}$/D', $this->table );
		$table = '`' . $this->table . '`';
		// Use the proven A1 two-connection fixture pattern: raw mysqli DDL
		// guarantees a persistent shared table, never a PHPUnit-only shadow.
		$created = mysqli_query( $a->dbh, 'CREATE TABLE ' . $table . ' (
			id bigint unsigned NOT NULL PRIMARY KEY,
			is_active tinyint(1) NOT NULL,
			trashed_at datetime NULL,
			generated_count int unsigned NOT NULL DEFAULT 0
		) ENGINE=InnoDB' );
		self::assertTrue( $created, 'B4.2 shared fixture creation failed: ' . mysqli_error( $a->dbh ) );
		$this->owns_table = true;
		self::assertSame( 1, $a->insert( $this->table, [ 'id' => 1, 'is_active' => 1, 'generated_count' => 0 ], [ '%d', '%d', '%d' ] ) );
		// Fail immediately if B cannot see this persistent fixture; a table
		// visibility issue is NOT proof that locking was effective.
		self::assertSame( 1, (int) $b->get_var( 'SELECT is_active FROM ' . $table . ' WHERE id = 1' ), 'Session B must see the same committed shared InnoDB row before attempting a lock.' );
		self::assertSame( 0, (int) $b->get_var( 'SELECT @@in_transaction' ), 'Session B must not inherit a WordPress PHPUnit transaction.' );
		self::assertNotFalse( $b->query( 'SET SESSION innodb_lock_wait_timeout = 1' ) );

		self::assertNotFalse( $a->query( 'START TRANSACTION' ) );
		try {
			self::assertSame( 1, (int) $a->get_var( 'SELECT id FROM ' . $table . ' WHERE id = 1 FOR UPDATE' ) );
			self::assertSame( 1, (int) $a->get_var( 'SELECT is_active FROM ' . $table . ' WHERE id = 1' ) );
			// Trash uses the same rule-row lock; it cannot commit mid-create.
			$b->suppress_errors( true );
			try {
				self::assertFalse( $b->query( "UPDATE " . $table . " SET is_active = 0, trashed_at = UTC_TIMESTAMP() WHERE id = 1" ) );
				self::assertStringContainsString(
					'Lock wait timeout exceeded',
					(string) $b->last_error,
					'Only a genuine InnoDB row-lock timeout demonstrates serialization; missing tables must fail the test.'
				);
			} finally {
				$b->suppress_errors( false );
			}
			self::assertSame( 1, $a->query( 'UPDATE ' . $table . ' SET generated_count = generated_count + 1 WHERE id = 1' ) );
		} finally {
			self::assertNotFalse( $a->query( 'COMMIT' ) );
		}
		self::assertSame( 1, $b->query( 'UPDATE ' . $table . ' SET is_active = 0, trashed_at = UTC_TIMESTAMP() WHERE id = 1' ) );
		// A new generator after Trash cannot create another child.
		self::assertSame( 0, $a->query( 'UPDATE ' . $table . ' SET generated_count = generated_count + 1 WHERE id = 1 AND is_active = 1 AND trashed_at IS NULL' ) );
		self::assertSame( 1, (int) $b->get_var( 'SELECT generated_count FROM ' . $table . ' WHERE id = 1' ) );
		self::assertSame( 0, (int) $b->get_var( 'SELECT is_active FROM ' . $table . ' WHERE id = 1' ) );
		self::assertSame( 0, (int) $a->get_var( 'SELECT @@in_transaction' ) );
	}

	public function test_private_project_and_work_item_rest_reads_fail_closed(): void {
		if ( ! post_type_exists( PostTypes::WORK_ITEM ) || ! post_type_exists( PostTypes::PROJECT ) ) {
			PostTypes::register();
		}
		$request = new WP_REST_Request( 'GET', '/' );
		foreach ( [ 0, self::factory()->user->create( [ 'role' => 'subscriber' ] ) ] as $actor ) {
			wp_set_current_user( $actor );
			foreach ( [
				new WorkItemRestController( PostTypes::WORK_ITEM ),
				new ProjectRestController( PostTypes::PROJECT ),
			] as $controller ) {
				$result = $controller->get_items_permissions_check( $request );
				self::assertInstanceOf( WP_Error::class, $result );
				self::assertSame( 'rest_forbidden', $result->get_error_code() );
				$result = $controller->get_item_permissions_check( $request );
				self::assertInstanceOf( WP_Error::class, $result );
				self::assertSame( 'rest_forbidden', $result->get_error_code() );
			}
		}
		$tracker_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		( new WP_User( $tracker_id ) )->add_cap( Capabilities::TRACK_TIME );
		wp_set_current_user( $tracker_id );
		self::assertFalse( current_user_can( Capabilities::MANAGE ) );
		self::assertInstanceOf( WP_Error::class, ( new WorkItemRestController( PostTypes::WORK_ITEM ) )->get_items_permissions_check( $request ) );
	}

	public function test_frontend_private_work_item_requires_explicit_opt_in(): void {
		if ( ! post_type_exists( PostTypes::WORK_ITEM ) ) {
			PostTypes::register();
		}
		$work_id = self::factory()->post->create( [
			'post_type' => PostTypes::WORK_ITEM,
			'post_status' => 'publish',
			'post_title' => 'B4.2 private Work Item',
		] );
		wp_set_current_user( 0 );
		self::assertFalse( FrontendAccess::can_read_work_item( $work_id ) );
		$actor = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $actor );
		self::assertFalse( FrontendAccess::can_read_work_item( $work_id ) );
		$allow = static fn( bool $approved, WP_Post $post, string $type, int $user_id ): bool =>
			$user_id > 0 && PostTypes::WORK_ITEM === $type && $post->ID === $work_id;
		add_filter( 'cb_work_frontend_can_read', $allow, 10, 4 );
		try {
			self::assertTrue( FrontendAccess::can_read_work_item( $work_id ) );
			wp_set_current_user( 0 );
			self::assertFalse( FrontendAccess::can_read_work_item( $work_id ) );
		} finally {
			remove_filter( 'cb_work_frontend_can_read', $allow, 10 );
		}
	}

	/** Real admin-post handler guards must reject an invalid nonce before mutation. */
	public function test_manager_with_invalid_nonce_cannot_mutate_work(): void {
		$actor = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		( new WP_User( $actor ) )->add_cap( Capabilities::MANAGE );
		wp_set_current_user( $actor );
		self::assertTrue( current_user_can( Capabilities::MANAGE ) );

		$before_post = $_POST;
		$before_request = $_REQUEST;
		$die = static fn(): callable => static function (): never {
			throw new RuntimeException( 'b42_nonce_rejected' );
		};
		add_filter( 'wp_die_handler', $die, PHP_INT_MAX );

		try {
			$_POST = [
				'_wpnonce' => 'invalid-work-b42-nonce',
				'rule_id' => '987654',
				'work_item_id' => '987654',
				'status' => 'completed',
			];
			$_REQUEST = $_POST;
			foreach ( [
				[ \CB\Work\Admin\RecurrenceActions::class, 'trash' ],
				[ \CB\Work\Admin\OperationalActions::class, 'transition_work_item' ],
				[ \CB\Work\Admin\TaxRateActions::class, 'add' ],
			] as $handler ) {
				try {
					call_user_func( $handler );
					self::fail( 'Admin mutation continued past invalid nonce: ' . $handler[1] );
				} catch ( RuntimeException $exception ) {
					self::assertSame( 'b42_nonce_rejected', $exception->getMessage() );
				}
			}
		} finally {
			remove_filter( 'wp_die_handler', $die, PHP_INT_MAX );
			$_POST = $before_post;
			$_REQUEST = $before_request;
		}
	}

	/** Guest and subscriber should fail capability checks before action effects. */
	public function test_unprivileged_admin_post_mutations_fail_closed(): void {
		$before_post = $_POST;
		$before_request = $_REQUEST;
		$die = static fn(): callable => static function (): never {
			throw new RuntimeException( 'b42_unauthorized' );
		};
		add_filter( 'wp_die_handler', $die, PHP_INT_MAX );
		try {
			$_POST = [ '_wpnonce' => 'invalid', 'rule_id' => '12345', 'work_item_id' => '12345' ];
			$_REQUEST = $_POST;
			foreach ( [ 0, self::factory()->user->create( [ 'role' => 'subscriber' ] ) ] as $actor ) {
				wp_set_current_user( $actor );
				self::assertFalse( current_user_can( Capabilities::MANAGE ) );
				foreach ( [
					[ \CB\Work\Admin\RecurrenceActions::class, 'trash' ],
					[ \CB\Work\Admin\OperationalActions::class, 'transition_work_item' ],
				] as $handler ) {
					try {
						call_user_func( $handler );
						self::fail( 'Unauthorized mutation proceeded: ' . $handler[1] );
					} catch ( RuntimeException $exception ) {
						self::assertSame( 'b42_unauthorized', $exception->getMessage() );
					}
				}
			}
		} finally {
			remove_filter( 'wp_die_handler', $die, PHP_INT_MAX );
			$_POST = $before_post;
			$_REQUEST = $before_request;
		}
	}
}

<?php
declare(strict_types=1);

use CB\Work\Database\Schema;
use CB\Work\Governance\DeferredEvents;
use CB\Work\Governance\WorkEventOutbox;
use CoreBlueprint\Core\DB;
use CoreBlueprint\Core\Database\SchemaRegistry;

/**
 * B3.1 Golden: real Base schema upgrade and independent-connection visibility.
 *
 * This fixture is intentionally NOT WP_UnitTestCase. It uses the real
 * InnoDB outbox (never a connection-local TEMPORARY TABLE), in the uniquely
 * named disposable Work integration database created by check-integration.
 */
final class ProjectBundleGoldenAcceptanceIntegrationTest extends \PHPUnit\Framework\TestCase {
	private int $previous_autocommit = 1;
	private bool $autocommit_changed = false;
	private mixed $previous_schema = null;
	private string $sentinel_code = '';
	/** @var string[] */
	private array $created_event_keys = [];
	private int $published_hooks = 0;
	/** @var string[] */
	private array $published_keys = [];

	protected function setUp(): void {
		parent::setUp();
		if ( ! defined( 'DB_NAME' ) || DB_NAME !== 'core_blueprint_work_test'
			|| ! defined( 'DB_HOST' ) || DB_HOST !== '127.0.0.1:3307' ) {
			self::fail( 'Golden database tests may only use the disposable Work database.' );
		}

		global $wpdb;
		self::assertSame( 'cbworktests_', $wpdb->prefix, 'Golden tests require the isolated WP test prefix.' );
		self::assertSame( 0, (int) $wpdb->get_var( 'SELECT @@in_transaction' ), 'Never alter a PHPUnit-owned transaction.' );
		$this->previous_schema = get_option( Schema::OPTION, null );

		$autocommit = $wpdb->get_var( 'SELECT @@autocommit' );
		self::assertNotNull( $autocommit );
		$this->previous_autocommit = (int) $autocommit;
		self::assertContains( $this->previous_autocommit, [ 0, 1 ] );
		if ( 0 === $this->previous_autocommit ) {
			self::assertNotFalse( $wpdb->query( 'SET SESSION autocommit = 1' ) );
			$this->autocommit_changed = true;
		}
		self::assertSame( 0, (int) $wpdb->get_var( 'SELECT @@in_transaction' ) );

		$registered = SchemaRegistry::definitions();
		self::assertArrayHasKey( 'core-blueprint-work', $registered, 'Base must own the production schema contract.' );
		self::assertSame( CB_WORK_SCHEMA_VERSION, (string) $registered['core-blueprint-work']['version'] );
		self::assertTrue( DB::reconcile_registered_schema( 'core-blueprint-work', true ), 'Seed the real current Work tables through Base.' );
		self::assertSame( CB_WORK_SCHEMA_VERSION, (string) get_option( Schema::OPTION ) );
	}

	protected function tearDown(): void {
		global $wpdb;
		if ( 0 !== (int) $wpdb->get_var( 'SELECT @@in_transaction' ) ) {
			$wpdb->query( 'ROLLBACK' );
		}
		$table = Schema::outbox_table();
		foreach ( $this->created_event_keys as $key ) {
			$wpdb->delete( $table, [ 'event_key' => $key ], [ '%s' ] );
		}
		if ( '' !== $this->sentinel_code ) {
			$wpdb->delete( Schema::work_types_table(), [ 'code' => $this->sentinel_code ], [ '%s' ] );
		}
		remove_action( 'cb_work_project_created', [ $this, 'record_project_event' ], 10 );
		// If a migration assertion failed after the disposable outbox was
		// dropped, restore the table using the *real* Base migration path.
		if ( CB_WORK_SCHEMA_VERSION !== (string) get_option( Schema::OPTION ) ) {
			update_option( Schema::OPTION, '2.0' );
		}
		DB::reconcile_registered_schema( 'core-blueprint-work', true );
		if ( null === $this->previous_schema ) {
			delete_option( Schema::OPTION );
		} else {
			update_option( Schema::OPTION, $this->previous_schema );
		}
		if ( $this->autocommit_changed ) {
			self::assertSame( 0, (int) $wpdb->get_var( 'SELECT @@in_transaction' ) );
			self::assertNotFalse( $wpdb->query( 'SET SESSION autocommit = 0' ) );
			$this->autocommit_changed = false;
		}
		parent::tearDown();
	}

	public function record_project_event( int $project_id, array $project, string $event_key ): void {
		unset( $project_id, $project );
		$this->published_hooks++;
		$this->published_keys[] = $event_key;
	}

	public function test_real_schema_20_to_22_upgrade_preserves_work_data_and_installs_indexed_innodb_outbox(): void {
		global $wpdb;
		$table = Schema::outbox_table();
		self::assertSame( 0, (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) ),
			'Never drop a populated outbox, even in the test database.' );

		$this->sentinel_code = 'b31g_' . substr( str_replace( '-', '', wp_generate_uuid4() ), 0, 12 );
		$inserted = $wpdb->insert( Schema::work_types_table(), [
			'code' => $this->sentinel_code,
			'label' => 'B3.1 existing Work Type',
			'is_active' => 1,
			'sort_order' => 999,
			'created_at' => current_time( 'mysql', true ),
			'updated_at' => current_time( 'mysql', true ),
		], [ '%s', '%s', '%d', '%d', '%s', '%s' ] );
		self::assertSame( 1, $inserted, 'The pre-existing Work Type must actually persist.' );

		// Simulate the real released 2.0 state: all legacy Work tables
		// remain, but the event outbox introduced by 2.1 is not present.
		self::assertNotFalse( $wpdb->query( $wpdb->prepare( 'DROP TABLE %i', $table ) ) );
		update_option( Schema::OPTION, '2.0' );
		self::assertSame( '2.0', (string) get_option( Schema::OPTION ) );
		self::assertNull( $wpdb->get_var( $wpdb->prepare(
			'SHOW TABLES LIKE %s', $wpdb->esc_like( $table )
		) ) );

		self::assertTrue( DB::reconcile_registered_schema( 'core-blueprint-work', false ),
			'Production Base SchemaRegistry must complete the 2.0 to 2.2 upgrade.' );
		self::assertSame( CB_WORK_SCHEMA_VERSION, (string) get_option( Schema::OPTION ) );
		self::assertSame( $table, $wpdb->get_var( $wpdb->prepare(
			'SHOW TABLES LIKE %s', $wpdb->esc_like( $table )
		) ) );

		$ddl = $wpdb->get_row( $wpdb->prepare( 'SHOW CREATE TABLE %i', $table ), ARRAY_N );
		self::assertIsArray( $ddl );
		self::assertMatchesRegularExpression( '/\bENGINE\s*=\s*InnoDB\b/i', (string) $ddl[1] );
		$columns = $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $table ) );
		foreach ( [ 'event_key', 'event_kind', 'event_name', 'payload', 'state', 'attempts', 'last_error_code', 'claimed_at', 'delivered_at' ] as $column ) {
			self::assertContains( $column, $columns );
		}
		$indexes = $wpdb->get_results( $wpdb->prepare( 'SHOW INDEX FROM %i', $table ), ARRAY_A );
		self::assertIsArray( $indexes );
		$names = array_column( $indexes, 'Key_name' );
		foreach ( [ 'PRIMARY', 'event_key', 'state_claimed', 'state_delivered' ] as $name ) {
			self::assertContains( $name, $names );
		}
		$event_keys = array_values( array_filter( $indexes, static fn( array $index ): bool =>
			( $index['Key_name'] ?? '' ) === 'event_key'
		) );
		self::assertCount( 1, $event_keys );
		self::assertSame( '0', (string) $event_keys[0]['Non_unique'], 'Each outbox event must have an immutable unique key.' );

		// Schema 2.2 must preserve the B3.1 outbox and add recoverable
		// Recurring Work lifecycle columns without dropping legacy rows.
		$recurrence = Schema::recurrence_rules_table();
		$recurrence_columns = $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $recurrence ) );
		foreach ( [ 'trashed_at', 'trashed_by' ] as $column ) {
			self::assertContains( $column, $recurrence_columns, 'Recurrence lifecycle migration must add its recoverable trash state.' );
		}
		$recurrence_indexes = $wpdb->get_results( $wpdb->prepare( 'SHOW INDEX FROM %i', $recurrence ), ARRAY_A );
		self::assertIsArray( $recurrence_indexes );
		self::assertContains( 'trash_active_next', array_column( $recurrence_indexes, 'Key_name' ), 'The bounded recurring generation query must have its lifecycle index.' );


		$preserved = $wpdb->get_var( $wpdb->prepare(
			'SELECT label FROM %i WHERE code = %s', Schema::work_types_table(), $this->sentinel_code
		) );
		self::assertSame( 'B3.1 existing Work Type', $preserved, 'Migration must not lose existing Work data.' );
		self::assertTrue( DB::reconcile_registered_schema( 'core-blueprint-work', true ),
			'Repeated reconciliation must be idempotent.' );
		self::assertSame( 'B3.1 existing Work Type', $wpdb->get_var( $wpdb->prepare(
			'SELECT label FROM %i WHERE code = %s', Schema::work_types_table(), $this->sentinel_code
		) ) );

		// An existing 2.1 installation also advances through Base's
		// official schema registry to 2.2 without disturbing Work data.
		update_option( Schema::OPTION, '2.1' );
		self::assertTrue( DB::reconcile_registered_schema( 'core-blueprint-work', false ) );
		self::assertSame( CB_WORK_SCHEMA_VERSION, (string) get_option( Schema::OPTION ) );
		self::assertSame( 'B3.1 existing Work Type', $wpdb->get_var( $wpdb->prepare(
			'SELECT label FROM %i WHERE code = %s', Schema::work_types_table(), $this->sentinel_code
		) ) );
	}

	public function test_second_connection_sees_only_committed_events_and_drains_once_with_stable_key(): void {
		global $wpdb;
		$writer = $wpdb;
		$reader = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		self::assertNotFalse( $reader->dbh, 'A genuinely separate WordPress DB connection is required.' );
		$reader->set_prefix( $writer->prefix );
		self::assertSame( 1, (int) $reader->get_var( 'SELECT @@autocommit' ) );
		self::assertSame( 0, (int) $reader->get_var( 'SELECT @@in_transaction' ) );
		self::assertSame( 'core_blueprint_work_test', $reader->get_var( 'SELECT DATABASE()' ) );
		self::assertNotSame( (string) $writer->get_var( 'SELECT CONNECTION_ID()' ), (string) $reader->get_var( 'SELECT CONNECTION_ID()' ) );
		$table = Schema::outbox_table();
		add_action( 'cb_work_project_created', [ $this, 'record_project_event' ], 10, 3 );

		try {
			// A released savepoint is *not* an external commit. The reader
			// must not see success events while the owner still may roll back.
			self::assertNotFalse( $writer->query( 'START TRANSACTION' ) );
			self::assertNotFalse( $writer->query( 'SAVEPOINT cb_work_b31_accept' ) );
			$rolled_back_key = $this->queue_project_lifecycle( 81001 );
			self::assertSame( 1, $this->count_by_key( $writer, $rolled_back_key ) );
			self::assertSame( 0, $this->count_by_key( $reader, $rolled_back_key ) );
			self::assertNotFalse( $writer->query( 'RELEASE SAVEPOINT cb_work_b31_accept' ) );
			self::assertSame( 0, $this->count_by_key( $reader, $rolled_back_key ) );
			self::assertSame( 0, WorkEventOutbox::drain(), 'Owner may never drain while rollback is possible.' );
			self::assertNotFalse( $writer->query( 'ROLLBACK' ) );
			self::assertSame( 0, $this->count_by_key( $reader, $rolled_back_key ) );
			self::assertSame( 0, $this->published_hooks );

			self::assertNotFalse( $writer->query( 'START TRANSACTION' ) );
			$committed_key = $this->queue_project_lifecycle( 81002 );
			$this->created_event_keys[] = $committed_key;
			self::assertSame( 0, $this->count_by_key( $reader, $committed_key ) );
			self::assertNotFalse( $writer->query( 'COMMIT' ) );
			self::assertSame( 1, $this->count_by_key( $reader, $committed_key ) );

			// Dispatch on the *second* connection, not the writer that
			// inserted the event. Global DB replacement is fully scoped.
			$wpdb = $reader;
			try {
				self::assertSame( 1, WorkEventOutbox::drain(), 'Independent post-commit worker must publish once.' );
				self::assertSame( 0, WorkEventOutbox::drain(), 'Normal replay must not publish twice.' );
			} finally {
				$wpdb = $writer;
			}
			self::assertSame( 1, $this->published_hooks );
			self::assertSame( [ $committed_key ], $this->published_keys );
			self::assertSame( 'delivered', $writer->get_var( $writer->prepare(
				'SELECT state FROM %i WHERE event_key = %s', $table, $committed_key
			) ) );
		} finally {
			$wpdb = $writer;
			if ( 0 !== (int) $writer->get_var( 'SELECT @@in_transaction' ) ) {
				$writer->query( 'ROLLBACK' );
			}
			$reader->close();
		}
	}

	private function queue_project_lifecycle( int $project_id ): string {
		global $wpdb;
		DeferredEvents::begin();
		try {
			DeferredEvents::lifecycle( 'cb_work_project_created', $project_id, [ 'id' => $project_id ] );
			self::assertTrue( DeferredEvents::finish() );
		} catch ( \Throwable $e ) {
			DeferredEvents::cancel();
			throw $e;
		}
		$table = Schema::outbox_table();
		$key = $wpdb->get_var( $wpdb->prepare(
			'SELECT event_key FROM %i ORDER BY id DESC LIMIT 1', $table
		) );
		self::assertIsString( $key );
		self::assertNotEmpty( $key );
		return $key;
	}

	private function count_by_key( wpdb $connection, string $event_key ): int {
		$table = Schema::outbox_table();
		return (int) $connection->get_var( $connection->prepare(
			'SELECT COUNT(*) FROM %i WHERE event_key = %s', $table, $event_key
		) );
	}
}

<?php
declare(strict_types=1);

use CB\Work\Database\Schema;
use CB\Work\Governance\DeferredEvents;
use CB\Work\Governance\Events;
use CB\Work\Governance\WorkEventOutbox;

/**
 * B3.1 separate-connection-ownership delivery tests without WP_UnitTestCase's
 * implicit outer transaction. Only the disposable Work database is allowed.
 */
final class ProjectBundleOutboxDeliveryIntegrationTest extends \PHPUnit\Framework\TestCase {
	private mixed $previous_schema = null;
	private int $previous_autocommit = 1;
	private bool $autocommit_changed = false;
	private string $table = '';
	private int $work_hooks = 0;
	private int $work_audits = 0;
	/** @var string[] */
	private array $delivered_keys = [];

	protected function setUp(): void {
		parent::setUp();
		if ( ! defined( 'DB_NAME' ) || 'core_blueprint_work_test' !== (string) DB_NAME
			|| ! defined( 'DB_HOST' ) || '127.0.0.1:3307' !== (string) DB_HOST ) {
			self::fail( 'Outbox delivery tests require the disposable local Work database.' );
		}
		global $wpdb;
		self::assertSame( 0, (int) $wpdb->get_var( 'SELECT @@in_transaction' ), 'This delivery fixture may not use the PHPUnit outer transaction.' );

		// The WordPress PHPUnit WP_UnitTestCase harness sets autocommit=0 and
		// does not reset it during tear_down(). Under that mode even a SELECT
		// after COMMIT can begin a new implicit transaction, correctly causing
		// the production worker to refuse dispatch. This standalone delivery
		// fixture must restore normal WordPress autocommit=1 on its isolated
		// connection, then restore the incoming mode on tearDown().
		$autocommit = $wpdb->get_var( 'SELECT @@autocommit' );
		self::assertContains( (int) $autocommit, [ 0, 1 ], 'Readable MariaDB autocommit state is required.' );
		$this->previous_autocommit = (int) $autocommit;
		if ( 0 === $this->previous_autocommit ) {
			self::assertNotFalse( $wpdb->query( 'SET SESSION autocommit = 1' ) );
			$this->autocommit_changed = true;
		}
		self::assertSame( 1, (int) $wpdb->get_var( 'SELECT @@autocommit' ), 'Event dispatch fixture must run in ordinary WordPress autocommit mode.' );
		self::assertSame( 0, (int) $wpdb->get_var( 'SELECT @@in_transaction' ), 'Delivery fixture must not inherit an outer transaction.' );
		$this->table = Schema::outbox_table();
		$created = $wpdb->query( 'CREATE TEMPORARY TABLE ' . $this->table . ' (
			id bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
			event_key char(36) NOT NULL,
			event_kind varchar(16) NOT NULL,
			event_name varchar(128) NOT NULL,
			payload longtext NOT NULL,
			state varchar(16) NOT NULL DEFAULT "pending",
			attempts smallint unsigned NOT NULL DEFAULT 0,
			last_error_code varchar(80) NOT NULL DEFAULT "",
			claimed_at datetime NULL,
			delivered_at datetime NULL,
			created_at datetime NOT NULL,
			UNIQUE KEY event_key (event_key)
		) ENGINE=InnoDB' );
		self::assertNotFalse( $created );
		$this->previous_schema = get_option( Schema::OPTION, null );
		update_option( Schema::OPTION, CB_WORK_SCHEMA_VERSION );
		Events::register();
		add_action( 'cb_work_project_created', [ $this, 'record_work_hook' ], 10, 3 );
		add_action( 'core_blueprint_audit_log_written', [ $this, 'record_work_audit' ], 10, 4 );
	}

	protected function tearDown(): void {
		remove_action( 'cb_work_project_created', [ $this, 'record_work_hook' ], 10 );
		remove_action( 'core_blueprint_audit_log_written', [ $this, 'record_work_audit' ], 10 );
		global $wpdb;
		if ( (int) $wpdb->get_var( 'SELECT @@in_transaction' ) !== 0 ) {
			$wpdb->query( 'ROLLBACK' );
		}
		if ( '' !== $this->table ) {
			$wpdb->query( 'DROP TEMPORARY TABLE IF EXISTS ' . $this->table );
		}
		if ( null === $this->previous_schema ) {
			delete_option( Schema::OPTION );
		} else {
			update_option( Schema::OPTION, $this->previous_schema );
		}
		// Never leave the global WordPress test DB connection in a different
		// transaction policy for the next PHPUnit case.
		if ( $this->autocommit_changed ) {
			self::assertSame( 0, (int) $wpdb->get_var( 'SELECT @@in_transaction' ), 'Fixture cleanup must leave no pending transaction.' );
			self::assertNotFalse( $wpdb->query( 'SET SESSION autocommit = 0' ) );
			self::assertSame( 0, (int) $wpdb->get_var( 'SELECT @@autocommit' ) );
			$this->autocommit_changed = false;
		}
		parent::tearDown();
	}

	public function record_work_hook( int $project_id, array $project, string $event_key = '' ): void {
		unset( $project_id, $project );
		$this->work_hooks++;
		$this->delivered_keys[] = $event_key;
	}

	public function record_work_audit( int $row_id, string $event_type ): void {
		unset( $row_id );
		if ( 'work_project_created' === $event_type ) {
			$this->work_audits++;
		}
	}

	public function test_outer_rollback_erases_events_and_commit_allows_single_delivery(): void {
		global $wpdb;
		self::assertNotFalse( $wpdb->query( 'START TRANSACTION' ) );
		$this->queue_project_events( 7001 );
		self::assertSame( 0, WorkEventOutbox::drain(), 'A savepoint release is not an external commit.' );
		self::assertNotFalse( $wpdb->query( 'ROLLBACK' ) );
		self::assertSame( 0, $this->outbox_rows() );
		self::assertSame( 0, $this->work_hooks );
		self::assertSame( 0, $this->work_audits );

		self::assertNotFalse( $wpdb->query( 'START TRANSACTION' ) );
		$this->queue_project_events( 7002 );
		self::assertNotFalse( $wpdb->query( 'COMMIT' ) );
		self::assertSame( 2, $this->outbox_rows() );
		self::assertSame( 0, (int) $wpdb->get_var( 'SELECT @@in_transaction' ), 'Outer COMMIT must close its transaction.' );
		self::assertSame( CB_WORK_SCHEMA_VERSION, (string) get_option( Schema::OPTION ), 'Delivery requires a schema-ready Work environment.' );
		$delivered = WorkEventOutbox::drain();
		self::assertSame( 2, $delivered, $this->delivery_diagnostics() );
		self::assertSame( 1, $this->work_hooks );
		self::assertSame( 1, $this->work_audits );
		self::assertCount( 1, $this->delivered_keys );
		self::assertNotEmpty( $this->delivered_keys[0] );
		self::assertSame( 0, WorkEventOutbox::drain(), 'Delivered events must not be reprocessed in a normal replay.' );
		self::assertSame( 1, $this->work_hooks );
	}

	public function test_throwing_subscriber_retries_with_stable_event_key(): void {
		global $wpdb;
		self::assertNotFalse( $wpdb->query( 'START TRANSACTION' ) );
		DeferredEvents::begin();
		DeferredEvents::lifecycle( 'cb_work_project_created', 7003, [ 'id' => 7003 ] );
		self::assertTrue( DeferredEvents::finish() );
		self::assertNotFalse( $wpdb->query( 'COMMIT' ) );
		$reject = static function (): void { throw new \RuntimeException( 'Simulated failing subscriber.' ); };
		add_action( 'cb_work_project_created', $reject, 1, 0 );
		try {
			self::assertSame( 0, WorkEventOutbox::drain(), 'Throwing subscribers must not mark the event delivered.' );
		} finally {
			remove_action( 'cb_work_project_created', $reject, 1 );
		}
		self::assertSame( 0, $this->work_hooks );
		self::assertSame( 1, (int) $wpdb->get_var( 'SELECT attempts FROM ' . $this->table . ' LIMIT 1' ) );
		$delivered = WorkEventOutbox::drain();
		self::assertSame( 1, $delivered, $this->delivery_diagnostics() );
		self::assertSame( 1, $this->work_hooks );
		self::assertCount( 1, $this->delivered_keys );
		self::assertSame( 0, WorkEventOutbox::drain() );
	}

	public function test_exhausted_failed_events_remain_inspectable_and_are_not_republished(): void {
		global $wpdb;
		$event_key = wp_generate_uuid4();
		self::assertSame( 1, $wpdb->insert( $this->table, [
			'event_key' => $event_key,
			'event_kind' => 'lifecycle',
			'event_name' => 'cb_work_project_created',
			'payload' => '{invalid-json',
			'state' => 'pending',
			'attempts' => 11,
			'created_at' => current_time( 'mysql', true ),
		], [ '%s', '%s', '%s', '%s', '%s', '%d', '%s' ] ) );

		self::assertSame( 0, WorkEventOutbox::drain(), 'Malformed event must never be published.' );
		$record = $wpdb->get_row( $wpdb->prepare(
			'SELECT state, attempts, last_error_code FROM %i WHERE event_key = %s',
			$this->table, $event_key
		), ARRAY_A );
		self::assertIsArray( $record );
		self::assertSame( 'pending', $record['state'], 'Retain terminal failures for operator inspection.' );
		self::assertSame( 12, (int) $record['attempts'] );
		self::assertSame( 'decode_jsonexception', $record['last_error_code'],
			'Failure diagnostic must not contain event payload or exception message.' );

		self::assertSame( 0, WorkEventOutbox::drain(), 'Attempt cap must stop automatic replays.' );
		self::assertSame( 12, (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT attempts FROM %i WHERE event_key = %s', $this->table, $event_key
		) ) );
		self::assertSame( 0, $this->work_hooks );
	}

	public function test_processing_claim_recovers_only_after_lease_expires(): void {
		global $wpdb;
		$key = wp_generate_uuid4();
		$payload = wp_json_encode( [ 'args' => [ 7005, [ 'id' => 7005 ] ] ] );
		self::assertIsString( $payload );
		self::assertSame( 1, $wpdb->insert( $this->table, [
			'event_key' => $key,
			'event_kind' => 'lifecycle',
			'event_name' => 'cb_work_project_created',
			'payload' => $payload,
			'state' => 'processing',
			'attempts' => 1,
			'claimed_at' => current_time( 'mysql', true ),
			'created_at' => current_time( 'mysql', true ),
		], [ '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' ] ) );

		self::assertSame( 0, WorkEventOutbox::drain(), 'Fresh worker lease must never be stolen.' );
		self::assertSame( 0, $this->work_hooks );

		$expired = gmdate( 'Y-m-d H:i:s', time() - 900 );
		self::assertSame( 1, $wpdb->update( $this->table, [
			'claimed_at' => $expired,
		], [ 'event_key' => $key ], [ '%s' ], [ '%s' ] ) );
		self::assertSame( 1, WorkEventOutbox::drain(), $this->delivery_diagnostics() );
		self::assertSame( 1, $this->work_hooks );
		self::assertSame( [ $key ], $this->delivered_keys );
		self::assertSame( 2, (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT attempts FROM %i WHERE event_key = %s', $this->table, $key
		) ) );
		self::assertSame( 0, WorkEventOutbox::drain(), 'Acknowledged lease replay must be idempotent in normal execution.' );
	}

	private function queue_project_events( int $id ): void {
		DeferredEvents::begin();
		DeferredEvents::audit( Events::PROJECT_CREATED, 'notice', [
			'project_id' => $id, 'actor_user_id' => 0, 'channel' => 'b31_test',
		] );
		DeferredEvents::lifecycle( 'cb_work_project_created', $id, [ 'id' => $id ] );
		self::assertTrue( DeferredEvents::finish() );
	}

	private function delivery_diagnostics(): string {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT event_name, state, attempts, last_error_code FROM ' . $this->table . ' ORDER BY id', ARRAY_A );
		return 'Work outbox delivery: ' . ( wp_json_encode( $rows ) ?: 'unavailable' ) . '; db_error=' . (string) $wpdb->last_error;
	}

	private function outbox_rows(): int {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $this->table );
	}
}

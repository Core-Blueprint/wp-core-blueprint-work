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
		$this->table = Schema::outbox_table();
		$created = $wpdb->query( 'CREATE TEMPORARY TABLE ' . $this->table . ' (
			id bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
			event_key char(36) NOT NULL,
			event_kind varchar(16) NOT NULL,
			event_name varchar(128) NOT NULL,
			payload longtext NOT NULL,
			state varchar(16) NOT NULL DEFAULT "pending",
			attempts smallint unsigned NOT NULL DEFAULT 0,
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
		self::assertSame( 1, $wpdb->query( 'START TRANSACTION' ) === false ? 0 : 1 );
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
		self::assertSame( 2, WorkEventOutbox::drain() );
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
		self::assertSame( 1, WorkEventOutbox::drain() );
		self::assertSame( 1, $this->work_hooks );
		self::assertCount( 1, $this->delivered_keys );
		self::assertSame( 0, WorkEventOutbox::drain() );
	}

	private function queue_project_events( int $id ): void {
		DeferredEvents::begin();
		DeferredEvents::audit( Events::PROJECT_CREATED, 'notice', [
			'project_id' => $id, 'actor_user_id' => 0, 'channel' => 'b31_test',
		] );
		DeferredEvents::lifecycle( 'cb_work_project_created', $id, [ 'id' => $id ] );
		self::assertTrue( DeferredEvents::finish() );
	}

	private function outbox_rows(): int {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $this->table );
	}
}

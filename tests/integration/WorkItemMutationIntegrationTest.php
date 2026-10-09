<?php
declare(strict_types=1);

use CB\Work\Content\PostTypes;
use CB\Work\Content\WorkItemMeta;
use CB\Work\Database\Schema;
use CB\Work\Domain\WorkItemStatus;
use CB\Work\Repository\WorkItems;

/**
 * Optional real-WordPress / MariaDB A1 integration. The WordPress PHPUnit
 * harness already owns a transaction: Work must use SAVEPOINTs, not commit it.
 *
 * Never execute against a production database. TEMPORARY InnoDB child tables
 * are connection-local; WordPress test posts/meta are rolled back by PHPUnit.
 */
final class WorkItemMutationIntegrationTest extends WP_UnitTestCase {
	/** @var string[] */
	private array $temporary_tables = [];

	public function set_up(): void {
		if ( ! defined( 'DB_NAME' )
			|| ! preg_match( '/(?:^|[_-])test(?:$|[_-])|testing|testdb/i', (string) DB_NAME )
			|| ! defined( 'DB_HOST' )
			|| ! preg_match( '/^(?:localhost|127\\.0\\.0\\.1|\\[?::1\\]?)(?::[0-9]+)?$/i', (string) DB_HOST )
		) {
			self::fail( 'Work A1 requires the local WordPress PHPUnit test database.' );
		}

		parent::set_up();

		global $wpdb;
		register_post_type( PostTypes::WORK_ITEM, [ 'public' => false ] );
		WorkItemMeta::register_meta();
		update_option( Schema::OPTION, CB_WORK_SCHEMA_VERSION );

		$assignments = Schema::assignments_table();
		$created = $wpdb->query( 'CREATE TEMPORARY TABLE ' . $assignments . ' (
			work_item_id bigint unsigned NOT NULL,
			user_id bigint unsigned NOT NULL,
			assigned_at datetime NOT NULL,
			PRIMARY KEY (work_item_id, user_id),
			CONSTRAINT cb_work_a1_assignment_fail CHECK (assigned_at < \'2000-01-01 00:00:00\')
		) ENGINE=InnoDB' );
		self::assertNotFalse( $created );
		$this->temporary_tables[] = $assignments;

		$relations = Schema::relations_table();
		$created = $wpdb->query( 'CREATE TEMPORARY TABLE ' . $relations . ' (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			work_item_id bigint unsigned NOT NULL,
			provider varchar(64) NOT NULL,
			relation_type varchar(64) NOT NULL,
			external_id varchar(191) NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id)
		) ENGINE=InnoDB' );
		self::assertNotFalse( $created );
		$this->temporary_tables[] = $relations;
	}

	public function tear_down(): void {
		global $wpdb;
		foreach ( array_reverse( $this->temporary_tables ) as $table ) {
			$wpdb->query( 'DROP TEMPORARY TABLE IF EXISTS ' . $table );
		}
		$this->temporary_tables = [];
		if ( post_type_exists( PostTypes::WORK_ITEM ) ) {
			unregister_post_type( PostTypes::WORK_ITEM );
		}
		parent::tear_down();
	}

	public function test_stale_status_is_rejected_and_completion_metadata_is_coherent(): void {
		$user_id = self::factory()->user->create();
		$item_id = $this->seed_work_item();
		self::assertSame( 'planned', get_post_meta( $item_id, WorkItemMeta::STATUS, true ) );

		global $wpdb;
		self::assertSame( 1, (int) $wpdb->get_var( 'SELECT @@in_transaction' ), 'WordPress PHPUnit must own the surrounding transaction.' );

		self::assertTrue( WorkItems::transition_status( $item_id, WorkItemStatus::COMPLETED, $user_id, WorkItemStatus::PLANNED ) );
		self::assertSame( WorkItemStatus::COMPLETED, get_post_meta( $item_id, WorkItemMeta::STATUS, true ) );
		self::assertNotEmpty( get_post_meta( $item_id, WorkItemMeta::COMPLETED_AT, true ) );
		self::assertSame( $user_id, (int) get_post_meta( $item_id, WorkItemMeta::COMPLETED_BY, true ) );

		self::assertFalse( WorkItems::transition_status( $item_id, WorkItemStatus::BLOCKED, $user_id, WorkItemStatus::PLANNED ) );
		self::assertSame( WorkItemStatus::COMPLETED, get_post_meta( $item_id, WorkItemMeta::STATUS, true ) );

		self::assertTrue( WorkItems::transition_status( $item_id, WorkItemStatus::PLANNED, $user_id, WorkItemStatus::COMPLETED ) );
		self::assertSame( WorkItemStatus::PLANNED, get_post_meta( $item_id, WorkItemMeta::STATUS, true ) );
		self::assertSame( '', (string) get_post_meta( $item_id, WorkItemMeta::COMPLETED_AT, true ) );
		self::assertSame( '', (string) get_post_meta( $item_id, WorkItemMeta::COMPLETED_BY, true ) );
		self::assertSame( 1, (int) $wpdb->get_var( 'SELECT @@in_transaction' ), 'Work must never commit PHPUnit-owned transaction.' );
	}

	public function test_failed_assignment_insert_rolls_back_post_and_meta_writes(): void {
		$user_id = self::factory()->user->create();
		$item_id = $this->seed_work_item();
		$before = get_post( $item_id );
		self::assertInstanceOf( WP_Post::class, $before );

		global $wpdb;
		$wpdb->suppress_errors( true );
		try {
			$success = WorkItems::update( $item_id, [
				'title'             => 'Should roll back',
				'work_context'      => 'internal',
				'priority'          => 'urgent',
				'assigned_user_ids' => [ $user_id ],
			] );
		} finally {
			$wpdb->suppress_errors( false );
		}

		self::assertFalse( $success, 'CHECK constraint must reject the assignment and abort all Work-owned changes.' );
		self::assertSame( $before->post_title, get_post( $item_id )->post_title );
		self::assertSame( 'normal', get_post_meta( $item_id, WorkItemMeta::PRIORITY, true ) );
		self::assertSame( [], WorkItems::assignments( $item_id ) );
	}

	private function seed_work_item(): int {
		$id = self::factory()->post->create( [
			'post_type'   => PostTypes::WORK_ITEM,
			'post_status' => 'draft',
			'post_title'  => 'Original A1 Work Item',
		] );
		self::assertGreaterThan( 0, $id );
		update_post_meta( $id, WorkItemMeta::WORK_CONTEXT, 'internal' );
		update_post_meta( $id, WorkItemMeta::PRIORITY, 'normal' );
		WorkItemMeta::ensure_status( $id );
		return $id;
	}
}

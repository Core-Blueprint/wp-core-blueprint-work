<?php
declare(strict_types=1);

use CB\Work\Content\PostTypes;
use CB\Work\Content\WorkItemMeta;
use CB\Work\Database\Schema;
use CB\Work\Domain\WorkItemStatus;
use CB\Work\Frontend\Actions\WorkItems as FrontendWorkItems;
use CB\Work\Repository\WorkItems as WorkItemRepository;

/** B3.2 client snapshot CAS and authorization at the D3 PHP boundary. */
final class WorkFrontendStatusCasIntegrationTest extends WP_UnitTestCase {
	private array $temporary_tables = [];

	public function set_up(): void {
		if ( ! defined( 'DB_NAME' ) || 'core_blueprint_work_test' !== (string) DB_NAME
			|| ! defined( 'DB_HOST' ) || '127.0.0.1:3307' !== (string) DB_HOST ) {
			self::fail( 'B3.2 frontend integration requires the disposable Work test database.' );
		}
		parent::set_up();
		global $wpdb;
		register_post_type( PostTypes::WORK_ITEM, [ 'public' => false ] );
		WorkItemMeta::register_meta();
		update_option( Schema::OPTION, CB_WORK_SCHEMA_VERSION );
		$this->temporary_tables = [ Schema::assignments_table(), Schema::relations_table() ];
		self::assertNotFalse( $wpdb->query( 'CREATE TEMPORARY TABLE ' . $this->temporary_tables[0] . ' (
			work_item_id bigint unsigned NOT NULL,
			user_id bigint unsigned NOT NULL,
			assigned_at datetime NOT NULL,
			PRIMARY KEY (work_item_id,user_id)
		) ENGINE=InnoDB' ) );
		self::assertNotFalse( $wpdb->query( 'CREATE TEMPORARY TABLE ' . $this->temporary_tables[1] . ' (
			id bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
			work_item_id bigint unsigned NOT NULL,
			provider varchar(64) NOT NULL,
			relation_type varchar(64) NOT NULL,
			external_id varchar(191) NOT NULL,
			created_at datetime NOT NULL
		) ENGINE=InnoDB' ) );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
		add_filter( 'cb_work_frontend_can_read', [ $this, 'allow_read' ], 10, 4 );
		add_filter( 'cb_work_frontend_can_transition_work_item', [ $this, 'allow_transition' ], 10, 4 );
	}

	public function tear_down(): void {
		remove_filter( 'cb_work_frontend_can_read', [ $this, 'allow_read' ], 10 );
		remove_filter( 'cb_work_frontend_can_transition_work_item', [ $this, 'allow_transition' ], 10 );
		global $wpdb;
		foreach ( array_reverse( $this->temporary_tables ) as $table ) {
			$wpdb->query( 'DROP TEMPORARY TABLE IF EXISTS ' . $table );
		}
		unregister_post_type( PostTypes::WORK_ITEM );
		parent::tear_down();
	}

	public function allow_read( bool $allowed, \WP_Post $post, string $type, int $actor ): bool {
		return $actor > 0 && PostTypes::WORK_ITEM === $type;
	}

	public function allow_transition( bool $allowed, \WP_Post $post, string $target, int $actor ): bool {
		return $actor > 0;
	}

	public function test_required_client_status_rejects_stale_and_duplicate_actions(): void {
		$id = self::factory()->post->create( [
			'post_type' => PostTypes::WORK_ITEM, 'post_status' => 'publish', 'post_title' => 'B3.2 work',
		] );
		update_post_meta( $id, WorkItemMeta::WORK_CONTEXT, 'internal' );
		WorkItemMeta::ensure_status( $id );

		$missing = FrontendWorkItems::transition_status( $id, WorkItemStatus::IN_PROGRESS );
		self::assertInstanceOf( \WP_Error::class, $missing );
		self::assertSame( 'work_transition_precondition_required', $missing->get_error_code() );

		$first = FrontendWorkItems::transition_status( $id, WorkItemStatus::IN_PROGRESS, WorkItemStatus::PLANNED );
		self::assertIsArray( $first );
		self::assertSame( WorkItemStatus::IN_PROGRESS, $first['status'] );

		$stale = FrontendWorkItems::transition_status( $id, WorkItemStatus::BLOCKED, WorkItemStatus::PLANNED );
		self::assertInstanceOf( \WP_Error::class, $stale );
		self::assertSame( 'work_transition_conflict', $stale->get_error_code() );
		self::assertSame( WorkItemStatus::IN_PROGRESS, WorkItemRepository::get( $id )['status'] );

		$next = FrontendWorkItems::transition_status( $id, WorkItemStatus::BLOCKED, WorkItemStatus::IN_PROGRESS );
		self::assertIsArray( $next );
		self::assertSame( WorkItemStatus::BLOCKED, $next['status'] );

		wp_set_current_user( 0 );
		$denied = FrontendWorkItems::transition_status( $id, WorkItemStatus::PLANNED, WorkItemStatus::BLOCKED );
		self::assertInstanceOf( \WP_Error::class, $denied );
		self::assertSame( 'work_resource_unavailable', $denied->get_error_code() );
	}
}

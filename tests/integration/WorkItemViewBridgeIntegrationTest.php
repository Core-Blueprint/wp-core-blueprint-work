<?php
declare(strict_types=1);

use CB\Work\Admin\Operations;
use CB\Work\Admin\WorkItemViewBridge;
use CB\Work\Capabilities;
use CB\Work\Content\PostTypes;
use CB\Work\Content\WorkItemMeta;
use CB\Work\Database\Schema;

/** Canonical Work renderer must be reusable without editing protected G0/B1 source. */
final class WorkItemViewBridgeIntegrationTest extends WP_UnitTestCase {
	/** @var list<string> */
	private array $temp_tables = [];
	/** @var list<string> */
	private array $registered_types = [];
	private bool $added_ajax_hook = false;

	public function set_up(): void {
		if ( ! defined( 'DB_NAME' ) || 'core_blueprint_work_test' !== (string) DB_NAME
			|| ! defined( 'DB_HOST' ) || '127.0.0.1:3307' !== (string) DB_HOST ) {
			self::fail( 'UWE-03C.1 requires the isolated canonical Work test database.' );
		}
		parent::set_up();
		if ( ! post_type_exists( PostTypes::WORK_ITEM ) ) {
			PostTypes::register();
			$this->registered_types = [ PostTypes::WORK_ITEM, PostTypes::PROJECT, PostTypes::SERVICE ];
		}
		WorkItemMeta::register_meta();
		update_option( Schema::OPTION, CB_WORK_SCHEMA_VERSION );

		$manager_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		$manager = get_userdata( $manager_id );
		self::assertInstanceOf( WP_User::class, $manager );
		$manager->add_cap( Capabilities::MANAGE );
		wp_set_current_user( $manager_id );
		if ( false === has_action( 'wp_ajax_' . WorkItemViewBridge::ACTION, [ WorkItemViewBridge::class, 'handle_ajax' ] ) ) {
			WorkItemViewBridge::init();
			$this->added_ajax_hook = true;
		}

		global $wpdb;
		$assign = Schema::assignments_table();
		self::assertNotFalse( $wpdb->query( "CREATE TEMPORARY TABLE {$assign} (
			work_item_id bigint unsigned NOT NULL,
			user_id bigint unsigned NOT NULL,
			assigned_at datetime NOT NULL,
			PRIMARY KEY (work_item_id,user_id)
		) ENGINE=InnoDB" ) );
		$this->temp_tables[] = $assign;
		$relations = Schema::relations_table();
		self::assertNotFalse( $wpdb->query( "CREATE TEMPORARY TABLE {$relations} (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			work_item_id bigint unsigned NOT NULL,
			provider varchar(64) NOT NULL,
			relation_type varchar(64) NOT NULL,
			external_id varchar(191) NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id)
		) ENGINE=InnoDB" ) );
		$this->temp_tables[] = $relations;
	}

	public function tear_down(): void {
		if ( $this->added_ajax_hook ) {
			remove_action( 'wp_ajax_' . WorkItemViewBridge::ACTION, [ WorkItemViewBridge::class, 'handle_ajax' ] );
			$this->added_ajax_hook = false;
		}
		global $wpdb;
		foreach ( array_reverse( $this->temp_tables ) as $table ) {
			$wpdb->query( 'DROP TEMPORARY TABLE IF EXISTS ' . $table );
		}
		$this->temp_tables = [];
		foreach ( $this->registered_types as $type ) {
			if ( post_type_exists( $type ) ) {
				unregister_post_type( $type );
			}
		}
		$this->registered_types = [];
		parent::tear_down();
	}

	private function seed(): int {
		$id = self::factory()->post->create( [
			'post_type' => PostTypes::WORK_ITEM,
			'post_status' => 'publish',
			'post_title' => 'UWE 03C canonical item',
		] );
		update_post_meta( $id, WorkItemMeta::WORK_CONTEXT, 'internal' );
		update_post_meta( $id, WorkItemMeta::STATUS, 'planned' );
		update_post_meta( $id, WorkItemMeta::SCHEDULED_ON, current_time( 'Y-m-d' ) );
		return $id;
	}

	public function test_each_classic_view_renders_through_exact_existing_operations(): void {
		$this->seed();
		self::assertNotFalse( has_action( 'wp_ajax_' . WorkItemViewBridge::ACTION, [ WorkItemViewBridge::class, 'handle_ajax' ] ) );
		$expected = [
			'table' => 'cb-work-items-table',
			'list' => 'cb-work-items-list',
			'kanban' => 'cb-work-items-kanban',
			'calendar' => 'cb-work-calendar',
		];
		foreach ( $expected as $view => $marker ) {
			$original = $_GET;
			try {
				$_GET = [ 'view' => $view, 's' => 'UWE 03C' ];
				ob_start();
				Operations::render_work_items();
				$native = (string) ob_get_clean();
			} finally {
				$_GET = $original;
			}
			$actual = WorkItemViewBridge::render_html( [ 'view' => $view, 's' => 'UWE 03C' ] );
			self::assertIsArray( $actual );
			self::assertSame( $view, $actual['view'] );
			self::assertStringContainsString( $marker, $actual['html'] );
			self::assertSame( $native, $actual['html'], 'Bridge must preserve byte-identical canonical renderer markup.' );
			self::assertSame( $original, $_GET, 'Bridge must restore the original request context.' );
		}
	}

	public function test_denied_user_and_invalid_query_fail_closed_without_global_side_effects(): void {
		$original = $_GET;
		$bad_view = WorkItemViewBridge::render_html( [ 'view' => 'invalid' ] );
		self::assertWPError( $bad_view );
		self::assertSame( 'work_view_invalid_view', $bad_view->get_error_code() );
		$bad_data = WorkItemViewBridge::render_html( [ 's' => [ 'nested' ] ] );
		self::assertWPError( $bad_data );
		self::assertSame( 'work_view_invalid_parameter', $bad_data->get_error_code() );
		$bad_page = WorkItemViewBridge::render_html( [ 'paged' => '0' ] );
		self::assertWPError( $bad_page );
		self::assertSame( $original, $_GET );

		$tracker_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		$tracker = get_userdata( $tracker_id );
		self::assertInstanceOf( WP_User::class, $tracker );
		$tracker->add_cap( Capabilities::TRACK_TIME );
		wp_set_current_user( $tracker_id );
		$denied = WorkItemViewBridge::render_html( [ 'view' => 'table' ] );
		self::assertWPError( $denied );
		self::assertSame( 'work_view_forbidden', $denied->get_error_code() );
		self::assertSame( $original, $_GET );
	}
}

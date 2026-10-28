<?php
declare(strict_types=1);

use CB\Work\Capabilities;
use CB\Work\Content\PostTypes;
use CB\Work\Content\WorkItemMeta;
use CB\Work\Database\Schema;
use CB\Work\Domain\WorkItemStatus;
use CB\Work\Transport\Rest\WorkspaceReadController;

/**
 * Real WP 7.0 / MariaDB acceptance for the manager-only UWE-03B.1 REST read layer.
 * No production DB or external HTTP. Temporary Work link tables are test scoped.
 */
final class WorkspaceReadRestIntegrationTest extends WP_UnitTestCase {
	private int $manager_id = 0;
	/** @var string[] */
	private array $temporary_tables = [];
	/** @var string[] */
	private array $created_types = [];

	public function set_up(): void {
		if (
			! defined( 'DB_NAME' ) || 'core_blueprint_work_test' !== (string) DB_NAME
			|| ! defined( 'DB_HOST' ) || '127.0.0.1:3307' !== (string) DB_HOST
		) {
			self::fail( 'UWE-03B.1 integration requires the isolated canonical Work database.' );
		}
		parent::set_up();
		foreach ( [ PostTypes::WORK_ITEM, PostTypes::PROJECT, PostTypes::SERVICE ] as $type ) {
			if ( ! post_type_exists( $type ) ) {
				PostTypes::register();
				$this->created_types = [ PostTypes::WORK_ITEM, PostTypes::PROJECT, PostTypes::SERVICE ];
				break;
			}
		}
		WorkItemMeta::register_meta();
		update_option( Schema::OPTION, CB_WORK_SCHEMA_VERSION );

		$this->manager_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		$manager = get_userdata( $this->manager_id );
		self::assertInstanceOf( WP_User::class, $manager );
		$manager->add_cap( Capabilities::MANAGE );
		wp_set_current_user( $this->manager_id );
		self::assertTrue( \cb_work_runtime_ready() );
		self::assertTrue( current_user_can( Capabilities::MANAGE ) );

		global $wpdb;
		$assignments = Schema::assignments_table();
		self::assertNotFalse( $wpdb->query( "CREATE TEMPORARY TABLE {$assignments} (
			work_item_id bigint unsigned NOT NULL,
			user_id bigint unsigned NOT NULL,
			assigned_at datetime NOT NULL,
			PRIMARY KEY (work_item_id,user_id)
		) ENGINE=InnoDB" ) );
		$this->temporary_tables[] = $assignments;

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
		$this->temporary_tables[] = $relations;
	}

	public function tear_down(): void {
		global $wpdb;
		foreach ( array_reverse( $this->temporary_tables ) as $table ) {
			$wpdb->query( 'DROP TEMPORARY TABLE IF EXISTS ' . $table );
		}
		$this->temporary_tables = [];
		foreach ( $this->created_types as $type ) {
			if ( post_type_exists( $type ) ) {
				unregister_post_type( $type );
			}
		}
		$this->created_types = [];
		parent::tear_down();
	}

	/** @param array<string,mixed> $params */
	private function request( string $section, array $params = [], string $method = 'GET' ): WP_REST_Response {
		$rest = new WP_REST_Request( $method, '/cb-work/v1/workspace/' . $section );
		foreach ( $params as $key => $value ) {
			$rest->set_param( $key, $value );
		}
		return rest_do_request( $rest );
	}

	public function test_get_only_route_is_manager_gated_and_not_a_public_resource(): void {
		self::assertNotFalse( has_action( 'rest_api_init', [ WorkspaceReadController::class, 'register_routes' ] ) );
		$allowed = $this->request( 'projects' );
		self::assertSame( 200, $allowed->get_status() );

		$tracker_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		$tracker = get_userdata( $tracker_id );
		self::assertInstanceOf( WP_User::class, $tracker );
		$tracker->add_cap( Capabilities::TRACK_TIME );
		wp_set_current_user( $tracker_id );

		$denied = $this->request( 'projects' );
		self::assertSame( 403, $denied->get_status() );
		self::assertSame( 'rest_forbidden', $denied->get_data()['code'] );
		$direct = WorkspaceReadController::read( new WP_REST_Request( 'GET', '/cb-work/v1/workspace/projects' ) );
		self::assertWPError( $direct, 'Direct callback invocation must fail closed.' );

		wp_set_current_user( 0 );
		$guest = $this->request( 'services' );
		self::assertSame( 401, $guest->get_status() );
		self::assertSame( 'rest_forbidden', $guest->get_data()['code'] );
	}

	public function test_projects_and_services_have_bounded_stable_pages_and_safe_list_fields(): void {
		foreach ( [ 'Alpha', 'Beta', 'Gamma' ] as $title ) {
			self::factory()->post->create( [
				'post_type' => PostTypes::PROJECT,
				'post_status' => 'publish',
				'post_title' => 'UWE Project ' . $title,
			] );
		}
		foreach ( [ 'Audit', 'Maintenance' ] as $title ) {
			self::factory()->post->create( [
				'post_type' => PostTypes::SERVICE,
				'post_status' => 'draft',
				'post_title' => 'UWE Service ' . $title,
			] );
		}

		$one = $this->request( 'projects', [ 'search' => 'UWE Project', 'page' => 1, 'per_page' => 1 ] );
		$two = $this->request( 'projects', [ 'search' => 'UWE Project', 'page' => 2, 'per_page' => 1 ] );
		$outside = $this->request( 'projects', [ 'search' => 'UWE Project', 'page' => 8, 'per_page' => 1 ] );
		foreach ( [ $one, $two, $outside ] as $response ) {
			self::assertSame( 200, $response->get_status() );
			self::assertSame( 3, $response->get_data()['total'] );
			self::assertSame( 3, $response->get_data()['pages'] );
		}
		self::assertCount( 1, $one->get_data()['items'] );
		self::assertCount( 1, $two->get_data()['items'] );
		self::assertSame( 'UWE Project Alpha', $one->get_data()['items'][0]['title'] );
		self::assertSame( 'UWE Project Beta', $two->get_data()['items'][0]['title'] );
		self::assertSame( [], $outside->get_data()['items'] );
		self::assertSame( [ 'id', 'title', 'status' ], array_keys( $one->get_data()['items'][0] ) );

		$services = $this->request( 'services', [ 'search' => 'UWE Service', 'per_page' => 10 ] );
		self::assertSame( 200, $services->get_status() );
		self::assertSame( 2, $services->get_data()['total'] );
		self::assertCount( 2, $services->get_data()['items'] );
		self::assertSame( 'draft', $services->get_data()['items'][0]['status'] );
		self::assertSame( [ 'id', 'title', 'status' ], array_keys( $services->get_data()['items'][0] ) );
	}

	public function test_work_items_delegate_status_filter_and_pagination_to_canonical_query(): void {
		foreach ( [ WorkItemStatus::PLANNED, WorkItemStatus::COMPLETED ] as $status ) {
			$id = self::factory()->post->create( [
				'post_type' => PostTypes::WORK_ITEM,
				'post_status' => 'publish',
				'post_title' => 'UWE Work ' . $status,
			] );
			update_post_meta( $id, WorkItemMeta::STATUS, $status );
			update_post_meta( $id, WorkItemMeta::WORK_CONTEXT, 'internal' );
		}
		$all = $this->request( 'work-items', [ 'per_page' => 1, 'sort' => 'title' ] );
		self::assertSame( 200, $all->get_status() );
		self::assertSame( 2, $all->get_data()['total'] );
		self::assertSame( 2, $all->get_data()['pages'] );
		self::assertCount( 1, $all->get_data()['items'] );

		$planned = $this->request( 'work-items', [ 'status' => WorkItemStatus::PLANNED, 'per_page' => 25 ] );
		self::assertSame( 200, $planned->get_status() );
		self::assertSame( 1, $planned->get_data()['total'] );
		self::assertSame( WorkItemStatus::PLANNED, $planned->get_data()['items'][0]['status'] );
		$record = $planned->get_data()['items'][0];
		self::assertArrayHasKey( 'updated_at', $record );
		foreach ( [ 'customer_id', 'customer_provider', 'description', 'relations', 'pricing', 'assigned_user_ids' ] as $sensitive ) {
			self::assertArrayNotHasKey( $sensitive, $record );
		}
	}

	public function test_invalid_parameters_and_all_write_methods_fail_closed(): void {
		$bad_size = $this->request( 'projects', [ 'per_page' => 101 ] );
		self::assertSame( 400, $bad_size->get_status() );
		$bad_page = $this->request( 'services', [ 'page' => 0 ] );
		self::assertSame( 400, $bad_page->get_status() );
		$bad_filter = $this->request( 'work-items', [ 'status' => 'no-such-status' ] );
		self::assertSame( 400, $bad_filter->get_status() );

		$before = wp_count_posts( PostTypes::PROJECT );
		$post = $this->request( 'projects', [], 'POST' );
		self::assertSame( 404, $post->get_status() );
		$after = wp_count_posts( PostTypes::PROJECT );
		self::assertEquals( $before, $after );
	}
}

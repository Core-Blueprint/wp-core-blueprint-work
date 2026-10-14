<?php
declare(strict_types=1);

use CB\Work\Content\PostTypes;
use CB\Work\Content\WorkItemMeta;
use CB\Work\Database\Schema;
use CB\Work\Repository\WorkItems;

/**
 * B2 facade-equivalence acceptance on real WordPress 7.0/MariaDB.
 *
 * Only tools/check-integration's explicitly disposable Work DB is accepted.
 */
final class WorkItemArchitectureIntegrationTest extends WP_UnitTestCase {
	/** @var list<string> */
	private array $temporary_tables = [];
	private bool $added_delete_hook = false;

	public function set_up(): void {
		if ( ! defined( 'DB_NAME' ) || 'core_blueprint_work_test' !== (string) DB_NAME
			|| ! defined( 'DB_HOST' ) || '127.0.0.1:3307' !== (string) DB_HOST ) {
			self::fail( 'B2 integration requires the isolated canonical local Work database.' );
		}
		parent::set_up();
		global $wpdb;
		register_post_type( PostTypes::WORK_ITEM, [ 'public' => false ] );
		WorkItemMeta::register_meta();
		update_option( Schema::OPTION, CB_WORK_SCHEMA_VERSION );
		// Preserve existing runtime hooks; only add the facade hook when absent.
		if ( false === has_action( 'before_delete_post', [ WorkItems::class, 'before_delete_post' ] ) ) {
			add_action( 'before_delete_post', [ WorkItems::class, 'before_delete_post' ], 10, 2 );
			$this->added_delete_hook = true;
		}

		$assignments = Schema::assignments_table();
		self::assertNotFalse( $wpdb->query( "CREATE TEMPORARY TABLE {$assignments} (
			work_item_id bigint unsigned NOT NULL,
			user_id bigint unsigned NOT NULL,
			assigned_at datetime NOT NULL,
			PRIMARY KEY (work_item_id, user_id)
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
			PRIMARY KEY (id),
			UNIQUE KEY work_relation (work_item_id,provider,relation_type,external_id)
		) ENGINE=InnoDB" ) );
		$this->temporary_tables[] = $relations;
	}

	public function tear_down(): void {
		global $wpdb;
		if ( $this->added_delete_hook ) {
			remove_action( 'before_delete_post', [ WorkItems::class, 'before_delete_post' ], 10 );
			$this->added_delete_hook = false;
		}
		foreach ( array_reverse( $this->temporary_tables ) as $table ) {
			$wpdb->query( 'DROP TEMPORARY TABLE IF EXISTS ' . $table );
		}
		$this->temporary_tables = [];
		if ( post_type_exists( PostTypes::WORK_ITEM ) ) {
			unregister_post_type( PostTypes::WORK_ITEM );
		}
		parent::tear_down();
	}

	public function test_create_update_read_and_delete_keep_facade_and_links_consistent(): void {
		global $wpdb;
		$user_id = self::factory()->user->create();
		$work_item_id = WorkItems::create( [
			'title'             => 'B2 linked Work Item',
			'work_context'      => 'internal',
			'assigned_user_ids' => [ $user_id ],
			'source_provider'   => 'b2-test',
			'source_type'       => 'task',
			'source_id'         => 'reference-42',
		] );
		self::assertGreaterThan( 0, $work_item_id );
		$item = WorkItems::get( $work_item_id );
		self::assertIsArray( $item );
		self::assertSame( 'B2 linked Work Item', $item['title'] );
		self::assertSame( [ $user_id ], $item['assigned_user_ids'] );
		self::assertCount( 1, $item['relations'] );
		self::assertSame( 'b2-test', $item['relations'][0]['provider'] );
		self::assertSame( 'task', $item['relations'][0]['relation_type'] );
		self::assertSame( 'reference-42', $item['relations'][0]['external_id'] );
		self::assertSame( 1, (int) $wpdb->get_var( 'SELECT @@in_transaction' ), 'B2 must preserve the test-owned transaction.' );

		self::assertTrue( WorkItems::update( $work_item_id, [
			'priority'          => 'high',
			'assigned_user_ids' => [],
		] ) );
		$item = WorkItems::get( $work_item_id );
		self::assertIsArray( $item );
		self::assertSame( 'high', $item['priority'] );
		self::assertSame( [], $item['assigned_user_ids'] );
		self::assertCount( 1, $item['relations'], 'Updating assignments must never discard source relations.' );
		self::assertSame( 1, (int) $wpdb->get_var( 'SELECT @@in_transaction' ) );

		self::assertInstanceOf( WP_Post::class, wp_delete_post( $work_item_id, true ) );
		self::assertSame( 0, (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Schema::assignments_table() . ' WHERE work_item_id = %d', $work_item_id ) ) );
		self::assertSame( 0, (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Schema::relations_table() . ' WHERE work_item_id = %d', $work_item_id ) ) );
	}

	public function test_incomplete_source_reference_compensates_failed_creation(): void {
		global $wpdb;
		$user_id = self::factory()->user->create();
		$before = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . $wpdb->posts . ' WHERE post_type = %s AND post_title = %s', PostTypes::WORK_ITEM, 'B2 invalid source' ) );
		self::assertSame( 0, WorkItems::create( [
			'title'             => 'B2 invalid source',
			'work_context'      => 'internal',
			'assigned_user_ids' => [ $user_id ],
			'source_provider'   => 'b2-test',
		] ) );
		$after = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . $wpdb->posts . ' WHERE post_type = %s AND post_title = %s', PostTypes::WORK_ITEM, 'B2 invalid source' ) );
		self::assertSame( $before, $after, 'A failed source-identity validation must not retain an orphan Work Item.' );
		self::assertSame( 1, (int) $wpdb->get_var( 'SELECT @@in_transaction' ), 'Compensating delete must not commit PHPUnit-owned DB transaction.' );
	}
}

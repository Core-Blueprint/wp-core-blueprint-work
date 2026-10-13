<?php
declare(strict_types=1);

use CB\Work\Capabilities;
use CB\Work\Content\PostTypes;
use CB\Work\Content\ProjectMeta;
use CB\Work\Content\WorkItemMeta;
use CB\Work\Database\Schema;
use CB\Work\Integration\DataExchange\ProjectBundleEntity;
use CB\Work\Governance\WorkEventOutbox;
use CB\Work\Repository\PortableIdentities;
use CoreBlueprint\Core\DataExchange\Foundation;

/**
 * Project bundle fail-nth integration on the disposable Work test database.
 * WordPress PHPUnit already owns the outer transaction.
 */
final class ProjectBundleIntegrityIntegrationTest extends WP_UnitTestCase {
	/** @var string[] */
	private array $temporary_tables = [];
	private int $emitted_work_lifecycle = 0;
	private int $emitted_work_audits = 0;

	private const WORK_SUCCESS_HOOKS = [
		'cb_work_project_created',
		'cb_work_project_updated',
		'cb_work_work_item_created',
		'cb_work_work_item_updated',
		'cb_work_work_item_status_changed',
	];

	public function set_up(): void {
		if ( ! defined( 'DB_NAME' ) || 'core_blueprint_work_test' !== (string) DB_NAME
			|| ! defined( 'DB_HOST' ) || '127.0.0.1:3307' !== (string) DB_HOST ) {
			self::fail( 'B3.1 integration requires the canonical isolated Work test database.' );
		}
		parent::set_up();

		global $wpdb;
		self::assertSame( 1, (int) $wpdb->get_var( 'SELECT @@in_transaction' ) );
		PostTypes::register();
		ProjectMeta::register_meta();
		WorkItemMeta::register_meta();
		update_option( Schema::OPTION, CB_WORK_SCHEMA_VERSION );
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		( new WP_User( $user_id ) )->add_cap( Capabilities::MANAGE );
		wp_set_current_user( $user_id );

		// Base's Privileged Access Guard requires a signed approval for newly
		// created administrators. Exercise the real governance boundary rather
		// than bypassing user_has_cap or weakening Work's authorization.
		$user = wp_get_current_user();
		self::assertInstanceOf( WP_User::class, $user );
		self::assertTrue( \CoreBlueprint\Core\Permissions\PrivilegedAccessPolicy::is_privileged( $user ) );
		if ( \CoreBlueprint\Core\Permissions\PrivilegedAccessPolicy::enforces_approval() ) {
			self::assertFalse(
				current_user_can( Capabilities::MANAGE ),
				'An unapproved administrator must not manage Work during enforcement.'
			);
		}
		self::assertTrue(
			\CoreBlueprint\Core\Permissions\PrivilegedAccessRegistry::approve( $user, $user_id, 'work_b31_integration' ),
			'The isolated test administrator must receive a valid Base-signed approval.'
		);
		self::assertTrue( \CoreBlueprint\Core\Permissions\PrivilegedAccessRegistry::is_approved( $user ) );
		self::assertTrue( current_user_can( Capabilities::MANAGE ), 'Approved administrator must hold the real Work capability.' );

		$tables = [

			Schema::outbox_table() => '(
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
			) ENGINE=InnoDB',
			Schema::portable_identities_table() => '(
				id bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
				entity_type varchar(32) NOT NULL,
				local_id bigint unsigned NOT NULL,
				portable_key char(36) NOT NULL,
				created_at datetime NOT NULL,
				UNIQUE KEY entity_local (entity_type,local_id),
				UNIQUE KEY portable_key (portable_key)
			) ENGINE=InnoDB',
			Schema::assignments_table() => '(
				work_item_id bigint unsigned NOT NULL,
				user_id bigint unsigned NOT NULL,
				assigned_at datetime NOT NULL,
				PRIMARY KEY (work_item_id,user_id)
			) ENGINE=InnoDB',
			Schema::relations_table() => '(
				id bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
				work_item_id bigint unsigned NOT NULL,
				provider varchar(64) NOT NULL,
				relation_type varchar(64) NOT NULL,
				external_id varchar(191) NOT NULL,
				created_at datetime NOT NULL
			) ENGINE=InnoDB',
			Schema::recurrence_rules_table() => '(
				id bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
				project_id bigint unsigned NOT NULL,
				work_context varchar(16) NOT NULL,
				customer_provider varchar(64) NOT NULL,
				customer_type varchar(64) NOT NULL,
				customer_id varchar(191) NOT NULL,
				updated_by bigint unsigned NOT NULL,
				updated_at datetime NOT NULL,
				billing_disposition varchar(32) NOT NULL
			) ENGINE=InnoDB',
		];
		foreach ( self::WORK_SUCCESS_HOOKS as $hook ) {
			add_action( $hook, [ $this, 'count_work_lifecycle' ], 10, 5 );
		}
		add_action( 'core_blueprint_audit_log_written', [ $this, 'count_work_audit' ], 10, 4 );

		foreach ( $tables as $name => $definition ) {
			self::assertNotFalse( $wpdb->query( 'CREATE TEMPORARY TABLE ' . $name . ' ' . $definition ) );
			$this->temporary_tables[] = $name;
		}
	}

	public function tear_down(): void {
		foreach ( self::WORK_SUCCESS_HOOKS as $hook ) {
			remove_action( $hook, [ $this, 'count_work_lifecycle' ], 10 );
		}
		remove_action( 'core_blueprint_audit_log_written', [ $this, 'count_work_audit' ], 10 );
		global $wpdb;
		foreach ( array_reverse( $this->temporary_tables ) as $name ) {
			$wpdb->query( 'DROP TEMPORARY TABLE IF EXISTS ' . $name );
		}
		$this->temporary_tables = [];
		parent::tear_down();
	}

	public function test_fail_nth_create_leaves_no_project_item_or_identity_and_retry_converges(): void {
		$record = $this->fixture();
		$entity = new ProjectBundleEntity();
		$plan = $entity->plan_import( $record, Foundation::MODE_CREATE_UPDATE, 1 );
		self::assertIsArray( $plan );
		self::assertSame( Foundation::OP_CREATE, $plan['operation'] );

		$writes = 0;
		$fail = static function ( array $data ) use ( &$writes ): array {
			if ( PostTypes::WORK_ITEM === ( $data['post_type'] ?? '' ) && ++$writes === 2 ) {
				throw new RuntimeException( 'B3.1 fail-nth Work Item insert' );
			}
			return $data;
		};
		add_filter( 'wp_insert_post_data', $fail, 10, 1 );
		try {
			$result = $entity->apply_import( $plan );
		} finally {
			remove_filter( 'wp_insert_post_data', $fail, 10 );
		}
		self::assertWPError( $result );
		self::assertSame( 'work_project_bundle_apply_failed', $result->get_error_code() );
		self::assertSame( 2, $writes );
		$this->assert_no_new_published_bundle_events();
		self::assertSame( 0, PortableIdentities::local_id( PortableIdentities::PROJECT, $record['portable_key'] ) );
		foreach ( $record['work_items'] as $item ) {
			self::assertSame( 0, PortableIdentities::local_id( PortableIdentities::WORK_ITEM, $item['portable_key'] ) );
		}

		$posts = get_posts( [
			'post_type' => [ PostTypes::PROJECT, PostTypes::WORK_ITEM ],
			'post_status' => 'any',
			'posts_per_page' => -1,
			'fields' => 'ids',
		] );
		self::assertSame( [], $posts );

		$fresh = $entity->plan_import( $record, Foundation::MODE_CREATE_UPDATE, 1 );
		self::assertIsArray( $fresh );
		self::assertSame( Foundation::OP_CREATE, $fresh['operation'] );
		self::assertIsArray( $entity->apply_import( $fresh ) );
		self::assertGreaterThan( 0, $this->outbox_count() );
		self::assertSame( 0, WorkEventOutbox::drain(), 'Never deliver before the PHPUnit outer transaction commits.' );
		self::assertSame( 0, $this->emitted_work_lifecycle );
		self::assertSame( 0, $this->emitted_work_audits );
		$project_id = PortableIdentities::local_id( PortableIdentities::PROJECT, $record['portable_key'] );
		self::assertGreaterThan( 0, $project_id );
		foreach ( $record['work_items'] as $item ) {
			self::assertGreaterThan( 0, PortableIdentities::local_id( PortableIdentities::WORK_ITEM, $item['portable_key'] ) );
		}
		$replayed = $entity->plan_import( $record, Foundation::MODE_CREATE_UPDATE, 1 );
		self::assertSame( Foundation::OP_SKIP, $replayed['operation'] );
	}

	public function test_fail_nth_update_restores_existing_project_and_first_item_and_preserves_outer_transaction(): void {
		$record = $this->fixture();
		$entity = new ProjectBundleEntity();
		$initial = $entity->plan_import( $record, Foundation::MODE_CREATE_UPDATE, 1 );
		self::assertIsArray( $entity->apply_import( $initial ) );
		$project_id = PortableIdentities::local_id( PortableIdentities::PROJECT, $record['portable_key'] );
		$first_id = PortableIdentities::local_id( PortableIdentities::WORK_ITEM, $record['work_items'][0]['portable_key'] );
		self::assertGreaterThan( 0, $first_id );

		$record['title'] = 'Modified Project';
		$record['work_items'][0]['title'] = 'Modified first item';
		$record['work_items'][0]['priority'] = 'high';
		$record['work_items'][1]['title'] = 'Modified second item';
		$plan = $entity->plan_import( $record, Foundation::MODE_CREATE_UPDATE, 1 );
		self::assertIsArray( $plan );
		self::assertSame( Foundation::OP_UPDATE, $plan['operation'] );

		global $wpdb;
		self::assertSame( 1, (int) $wpdb->get_var( 'SELECT @@in_transaction' ) );
		$before_outbox_count = $this->outbox_count();
		$writes = 0;
		$fail = static function ( array $data ) use ( &$writes ): array {
			if ( PostTypes::WORK_ITEM === ( $data['post_type'] ?? '' ) && str_starts_with( (string) ( $data['post_title'] ?? '' ), 'Modified' ) && ++$writes === 2 ) {
				throw new RuntimeException( 'B3.1 fail-nth Work Item update' );
			}
			return $data;
		};
		add_filter( 'wp_insert_post_data', $fail, 10, 1 );
		try {
			$result = $entity->apply_import( $plan );
		} finally {
			remove_filter( 'wp_insert_post_data', $fail, 10 );
		}
		self::assertWPError( $result );
		self::assertSame( 2, $writes );
		self::assertSame( $before_outbox_count, $this->outbox_count(), 'Failed UPDATE must not persist new success events.' );
		self::assertSame( 0, $this->emitted_work_lifecycle, 'Failed UPDATE must not emit Work lifecycle events.' );
		self::assertSame( 0, $this->emitted_work_audits, 'Failed UPDATE must not emit a Base audit success callback.' );
		self::assertSame( 1, (int) $wpdb->get_var( 'SELECT @@in_transaction' ), 'Import must not commit the PHPUnit transaction.' );
		self::assertSame( 'Project Bundle Fixture', get_post( $project_id )->post_title );
		self::assertSame( 'Work Item 1', get_post( $first_id )->post_title );
		self::assertSame( 'normal', get_post_meta( $first_id, WorkItemMeta::PRIORITY, true ) );

		$fresh = $entity->plan_import( $record, Foundation::MODE_CREATE_UPDATE, 1 );
		self::assertIsArray( $fresh );
		self::assertSame( Foundation::OP_UPDATE, $fresh['operation'] );
		self::assertIsArray( $entity->apply_import( $fresh ) );
		self::assertSame( 'Modified Project', get_post( $project_id )->post_title );
		self::assertSame( 'Modified first item', get_post( $first_id )->post_title );
		self::assertSame( 'high', get_post_meta( $first_id, WorkItemMeta::PRIORITY, true ) );
		self::assertSame( 1, (int) $wpdb->get_var( 'SELECT @@in_transaction' ) );
	}


	private function outbox_count(): int {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Schema::outbox_table() );
	}

	private function assert_no_new_published_bundle_events(): void {
		self::assertSame( 0, $this->outbox_count(), 'Rolled-back bundle must have no pending success events.' );
		self::assertSame( 0, $this->emitted_work_lifecycle, 'Rolled-back bundle must not publish Work success hooks.' );
		self::assertSame( 0, $this->emitted_work_audits, 'Rolled-back bundle must not publish Base success audit callbacks.' );
	}

	public function count_work_lifecycle(): void {
		$this->emitted_work_lifecycle++;
	}

	public function count_work_audit( int $id, string $event_type ): void {
		unset( $id );
		if ( str_starts_with( $event_type, 'work_' ) ) {
			$this->emitted_work_audits++;
		}
	}

	/** @return array<string,mixed> */
	private function fixture(): array {
		$items = [];
		for ( $i = 1; $i <= 3; $i++ ) {
			$items[] = [
				'portable_key' => wp_generate_uuid4(),
				'title' => 'Work Item ' . $i,
				'description' => '',
				'priority' => 'normal',
				'estimated_minutes' => 30,
				'scheduled_on' => '',
				'due_on' => '',
				'status' => 'planned',
			];
		}
		return [
			'portable_key' => wp_generate_uuid4(),
			'title' => 'Project Bundle Fixture',
			'description' => '',
			'work_context' => 'internal',
			'starts_on' => '',
			'due_on' => '',
			'work_items' => $items,
		];
	}
}

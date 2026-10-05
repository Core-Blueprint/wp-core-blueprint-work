<?php
declare(strict_types=1);

namespace {
	define( 'ABSPATH', '/tmp/wp/' );
	define( 'ARRAY_A', 'ARRAY_A' );
	define( 'CB_WORK_SCHEMA_VERSION', '1.3' );

	final class WP_Error {}

	final class WP_Post {
		public string $post_date_gmt = '2026-09-05 09:00:00';

		public function __construct(
			public int $ID,
			public string $post_title,
			public string $post_modified_gmt,
			public string $post_type = 'cb_work_item',
			public string $post_status = 'publish',
			public string $post_content = '',
			public int $post_author = 1
		) {}
	}

	final class WP_Query {
		/** @var int[] */
		public array $posts = [];
		public int $found_posts = 0;

		/** @param array<string,mixed> $args */
		public function __construct( array $args ) {
			$GLOBALS['cb_work_d2_last_query_args'] = $args;
			$ids = $GLOBALS['cb_work_d2_query_ids'];
			if ( isset( $args['post__in'] ) && is_array( $args['post__in'] ) ) {
				$ids = array_values( array_intersect( $ids, array_map( 'intval', $args['post__in'] ) ) );
			}
			$this->posts = $ids;
			$this->found_posts = count( $ids );
		}
	}

	final class CB_Work_D2_Wpdb {
		public function prepare( string $sql, mixed ...$args ): string {
			unset( $args );
			return $sql;
		}

		/** @return int[] */
		public function get_col( string $sql ): array {
			if ( str_contains( $sql, 'WHERE user_id = %d' ) ) {
				return [ 3, 1 ];
			}
			if ( str_contains( $sql, 'WHERE work_item_id = %d' ) ) {
				return [ 7 ];
			}
			return [];
		}

		/** @return array<int,array<string,mixed>> */
		public function get_results( string $sql, mixed $output = null ): array {
			unset( $sql, $output );
			return [];
		}
	}

	$GLOBALS['wpdb'] = new CB_Work_D2_Wpdb();
	$GLOBALS['cb_work_d2_query_ids'] = [ 1, 2, 3 ];
	$GLOBALS['cb_work_d2_posts'] = [
		1 => new WP_Post( 1, 'Alpha', '2026-09-05 10:00:00' ),
		2 => new WP_Post( 2, 'Beta', '2026-09-05 12:00:00' ),
		3 => new WP_Post( 3, 'Gamma', '2026-09-05 11:00:00' ),
	];
	$GLOBALS['cb_work_d2_meta'] = [
		1 => [
			'_status' => 'planned',
			'_priority' => 'normal',
			'_project' => 9,
			'_service' => 4,
			'_type' => 6,
			'_scheduled' => '2026-09-07',
			'_due' => '2026-09-08',
			'_billing' => 'hourly',
			'_context' => 'customer',
			'_customer_provider' => 'crm',
			'_customer_type' => 'contact',
			'_customer_id' => '42',
		],
		2 => [
			'_status' => 'in_progress',
			'_priority' => 'high',
			'_project' => 9,
			'_service' => 4,
			'_type' => 6,
			'_scheduled' => '2026-09-09',
			'_due' => '2026-09-20',
			'_billing' => 'hourly',
			'_context' => 'customer',
			'_customer_provider' => 'crm',
			'_customer_type' => 'organization',
			'_customer_id' => '42',
		],
		3 => [
			'_status' => 'in_progress',
			'_priority' => 'high',
			'_project' => 9,
			'_service' => 4,
			'_type' => 6,
			'_scheduled' => '2026-09-08',
			'_due' => '2026-09-10',
			'_billing' => 'hourly',
			'_context' => 'customer',
			'_customer_provider' => 'crm',
			'_customer_type' => 'organization',
			'_customer_id' => '42',
		],
	];

	function sanitize_key( string $value ): string { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', $value ) ?? '' ); }
	function sanitize_text_field( string $value ): string { return trim( strip_tags( $value ) ); }
	function absint( mixed $value ): int { return abs( (int) $value ); }
	function get_option( string $key, mixed $default = false ): mixed { unset( $key, $default ); return '1.3'; }
	function update_meta_cache( string $type, array $ids ): bool { unset( $type, $ids ); return true; }
	function get_post( int $id ): ?WP_Post { return $GLOBALS['cb_work_d2_posts'][ $id ] ?? null; }
	function get_post_meta( int $id, string $key, bool $single = false ): mixed {
		unset( $single );
		return $GLOBALS['cb_work_d2_meta'][ $id ][ $key ] ?? '';
	}
	function is_wp_error( mixed $value ): bool { return $value instanceof WP_Error; }
	function assert_true( bool $condition, string $message ): void {
		if ( ! $condition ) {
			fwrite( STDERR, "D2 operational query smoke failed: {$message}\n" );
			exit( 1 );
		}
	}
}

namespace CB\Work\Domain {
	final class WorkContext {
		public const INTERNAL = 'internal';
		public const CUSTOMER = 'customer';
		public static function all(): array { return [ self::INTERNAL, self::CUSTOMER ]; }
		public static function is_valid( string $value ): bool { return in_array( $value, self::all(), true ); }
		public static function sanitize( mixed $value ): string {
			$value = \sanitize_key( is_scalar( $value ) ? (string) $value : '' );
			return self::is_valid( $value ) ? $value : '';
		}
		public static function requires_customer( string $value ): bool { return self::CUSTOMER === $value; }
	}

	final class WorkItemStatus {
		public const PLANNED = 'planned';
		public const IN_PROGRESS = 'in_progress';
		public const COMPLETED = 'completed';
		public const SKIPPED = 'skipped';
		public const CANCELLED = 'cancelled';
		public static function all(): array { return [ self::PLANNED, self::IN_PROGRESS, self::COMPLETED, self::SKIPPED, self::CANCELLED ]; }
		public static function active(): array { return [ self::PLANNED, self::IN_PROGRESS ]; }
		public static function is_valid( string $value ): bool { return in_array( $value, self::all(), true ); }
		public static function can_transition( string $from, string $to ): bool { unset( $from, $to ); return true; }
	}

	final class WorkItemPriority {
		public const NORMAL = 'normal';
		public static function all(): array { return [ 'low', 'normal', 'high', 'urgent' ]; }
		public static function is_valid( string $value ): bool { return in_array( $value, self::all(), true ); }
	}

	final class BillingDisposition {
		public static function all(): array { return [ 'hourly', 'fixed', 'included', 'non_billable' ]; }
		public static function is_valid( string $value ): bool { return in_array( $value, self::all(), true ); }
	}
}

namespace CB\Work\Content {
	final class PostTypes { public const WORK_ITEM = 'cb_work_item'; }

	final class WorkItemMeta {
		public const WORK_CONTEXT = '_context';
		public const CUSTOMER_PROVIDER = '_customer_provider';
		public const CUSTOMER_TYPE = '_customer_type';
		public const CUSTOMER_ID = '_customer_id';
		public const PROJECT_ID = '_project';
		public const SERVICE_ID = '_service';
		public const WORK_TYPE_ID = '_type';
		public const PRIORITY = '_priority';
		public const SCHEDULED_ON = '_scheduled';
		public const DUE_ON = '_due';
		public const STATUS = '_status';
		public const BILLING_DISPOSITION = '_billing';

		/** @return array<string,mixed> */
		public static function get( int $id ): array {
			$meta = $GLOBALS['cb_work_d2_meta'][ $id ];
			return [
				'work_context' => $meta[self::WORK_CONTEXT] ?? '',
				'customer_provider' => $meta[self::CUSTOMER_PROVIDER] ?? '',
				'customer_type' => $meta[self::CUSTOMER_TYPE] ?? '',
				'customer_id' => $meta[self::CUSTOMER_ID] ?? '',
				'project_id' => $meta[self::PROJECT_ID] ?? null,
				'service_id' => $meta[self::SERVICE_ID] ?? null,
				'work_type_id' => $meta[self::WORK_TYPE_ID] ?? null,
				'priority' => $meta[self::PRIORITY] ?? 'normal',
				'estimated_minutes' => 0,
				'scheduled_on' => $meta[self::SCHEDULED_ON] ?? null,
				'due_on' => $meta[self::DUE_ON] ?? null,
				'status' => $meta[self::STATUS] ?? 'planned',
				'billing_disposition' => $meta[self::BILLING_DISPOSITION] ?? '',
				'completed_at' => null,
				'completed_by' => null,
			];
		}
	}
}

namespace CB\Work\Database {
	final class Schema {
		public const OPTION = 'cb_work_db_version';
		public static function assignments_table(): string { return 'wp_cb_work_item_assignments'; }
		public static function relations_table(): string { return 'wp_cb_work_item_relations'; }
	}
}

namespace CB\Work\PublicApi {
	final class Services { public static function get( int $id ): ?array { unset( $id ); return []; } }
}

namespace CB\Work\Integration {
	final class CRMCustomers {
		public static function reference( string $token ): array|null|\WP_Error {
			return match ( $token ) {
				'crm:contact:42' => [ 'provider' => 'crm', 'type' => 'contact', 'id' => '42' ],
				'crm:organization:42' => [ 'provider' => 'crm', 'type' => 'organization', 'id' => '42' ],
				'' => null,
				default => new \WP_Error(),
			};
		}
	}
}

namespace CB\Work\Admin {
	final class Menu { public const WORK_ITEMS_SLUG = 'core-blueprint-work-items'; }
}

namespace {
	require dirname( __DIR__ ) . '/src/Query/WorkItemQuery.php';
	require dirname( __DIR__ ) . '/src/Admin/WorkItemViewState.php';
	require dirname( __DIR__ ) . '/src/Repository/WorkItems.php';

	$criteria = \CB\Work\Query\WorkItemQuery::normalize( [
		'statuses' => [ 'planned', 'invalid', 'in_progress', 'planned' ],
		'priorities' => 'high',
		'billing_dispositions' => [ 'hourly', 'invalid' ],
		'work_context' => 'customer',
		'project_id' => '9',
		'per_page' => 999,
		'sort' => 'invalid',
	] );
	assert_true( [ 'planned', 'in_progress' ] === $criteria['statuses'], 'criteria normalize valid statuses once and preserve their order.' );
	assert_true( [ 'high' ] === $criteria['priorities'], 'criteria accept scalar enum filters.' );
	assert_true( [ 'hourly' ] === $criteria['billing_dispositions'], 'criteria reject invalid billing filters.' );
	assert_true( 'customer' === $criteria['work_context'], 'criteria normalize canonical Work context.' );
	assert_true( 9 === $criteria['project_id'], 'criteria normalize object IDs.' );
	assert_true( 500 === $criteria['per_page'], 'criteria preserve the established repository limit ceiling.' );
	assert_true( 'workload' === $criteria['sort'], 'invalid sort falls back to canonical workload order.' );

	$state = \CB\Work\Admin\WorkItemViewState::from_request( [
		'view' => 'kanban',
		's' => 'Gamma',
		'status' => 'active',
		'priority' => 'high',
		'project_id' => '9',
		'service_id' => '4',
		'work_type_id' => '6',
		'assignee_id' => '7',
		'billing' => 'hourly',
		'work_context' => 'customer',
		'customer' => 'crm:organization:42',
		'scheduled_from' => '2026-09-01',
		'due_to' => '2026-09-30',
		'sort' => 'due',
		'paged' => '2',
	] );
	assert_true( 'kanban' === $state['view'], 'view state preserves a supported renderer.' );
	assert_true( true === $state['customer_valid'], 'opaque customer token resolves through the canonical Work adapter.' );
	assert_true( [ 'provider' => 'crm', 'type' => 'organization', 'id' => '42' ] === $state['query']['customer'], 'view state maps opaque transport to canonical customer criteria.' );
	assert_true( 'customer' === $state['query']['work_context'], 'view state carries Work context into canonical criteria.' );
	assert_true( [ 'planned', 'in_progress' ] === $state['query']['statuses'], 'active status maps to the canonical active workload states.' );
	assert_true( 2 === $state['query']['page'], 'view pagination flows into canonical query criteria.' );

	$return_state = \CB\Work\Admin\WorkItemViewState::from_request( [
		'view'       => 'list',
		'status'     => 'active',
		'priority'   => 'high',
		'project_id' => '12',
		'paged'      => '2',
	] );
	$return_args = \CB\Work\Admin\WorkItemViewState::query_args( $return_state );
	unset( $return_args['page'] );
	$restored_state = \CB\Work\Admin\WorkItemViewState::from_request( $return_args );
	$redirect_args  = \CB\Work\Admin\WorkItemViewState::query_args( $restored_state );
	assert_true(
		[
			'page'       => 'core-blueprint-work-items',
			'view'       => 'list',
			'status'     => 'active',
			'priority'   => 'high',
			'project_id' => 12,
			'paged'      => 2,
		] === $redirect_args,
		'List + Project 12 + Active + High + page 2 survives the canonical transition return-state roundtrip exactly.'
	);

	$invalid_customer = \CB\Work\Admin\WorkItemViewState::from_request( [ 'customer' => 'crm:invalid:42' ] );
	assert_true( false === $invalid_customer['customer_valid'], 'malformed customer filter is marked invalid instead of silently remapped.' );
	assert_true( null === $invalid_customer['query']['customer'], 'invalid customer never reaches persistence criteria.' );

	$result = \CB\Work\Repository\WorkItems::search( [
		'search' => 'Gamma',
		'statuses' => [ 'planned', 'in_progress' ],
		'priorities' => [ 'high' ],
		'project_id' => 9,
		'service_id' => 4,
		'work_type_id' => 6,
		'assignee_id' => 7,
		'billing_dispositions' => [ 'hourly' ],
		'work_context' => 'customer',
		'customer' => [ 'provider' => 'crm', 'type' => 'organization', 'id' => '42' ],
		'scheduled_from' => '2026-09-01',
		'due_to' => '2026-09-30',
		'sort' => 'workload',
		'page' => 1,
		'per_page' => 1,
	] );

	$args = $GLOBALS['cb_work_d2_last_query_args'];
	assert_true( 'Gamma' === ( $args['s'] ?? '' ), 'text search reaches the canonical WP query.' );
	assert_true( [ 3, 1 ] === ( $args['post__in'] ?? [] ), 'assignee filter resolves through the Work-owned assignment table.' );
	assert_true( isset( $args['meta_query'] ) && is_array( $args['meta_query'] ), 'canonical metadata filters are combined in one WP query.' );
	$serialized_filters = serialize( $args['meta_query'] );
	foreach ( [ '_status', '_priority', '_project', '_service', '_type', '_billing', '_context', '_customer_provider', '_customer_type', '_customer_id', '_scheduled', '_due' ] as $key ) {
		assert_true( str_contains( $serialized_filters, $key ), "meta filter {$key} is present." );
	}
	assert_true( 2 === $result['total'] && 2 === $result['pages'], 'result contract reports total and page count after assignee intersection.' );
	assert_true( 1 === count( $result['items'] ) && 3 === $result['items'][0]['id'], 'workload sorting runs before pagination and hydrates only the selected page.' );
	assert_true( [ 7 ] === $result['items'][0]['assigned_user_ids'], 'hydrated page retains canonical assignment data.' );

	$source = file_get_contents( dirname( __DIR__ ) . '/src/Repository/WorkItems.php' );
	assert_true( is_string( $source ) && str_contains( $source, 'public static function search(' ), 'repository exposes one internal canonical operational search engine.' );
	assert_true( substr_count( $source, 'self::search(' ) >= 2, 'legacy all/project helpers are thin wrappers over the canonical engine.' );
	assert_true( str_contains( $source, "array_slice( \$ids" ) && str_contains( $source, 'self::hydrate( $post )' ), 'engine paginates the matchset before full Work Item hydration.' );

	echo "D2 operational query smoke passed.\n";
}

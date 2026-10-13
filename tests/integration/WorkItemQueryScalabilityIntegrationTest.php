<?php
declare(strict_types=1);

use CB\Work\Content\PostTypes;
use CB\Work\Content\WorkItemMeta;
use CB\Work\Database\Schema;
use CB\Work\Domain\WorkItemStatus;
use CB\Work\Repository\WorkItems;

/**
 * Real WP 7.0 / MariaDB test of A2 SQL-first pagination and stable totals.
 * Test data is restricted to the disposable core_blueprint_work_test DB.
 */
final class WorkItemQueryScalabilityIntegrationTest extends WP_UnitTestCase {
	private array $temp_tables = [];

	public function set_up(): void {
		if ( ! defined( 'DB_NAME' ) || 'core_blueprint_work_test' !== (string) DB_NAME
			|| ! defined( 'DB_HOST' ) || '127.0.0.1:3307' !== (string) DB_HOST
		) {
			self::fail( 'A2 integration refuses non-canonical databases.' );
		}
		parent::set_up();
		register_post_type( PostTypes::WORK_ITEM, [ 'public' => false ] );
		WorkItemMeta::register_meta();
		update_option( Schema::OPTION, CB_WORK_SCHEMA_VERSION );

		global $wpdb;
		$assign = Schema::assignments_table();
		self::assertNotFalse( $wpdb->query(
			"CREATE TEMPORARY TABLE {$assign} (
				work_item_id bigint unsigned NOT NULL,
				user_id bigint unsigned NOT NULL,
				assigned_at datetime NOT NULL,
				PRIMARY KEY (work_item_id,user_id),
				KEY user_id (user_id)
			) ENGINE=InnoDB"
		) );
		$this->temp_tables[] = $assign;

		$relations = Schema::relations_table();
		self::assertNotFalse( $wpdb->query(
			"CREATE TEMPORARY TABLE {$relations} (
				id bigint unsigned NOT NULL AUTO_INCREMENT,
				work_item_id bigint unsigned NOT NULL,
				provider varchar(64) NOT NULL,
				relation_type varchar(64) NOT NULL,
				external_id varchar(191) NOT NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY (id)
			) ENGINE=InnoDB"
		) );
		$this->temp_tables[] = $relations;
	}

	public function tear_down(): void {
		global $wpdb;
		foreach ( array_reverse( $this->temp_tables ) as $name ) {
			$wpdb->query( 'DROP TEMPORARY TABLE IF EXISTS ' . $name );
		}
		$this->temp_tables = [];
		if ( post_type_exists( PostTypes::WORK_ITEM ) ) {
			unregister_post_type( PostTypes::WORK_ITEM );
		}
		parent::tear_down();
	}

	public function test_bounded_workload_paging_total_and_out_of_range_page(): void {
		$planned = $this->seed( 'Planned', WorkItemStatus::PLANNED, '2026-11-02' );
		$ip_late = $this->seed( 'Active late', WorkItemStatus::IN_PROGRESS, '2026-11-10' );
		$ip_early = $this->seed( 'Active early', WorkItemStatus::IN_PROGRESS, '2026-11-01' );
		$blocked = $this->seed( 'Blocked', WorkItemStatus::BLOCKED, '2026-10-30' );
		$requests = [];
		$capture = static function ( string $sql ) use ( &$requests ): string {
			if ( str_contains( $sql, 'SQL_CALC_FOUND_ROWS' )
				&& str_contains( $sql, PostTypes::WORK_ITEM ) ) {
				$requests[] = $sql;
			}
			return $sql;
		};
		add_filter( 'posts_request', $capture );
		try {
			$one = WorkItems::search( [ 'per_page' => 2, 'page' => 1 ] );
			$two = WorkItems::search( [ 'per_page' => 2, 'page' => 2 ] );
			$empty = WorkItems::search( [ 'per_page' => 2, 'page' => 4 ] );
		} finally {
			remove_filter( 'posts_request', $capture );
		}

		self::assertSame( [ $ip_early, $ip_late ], array_column( $one['items'], 'id' ) );
		self::assertSame( [ $planned, $blocked ], array_column( $two['items'], 'id' ) );
		self::assertSame( [], $empty['items'] );
		foreach ( [ $one, $two, $empty ] as $page ) {
			self::assertSame( 4, $page['total'] );
			self::assertSame( 2, $page['pages'] );
			self::assertSame( 2, $page['per_page'] );
		}
		self::assertCount( 4, $requests, 'Out-of-range WP_Query needs one additional bounded first-page count probe.' );
		self::assertStringContainsString( 'GROUP BY', $requests[0] );
		self::assertMatchesRegularExpression( '/LIMIT\s+0\s*,\s*2\b/i', $requests[0] );
		self::assertMatchesRegularExpression( '/LIMIT\s+2\s*,\s*2\b/i', $requests[1] );
		self::assertStringContainsString( 'cb_work_a2_sortmeta', $requests[0] );
	}

	public function test_sort_modes_keep_stable_id_ties_and_filters(): void {
		$alpha = $this->seed( 'alpha', WorkItemStatus::PLANNED, '2026-12-01', '2026-11-28' );
		$beta = $this->seed( 'Beta', WorkItemStatus::PLANNED, '', '2026-11-25' );
		$gamma = $this->seed( 'gamma', WorkItemStatus::PLANNED, '2026-12-01', '2026-11-28' );

		self::assertSame( [ $alpha, $beta, $gamma ], array_column( WorkItems::search( [ 'sort' => 'title' ] )['items'], 'id' ) );
		// Ensure an explicit identical updated timestamp before testing the
		// descending-ID tie-break. Factory times may straddle a second.
		global $wpdb;
		foreach ( [ $alpha, $beta, $gamma ] as $id ) {
			$wpdb->update( $wpdb->posts, [ 'post_modified_gmt' => '2026-10-01 11:00:00' ], [ 'ID' => $id ] );
			clean_post_cache( $id );
		}

		self::assertSame( [ $gamma, $alpha, $beta ], array_column( WorkItems::search( [ 'sort' => 'due' ] )['items'], 'id' ) );
		self::assertSame( [ $beta, $gamma, $alpha ], array_column( WorkItems::search( [ 'sort' => 'scheduled' ] )['items'], 'id' ) );
		self::assertSame( [ $gamma, $beta, $alpha ], array_column( WorkItems::search( [ 'sort' => 'updated' ] )['items'], 'id' ),
			'Same-second post_modified_gmt ties are broken by descending ID.' );

		$matches = WorkItems::search( [ 'statuses' => [ WorkItemStatus::PLANNED ], 'due_from' => '2026-12-01' ] );
		self::assertSame( 2, $matches['total'] );
		self::assertSame( [ $gamma, $alpha ], array_column( $matches['items'], 'id' ) );
	}

	public function test_assignment_sql_filter_and_page_batched_links(): void {
		$first = $this->seed( 'Assigned A', WorkItemStatus::PLANNED );
		$this->seed( 'Unassigned', WorkItemStatus::PLANNED );
		$last = $this->seed( 'Assigned B', WorkItemStatus::PLANNED );
		$user_id = self::factory()->user->create();
		global $wpdb;
		foreach ( [ $first, $last ] as $id ) {
			self::assertSame( 1, $wpdb->insert(
				Schema::assignments_table(),
				[ 'work_item_id' => $id, 'user_id' => $user_id, 'assigned_at' => '2026-10-01 12:00:00' ]
			) );
		}
		$wpdb->insert( Schema::relations_table(), [
			'work_item_id' => $last, 'provider' => 'crm', 'relation_type' => 'contact',
			'external_id' => '42', 'created_at' => '2026-10-01 12:00:00',
		] );

		$requests = [];
		$capture = static function ( string $sql ) use ( &$requests ): string {
			if ( str_contains( $sql, 'cb_work_a2_assigned' ) ) {
				$requests[] = $sql;
			}
			return $sql;
		};
		add_filter( 'posts_request', $capture );
		$start_count = $wpdb->num_queries;
		try {
			$page = WorkItems::search( [ 'assignee_id' => $user_id, 'per_page' => 1, 'page' => 1, 'sort' => 'updated' ] );
		} finally {
			remove_filter( 'posts_request', $capture );
		}
		self::assertCount( 1, $requests );
		self::assertStringContainsString( 'INNER JOIN', $requests[0] );
		self::assertSame( 2, $page['total'] );
		self::assertSame( [ $last ], array_column( $page['items'], 'id' ) );
		self::assertSame( [ $user_id ], $page['items'][0]['assigned_user_ids'] );
		self::assertCount( 1, $page['items'][0]['relations'] );
		self::assertSame( '42', $page['items'][0]['relations'][0]['external_id'] );
		self::assertLessThanOrEqual( 16, $wpdb->num_queries - $start_count,
			'One page query and bounded post/meta/link hydration, not per-matching-item lookups.' );
	}

	public function test_status_aggregate_uses_planned_fallback_and_excludes_trash(): void {
		$this->seed( 'Planned', WorkItemStatus::PLANNED );
		$this->seed( 'Active', WorkItemStatus::IN_PROGRESS );
		$this->seed( 'Done', WorkItemStatus::COMPLETED );
		$invalid = $this->seed( 'Invalid', 'nonsense' );
		$this->seed( 'Trash', WorkItemStatus::COMPLETED, '', '', 'trash' );
		// A legacy duplicate row must not create a duplicate status count.
		add_post_meta( $invalid, WorkItemMeta::STATUS, WorkItemStatus::COMPLETED );

		$counts = WorkItems::counts_by_status();
		self::assertSame( 2, $counts[ WorkItemStatus::PLANNED ] );
		self::assertSame( 1, $counts[ WorkItemStatus::IN_PROGRESS ] );
		self::assertSame( 1, $counts[ WorkItemStatus::COMPLETED ] );
		self::assertSame( 4, array_sum( $counts ) );
	}

	/**
	 * Opt-in profiling, not run by default. Use only the disposable Work
	 * database with CB_WORK_A2_PROFILE_SIZE = 1000|10000|25000.
	 * Measurements are evidence, not machine-specific pass thresholds.
	 */
	public function test_optional_profile_at_1k_10k_25k(): void {
		$size = (int) getenv( 'CB_WORK_A2_PROFILE_SIZE' );
		if ( ! in_array( $size, [ 1000, 10000, 25000 ], true ) ) {
			self::assertTrue( true, 'Opt-in A2 performance profile omitted during the regular fail-on-skipped Golden gate.' );
		}
		global $wpdb;
		for ( $done = 0; $done < $size; $done += 250 ) {
			$batch = min( 250, $size - $done );
			$holders = [];
			$params = [];
			for ( $i = 0; $i < $batch; $i++ ) {
				$holders[] = '(%s,%s,%s,%s,%s,%s,%s)';
				array_push( $params,
					'2026-10-01 12:00:00', '2026-10-01 12:00:00',
					'2026-10-01 12:00:00', '2026-10-01 12:00:00',
					'A2 load item ' . str_pad( (string) ( $done + $i ), 6, '0', STR_PAD_LEFT ),
					PostTypes::WORK_ITEM, 'publish'
				);
			}
			$sql = 'INSERT INTO ' . $wpdb->posts
				. ' (post_date,post_date_gmt,post_modified,post_modified_gmt,post_title,post_type,post_status)'
				. ' VALUES ' . implode( ', ', $holders );
			self::assertSame( $batch, $wpdb->query( $wpdb->prepare( $sql, ...$params ) ) );

			// Bulk INNOdb auto-increment allocates consecutive IDs within
			// one multi-row insert. Seed representative status/due metadata,
			// not merely 25k empty fallback rows.
			$first_id = (int) $wpdb->insert_id;
			self::assertGreaterThan( 0, $first_id );
			$meta_holders = [];
			$meta_params = [];
			$status_values = [ 'planned', 'in_progress', 'completed' ];
			for ( $i = 0; $i < $batch; $i++ ) {
				$item_id = $first_id + $i;
				$meta_holders[] = '(%d,%s,%s),(%d,%s,%s)';
				array_push( $meta_params,
					$item_id, WorkItemMeta::STATUS, $status_values[ ( $done + $i ) % 3 ],
					$item_id, WorkItemMeta::DUE_ON, '2026-11-' . str_pad( (string) ( 1 + ( $done + $i ) % 28 ), 2, '0', STR_PAD_LEFT )
				);
			}
			$meta_sql = 'INSERT INTO ' . $wpdb->postmeta
				. ' (post_id,meta_key,meta_value) VALUES ' . implode( ', ', $meta_holders );
			self::assertSame( $batch * 2, $wpdb->query( $wpdb->prepare( $meta_sql, ...$meta_params ) ) );
		}
		$bench = [];
		foreach ( [
			'workload' => [ 'sort' => 'workload' ],
			'due' => [ 'sort' => 'due' ],
			'status_count' => [],
		] as $name => $criteria ) {
			$start_queries = $wpdb->num_queries;
			$start_memory = memory_get_usage( true );
			$start = hrtime( true );
			$result = 'status_count' === $name
				? WorkItems::counts_by_status()
				: WorkItems::search( [ ...$criteria, 'per_page' => 50, 'page' => 1 ] );
			$bench[ $name ] = [
				'elapsed_ms' => round( ( hrtime( true ) - $start ) / 1000000, 2 ),
				'queries' => $wpdb->num_queries - $start_queries,
				'peak_delta_bytes' => max( 0, memory_get_peak_usage( true ) - $start_memory ),
			];
			if ( 'status_count' === $name ) {
				self::assertSame( $size, array_sum( $result ) );
			} else {
				self::assertSame( $size, $result['total'] );
				self::assertCount( 50, $result['items'] );
			}
		}
		fwrite( STDERR, 'Work A2 PROFILE dataset=' . $size . ' ' . wp_json_encode( $bench ) . "\n" );
	}

	private function seed(
		string $title,
		string $status,
		string $due = '',
		string $scheduled = '',
		string $post_status = 'publish'
	): int {
		$id = self::factory()->post->create( [
			'post_type' => PostTypes::WORK_ITEM,
			'post_status' => $post_status,
			'post_title' => $title,
		] );
		self::assertGreaterThan( 0, $id );
		update_post_meta( $id, WorkItemMeta::STATUS, $status );
		update_post_meta( $id, WorkItemMeta::WORK_CONTEXT, 'internal' );
		if ( '' !== $due ) {
			update_post_meta( $id, WorkItemMeta::DUE_ON, $due );
		}
		if ( '' !== $scheduled ) {
			update_post_meta( $id, WorkItemMeta::SCHEDULED_ON, $scheduled );
		}
		return $id;
	}
}

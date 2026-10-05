<?php
declare(strict_types=1);

namespace CB\Work\Repository;

use CB\Work\Content\PostTypes;
use CB\Work\Content\WorkItemMeta;
use CB\Work\Database\Schema;
use CB\Work\Domain\BillingDisposition;
use CB\Work\Domain\WorkContext;
use CB\Work\Domain\WorkItemPriority;
use CB\Work\Domain\WorkItemStatus;
use CB\Work\PublicApi\Services;
use CB\Work\Query\WorkItemQuery;

defined( 'ABSPATH' ) || exit;

final class WorkItems {
	public static function init(): void {
		add_action( 'before_delete_post', [ self::class, 'before_delete_post' ], 10, 2 );
	}

	/** @return array<int,array<string,mixed>> */
	public static function all( int $limit = 100, array $statuses = [] ): array {
		$result = self::search( [
			'statuses' => $statuses,
			'page'     => 1,
			'per_page' => max( 1, min( 500, $limit ) ),
			'sort'     => WorkItemQuery::SORT_WORKLOAD,
		] );
		return $result['items'];
	}

	/** @return array<int,array<string,mixed>> */
	public static function for_project( int $project_id, int $limit = 100, array $statuses = [] ): array {
		if ( $project_id <= 0 ) {
			return [];
		}
		$result = self::search( [
			'statuses'   => $statuses,
			'project_id' => $project_id,
			'page'       => 1,
			'per_page'   => max( 1, min( 500, $limit ) ),
			'sort'       => WorkItemQuery::SORT_WORKLOAD,
		] );
		return $result['items'];
	}

	/**
	 * Canonical operational Work Item query used by every D2 admin view.
	 *
	 * @param array<string,mixed> $criteria
	 * @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int,pages:int}
	 */
	public static function search( array $criteria = [] ): array {
		$criteria = WorkItemQuery::normalize( $criteria );
		$page     = (int) $criteria['page'];
		$per_page = (int) $criteria['per_page'];

		$args = [
			'post_type'              => PostTypes::WORK_ITEM,
			'post_status'            => self::managed_post_statuses(),
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'ignore_sticky_posts'    => true,
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		];
		if ( '' !== $criteria['search'] ) {
			$args['s'] = $criteria['search'];
		}

		$meta_query = self::query_meta_filters( $criteria );
		if ( [] !== $meta_query ) {
			$args['meta_query'] = count( $meta_query ) > 1
				? [ 'relation' => 'AND', ...$meta_query ]
				: $meta_query;
		}

		if ( (int) $criteria['assignee_id'] > 0 ) {
			$assigned_ids = self::assigned_work_item_ids( (int) $criteria['assignee_id'] );
			if ( [] === $assigned_ids ) {
				return self::empty_search_result( $page, $per_page );
			}
			$args['post__in'] = $assigned_ids;
		}

		$query = new \WP_Query( $args );
		$ids   = array_values( array_unique( array_filter( array_map( 'absint', $query->posts ) ) ) );
		if ( [] === $ids ) {
			return self::empty_search_result( $page, $per_page );
		}

		update_meta_cache( 'post', $ids );
		self::sort_query_ids( $ids, (string) $criteria['sort'] );

		$total     = count( $ids );
		$pages     = (int) ceil( $total / $per_page );
		$page_ids  = array_slice( $ids, ( $page - 1 ) * $per_page, $per_page );
		$items     = [];
		foreach ( $page_ids as $post_id ) {
			$post = get_post( $post_id );
			if ( $post instanceof \WP_Post && PostTypes::WORK_ITEM === $post->post_type ) {
				$items[] = self::hydrate( $post );
			}
		}

		return [
			'items'    => $items,
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
			'pages'    => $pages,
		];
	}

	/** @return array<string,mixed>|null */
	public static function get( int $id ): ?array {
		$post = $id > 0 ? get_post( $id ) : null;
		if (
			! $post instanceof \WP_Post
			|| PostTypes::WORK_ITEM !== $post->post_type
			|| in_array( $post->post_status, [ 'trash', 'auto-draft' ], true )
		) {
			return null;
		}
		return self::hydrate( $post );
	}

	public static function count(): int {
		$counts = wp_count_posts( PostTypes::WORK_ITEM );
		$total  = 0;
		if ( is_object( $counts ) ) {
			foreach ( get_object_vars( $counts ) as $status => $count ) {
				if ( ! in_array( $status, [ 'trash', 'auto-draft', 'inherit' ], true ) ) {
					$total += max( 0, (int) $count );
				}
		}
		}
		return $total;
	}

	public static function count_for_project( int $project_id ): int {
		if ( $project_id <= 0 ) {
			return 0;
		}
		$query = new \WP_Query( [
			'post_type'              => PostTypes::WORK_ITEM,
			'post_status'            => self::managed_post_statuses(),
			'posts_per_page'         => 1,
			'fields'                 => 'ids',
			'no_found_rows'          => false,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'meta_query'             => [
				[
					'key'   => WorkItemMeta::PROJECT_ID,
					'value' => $project_id,
					'type'  => 'NUMERIC',
				],
			],
		] );
		return max( 0, (int) $query->found_posts );
	}

	/** @return array<string,int> */
	public static function counts_by_status(): array {
		$counts = array_fill_keys( WorkItemStatus::all(), 0 );
		$query = new \WP_Query( [
			'post_type'              => PostTypes::WORK_ITEM,
			'post_status'            => self::managed_post_statuses(),
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => false,
		] );
		foreach ( $query->posts as $post_id ) {
			$status = sanitize_key( (string) get_post_meta( (int) $post_id, WorkItemMeta::STATUS, true ) );
			if ( ! WorkItemStatus::is_valid( $status ) ) {
				$status = WorkItemStatus::PLANNED;
			}
			$counts[ $status ]++;
		}
		return $counts;
	}

	/**
	 * Programmatic Work Item creation through the canonical CPT domain.
	 * Human administration uses the native Gutenberg Work Item editor.
	 *
	 * @param array<string,mixed> $input
	 */
	public static function create( array $input ): int {
		if ( ! self::schema_ready() ) {
			return 0;
		}
		$normalized = self::normalize_write( $input );
		if ( null === $normalized ) {
			return 0;
		}

		$post_id = wp_insert_post( [
			'post_type'    => PostTypes::WORK_ITEM,
			'post_status'  => 'publish',
			'post_title'   => $normalized['title'],
			'post_content' => wp_kses_post( $normalized['description'] ),
			'post_author'  => max( 0, (int) ( $input['created_by'] ?? get_current_user_id() ) ),
		], true );
		if ( is_wp_error( $post_id ) || $post_id <= 0 ) {
			return 0;
		}

		WorkItemMeta::save_details( $post_id, $normalized );
		WorkItemMeta::ensure_status( $post_id );
		if ( ! self::replace_assignments( $post_id, $normalized['assignments'] ) ) {
			wp_delete_post( $post_id, true );
			return 0;
		}

		$relation = self::reference( $input, 'source_' );
		if ( false === $relation ) {
			wp_delete_post( $post_id, true );
			return 0;
		}
		if ( '' !== $relation['provider'] && ! self::add_relation( $post_id, $relation['provider'], $relation['type'], $relation['id'] ) ) {
			wp_delete_post( $post_id, true );
			return 0;
		}

		WorkItemMeta::mark_initialized( $post_id );
		do_action( 'cb_work_work_item_created', $post_id, self::get( $post_id ) );
		return $post_id;
	}

	/** @param array<string,mixed> $input */
	public static function update( int $id, array $input ): bool {
		$current = self::get( $id );
		if ( null === $current || ! self::schema_ready() ) {
			return false;
		}
		$normalized = self::normalize_write( $input, $current );
		if ( null === $normalized ) {
			return false;
		}

		$result = wp_update_post( [
			'ID'           => $id,
			'post_title'   => $normalized['title'],
			'post_content' => wp_kses_post( $normalized['description'] ),
		], true );
		if ( is_wp_error( $result ) || $result <= 0 ) {
			return false;
		}

		WorkItemMeta::save_details( $id, $normalized );
		WorkItemMeta::ensure_status( $id );
		if ( $normalized['assignments_changed'] && ! self::replace_assignments( $id, $normalized['assignments'] ) ) {
			return false;
		}
		if ( ! WorkItemMeta::is_initialized( $id ) ) {
			WorkItemMeta::mark_initialized( $id );
		}
		do_action( 'cb_work_work_item_updated', $id, self::get( $id ), $current );
		return true;
	}

	/**
	 * Persists Work Item meta-box values after WordPress has already saved the
	 * Gutenberg title/content for the canonical CPT object.
	 *
	 * @param array<string,mixed> $input
	 */
	public static function save_editor( int $id, array $input ): bool {
		$current = self::get( $id );
		if ( null === $current || ! self::schema_ready() ) {
			return false;
		}
		$normalized = self::normalize_write( $input, $current );
		if ( null === $normalized ) {
			return false;
		}

		$was_initialized = WorkItemMeta::is_initialized( $id );
		WorkItemMeta::save_details( $id, $normalized );
		WorkItemMeta::ensure_status( $id );
		if ( $normalized['assignments_changed'] && ! self::replace_assignments( $id, $normalized['assignments'] ) ) {
			return false;
		}

		if ( ! $was_initialized ) {
			WorkItemMeta::mark_initialized( $id );
			do_action( 'cb_work_work_item_created', $id, self::get( $id ) );
			return true;
		}
		do_action( 'cb_work_work_item_updated', $id, self::get( $id ), $current );
		return true;
	}

	public static function transition_status( int $id, string $to, int $actor_user_id = 0 ): bool {
		$item = self::get( $id );
		$to   = sanitize_key( $to );
		if ( null === $item || ! WorkItemStatus::is_valid( $to ) ) {
			return false;
		}
		$from = (string) $item['status'];
		if ( ! WorkItemStatus::can_transition( $from, $to ) ) {
			return false;
		}
		WorkItemMeta::set_status( $id, $to, $actor_user_id );
		do_action( 'cb_work_work_item_status_changed', $id, $from, $to, self::get( $id ) );
		return true;
	}

	/** @return int[] */
	public static function assignments( int $work_item_id ): array {
		if ( ! self::schema_ready() || ! self::exists( $work_item_id ) ) {
			return [];
		}
		global $wpdb;
		$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT user_id FROM ' . Schema::assignments_table() . ' WHERE work_item_id = %d ORDER BY user_id ASC', $work_item_id ) );
		return is_array( $ids ) ? array_values( array_map( 'intval', $ids ) ) : [];
	}

	/** @return array<int,array<string,mixed>> */
	public static function relations( int $work_item_id ): array {
		if ( ! self::schema_ready() || ! self::exists( $work_item_id ) ) {
			return [];
		}
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare( 'SELECT id, provider, relation_type, external_id, created_at FROM ' . Schema::relations_table() . ' WHERE work_item_id = %d ORDER BY id ASC', $work_item_id ),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : [];
	}

	/** @param int[] $user_ids */
	public static function replace_assignments( int $work_item_id, array $user_ids ): bool {
		if ( ! self::schema_ready() || ! self::exists( $work_item_id ) ) {
			return false;
		}
		$user_ids = self::normalize_assignments( $user_ids );
		if ( null === $user_ids ) {
			return false;
		}

		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );
		$deleted = $wpdb->delete( Schema::assignments_table(), [ 'work_item_id' => $work_item_id ], [ '%d' ] );
		if ( false === $deleted ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}
		$now = current_time( 'mysql', true );
		foreach ( $user_ids as $user_id ) {
			$ok = $wpdb->insert(
				Schema::assignments_table(),
				[ 'work_item_id' => $work_item_id, 'user_id' => $user_id, 'assigned_at' => $now ],
				[ '%d', '%d', '%s' ]
			);
			if ( false === $ok ) {
				$wpdb->query( 'ROLLBACK' );
				return false;
			}
		}
		$wpdb->query( 'COMMIT' );
		return true;
	}

	public static function add_relation( int $work_item_id, string $provider, string $relation_type, string $external_id ): bool {
		if ( ! self::schema_ready() || ! self::exists( $work_item_id ) ) {
			return false;
		}
		$provider      = substr( sanitize_key( $provider ), 0, 64 );
		$relation_type = substr( sanitize_key( $relation_type ), 0, 64 );
		$external_id   = substr( sanitize_text_field( $external_id ), 0, 191 );
		if ( '' === $provider || '' === $relation_type || '' === $external_id ) {
			return false;
		}
		global $wpdb;
		$ok = $wpdb->insert(
			Schema::relations_table(),
			[
				'work_item_id' => $work_item_id,
				'provider'     => $provider,
				'relation_type'=> $relation_type,
				'external_id'  => $external_id,
				'created_at'   => current_time( 'mysql', true ),
			],
			[ '%d', '%s', '%s', '%s', '%s' ]
		);
		return false !== $ok;
	}

	public static function purge_links( int $work_item_id ): void {
		if ( ! self::schema_ready() || $work_item_id <= 0 ) {
			return;
		}
		global $wpdb;
		$wpdb->delete( Schema::assignments_table(), [ 'work_item_id' => $work_item_id ], [ '%d' ] );
		$wpdb->delete( Schema::relations_table(), [ 'work_item_id' => $work_item_id ], [ '%d' ] );
	}

	public static function before_delete_post( int $post_id, \WP_Post $post ): void {
		if ( PostTypes::WORK_ITEM === $post->post_type ) {
			self::purge_links( $post_id );
		}
	}

	/**
	 * @param array<string,mixed> $criteria
	 * @return array<int,array<string,mixed>>
	 */
	private static function query_meta_filters( array $criteria ): array {
		$filters = [];
		if ( [] !== $criteria['statuses'] ) {
			$filters[] = [ 'key' => WorkItemMeta::STATUS, 'value' => $criteria['statuses'], 'compare' => 'IN' ];
		}
		if ( [] !== $criteria['priorities'] ) {
			$filters[] = [ 'key' => WorkItemMeta::PRIORITY, 'value' => $criteria['priorities'], 'compare' => 'IN' ];
		}
		foreach ( [
			WorkItemMeta::PROJECT_ID   => (int) $criteria['project_id'],
			WorkItemMeta::SERVICE_ID   => (int) $criteria['service_id'],
			WorkItemMeta::WORK_TYPE_ID => (int) $criteria['work_type_id'],
		] as $key => $value ) {
			if ( $value > 0 ) {
				$filters[] = [ 'key' => $key, 'value' => $value, 'type' => 'NUMERIC' ];
			}
		}
		if ( [] !== $criteria['billing_dispositions'] ) {
			$filters[] = [ 'key' => WorkItemMeta::BILLING_DISPOSITION, 'value' => $criteria['billing_dispositions'], 'compare' => 'IN' ];
		}
		if ( is_array( $criteria['customer'] ) ) {
			$filters[] = [ 'key' => WorkItemMeta::CUSTOMER_PROVIDER, 'value' => $criteria['customer']['provider'] ];
			$filters[] = [ 'key' => WorkItemMeta::CUSTOMER_TYPE, 'value' => $criteria['customer']['type'] ];
			$filters[] = [ 'key' => WorkItemMeta::CUSTOMER_ID, 'value' => $criteria['customer']['id'] ];
		}
		foreach ( [
			[ 'from' => 'scheduled_from', 'to' => 'scheduled_to', 'key' => WorkItemMeta::SCHEDULED_ON ],
			[ 'from' => 'due_from', 'to' => 'due_to', 'key' => WorkItemMeta::DUE_ON ],
		] as $range ) {
			if ( '' !== $criteria[ $range['from'] ] ) {
				$filters[] = [ 'key' => $range['key'], 'value' => $criteria[ $range['from'] ], 'compare' => '>=', 'type' => 'DATE' ];
			}
			if ( '' !== $criteria[ $range['to'] ] ) {
				$filters[] = [ 'key' => $range['key'], 'value' => $criteria[ $range['to'] ], 'compare' => '<=', 'type' => 'DATE' ];
			}
		}
		return $filters;
	}

	/** @return int[] */
	private static function assigned_work_item_ids( int $user_id ): array {
		if ( $user_id <= 0 || ! self::schema_ready() ) {
			return [];
		}
		global $wpdb;
		$ids = $wpdb->get_col(
			$wpdb->prepare( 'SELECT work_item_id FROM ' . Schema::assignments_table() . ' WHERE user_id = %d ORDER BY work_item_id ASC', $user_id )
		);
		return is_array( $ids ) ? array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) ) : [];
	}

	/** @param int[] $ids */
	private static function sort_query_ids( array &$ids, string $sort ): void {
		usort( $ids, static function ( int $a, int $b ) use ( $sort ): int {
			if ( WorkItemQuery::SORT_TITLE === $sort ) {
				$a_post = get_post( $a );
				$b_post = get_post( $b );
				$by_title = strcasecmp(
					$a_post instanceof \WP_Post ? (string) $a_post->post_title : '',
					$b_post instanceof \WP_Post ? (string) $b_post->post_title : ''
				);
				return 0 !== $by_title ? $by_title : $a <=> $b;
			}
			if ( WorkItemQuery::SORT_UPDATED === $sort ) {
				$a_post = get_post( $a );
				$b_post = get_post( $b );
				$a_updated = $a_post instanceof \WP_Post ? (string) $a_post->post_modified_gmt : '';
				$b_updated = $b_post instanceof \WP_Post ? (string) $b_post->post_modified_gmt : '';
				return $a_updated !== $b_updated ? strcmp( $b_updated, $a_updated ) : $b <=> $a;
			}

			$key = WorkItemQuery::SORT_SCHEDULED === $sort ? WorkItemMeta::SCHEDULED_ON : WorkItemMeta::DUE_ON;
			if ( WorkItemQuery::SORT_DUE === $sort || WorkItemQuery::SORT_SCHEDULED === $sort ) {
				$a_date = (string) get_post_meta( $a, $key, true );
				$b_date = (string) get_post_meta( $b, $key, true );
				$a_date = '' !== $a_date ? $a_date : '9999-12-31';
				$b_date = '' !== $b_date ? $b_date : '9999-12-31';
				return $a_date !== $b_date ? strcmp( $a_date, $b_date ) : $b <=> $a;
			}

			$a_status = sanitize_key( (string) get_post_meta( $a, WorkItemMeta::STATUS, true ) );
			$b_status = sanitize_key( (string) get_post_meta( $b, WorkItemMeta::STATUS, true ) );
			if ( ! WorkItemStatus::is_valid( $a_status ) ) {
				$a_status = WorkItemStatus::PLANNED;
			}
			if ( ! WorkItemStatus::is_valid( $b_status ) ) {
				$b_status = WorkItemStatus::PLANNED;
			}
			$rank   = [ WorkItemStatus::IN_PROGRESS => 0, WorkItemStatus::PLANNED => 1 ];
			$a_rank = $rank[ $a_status ] ?? 2;
			$b_rank = $rank[ $b_status ] ?? 2;
			if ( $a_rank !== $b_rank ) {
				return $a_rank <=> $b_rank;
			}
			$a_due = (string) get_post_meta( $a, WorkItemMeta::DUE_ON, true );
			$b_due = (string) get_post_meta( $b, WorkItemMeta::DUE_ON, true );
			$a_due = '' !== $a_due ? $a_due : '9999-12-31';
			$b_due = '' !== $b_due ? $b_due : '9999-12-31';
			return $a_due !== $b_due ? strcmp( $a_due, $b_due ) : $b <=> $a;
		} );
	}

	/** @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int,pages:int} */
	private static function empty_search_result( int $page, int $per_page ): array {
		return [ 'items' => [], 'total' => 0, 'page' => $page, 'per_page' => $per_page, 'pages' => 0 ];
	}

	/**
	 * @param array<string,mixed>      $input
	 * @param array<string,mixed>|null $current
	 * @return array<string,mixed>|null
	 */
	private static function normalize_write( array $input, ?array $current = null ): ?array {
		$title             = sanitize_text_field( (string) ( $input['title'] ?? ( $current['title'] ?? '' ) ) );
		$description       = (string) ( $input['description'] ?? ( $current['description'] ?? '' ) );
		$context           = WorkContext::sanitize( $input['work_context'] ?? ( $current['work_context'] ?? '' ) );
		$customer          = self::reference( $input, 'customer_', $current );
		$project_id        = max( 0, (int) ( $input['project_id'] ?? ( $current['project_id'] ?? 0 ) ) );
		$service_id        = max( 0, (int) ( $input['service_id'] ?? ( $current['service_id'] ?? 0 ) ) );
		$work_type_id      = max( 0, (int) ( $input['work_type_id'] ?? ( $current['work_type_id'] ?? 0 ) ) );
		$priority          = sanitize_key( (string) ( $input['priority'] ?? ( $current['priority'] ?? WorkItemPriority::NORMAL ) ) );
		$estimated_minutes = max( 0, (int) ( $input['estimated_minutes'] ?? ( $current['estimated_minutes'] ?? 0 ) ) );
		$scheduled_on      = self::date( (string) ( $input['scheduled_on'] ?? ( $current['scheduled_on'] ?? '' ) ) );
		$due_on            = self::date( (string) ( $input['due_on'] ?? ( $current['due_on'] ?? '' ) ) );
		$billing           = sanitize_key( (string) ( $input['billing_disposition'] ?? ( $current['billing_disposition'] ?? '' ) ) );

		$assignments_changed = array_key_exists( 'assigned_user_ids', $input ) || null === $current;
		$assignments = $assignments_changed
			? self::normalize_assignments( $input['assigned_user_ids'] ?? [] )
			: (array) ( $current['assigned_user_ids'] ?? [] );

		if ( '' === $title || false === $customer || null === $assignments ) {
			return null;
		}

		if ( $project_id > 0 ) {
			$project = Projects::get( $project_id );
			if ( null === $project ) {
				return null;
			}
			$context = WorkContext::sanitize( $project['work_context'] ?? '' );
			if ( ! WorkContext::is_valid( $context ) ) {
				return null;
			}
			$customer = [
				'provider' => (string) ( $project['customer_provider'] ?? '' ),
				'type'     => (string) ( $project['customer_type'] ?? '' ),
				'id'       => (string) ( $project['customer_id'] ?? '' ),
			];
		} elseif ( '' === $context && '' !== $customer['provider'] ) {
			$context = WorkContext::CUSTOMER;
		}

		$legacy_unclassified = null !== $current
			&& '' === $context
			&& '' === (string) ( $current['work_context'] ?? '' );
		if ( ! WorkContext::is_valid( $context ) && ! $legacy_unclassified ) {
			return null;
		}

		if ( WorkContext::INTERNAL === $context ) {
			$customer = [ 'provider' => '', 'type' => '', 'id' => '' ];
			$billing  = BillingDisposition::NON_BILLABLE;
		} elseif ( WorkContext::CUSTOMER === $context ) {
			if ( '' === $customer['provider'] || '' === $customer['type'] || '' === $customer['id'] ) {
				return null;
			}
		}

		if ( $service_id > 0 && null === Services::get( $service_id ) ) {
			return null;
		}
		if ( $work_type_id > 0 && null === WorkTypes::get( $work_type_id ) ) {
			return null;
		}
		if ( ! WorkItemPriority::is_valid( $priority ) ) {
			return null;
		}
		if ( '' !== $billing && ! BillingDisposition::is_valid( $billing ) ) {
			return null;
		}
		if ( null !== $scheduled_on && null !== $due_on && $due_on < $scheduled_on ) {
			return null;
		}

		return [
			'title'               => $title,
			'description'         => $description,
			'work_context'        => $context,
			'customer_provider'   => $customer['provider'],
			'customer_type'       => $customer['type'],
			'customer_id'         => $customer['id'],
			'project_id'          => $project_id,
			'service_id'          => $service_id,
			'work_type_id'        => $work_type_id,
			'priority'            => $priority,
			'estimated_minutes'   => $estimated_minutes,
			'scheduled_on'        => $scheduled_on,
			'due_on'              => $due_on,
			'billing_disposition' => $billing,
			'assignments'         => $assignments,
			'assignments_changed' => $assignments_changed,
		];
	}

	/** @return array<string,mixed> */
	private static function hydrate( \WP_Post $post ): array {
		$meta = WorkItemMeta::get( (int) $post->ID );
		return [
			'id'                  => (int) $post->ID,
			'title'               => sanitize_text_field( (string) $post->post_title ),
			'description'         => (string) $post->post_content,
			'post_status'         => (string) $post->post_status,
			'work_context'      => $meta['work_context'],
			'customer_provider'   => $meta['customer_provider'],
			'customer_type'       => $meta['customer_type'],
			'customer_id'         => $meta['customer_id'],
			'project_id'          => $meta['project_id'],
			'service_id'          => $meta['service_id'],
			'work_type_id'        => $meta['work_type_id'],
			'priority'            => $meta['priority'],
			'estimated_minutes'   => $meta['estimated_minutes'],
			'scheduled_on'        => $meta['scheduled_on'],
			'due_on'              => $meta['due_on'],
			'status'              => $meta['status'],
			'billing_disposition' => $meta['billing_disposition'],
			'completed_at'        => $meta['completed_at'],
			'completed_by'        => $meta['completed_by'],
			'created_by'          => (int) $post->post_author,
			'created_at'          => (string) $post->post_date_gmt,
			'updated_at'          => (string) $post->post_modified_gmt,
			'assigned_user_ids'   => self::assignments( (int) $post->ID ),
			'relations'           => self::relations( (int) $post->ID ),
		];
	}

	/**
	 * @param array<string,mixed>      $input
	 * @param array<string,mixed>|null $current
	 * @return array{provider:string,type:string,id:string}|false
	 */
	private static function reference( array $input, string $prefix, ?array $current = null ): array|false {
		$has_any = array_key_exists( $prefix . 'provider', $input )
			|| array_key_exists( $prefix . 'type', $input )
			|| array_key_exists( $prefix . 'id', $input );

		if ( ! $has_any && null !== $current ) {
			return [
				'provider' => (string) ( $current[ $prefix . 'provider' ] ?? '' ),
				'type'     => (string) ( $current[ $prefix . 'type' ] ?? '' ),
				'id'       => (string) ( $current[ $prefix . 'id' ] ?? '' ),
			];
		}

		$provider = substr( sanitize_key( (string) ( $input[ $prefix . 'provider' ] ?? '' ) ), 0, 64 );
		$type     = substr( sanitize_key( (string) ( $input[ $prefix . 'type' ] ?? '' ) ), 0, 64 );
		$id       = substr( sanitize_text_field( (string) ( $input[ $prefix . 'id' ] ?? '' ) ), 0, 191 );
		if ( '' === $provider && '' === $type && '' === $id ) {
			return [ 'provider' => '', 'type' => '', 'id' => '' ];
		}
		if ( '' === $provider || '' === $type || '' === $id ) {
			return false;
		}
		return [ 'provider' => $provider, 'type' => $type, 'id' => $id ];
	}

	/** @return int[]|null */
	private static function normalize_assignments( mixed $raw ): ?array {
		if ( is_scalar( $raw ) ) {
			$raw = '' === trim( (string) $raw ) ? [] : explode( ',', (string) $raw );
		}
		if ( ! is_array( $raw ) ) {
			return null;
		}
		$user_ids = array_values( array_unique( array_filter( array_map( 'absint', $raw ) ) ) );
		foreach ( $user_ids as $user_id ) {
			if ( false === get_userdata( $user_id ) ) {
				return null;
			}
		}
		return $user_ids;
	}

	private static function date( string $value ): ?string {
		$value = trim( $value );
		if ( '' === $value ) {
			return null;
		}
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $value );
		return $date && $date->format( 'Y-m-d' ) === $value ? $value : null;
	}

	private static function exists( int $work_item_id ): bool {
		$post = $work_item_id > 0 ? get_post( $work_item_id ) : null;
		return $post instanceof \WP_Post && PostTypes::WORK_ITEM === $post->post_type && 'trash' !== $post->post_status;
	}

	/** @return string[] */
	private static function managed_post_statuses(): array {
		return [ 'publish', 'draft', 'pending', 'private', 'future' ];
	}

	private static function schema_ready(): bool {
		return defined( 'CB_WORK_SCHEMA_VERSION' )
			&& CB_WORK_SCHEMA_VERSION === (string) get_option( Schema::OPTION, '0' );
	}
}

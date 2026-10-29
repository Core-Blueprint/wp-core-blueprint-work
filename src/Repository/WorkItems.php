<?php
declare(strict_types=1);

namespace CB\Work\Repository;

use CB\Work\Governance\DeferredEvents;

use CB\Work\Content\PostTypes;
use CB\Work\Content\WorkItemMeta;
use CB\Work\Database\Schema;
use CB\Work\Domain\WorkItemStatus;
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

		// Assignee membership is resolved by a scoped SQL join, never by
		// loading every assigned Work Item ID into PHP.
		if ( (int) $criteria['assignee_id'] > 0 && ! self::schema_ready() ) {
			return self::empty_search_result( $page, $per_page );
		}
		$args = [
			'post_type'              => PostTypes::WORK_ITEM,
			'post_status'            => self::managed_post_statuses(),
			'ignore_sticky_posts'    => true,
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

		$page_result = WorkItemReadQuery::page( $args, $criteria );
		$page_ids    = $page_result['ids'];
		$total       = $page_result['total'];
		$pages       = (int) ceil( $total / $per_page );

		// Prime only the requested page and load Work-owned links in batches.
		WorkItemReadQuery::prime_page( $page_ids );
		$related = WorkItemReadQuery::related_page( $page_ids, self::schema_ready() );
		$items   = [];
		foreach ( $page_ids as $post_id ) {
			$post = get_post( $post_id );
			if ( $post instanceof \WP_Post && PostTypes::WORK_ITEM === $post->post_type ) {
				$items[] = self::hydrate(
					$post,
					$related['assignments'][ $post_id ] ?? [],
					$related['relations'][ $post_id ] ?? []
				);
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
		return WorkItemReadQuery::counts_by_status();
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

		$relation = WorkItemShape::reference( $input, 'source_' );
		if ( false === $relation ) {
			wp_delete_post( $post_id, true );
			return 0;
		}
		if ( '' !== $relation['provider'] && ! self::add_relation( $post_id, $relation['provider'], $relation['type'], $relation['id'] ) ) {
			wp_delete_post( $post_id, true );
			return 0;
		}

		WorkItemMeta::mark_initialized( $post_id );
		DeferredEvents::lifecycle( 'cb_work_work_item_created', $post_id, self::get( $post_id ) );
		return $post_id;
	}

	/** @param array<string,mixed> $input */
	public static function update( int $id, array $input, ?string $transition_to = null, int $actor_user_id = 0, ?string $expected_from = null ): bool {
		$current = null;
		$transitioned_from = null;
		$updated = WorkItemMutationTransaction::run( $id, static function () use ( $id, $input, $transition_to, $actor_user_id, $expected_from, &$current, &$transitioned_from ): bool {
			$current = self::get( $id );
			if ( null === $current || ! self::schema_ready() || ( null !== $expected_from && (string) $current['status'] !== $expected_from ) ) {
				return false;
			}
			if ( null !== $transition_to && ! WorkItemStatus::can_transition( (string) $current['status'], sanitize_key( $transition_to ) ) ) {
				return false;
			}
			$normalized = self::normalize_write( $input, $current );
			if ( null === $normalized ) {
				return false;
			}

			// A partial modal/quick edit must never round-trip Gutenberg blocks
			// through wp_kses_post. Change content only when explicitly asked.
			$post_update = [ 'ID' => $id, 'post_title' => $normalized['title'] ];
			if ( array_key_exists( 'description', $input ) ) {
				$post_update['post_content'] = wp_kses_post( $normalized['description'] );
			}
			$result = wp_update_post( $post_update, true );
			if ( is_wp_error( $result ) || $result <= 0 ) {
				return false;
			}

			WorkItemMeta::save_details( $id, $normalized );
			if ( ! WorkItemMeta::details_match( $id, $normalized ) ) {
				return false;
			}
			WorkItemMeta::ensure_status( $id );
			if ( $normalized['assignments_changed'] && ! WorkItemLinks::write_assignments( $id, $normalized['assignments'] ) ) {
				return false;
			}
			if ( null !== $transition_to ) {
				$previous_raw = (string) get_post_meta( $id, WorkItemMeta::STATUS, true );
				if ( ! WorkItemMeta::set_status( $id, sanitize_key( $transition_to ), $actor_user_id, $previous_raw ) ) {
					return false;
				}
				$transitioned_from = (string) $current['status'];
			}
			if ( ! WorkItemMeta::is_initialized( $id ) ) {
				WorkItemMeta::mark_initialized( $id );
				if ( ! WorkItemMeta::is_initialized( $id ) ) {
					return false;
				}
			}
			return true;
		} );
		if ( ! $updated ) {
			return false;
		}

		DeferredEvents::lifecycle( 'cb_work_work_item_updated', $id, self::get( $id ), $current );
		if ( null !== $transitioned_from ) {
			DeferredEvents::lifecycle( 'cb_work_work_item_status_changed', $id, $transitioned_from, sanitize_key( (string) $transition_to ), self::get( $id ) );
		}
		return true;
	}

	/**
	 * Persists only the Work-owned editor meta/assignment subset atomically.
	 * WordPress has already saved Gutenberg title/content before this hook.
	 *
	 * @param array<string,mixed> $input
	 */
	public static function save_editor( int $id, array $input, ?string $transition_to = null, int $actor_user_id = 0, ?string $expected_from = null ): bool {
		$current = null;
		$was_initialized = false;
		$transitioned_from = null;
		$updated = WorkItemMutationTransaction::run( $id, static function () use ( $id, $input, $transition_to, $actor_user_id, $expected_from, &$current, &$was_initialized, &$transitioned_from ): bool {
			$current = self::get( $id );
			if ( null === $current || ! self::schema_ready() || ( null !== $expected_from && (string) $current['status'] !== $expected_from ) ) {
				return false;
			}
			if ( null !== $transition_to && ! WorkItemStatus::can_transition( (string) $current['status'], sanitize_key( $transition_to ) ) ) {
				return false;
			}
			$normalized = self::normalize_write( $input, $current );
			if ( null === $normalized ) {
				return false;
			}

			$was_initialized = WorkItemMeta::is_initialized( $id );
			WorkItemMeta::save_details( $id, $normalized );
			if ( ! WorkItemMeta::details_match( $id, $normalized ) ) {
				return false;
			}
			WorkItemMeta::ensure_status( $id );
			if ( $normalized['assignments_changed'] && ! WorkItemLinks::write_assignments( $id, $normalized['assignments'] ) ) {
				return false;
			}
			if ( null !== $transition_to ) {
				$previous_raw = (string) get_post_meta( $id, WorkItemMeta::STATUS, true );
				if ( ! WorkItemMeta::set_status( $id, sanitize_key( $transition_to ), $actor_user_id, $previous_raw ) ) {
					return false;
				}
				$transitioned_from = (string) $current['status'];
			}
			if ( ! $was_initialized ) {
				WorkItemMeta::mark_initialized( $id );
				if ( ! WorkItemMeta::is_initialized( $id ) ) {
					return false;
				}
			}
			return true;
		} );
		if ( ! $updated ) {
			return false;
		}
		if ( ! $was_initialized ) {
			DeferredEvents::lifecycle( 'cb_work_work_item_created', $id, self::get( $id ) );
		} else {
			DeferredEvents::lifecycle( 'cb_work_work_item_updated', $id, self::get( $id ), $current );
		}
		if ( null !== $transitioned_from ) {
			DeferredEvents::lifecycle( 'cb_work_work_item_status_changed', $id, $transitioned_from, sanitize_key( (string) $transition_to ), self::get( $id ) );
		}
		return true;
	}

	public static function sync_project_context( int $project_id ): int {
		if ( $project_id <= 0 || null === Projects::get( $project_id ) || ! self::schema_ready() ) {
			return 0;
		}

		$page    = 1;
		$updated = 0;
		do {
			$result = self::search( [
				'statuses'   => WorkItemStatus::active(),
				'project_id' => $project_id,
				'page'       => $page,
				'per_page'   => 100,
				'sort'       => WorkItemQuery::SORT_UPDATED,
			] );
			foreach ( $result['items'] as $item ) {
				if ( self::update( (int) $item['id'], [] ) ) {
					$updated++;
				}
			}
			$page++;
		} while ( $page <= (int) $result['pages'] );

		return $updated;
	}

	/**
	 * The expected source status is optional for compatibility with legacy
	 * server-side consumers; UI transports should send it to reject stale moves.
	 * Callers receive false for invalid, stale or failed persistence.
	 */
	public static function transition_status( int $id, string $to, int $actor_user_id = 0, ?string $expected_from = null ): bool {
		$to = sanitize_key( $to );
		if ( ! WorkItemStatus::is_valid( $to ) ) {
			return false;
		}
		$from = '';
		$updated = WorkItemMutationTransaction::run( $id, static function () use ( $id, $to, $actor_user_id, $expected_from, &$from ): bool {
			$item = self::get( $id );
			if ( null === $item ) {
				return false;
			}
			$from = (string) $item['status'];
			if ( null !== $expected_from && $from !== $expected_from ) {
				return false;
			}
			if ( ! WorkItemStatus::can_transition( $from, $to ) ) {
				return false;
			}
			$previous_raw = (string) get_post_meta( $id, WorkItemMeta::STATUS, true );
			return WorkItemMeta::set_status( $id, $to, $actor_user_id, $previous_raw );
		} );
		if ( ! $updated ) {
			return false;
		}
		DeferredEvents::lifecycle( 'cb_work_work_item_status_changed', $id, $from, $to, self::get( $id ) );
		return true;
	}

	/** @return int[] */
	public static function assignments( int $work_item_id ): array {
		return WorkItemLinks::assignments( $work_item_id );
	}

	/** @return array<int,array<string,mixed>> */
	public static function relations( int $work_item_id ): array {
		return WorkItemLinks::relations( $work_item_id );
	}

	/** @param int[] $user_ids */
	public static function replace_assignments( int $work_item_id, array $user_ids ): bool {
		return WorkItemLinks::replace_assignments( $work_item_id, $user_ids );
	}

	public static function add_relation( int $work_item_id, string $provider, string $relation_type, string $external_id ): bool {
		return WorkItemLinks::add_relation( $work_item_id, $provider, $relation_type, $external_id );
	}

	public static function purge_links( int $work_item_id ): void {
		WorkItemLinks::purge_links( $work_item_id );
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
		return WorkItemShape::query_meta_filters( $criteria );
	}


	/** @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int,pages:int} */
	private static function empty_search_result( int $page, int $per_page ): array {
		return WorkItemShape::empty_search_result( $page, $per_page );
	}

	/**
	 * @param array<string,mixed>      $input
	 * @param array<string,mixed>|null $current
	 * @return array<string,mixed>|null
	 */
	private static function normalize_write( array $input, ?array $current = null ): ?array {
		return WorkItemShape::normalize_write( $input, $current );
	}

	/** @return array<string,mixed> */
	private static function hydrate( \WP_Post $post, ?array $assigned = null, ?array $links = null ): array {
		return WorkItemShape::hydrate( $post, $assigned, $links );
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

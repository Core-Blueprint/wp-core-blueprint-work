<?php
declare(strict_types=1);

namespace CB\Work\Repository;

use CB\Work\Content\PostTypes;
use CB\Work\Content\WorkItemMeta;
use CB\Work\Database\Schema;
use CB\Work\Domain\WorkItemStatus;
use CB\Work\Query\WorkItemQuery;

defined( 'ABSPATH' ) || exit;

/**
 * A2: bounded, deterministic WordPress reads.
 *
 * Keep meta filtering in WP_Query (and its usual hooks). Only the canonical
 * sort, assignment membership and ID grouping are added for this query.
 * No full matching-ID list is brought back to PHP before pagination.
 */
final class WorkItemReadQuery {
	/**
	 * @param array<string,mixed> $args WordPress filtering criteria.
	 * @param array<string,mixed> $criteria Normalized WorkItemQuery criteria.
	 * @return array{ids:int[],total:int}
	 */
	public static function page( array $args, array $criteria ): array {
		global $wpdb;
		$per_page = max( 1, min( 500, (int) $criteria['per_page'] ) );
		$page     = max( 1, (int) $criteria['page'] );
		$assignee = max( 0, (int) $criteria['assignee_id'] );

		$args['posts_per_page']         = $per_page;
		$args['paged']                  = $page;
		$args['fields']                 = 'ids';
		$args['no_found_rows']          = false;
		$args['suppress_filters']       = false;
		$args['update_post_meta_cache'] = false;
		$args['update_post_term_cache'] = false;

		// Unique query marker: never change nested or third-party WP_Query calls.
		static $sequence = 0;
		$scope = 'cb-work-a2-' . (string) ++$sequence;
		$args['cb_work_a2_scope'] = $scope;

		$clauses_filter = static function ( array $clauses, \WP_Query $query ) use ( $scope, $assignee, $criteria, $wpdb ): array {
			if ( $query->get( 'cb_work_a2_scope' ) !== $scope ) {
				return $clauses;
			}
			if ( $assignee > 0 ) {
				$clauses['join'] .= $wpdb->prepare(
					' INNER JOIN ' . Schema::assignments_table() . ' cb_work_a2_assigned'
					. ' ON cb_work_a2_assigned.work_item_id = ' . $wpdb->posts . '.ID'
					. ' AND cb_work_a2_assigned.user_id = %d',
					$assignee
				);
			}

			// WP meta filters may join a post more than once (OR/date
			// branches or legacy duplicate postmeta). GROUP BY ID avoids
			// MySQL 8 SELECT DISTINCT / ORDER BY expression restrictions.
			$clauses['groupby'] = $wpdb->posts . '.ID';
			$clauses['distinct'] = '';
			$clauses['orderby']  = self::orderby_sql( (string) $criteria['sort'] );
			return $clauses;
		};

		add_filter( 'posts_clauses', $clauses_filter, 20, 2 );
		try {
			$query = new \WP_Query( $args );
		} finally {
			remove_filter( 'posts_clauses', $clauses_filter, 20 );
		}

		$ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $query->posts ) ) ) );
		return [ 'ids' => $ids, 'total' => max( 0, (int) $query->found_posts ) ];
	}

	/**
	 * Identical intent to the old PHP comparators, with stable ID tie breaks.
	 * Dates are canonical ISO Y-m-d strings and empty dates go last.
	 * SQL collation determines international title ordering (WordPress default).
	 */
	private static function orderby_sql( string $sort ): string {
		global $wpdb;
		$id = $wpdb->posts . '.ID';
		if ( WorkItemQuery::SORT_TITLE === $sort ) {
			return $wpdb->posts . '.post_title ASC, ' . $id . ' ASC';
		}
		if ( WorkItemQuery::SORT_UPDATED === $sort ) {
			return $wpdb->posts . '.post_modified_gmt DESC, ' . $id . ' DESC';
		}

		$due = self::meta_value_sql( WorkItemMeta::DUE_ON );
		if ( WorkItemQuery::SORT_SCHEDULED === $sort || WorkItemQuery::SORT_DUE === $sort ) {
			$date = WorkItemQuery::SORT_SCHEDULED === $sort
				? self::meta_value_sql( WorkItemMeta::SCHEDULED_ON )
				: $due;
			return "COALESCE(NULLIF({$date}, ''), '9999-12-31') ASC, {$id} DESC";
		}

		$status = self::meta_value_sql( WorkItemMeta::STATUS );
		// Invalid/missing status is displayed as Planned by WorkItemMeta.
		$rank = "CASE WHEN LOWER({$status}) = 'in_progress' THEN 0"
			. " WHEN LOWER({$status}) IN ('blocked','completed','skipped','cancelled') THEN 2"
			. ' ELSE 1 END';
		return "{$rank} ASC, COALESCE(NULLIF({$due}, ''), '9999-12-31') ASC, {$id} DESC";
	}

	private static function meta_value_sql( string $meta_key ): string {
		global $wpdb;
		return '(SELECT cb_work_a2_sortmeta.meta_value FROM ' . $wpdb->postmeta
			. ' cb_work_a2_sortmeta WHERE cb_work_a2_sortmeta.post_id = ' . $wpdb->posts . '.ID'
			. ' AND cb_work_a2_sortmeta.meta_key = ' . $wpdb->prepare( '%s', $meta_key )
			. ' ORDER BY cb_work_a2_sortmeta.meta_id ASC LIMIT 1)';
	}

	/** @return array<string,int> */
	public static function counts_by_status(): array {
		global $wpdb;
		$counts   = array_fill_keys( WorkItemStatus::all(), 0 );
		$statuses = [ 'publish', 'draft', 'pending', 'private', 'future' ];
		$values   = WorkItemStatus::all();
		$valid    = implode( ', ', array_fill( 0, count( $values ), '%s' ) );
		$managed  = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );

		// Select the oldest meta row, as get_post_meta(..., true) does.
		// An invalid or absent status has the canonical Planned fallback.
		$sql = "SELECT CASE WHEN LOWER(COALESCE(sm.meta_value, '')) IN ({$valid})"
			. ' THEN LOWER(sm.meta_value) ELSE %s END AS canonical_status,'
			. ' COUNT(*) AS item_count'
			. ' FROM ' . $wpdb->posts . ' p'
			. ' LEFT JOIN ' . $wpdb->postmeta . ' sm ON sm.meta_id ='
			. ' (SELECT MIN(first_pm.meta_id) FROM ' . $wpdb->postmeta . ' first_pm'
			. ' WHERE first_pm.post_id = p.ID AND first_pm.meta_key = %s)'
			. ' WHERE p.post_type = %s AND p.post_status IN (' . $managed . ')'
			. ' GROUP BY canonical_status';

		$params = [ ...$values, WorkItemStatus::PLANNED, WorkItemMeta::STATUS, PostTypes::WORK_ITEM, ...$statuses ];
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ), ARRAY_A );
		foreach ( is_array( $rows ) ? $rows : [] as $row ) {
			$status = (string) ( $row['canonical_status'] ?? '' );
			if ( array_key_exists( $status, $counts ) ) {
				$counts[ $status ] = max( 0, (int) ( $row['item_count'] ?? 0 ) );
			}
		}
		return $counts;
	}

	/**
	 * Prime only the selected page's posts and postmeta (not the full match).
	 *
	 * @param int[] $ids
	 */
	public static function prime_page( array $ids ): void {
		if ( [] !== $ids ) {
			_prime_post_caches( $ids, false, true );
		}
	}

	/**
	 * Batch-load Work-owned links for just the current page, preventing two
	 * additional SQL queries per Work Item during hydration.
	 *
	 * @param int[] $ids
	 * @return array{assignments:array<int,int[]>,relations:array<int,array<int,array<string,mixed>>>}
	 */
	public static function related_page( array $ids, bool $schema_ready ): array {
		$related = [ 'assignments' => [], 'relations' => [] ];
		if ( [] === $ids || ! $schema_ready ) {
			return $related;
		}
		global $wpdb;
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
		if ( [] === $ids ) {
			return $related;
		}
		$in = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
		$assignments = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT work_item_id, user_id FROM ' . Schema::assignments_table()
				. " WHERE work_item_id IN ({$in}) ORDER BY work_item_id ASC, user_id ASC",
				...$ids
			),
			ARRAY_A
		);
		foreach ( is_array( $assignments ) ? $assignments : [] as $row ) {
			$related['assignments'][ (int) $row['work_item_id'] ][] = (int) $row['user_id'];
		}

		$relations = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT work_item_id, id, provider, relation_type, external_id, created_at FROM ' . Schema::relations_table()
				. " WHERE work_item_id IN ({$in}) ORDER BY work_item_id ASC, id ASC",
				...$ids
			),
			ARRAY_A
		);
		foreach ( is_array( $relations ) ? $relations : [] as $row ) {
			$id = (int) $row['work_item_id'];
			unset( $row['work_item_id'] );
			$related['relations'][ $id ][] = $row;
		}
		return $related;
	}
}

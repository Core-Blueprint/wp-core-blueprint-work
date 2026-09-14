<?php
declare(strict_types=1);

namespace CB\Work\Repository;

use CB\Work\Content\PostTypes;
use CB\Work\Content\WorkItemMeta;
use CB\Work\Domain\WorkItemStatus;

defined( 'ABSPATH' ) || exit;

/**
 * Project-scoped Work Item summary for operational workspace composition.
 *
 * Read-only by design: canonical Work Item persistence remains owned by
 * WorkItems/WorkItemMeta and their governed mutation seams.
 */
final class ProjectWorkSummary {
	/** @return array<string,int> */
	public static function counts_by_status( int $project_id ): array {
		$counts = array_fill_keys( WorkItemStatus::all(), 0 );
		if ( $project_id <= 0 ) {
			return $counts;
		}

		$total      = WorkItems::count_for_project( $project_id );
		$classified = 0;
		foreach ( WorkItemStatus::all() as $status ) {
			if ( WorkItemStatus::PLANNED === $status ) {
				continue;
			}
			$counts[ $status ] = self::count_explicit_status( $project_id, $status );
			$classified       += $counts[ $status ];
		}

		// Canonical Work Item semantics treat missing/invalid status as planned.
		$counts[ WorkItemStatus::PLANNED ] = max( 0, $total - $classified );
		return $counts;
	}

	private static function count_explicit_status( int $project_id, string $status ): int {
		$query = new \WP_Query( [
			'post_type'              => PostTypes::WORK_ITEM,
			'post_status'            => [ 'publish', 'draft', 'pending', 'private', 'future' ],
			'posts_per_page'         => 1,
			'fields'                 => 'ids',
			'no_found_rows'          => false,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'meta_query'             => [
				'relation' => 'AND',
				[
					'key'   => WorkItemMeta::PROJECT_ID,
					'value' => $project_id,
					'type'  => 'NUMERIC',
				],
				[
					'key'   => WorkItemMeta::STATUS,
					'value' => $status,
				],
			],
		] );
		return max( 0, (int) $query->found_posts );
	}
}

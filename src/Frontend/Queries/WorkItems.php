<?php
declare(strict_types=1);

namespace CB\Work\Frontend\Queries;

use CB\Work\Frontend\Data\WorkItem;
use CB\Work\Repository\WorkItems as WorkItemRepository;

defined( 'ABSPATH' ) || exit;

final class WorkItems {
	private const MAX_CANDIDATES = 500;
	private const MAX_RESULTS    = 100;

	/**
	 * Frontend-safe subset of the canonical D2 Work Item query contract.
	 * Customer, billing and assignee filters are intentionally not exposed here.
	 *
	 * @param array<string,mixed> $args
	 * @return array{items:array<int,array<string,mixed>>,count:int,limit:int}
	 */
	public static function query( array $args = [] ): array {
		$limit       = self::limit( $args['limit'] ?? 30 );
		$include_ids = self::ids( $args['include_ids'] ?? [] );
		$criteria    = [
			'search'         => self::scalar( $args['search'] ?? '' ),
			'statuses'       => $args['statuses'] ?? [],
			'priorities'     => $args['priorities'] ?? [],
			'project_id'     => self::id( $args['project_id'] ?? 0 ),
			'service_id'     => self::id( $args['service_id'] ?? 0 ),
			'work_type_id'   => self::id( $args['work_type_id'] ?? 0 ),
			'scheduled_from' => self::scalar( $args['scheduled_from'] ?? '' ),
			'scheduled_to'   => self::scalar( $args['scheduled_to'] ?? '' ),
			'due_from'       => self::scalar( $args['due_from'] ?? '' ),
			'due_to'         => self::scalar( $args['due_to'] ?? '' ),
			'sort'           => self::scalar( $args['sort'] ?? 'workload' ),
			'page'           => 1,
			'per_page'       => self::MAX_CANDIDATES,
		];

		$result = WorkItemRepository::search( $criteria );
		$items  = [];
		foreach ( (array) ( $result['items'] ?? [] ) as $candidate ) {
			$id = absint( $candidate['id'] ?? 0 );
			if ( $id <= 0 || ( [] !== $include_ids && ! in_array( $id, $include_ids, true ) ) ) {
				continue;
			}
			$item = WorkItem::get( $id );
			if ( is_wp_error( $item ) ) {
				continue;
			}
			$items[] = $item;
			if ( count( $items ) >= $limit ) {
				break;
			}
		}

		return [ 'items' => $items, 'count' => count( $items ), 'limit' => $limit ];
	}

	private static function limit( mixed $value ): int {
		$value = is_scalar( $value ) ? absint( $value ) : 30;
		return max( 1, min( self::MAX_RESULTS, $value ?: 30 ) );
	}

	private static function id( mixed $value ): int {
		return is_scalar( $value ) ? absint( $value ) : 0;
	}

	private static function scalar( mixed $value ): string {
		return is_scalar( $value ) ? (string) $value : '';
	}

	/** @return int[] */
	private static function ids( mixed $raw ): array {
		$values = is_array( $raw ) ? $raw : ( is_scalar( $raw ) && '' !== trim( (string) $raw ) ? [ $raw ] : [] );
		$ids = [];
		foreach ( $values as $value ) {
			$id = is_scalar( $value ) ? absint( $value ) : 0;
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}
		return array_values( array_unique( $ids ) );
	}
}

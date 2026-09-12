<?php
declare(strict_types=1);

namespace CB\Work\PublicApi;

use CB\Work\Repository\WorkItemRelations;
use CB\Work\Repository\WorkItemSources;
use CB\Work\Repository\WorkItems as WorkItemRepository;

defined( 'ABSPATH' ) || exit;

/** Supported read-only Work Item contract for sibling integrations. */
final class WorkItems {
	/** @return array<string,mixed>|null */
	public static function get( int $work_item_id ): ?array {
		return WorkItemRepository::get( $work_item_id );
	}

	/** @return array<int,array<string,mixed>> */
	public static function all( int $limit = 100, array $statuses = [] ): array {
		return WorkItemRepository::all( $limit, $statuses );
	}

	/** @return array<string,mixed>|null */
	public static function get_by_source( string $provider, string $source_type, string $external_id ): ?array {
		$source = WorkItemSources::find( $provider, $source_type, $external_id );
		if ( null === $source || (int) $source['work_item_id'] <= 0 ) {
			return null;
		}
		return WorkItemRepository::get( (int) $source['work_item_id'] );
	}

	/** @return array<string,mixed>|null */
	public static function source( int $work_item_id ): ?array {
		return WorkItemSources::projection( WorkItemSources::for_work_item( $work_item_id ) );
	}

	/** @return array<int,array<string,mixed>> */
	public static function by_relation( string $provider, string $relation_type, string $external_id ): array {
		$items = [];
		foreach ( WorkItemRelations::find_work_item_ids( $provider, $relation_type, $external_id ) as $work_item_id ) {
			$item = WorkItemRepository::get( $work_item_id );
			if ( null !== $item ) {
				$items[] = $item;
			}
		}
		return $items;
	}
}

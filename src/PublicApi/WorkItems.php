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
		return self::project( WorkItemRepository::get( $work_item_id ) );
	}

	/** @return array<int,array<string,mixed>> */
	public static function all( int $limit = 100, array $statuses = [] ): array {
		$items = [];
		foreach ( WorkItemRepository::all( $limit, $statuses ) as $item ) {
			$projected = self::project( $item );
			if ( null !== $projected ) {
				$items[] = $projected;
			}
		}
		return $items;
	}

	/** @return array<string,mixed>|null */
	public static function get_by_source( string $provider, string $source_type, string $external_id ): ?array {
		$source = WorkItemSources::find( $provider, $source_type, $external_id );
		if ( null === $source || (int) $source['work_item_id'] <= 0 ) {
			return null;
		}
		return self::get( (int) $source['work_item_id'] );
	}

	/** @return array<string,mixed>|null */
	public static function source( int $work_item_id ): ?array {
		return WorkItemSources::projection( WorkItemSources::for_work_item( $work_item_id ) );
	}

	/** @return array<int,array<string,mixed>> */
	public static function by_relation( string $provider, string $relation_type, string $external_id ): array {
		if ( self::is_reserved_relation( $provider, $relation_type ) ) {
			return [];
		}

		$items = [];
		foreach ( WorkItemRelations::find_work_item_ids( $provider, $relation_type, $external_id ) as $work_item_id ) {
			$item = self::get( $work_item_id );
			if ( null !== $item ) {
				$items[] = $item;
			}
		}
		return $items;
	}

	/** @param array<string,mixed>|null $item @return array<string,mixed>|null */
	private static function project( ?array $item ): ?array {
		if ( null === $item ) {
			return null;
		}
		$item['relations'] = array_values( array_filter(
			(array) ( $item['relations'] ?? [] ),
			static fn ( mixed $relation ): bool => ! is_array( $relation ) || ! self::is_reserved_relation(
				(string) ( $relation['provider'] ?? '' ),
				(string) ( $relation['relation_type'] ?? '' )
			)
		) );
		return $item;
	}

	private static function is_reserved_relation( string $provider, string $relation_type ): bool {
		return WorkItemSources::RECOVERY_PROVIDER === sanitize_key( $provider )
			&& WorkItemSources::RECOVERY_TYPE === sanitize_key( $relation_type );
	}
}

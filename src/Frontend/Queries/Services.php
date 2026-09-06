<?php
declare(strict_types=1);

namespace CB\Work\Frontend\Queries;

use CB\Work\Frontend\Data\Service;
use CB\Work\PublicApi\Services as ServiceApi;

defined( 'ABSPATH' ) || exit;

final class Services {
	private const MAX_CANDIDATES = 250;
	private const MAX_RESULTS    = 100;

	/**
	 * @param array{search?:mixed,include_ids?:mixed,limit?:mixed} $args
	 * @return array{items:array<int,array<string,mixed>>,count:int,limit:int}
	 */
	public static function query( array $args = [] ): array {
		$limit       = self::limit( $args['limit'] ?? 30 );
		$search      = sanitize_text_field( trim( self::scalar( $args['search'] ?? '' ) ) );
		$include_ids = self::ids( $args['include_ids'] ?? [] );
		$items       = [];

		foreach ( ServiceApi::all( self::MAX_CANDIDATES, [ 'publish', 'draft', 'private', 'pending', 'future' ] ) as $candidate ) {
			$id = absint( $candidate['id'] ?? 0 );
			if ( $id <= 0 || ( [] !== $include_ids && ! in_array( $id, $include_ids, true ) ) ) {
				continue;
			}
			if ( '' !== $search && ! self::matches( $candidate, $search ) ) {
				continue;
			}
			$item = Service::get( $id );
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

	/** @param array<string,mixed> $candidate */
	private static function matches( array $candidate, string $search ): bool {
		$haystack = (string) ( $candidate['title'] ?? '' ) . ' ' . wp_strip_all_tags( (string) ( $candidate['description'] ?? '' ) );
		return false !== stripos( $haystack, $search );
	}

	private static function limit( mixed $value ): int {
		$value = is_scalar( $value ) ? absint( $value ) : 30;
		return max( 1, min( self::MAX_RESULTS, $value ?: 30 ) );
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

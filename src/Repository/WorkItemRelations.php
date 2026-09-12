<?php
declare(strict_types=1);

namespace CB\Work\Repository;

use CB\Work\Database\Schema;

defined( 'ABSPATH' ) || exit;

/** Provider-neutral reverse lookup for ordinary Work Item relations. */
final class WorkItemRelations {
	/** @return int[] */
	public static function find_work_item_ids( string $provider, string $relation_type, string $external_id, int $limit = 100 ): array {
		$relation = self::normalize( $provider, $relation_type, $external_id );
		if ( false === $relation || ! self::schema_ready() ) {
			return [];
		}
		$limit = max( 1, min( 500, $limit ) );

		global $wpdb;
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT work_item_id FROM ' . Schema::relations_table() . ' WHERE provider = %s AND relation_type = %s AND external_id = %s ORDER BY work_item_id ASC LIMIT %d',
				$relation['provider'],
				$relation['type'],
				$relation['id'],
				$limit
			)
		);
		if ( ! is_array( $ids ) ) {
			return [];
		}

		$resolved = [];
		foreach ( array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) ) as $work_item_id ) {
			if ( null !== WorkItems::get( $work_item_id ) ) {
				$resolved[] = $work_item_id;
			}
		}
		return $resolved;
	}

	/** @return array{provider:string,type:string,id:string}|false */
	public static function normalize( string $provider, string $relation_type, string $external_id ): array|false {
		$provider      = substr( sanitize_key( $provider ), 0, 64 );
		$relation_type = substr( sanitize_key( $relation_type ), 0, 64 );
		$external_id   = substr( sanitize_text_field( $external_id ), 0, 191 );
		if ( '' === $provider || '' === $relation_type || '' === $external_id ) {
			return false;
		}
		return [ 'provider' => $provider, 'type' => $relation_type, 'id' => $external_id ];
	}

	private static function schema_ready(): bool {
		return defined( 'CB_WORK_SCHEMA_VERSION' )
			&& (string) get_option( Schema::OPTION, '0' ) === (string) CB_WORK_SCHEMA_VERSION;
	}
}

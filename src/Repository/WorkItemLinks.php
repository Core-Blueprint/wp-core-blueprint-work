<?php
declare(strict_types=1);

namespace CB\Work\Repository;

use CB\Work\Content\PostTypes;
use CB\Work\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * B2.1: Work Item child-table persistence.
 * WorkItems remains the external and in-repository facade.
 */
final class WorkItemLinks {
	public static function assignments( int $work_item_id ): array {
		if ( ! self::schema_ready() || ! self::exists( $work_item_id ) ) {
			return [];
		}
		global $wpdb;
		$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT user_id FROM ' . Schema::assignments_table() . ' WHERE work_item_id = %d ORDER BY user_id ASC', $work_item_id ) );
		return is_array( $ids ) ? array_values( array_map( 'intval', $ids ) ) : [];
	}

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

	public static function replace_assignments( int $work_item_id, array $user_ids ): bool {
		if ( ! self::schema_ready() || ! self::exists( $work_item_id ) ) {
			return false;
		}
		$user_ids = self::normalize_assignments( $user_ids );
		if ( null === $user_ids ) {
			return false;
		}
		return WorkItemMutationTransaction::run(
			$work_item_id,
			static fn(): bool => self::write_assignments( $work_item_id, $user_ids )
		);
	}

	/**
	 * Low-level child-row writer: caller must hold the Work Item row lock.
	 * Never begins or commits a transaction independently.
	 *
	 * @param int[] $user_ids Already normalized and validated.
	 */
	public static function write_assignments( int $work_item_id, array $user_ids ): bool {
		global $wpdb;
		$deleted = $wpdb->delete( Schema::assignments_table(), [ 'work_item_id' => $work_item_id ], [ '%d' ] );
		if ( false === $deleted ) {
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
				return false;
			}
		}
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

	/** @return int[]|null */
	public static function normalize_assignments( mixed $raw ): ?array {
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

	private static function exists( int $work_item_id ): bool {
		$post = $work_item_id > 0 ? get_post( $work_item_id ) : null;
		return $post instanceof \WP_Post && PostTypes::WORK_ITEM === $post->post_type && 'trash' !== $post->post_status;
	}

	private static function schema_ready(): bool {
		return defined( 'CB_WORK_SCHEMA_VERSION' )
			&& CB_WORK_SCHEMA_VERSION === (string) get_option( Schema::OPTION, '0' );
	}
}

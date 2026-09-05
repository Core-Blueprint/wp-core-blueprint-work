<?php
declare(strict_types=1);

namespace CB\Work\Repository;

use CB\Work\Database\Schema;
defined( 'ABSPATH' ) || exit;

final class WorkTypes {
	/** @return array<int,array<string,mixed>> */
	public static function all( bool $include_inactive = false ): array {
		if ( ! self::schema_ready() ) {
			return [];
		}
		global $wpdb;
		$sql = 'SELECT * FROM ' . Schema::work_types_table();
		if ( ! $include_inactive ) {
			$sql .= ' WHERE is_active = 1';
		}
		$sql .= ' ORDER BY is_active DESC, sort_order ASC, label ASC, id ASC';
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		return is_array( $rows ) ? $rows : [];
	}

	/** @return array<string,mixed>|null */
	public static function get( int $id ): ?array {
		if ( ! self::schema_ready() || $id <= 0 ) {
			return null;
		}
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . Schema::work_types_table() . ' WHERE id = %d LIMIT 1', $id ),
			ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}

	/** @param array<string,mixed> $input */
	public static function create( array $input ): int {
		if ( ! self::schema_ready() ) {
			return 0;
		}
		global $wpdb;
		$label = sanitize_text_field( (string) ( $input['label'] ?? '' ) );
		$code  = substr( sanitize_title( (string) ( $input['code'] ?? $label ) ), 0, 64 );
		$order = max( 0, (int) ( $input['sort_order'] ?? 0 ) );
		if ( '' === $label || '' === $code ) {
			return 0;
		}
		$exists = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . Schema::work_types_table() . ' WHERE code = %s LIMIT 1', $code ) );
		if ( $exists ) {
			return 0;
		}
		$now = current_time( 'mysql', true );
		$ok = $wpdb->insert(
			Schema::work_types_table(),
			[
				'code'       => $code,
				'label'      => $label,
				'is_active'  => 1,
				'sort_order' => $order,
				'created_at' => $now,
				'updated_at' => $now,
			],
			[ '%s', '%s', '%d', '%d', '%s', '%s' ]
		);
		return false === $ok ? 0 : (int) $wpdb->insert_id;
	}

	public static function set_active( int $id, bool $active ): bool {
		if ( ! self::get( $id ) ) {
			return false;
		}
		global $wpdb;
		$updated = $wpdb->update(
			Schema::work_types_table(),
			[ 'is_active' => $active ? 1 : 0, 'updated_at' => current_time( 'mysql', true ) ],
			[ 'id' => $id ],
			[ '%d', '%s' ],
			[ '%d' ]
		);
		return false !== $updated;
	}

	private static function schema_ready(): bool {
		return defined( 'CB_WORK_SCHEMA_VERSION' )
			&& CB_WORK_SCHEMA_VERSION === (string) get_option( Schema::OPTION, '0' );
	}
}

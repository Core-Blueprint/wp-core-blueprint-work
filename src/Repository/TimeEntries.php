<?php
declare(strict_types=1);

namespace CB\Work\Repository;

use CB\Work\Database\Schema;
use CB\Work\Domain\TimeRange;

defined( 'ABSPATH' ) || exit;

final class TimeEntries {
	public const SOURCE_MANUAL = 'manual';
	public const SOURCE_TIMER  = 'timer';

	/** @return array<string,mixed>|null */
	public static function get( int $id ): ?array {
		if ( ! self::schema_ready() || $id <= 0 ) {
			return null;
		}
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . Schema::time_entries_table() . ' WHERE id = %d LIMIT 1', $id ),
			ARRAY_A
		);
		return is_array( $row ) ? self::hydrate( $row ) : null;
	}

	/** @return array<int,array<string,mixed>> */
	public static function all( int $limit = 200, int $user_id = 0, int $work_item_id = 0 ): array {
		if ( ! self::schema_ready() ) {
			return [];
		}
		global $wpdb;
		$limit = max( 1, min( 500, $limit ) );
		$where = [ 'ended_at IS NOT NULL' ];
		$args  = [];
		if ( $user_id > 0 ) {
			$where[] = 'user_id = %d';
			$args[]  = $user_id;
		}
		if ( $work_item_id > 0 ) {
			$where[] = 'work_item_id = %d';
			$args[]  = $work_item_id;
		}
		$sql = 'SELECT * FROM ' . Schema::time_entries_table() . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY started_at DESC, id DESC LIMIT ' . $limit;
		$rows = [] === $args ? $wpdb->get_results( $sql, ARRAY_A ) : $wpdb->get_results( $wpdb->prepare( $sql, ...$args ), ARRAY_A );
		return is_array( $rows ) ? array_values( array_map( [ self::class, 'hydrate' ], $rows ) ) : [];
	}

	public static function create_manual(
		int $work_item_id,
		int $user_id,
		string $started_at,
		string $ended_at,
		string $note = '',
		int $actor_user_id = 0
	): int {
		if ( ! self::schema_ready() || ! self::valid_context( $work_item_id, $user_id ) ) {
			return 0;
		}
		$duration = TimeRange::duration_seconds( $started_at, $ended_at );
		if ( null === $duration || $duration <= 0 || ! self::is_actual_end( $ended_at ) ) {
			return 0;
		}

		global $wpdb;
		$now = current_time( 'mysql', true );
		$ok = $wpdb->insert(
			Schema::time_entries_table(),
			[
				'work_item_id'     => $work_item_id,
				'user_id'          => $user_id,
				'entry_source'     => self::SOURCE_MANUAL,
				'started_at'       => $started_at,
				'ended_at'         => $ended_at,
				'duration_seconds' => $duration,
				'note'             => self::note( $note ),
				'revision'         => 1,
				'created_by'       => max( 0, $actor_user_id ),
				'updated_by'       => max( 0, $actor_user_id ),
				'created_at'       => $now,
				'updated_at'       => $now,
			],
			[ '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%d', '%s', '%s' ]
		);
		return false === $ok ? 0 : (int) $wpdb->insert_id;
	}

	/**
	 * Compare-and-swap correction for an already completed entry. A stale
	 * expected revision fails without overwriting the newer operator/user edit.
	 */
	public static function update_completed(
		int $id,
		int $expected_revision,
		int $work_item_id,
		int $user_id,
		string $started_at,
		string $ended_at,
		string $note,
		int $actor_user_id = 0
	): bool {
		if (
			! self::schema_ready()
			|| $id <= 0
			|| $expected_revision <= 0
			|| ! self::valid_context( $work_item_id, $user_id )
		) {
			return false;
		}
		$duration = TimeRange::duration_seconds( $started_at, $ended_at );
		if ( null === $duration || $duration <= 0 || ! self::is_actual_end( $ended_at ) ) {
			return false;
		}

		global $wpdb;
		$updated = $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . Schema::time_entries_table() . ' SET work_item_id = %d, user_id = %d, started_at = %s, ended_at = %s, duration_seconds = %d, note = %s, revision = revision + 1, updated_by = %d, updated_at = %s WHERE id = %d AND revision = %d AND ended_at IS NOT NULL',
				$work_item_id,
				$user_id,
				$started_at,
				$ended_at,
				$duration,
				self::note( $note ),
				max( 0, $actor_user_id ),
				current_time( 'mysql', true ),
				$id,
				$expected_revision
			)
		);
		return 1 === $updated;
	}

	public static function total_seconds_for_work_item( int $work_item_id ): int {
		if ( ! self::schema_ready() || $work_item_id <= 0 ) {
			return 0;
		}
		global $wpdb;
		$value = $wpdb->get_var(
			$wpdb->prepare( 'SELECT COALESCE(SUM(duration_seconds), 0) FROM ' . Schema::time_entries_table() . ' WHERE work_item_id = %d AND ended_at IS NOT NULL', $work_item_id )
		);
		return max( 0, (int) $value );
	}

	public static function total_seconds_for_user( int $user_id ): int {
		if ( ! self::schema_ready() || $user_id <= 0 ) {
			return 0;
		}
		global $wpdb;
		$value = $wpdb->get_var(
			$wpdb->prepare( 'SELECT COALESCE(SUM(duration_seconds), 0) FROM ' . Schema::time_entries_table() . ' WHERE user_id = %d AND ended_at IS NOT NULL', $user_id )
		);
		return max( 0, (int) $value );
	}

	private static function valid_context( int $work_item_id, int $user_id ): bool {
		return $work_item_id > 0
			&& null !== WorkItems::get( $work_item_id )
			&& $user_id > 0
			&& false !== get_userdata( $user_id );
	}

	/** Manual/corrected entries are actuals, never future planning records. */
	private static function is_actual_end( string $ended_at ): bool {
		return TimeRange::valid_utc( $ended_at )
			&& $ended_at <= current_time( 'mysql', true );
	}

	private static function note( string $note ): string {
		$note = sanitize_textarea_field( $note );
		return function_exists( 'mb_substr' ) ? mb_substr( $note, 0, 4000 ) : substr( $note, 0, 4000 );
	}

	/** @param array<string,mixed> $row @return array<string,mixed> */
	private static function hydrate( array $row ): array {
		return [
			'id'               => (int) $row['id'],
			'work_item_id'     => (int) $row['work_item_id'],
			'user_id'          => (int) $row['user_id'],
			'entry_source'     => (string) $row['entry_source'],
			'started_at'       => (string) $row['started_at'],
			'ended_at'         => null === $row['ended_at'] ? null : (string) $row['ended_at'],
			'duration_seconds' => max( 0, (int) $row['duration_seconds'] ),
			'note'             => (string) $row['note'],
			'revision'         => max( 1, (int) $row['revision'] ),
			'created_by'       => (int) $row['created_by'],
			'updated_by'       => (int) $row['updated_by'],
			'created_at'       => (string) $row['created_at'],
			'updated_at'       => (string) $row['updated_at'],
		];
	}

	private static function schema_ready(): bool {
		return defined( 'CB_WORK_SCHEMA_VERSION' )
			&& CB_WORK_SCHEMA_VERSION === (string) get_option( Schema::OPTION, '0' );
	}
}

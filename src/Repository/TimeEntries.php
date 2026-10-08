<?php
declare(strict_types=1);

namespace CB\Work\Repository;

use CB\Work\Database\Schema;
use CB\Work\Domain\TimeRange;
use CB\Work\Domain\TimeNote;

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


    /**
     * Server-paginated completed entries. All predicates also apply to the
     * summary count and duration, never just to the visible page.
     *
     * @param array<string,mixed> $criteria Validated TimeEntryListState data.
     * @return array{items:array<int,array<string,mixed>>,total:int,total_seconds:int,page:int,per_page:int,pages:int}
     */
    public static function query_completed( array $criteria ): array {
        $page = max( 1, (int) ( $criteria['page'] ?? 1 ) );
        $per_page = max( 1, min( 100, (int) ( $criteria['per_page'] ?? 25 ) ) );
        $empty = [ 'items' => [], 'total' => 0, 'total_seconds' => 0, 'page' => 1, 'per_page' => $per_page, 'pages' => 1 ];
        if ( ! self::schema_ready() ) {
            return $empty;
        }

        global $wpdb;
        $table = Schema::time_entries_table();
        $where = [ 'te.ended_at IS NOT NULL' ];
        $args = [];
        $user_id = (int) ( $criteria['user_id'] ?? 0 );
        if ( $user_id > 0 ) {
            $where[] = 'te.user_id = %d';
            $args[] = $user_id;
        }
        $source = (string) ( $criteria['source'] ?? '' );
        if ( in_array( $source, [ self::SOURCE_MANUAL, self::SOURCE_TIMER ], true ) ) {
            $where[] = 'te.entry_source = %s';
            $args[] = $source;
        }
        $from = (string) ( $criteria['from_utc'] ?? '' );
        $to = (string) ( $criteria['to_utc'] ?? '' );
        if ( '' !== $from && TimeRange::valid_utc( $from ) ) {
            $where[] = 'te.started_at >= %s';
            $args[] = $from;
        }
        if ( '' !== $to && TimeRange::valid_utc( $to ) ) {
            $where[] = 'te.started_at < %s';
            $args[] = $to;
        }
        if ( '' !== $from && '' !== $to && $from >= $to ) {
            return $empty;
        }

        $search = trim( (string) ( $criteria['search'] ?? '' ) );
        if ( '' !== $search ) {
            // Work Items are canonical WordPress CPT posts, not time-entry titles.
            // Correlated EXISTS prevents multiplying rows and total durations.
            $where[] = 'EXISTS (SELECT 1 FROM ' . $wpdb->posts . ' AS wi WHERE wi.ID = te.work_item_id AND wi.post_type = %s AND wi.post_title LIKE %s)';
            $args[] = \CB\Work\Content\PostTypes::WORK_ITEM;
            $args[] = '%' . $wpdb->esc_like( $search ) . '%';
        }

        $clause = implode( ' AND ', $where );
        $query = static fn( string $sql ): string => [] === $args ? $sql : $wpdb->prepare( $sql, ...$args );
        $summary = $wpdb->get_row(
            $query( "SELECT COUNT(*) AS total, COALESCE(SUM(te.duration_seconds), 0) AS total_seconds FROM {$table} AS te WHERE {$clause}" ),
            ARRAY_A
        );
        $total = max( 0, (int) ( $summary['total'] ?? 0 ) );
        $seconds = max( 0, (int) ( $summary['total_seconds'] ?? 0 ) );
        $pages = max( 1, (int) ceil( $total / $per_page ) );
        $page = min( $page, $pages );

        $sort_map = [
            'newest'   => 'te.started_at DESC, te.id DESC',
            'oldest'   => 'te.started_at ASC, te.id ASC',
            'longest'  => 'te.duration_seconds DESC, te.id DESC',
            'shortest' => 'te.duration_seconds ASC, te.id ASC',
            'source'   => 'te.entry_source ASC, te.started_at DESC, te.id DESC',
        ];
        $sort = (string) ( $criteria['sort'] ?? 'newest' );
        $order = $sort_map[ $sort ] ?? $sort_map['newest'];
        $offset = ( $page - 1 ) * $per_page;
        $sql = "SELECT te.* FROM {$table} AS te WHERE {$clause} ORDER BY {$order} LIMIT %d OFFSET %d";
        $rows = $wpdb->get_results(
            $wpdb->prepare( $sql, ...[ ...$args, $per_page, $offset ] ),
            ARRAY_A
        );
        return [
            'items'         => is_array( $rows ) ? array_values( array_map( [ self::class, 'hydrate' ], $rows ) ) : [],
            'total'         => $total,
            'total_seconds' => $seconds,
            'page'          => $page,
            'per_page'      => $per_page,
            'pages'         => $pages,
        ];
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
				'note'             => TimeNote::normalize( $note ),
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
				TimeNote::normalize( $note ),
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

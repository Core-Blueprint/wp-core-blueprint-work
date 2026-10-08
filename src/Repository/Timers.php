<?php
declare(strict_types=1);

namespace CB\Work\Repository;

use CB\Work\Database\Schema;
use CB\Work\Domain\TimeRange;
use CB\Work\Domain\TimeNote;

defined( 'ABSPATH' ) || exit;

final class Timers {
	/** @return array<string,mixed>|null */
	public static function active_for_user( int $user_id ): ?array {
		if ( ! self::schema_ready() || $user_id <= 0 ) {
			return null;
		}
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT t.user_id, t.time_entry_id, t.started_at, e.work_item_id, e.note FROM ' . Schema::active_timers_table() . ' t INNER JOIN ' . Schema::time_entries_table() . ' e ON e.id = t.time_entry_id WHERE t.user_id = %d LIMIT 1',
				$user_id
			),
			ARRAY_A
		);
		if ( ! is_array( $row ) ) {
			return null;
		}
		return [
			'user_id'       => (int) $row['user_id'],
			'time_entry_id' => (int) $row['time_entry_id'],
			'work_item_id'  => (int) $row['work_item_id'],
			'started_at'    => (string) $row['started_at'],
			'note'          => (string) $row['note'],
		];
	}

	public static function start( int $work_item_id, int $user_id, string $note = '', int $actor_user_id = 0 ): int {
		if (
			! self::schema_ready()
			|| $work_item_id <= 0
			|| null === WorkItems::get( $work_item_id )
			|| $user_id <= 0
			|| false === get_userdata( $user_id )
		) {
			return 0;
		}

		global $wpdb;
		$now  = current_time( 'mysql', true );
		$note = TimeNote::normalize( $note );
		$wpdb->query( 'START TRANSACTION' );

		$entry_ok = $wpdb->insert(
			Schema::time_entries_table(),
			[
				'work_item_id'     => $work_item_id,
				'user_id'          => $user_id,
				'entry_source'     => TimeEntries::SOURCE_TIMER,
				'started_at'       => $now,
				'ended_at'         => null,
				'duration_seconds' => 0,
				'note'             => $note,
				'revision'         => 1,
				'created_by'       => max( 0, $actor_user_id ),
				'updated_by'       => max( 0, $actor_user_id ),
				'created_at'       => $now,
				'updated_at'       => $now,
			],
			[ '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%d', '%s', '%s' ]
		);
		$entry_id = false === $entry_ok ? 0 : (int) $wpdb->insert_id;
		if ( $entry_id <= 0 ) {
			$wpdb->query( 'ROLLBACK' );
			return 0;
		}

		$timer_ok = $wpdb->insert(
			Schema::active_timers_table(),
			[
				'user_id'       => $user_id,
				'time_entry_id' => $entry_id,
				'started_at'    => $now,
				'created_at'    => $now,
			],
			[ '%d', '%d', '%s', '%s' ]
		);
		if ( false === $timer_ok ) {
			$wpdb->query( 'ROLLBACK' );
			return 0;
		}

		$wpdb->query( 'COMMIT' );
		return $entry_id;
	}

	/** @return array<string,mixed>|null Completed Time Entry or null on failure. */
	public static function stop( int $user_id, int $actor_user_id = 0 ): ?array {
		if ( ! self::schema_ready() || $user_id <= 0 ) {
			return null;
		}

		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );
		$timer = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . Schema::active_timers_table() . ' WHERE user_id = %d LIMIT 1 FOR UPDATE', $user_id ),
			ARRAY_A
		);
		if ( ! is_array( $timer ) ) {
			$wpdb->query( 'ROLLBACK' );
			return null;
		}

		$entry_id = (int) $timer['time_entry_id'];
		$entry = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . Schema::time_entries_table() . ' WHERE id = %d LIMIT 1 FOR UPDATE', $entry_id ),
			ARRAY_A
		);
		if (
			! is_array( $entry )
			|| (int) $entry['user_id'] !== $user_id
			|| null !== $entry['ended_at']
			|| TimeEntries::SOURCE_TIMER !== (string) $entry['entry_source']
		) {
			$wpdb->query( 'ROLLBACK' );
			return null;
		}

		$ended_at = current_time( 'mysql', true );
		$duration = TimeRange::duration_seconds( (string) $entry['started_at'], $ended_at );
		if ( null === $duration ) {
			$wpdb->query( 'ROLLBACK' );
			return null;
		}

		$updated = $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . Schema::time_entries_table() . ' SET ended_at = %s, duration_seconds = %d, revision = revision + 1, updated_by = %d, updated_at = %s WHERE id = %d AND ended_at IS NULL',
				$ended_at,
				$duration,
				max( 0, $actor_user_id ),
				$ended_at,
				$entry_id
			)
		);
		if ( 1 !== $updated ) {
			$wpdb->query( 'ROLLBACK' );
			return null;
		}

		$deleted = $wpdb->delete( Schema::active_timers_table(), [ 'user_id' => $user_id ], [ '%d' ] );
		if ( 1 !== $deleted ) {
			$wpdb->query( 'ROLLBACK' );
			return null;
		}

		$wpdb->query( 'COMMIT' );
		return TimeEntries::get( $entry_id );
	}


	/**
	 * Update the note of the current user's running timer without ending its entry.
	 * The active timer and its unfinished entry are locked to exclude concurrent stops.
	 */
	public static function update_active_note( int $user_id, int $entry_id, string $note, int $actor_user_id ): bool {
		if ( ! self::schema_ready() || $user_id <= 0 || $entry_id <= 0 || $actor_user_id !== $user_id ) {
			return false;
		}
		global $wpdb;
		$note = TimeNote::normalize( $note );
		$wpdb->query( 'START TRANSACTION' );
		$timer = $wpdb->get_row(
			$wpdb->prepare( 'SELECT time_entry_id FROM ' . Schema::active_timers_table() . ' WHERE user_id = %d LIMIT 1 FOR UPDATE', $user_id ),
			ARRAY_A
		);
		if ( ! is_array( $timer ) || (int) $timer['time_entry_id'] !== $entry_id ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}
		$entry = $wpdb->get_row(
			$wpdb->prepare( 'SELECT id, user_id, ended_at, entry_source, note, revision FROM ' . Schema::time_entries_table() . ' WHERE id = %d LIMIT 1 FOR UPDATE', $entry_id ),
			ARRAY_A
		);
		if ( ! is_array( $entry ) || (int) $entry['user_id'] !== $user_id || null !== $entry['ended_at'] || TimeEntries::SOURCE_TIMER !== (string) $entry['entry_source'] ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}
		if ( (string) $entry['note'] === $note ) {
			$wpdb->query( 'COMMIT' );
			return true;
		}
		$updated = $wpdb->update(
			Schema::time_entries_table(),
			[
				'note'       => $note,
				'updated_by' => $actor_user_id,
				'updated_at' => current_time( 'mysql', true ),
				'revision'   => 1 + (int) $entry['revision'],
			],
			[ 'id' => $entry_id, 'user_id' => $user_id, 'ended_at' => null ],
			[ '%s', '%d', '%s', '%d' ],
			[ '%d', '%d', '%s' ]
		);
		if ( 1 !== $updated ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}
		$wpdb->query( 'COMMIT' );
		return true;
	}

	private static function schema_ready(): bool {
		return defined( 'CB_WORK_SCHEMA_VERSION' )
			&& CB_WORK_SCHEMA_VERSION === (string) get_option( Schema::OPTION, '0' );
	}
}

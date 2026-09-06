<?php
declare(strict_types=1);

namespace CB\Work\Repository;

use CB\Work\Database\Schema;
use CB\Work\Domain\RecurrenceSchedule;

defined( 'ABSPATH' ) || exit;

final class RecurrenceOccurrences {
	public const SOURCE_PROVIDER = 'core-blueprint-work';
	public const SOURCE_TYPE     = 'recurrence_occurrence';

	/** @return array<string,mixed>|null */
	public static function get( int $id ): ?array {
		if ( ! self::schema_ready() || $id <= 0 ) {
			return null;
		}
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . Schema::recurrence_occurrences_table() . ' WHERE id = %d LIMIT 1', $id ),
			ARRAY_A
		);
		return is_array( $row ) ? self::hydrate( $row ) : null;
	}

	/** @return array<string,mixed>|null */
	public static function find( int $rule_id, string $occurrence_on ): ?array {
		if ( ! self::schema_ready() || $rule_id <= 0 || null === RecurrenceSchedule::add_days( $occurrence_on, 0 ) ) {
			return null;
		}
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . Schema::recurrence_occurrences_table() . ' WHERE rule_id = %d AND occurrence_on = %s LIMIT 1',
				$rule_id,
				$occurrence_on
			),
			ARRAY_A
		);
		return is_array( $row ) ? self::hydrate( $row ) : null;
	}

	public static function count_for_rule( int $rule_id ): int {
		if ( ! self::schema_ready() || $rule_id <= 0 ) {
			return 0;
		}
		global $wpdb;
		return max( 0, (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM ' . Schema::recurrence_occurrences_table() . ' WHERE rule_id = %d', $rule_id )
		) );
	}

	/**
	 * Atomically claims one ungenerated occurrence. A stale claim may be taken
	 * over after the bounded recovery timeout, allowing a later cron run to
	 * resume an interrupted generation safely.
	 */
	public static function claim( int $id, string $token, int $stale_after_seconds = 900 ): bool {
		if ( ! self::schema_ready() || $id <= 0 ) {
			return false;
		}
		$token = substr( sanitize_key( $token ), 0, 64 );
		if ( '' === $token ) {
			return false;
		}
		$stale_after_seconds = max( 60, min( DAY_IN_SECONDS, $stale_after_seconds ) );
		$now                 = current_time( 'mysql', true );
		$stale_before        = gmdate( 'Y-m-d H:i:s', time() - $stale_after_seconds );

		global $wpdb;
		$updated = $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . Schema::recurrence_occurrences_table() . " SET claim_token = %s, claimed_at = %s, attempt_count = attempt_count + 1, last_error = '' WHERE id = %d AND work_item_id = 0 AND (claim_token = '' OR claimed_at IS NULL OR claimed_at < %s)",
				$token,
				$now,
				$id,
				$stale_before
			)
		);
		return 1 === $updated;
	}

	public static function release( int $id, string $token, string $error = '' ): bool {
		if ( ! self::schema_ready() || $id <= 0 ) {
			return false;
		}
		$token = substr( sanitize_key( $token ), 0, 64 );
		$error = substr( sanitize_text_field( $error ), 0, 190 );
		if ( '' === $token ) {
			return false;
		}

		global $wpdb;
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE " . Schema::recurrence_occurrences_table() . " SET claim_token = '', claimed_at = NULL, last_error = %s WHERE id = %d AND work_item_id = 0 AND claim_token = %s",
				$error,
				$id,
				$token
			)
		);
		return false !== $updated;
	}

	/**
	 * Returns 0 when no linked Work Item exists, a positive Work Item ID for one
	 * unambiguous recurrence relation, and -1 if corrupted duplicate relations
	 * point multiple Work Items at the same occurrence.
	 */
	public static function find_work_item( int $occurrence_id ): int {
		if ( ! self::schema_ready() || $occurrence_id <= 0 ) {
			return 0;
		}
		global $wpdb;
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT work_item_id FROM ' . Schema::relations_table() . ' WHERE provider = %s AND relation_type = %s AND external_id = %s ORDER BY id ASC LIMIT 2',
				self::SOURCE_PROVIDER,
				self::SOURCE_TYPE,
				(string) $occurrence_id
			)
		);
		if ( ! is_array( $ids ) || [] === $ids ) {
			return 0;
		}
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
		if ( count( $ids ) > 1 ) {
			return -1;
		}
		$work_item_id = (int) ( $ids[0] ?? 0 );
		return $work_item_id > 0 && null !== WorkItems::get( $work_item_id ) ? $work_item_id : 0;
	}

	public static function attach_work_item( int $occurrence_id, int $work_item_id, string $token ): bool {
		$occurrence = self::get( $occurrence_id );
		if ( null === $occurrence || $work_item_id <= 0 ) {
			return false;
		}
		if ( $occurrence['work_item_id'] > 0 ) {
			return $occurrence['work_item_id'] === $work_item_id;
		}
		$token = substr( sanitize_key( $token ), 0, 64 );
		$item  = WorkItems::get( $work_item_id );
		if ( '' === $token || null === $item ) {
			return false;
		}

		$has_relation = false;
		foreach ( (array) ( $item['relations'] ?? [] ) as $relation ) {
			if (
				self::SOURCE_PROVIDER === (string) ( $relation['provider'] ?? '' )
				&& self::SOURCE_TYPE === (string) ( $relation['relation_type'] ?? '' )
				&& (string) $occurrence_id === (string) ( $relation['external_id'] ?? '' )
			) {
				$has_relation = true;
				break;
			}
		}
		if ( ! $has_relation ) {
			return false;
		}

		global $wpdb;
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE " . Schema::recurrence_occurrences_table() . " SET work_item_id = %d, generated_at = %s, claim_token = '', claimed_at = NULL, last_error = '' WHERE id = %d AND work_item_id = 0 AND claim_token = %s",
				$work_item_id,
				current_time( 'mysql', true ),
				$occurrence_id,
				$token
			)
		);
		return 1 === $updated;
	}

	/** @param array<string,mixed> $row @return array<string,mixed> */
	private static function hydrate( array $row ): array {
		return [
			'id'            => (int) $row['id'],
			'rule_id'       => (int) $row['rule_id'],
			'occurrence_on' => (string) $row['occurrence_on'],
			'work_item_id'  => (int) $row['work_item_id'],
			'claim_token'   => (string) ( $row['claim_token'] ?? '' ),
			'claimed_at'    => empty( $row['claimed_at'] ) ? null : (string) $row['claimed_at'],
			'attempt_count' => (int) ( $row['attempt_count'] ?? 0 ),
			'last_error'    => (string) ( $row['last_error'] ?? '' ),
			'created_at'    => (string) $row['created_at'],
			'generated_at'  => empty( $row['generated_at'] ) ? null : (string) $row['generated_at'],
		];
	}

	private static function schema_ready(): bool {
		return defined( 'CB_WORK_SCHEMA_VERSION' )
			&& CB_WORK_SCHEMA_VERSION === (string) get_option( Schema::OPTION, '0' );
	}
}

<?php
declare(strict_types=1);

namespace CB\Work\Repository;

use CB\Work\Database\Schema;
use CB\Work\Domain\BillingDisposition;
use CB\Work\Domain\RecurrenceSchedule;
use CB\Work\Domain\WorkItemPriority;
use CB\Work\PublicApi\Services;

defined( 'ABSPATH' ) || exit;

final class RecurrenceRules {
	/** @return array<int,array<string,mixed>> */
	public static function all( bool $active_only = false, int $limit = 250 ): array {
		if ( ! self::schema_ready() ) {
			return [];
		}
		global $wpdb;
		$limit = max( 1, min( 500, $limit ) );
		$sql   = 'SELECT * FROM ' . Schema::recurrence_rules_table();
		if ( $active_only ) {
			$sql .= ' WHERE is_active = 1';
		}
		$sql .= ' ORDER BY is_active DESC, next_occurrence_on IS NULL ASC, next_occurrence_on ASC, id ASC LIMIT ' . $limit;
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return [];
		}
		return array_values( array_map( [ self::class, 'hydrate_rule' ], $rows ) );
	}

	/** @return array<string,mixed>|null */
	public static function get( int $id ): ?array {
		if ( ! self::schema_ready() || $id <= 0 ) {
			return null;
		}
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . Schema::recurrence_rules_table() . ' WHERE id = %d LIMIT 1', $id ),
			ARRAY_A
		);
		return is_array( $row ) ? self::hydrate_rule( $row ) : null;
	}

	/** @param array<string,mixed> $input */
	public static function create( array $input ): int {
		if ( ! self::schema_ready() ) {
			return 0;
		}
		$normalized = self::normalize_write( $input );
		if ( null === $normalized ) {
			return 0;
		}

		global $wpdb;
		$now = current_time( 'mysql', true );
		$wpdb->query( 'START TRANSACTION' );
		$ok = $wpdb->insert(
			Schema::recurrence_rules_table(),
			self::rule_row( $normalized, $now, true ),
			self::rule_formats()
		);
		$rule_id = false === $ok ? 0 : (int) $wpdb->insert_id;
		if ( $rule_id <= 0 || ! self::replace_assignments( $rule_id, $normalized['assignments'], false ) ) {
			$wpdb->query( 'ROLLBACK' );
			return 0;
		}
		$wpdb->query( 'COMMIT' );
		return $rule_id;
	}

	/** @param array<string,mixed> $input */
	public static function update( int $id, array $input ): bool {
		$current = self::get( $id );
		if ( null === $current || ! self::schema_ready() ) {
			return false;
		}
		$normalized = self::normalize_write( $input, $current );
		if ( null === $normalized ) {
			return false;
		}

		global $wpdb;
		$now = current_time( 'mysql', true );
		$wpdb->query( 'START TRANSACTION' );
		$updated = $wpdb->update(
			Schema::recurrence_rules_table(),
			self::rule_row( $normalized, $now, false ),
			[ 'id' => $id ],
			self::rule_formats( false ),
			[ '%d' ]
		);
		if ( false === $updated ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}
		if ( $normalized['assignments_changed'] && ! self::replace_assignments( $id, $normalized['assignments'], false ) ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}
		$wpdb->query( 'COMMIT' );
		return true;
	}

	/** @return int[] */
	public static function assignments( int $rule_id ): array {
		if ( ! self::schema_ready() || $rule_id <= 0 ) {
			return [];
		}
		global $wpdb;
		$ids = $wpdb->get_col(
			$wpdb->prepare( 'SELECT user_id FROM ' . Schema::recurrence_assignments_table() . ' WHERE rule_id = %d ORDER BY user_id ASC', $rule_id )
		);
		return is_array( $ids ) ? array_values( array_map( 'intval', $ids ) ) : [];
	}

	/**
	 * Returns active rules whose next occurrence is inside that rule's own
	 * create-ahead window relative to the supplied canonical current date.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function due_for_generation( string $today_on, int $limit = 100 ): array {
		if ( ! self::schema_ready() || ! self::valid_date( $today_on ) ) {
			return [];
		}
		global $wpdb;
		$limit = max( 1, min( 500, $limit ) );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . Schema::recurrence_rules_table() . ' WHERE is_active = 1 AND next_occurrence_on IS NOT NULL AND next_occurrence_on <= DATE_ADD(%s, INTERVAL create_ahead_days DAY) ORDER BY next_occurrence_on ASC, id ASC LIMIT %d',
				$today_on,
				$limit
			),
			ARRAY_A
		);
		if ( ! is_array( $rows ) ) {
			return [];
		}
		return array_values( array_map( [ self::class, 'hydrate_rule' ], $rows ) );
	}

	/**
	 * Reserves only the rule's canonical next occurrence. The unique database
	 * key on (rule_id, occurrence_on) is the concurrency/idempotency boundary.
	 * A zero return means nothing was reserved by this caller.
	 */
	public static function reserve_next_occurrence( int $rule_id ): int {
		$rule = self::get( $rule_id );
		if ( null === $rule || ! $rule['is_active'] || null === $rule['next_occurrence_on'] ) {
			return 0;
		}
		global $wpdb;
		$ok = $wpdb->insert(
			Schema::recurrence_occurrences_table(),
			[
				'rule_id'       => $rule_id,
				'occurrence_on' => $rule['next_occurrence_on'],
				'work_item_id'  => 0,
				'created_at'    => current_time( 'mysql', true ),
				'generated_at'  => null,
			],
			[ '%d', '%s', '%d', '%s', '%s' ]
		);
		return false === $ok ? 0 : (int) $wpdb->insert_id;
	}

	/** @return array<string,mixed>|null */
	public static function occurrence( int $occurrence_id ): ?array {
		if ( ! self::schema_ready() || $occurrence_id <= 0 ) {
			return null;
		}
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . Schema::recurrence_occurrences_table() . ' WHERE id = %d LIMIT 1', $occurrence_id ),
			ARRAY_A
		);
		return is_array( $row ) ? self::hydrate_occurrence( $row ) : null;
	}

	/** @return array<int,array<string,mixed>> */
	public static function occurrences( int $rule_id, int $limit = 100 ): array {
		if ( ! self::schema_ready() || $rule_id <= 0 ) {
			return [];
		}
		global $wpdb;
		$limit = max( 1, min( 500, $limit ) );
		$rows = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM ' . Schema::recurrence_occurrences_table() . ' WHERE rule_id = %d ORDER BY occurrence_on DESC, id DESC LIMIT %d', $rule_id, $limit ),
			ARRAY_A
		);
		return is_array( $rows ) ? array_values( array_map( [ self::class, 'hydrate_occurrence' ], $rows ) ) : [];
	}

	/**
	 * Projects a reserved occurrence into the existing canonical Work Item
	 * create contract. It does not create the Work Item itself.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function occurrence_work_item_input( int $occurrence_id ): ?array {
		$occurrence = self::occurrence( $occurrence_id );
		if ( null === $occurrence || $occurrence['work_item_id'] > 0 ) {
			return null;
		}
		$rule = self::get( $occurrence['rule_id'] );
		if ( null === $rule ) {
			return null;
		}
		$due_on = RecurrenceSchedule::add_days( $occurrence['occurrence_on'], $rule['due_offset_days'] );
		if ( null === $due_on ) {
			return null;
		}
		return [
			'title'               => $rule['title'],
			'description'         => $rule['description'],
			'customer_provider'   => $rule['customer_provider'],
			'customer_type'       => $rule['customer_type'],
			'customer_id'         => $rule['customer_id'],
			'project_id'          => $rule['project_id'] ?? 0,
			'service_id'          => $rule['service_id'] ?? 0,
			'work_type_id'        => $rule['work_type_id'] ?? 0,
			'priority'            => $rule['priority'],
			'scheduled_on'        => $occurrence['occurrence_on'],
			'due_on'              => $due_on,
			'billing_disposition' => $rule['billing_disposition'],
			'assigned_user_ids'   => $rule['assigned_user_ids'],
			'source_provider'     => 'core-blueprint-work',
			'source_type'         => 'recurrence_occurrence',
			'source_id'           => (string) $occurrence_id,
		];
	}

	public static function attach_work_item( int $occurrence_id, int $work_item_id ): bool {
		$occurrence = self::occurrence( $occurrence_id );
		$item       = WorkItems::get( $work_item_id );
		if ( null === $occurrence || $occurrence['work_item_id'] > 0 || null === $item ) {
			return false;
		}

		$has_occurrence_relation = false;
		foreach ( (array) ( $item['relations'] ?? [] ) as $relation ) {
			if (
				'core-blueprint-work' === (string) ( $relation['provider'] ?? '' )
				&& 'recurrence_occurrence' === (string) ( $relation['relation_type'] ?? '' )
				&& (string) $occurrence_id === (string) ( $relation['external_id'] ?? '' )
			) {
				$has_occurrence_relation = true;
				break;
			}
		}
		if ( ! $has_occurrence_relation ) {
			return false;
		}

		global $wpdb;
		$updated = $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . Schema::recurrence_occurrences_table() . ' SET work_item_id = %d, generated_at = %s WHERE id = %d AND work_item_id = 0',
				$work_item_id,
				current_time( 'mysql', true ),
				$occurrence_id
			)
		);
		return 1 === $updated;
	}

	/**
	 * Advances only after the supplied occurrence has a generated canonical
	 * Work Item and is still the rule's current next occurrence. Finite rules
	 * end by storing NULL, never a sentinel date.
	 */
	public static function advance_after( int $rule_id, string $occurrence_on, int $actor_user_id = 0 ): bool {
		$rule = self::get( $rule_id );
		if ( null === $rule || null === $rule['next_occurrence_on'] || $rule['next_occurrence_on'] !== $occurrence_on ) {
			return false;
		}

		global $wpdb;
		$generated = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM ' . Schema::recurrence_occurrences_table() . ' WHERE rule_id = %d AND occurrence_on = %s AND work_item_id > 0 LIMIT 1',
				$rule_id,
				$occurrence_on
			)
		);
		if ( ! $generated ) {
			return false;
		}

		$next = RecurrenceSchedule::next_after(
			$rule['start_on'],
			$occurrence_on,
			$rule['frequency'],
			$rule['interval_count'],
			$rule['end_on']
		);
		$updated = $wpdb->update(
			Schema::recurrence_rules_table(),
			[
				'next_occurrence_on' => $next,
				'updated_by'         => max( 0, $actor_user_id ),
				'updated_at'         => current_time( 'mysql', true ),
			],
			[ 'id' => $rule_id, 'next_occurrence_on' => $occurrence_on ],
			[ '%s', '%d', '%s' ],
			[ '%d', '%s' ]
		);
		return 1 === $updated;
	}

	/** @param int[] $user_ids */
	private static function replace_assignments( int $rule_id, array $user_ids, bool $start_transaction = true ): bool {
		global $wpdb;
		if ( $start_transaction ) {
			$wpdb->query( 'START TRANSACTION' );
		}
		$deleted = $wpdb->delete( Schema::recurrence_assignments_table(), [ 'rule_id' => $rule_id ], [ '%d' ] );
		if ( false === $deleted ) {
			if ( $start_transaction ) {
				$wpdb->query( 'ROLLBACK' );
			}
			return false;
		}
		$now = current_time( 'mysql', true );
		foreach ( $user_ids as $user_id ) {
			$ok = $wpdb->insert(
				Schema::recurrence_assignments_table(),
				[ 'rule_id' => $rule_id, 'user_id' => $user_id, 'assigned_at' => $now ],
				[ '%d', '%d', '%s' ]
			);
			if ( false === $ok ) {
				if ( $start_transaction ) {
					$wpdb->query( 'ROLLBACK' );
				}
				return false;
			}
		}
		if ( $start_transaction ) {
			$wpdb->query( 'COMMIT' );
		}
		return true;
	}

	/**
	 * @param array<string,mixed>      $input
	 * @param array<string,mixed>|null $current
	 * @return array<string,mixed>|null
	 */
	private static function normalize_write( array $input, ?array $current = null ): ?array {
		$title        = sanitize_text_field( (string) ( $input['title'] ?? ( $current['title'] ?? '' ) ) );
		$description  = (string) ( $input['description'] ?? ( $current['description'] ?? '' ) );
		$customer     = self::customer_reference( $input, $current );
		$project_id   = max( 0, (int) ( $input['project_id'] ?? ( $current['project_id'] ?? 0 ) ) );
		$service_id   = max( 0, (int) ( $input['service_id'] ?? ( $current['service_id'] ?? 0 ) ) );
		$work_type_id = max( 0, (int) ( $input['work_type_id'] ?? ( $current['work_type_id'] ?? 0 ) ) );
		$priority     = sanitize_key( (string) ( $input['priority'] ?? ( $current['priority'] ?? WorkItemPriority::NORMAL ) ) );
		$billing      = sanitize_key( (string) ( $input['billing_disposition'] ?? ( $current['billing_disposition'] ?? '' ) ) );
		$frequency    = (string) ( $input['frequency'] ?? ( $current['frequency'] ?? '' ) );
		$interval     = (int) ( $input['interval_count'] ?? ( $current['interval_count'] ?? 1 ) );
		$start_on     = (string) ( $input['start_on'] ?? ( $current['start_on'] ?? '' ) );
		$end_raw      = array_key_exists( 'end_on', $input ) ? $input['end_on'] : ( $current['end_on'] ?? null );
		$end_on       = null === $end_raw ? null : (string) $end_raw;
		$schedule     = RecurrenceSchedule::normalize( $frequency, $interval, $start_on, $end_on );
		$create_ahead_days = (int) ( $input['create_ahead_days'] ?? ( $current['create_ahead_days'] ?? 14 ) );
		$due_offset_days   = (int) ( $input['due_offset_days'] ?? ( $current['due_offset_days'] ?? 0 ) );
		$is_active = array_key_exists( 'is_active', $input ) ? (bool) $input['is_active'] : (bool) ( $current['is_active'] ?? true );

		$assignments_changed = array_key_exists( 'assigned_user_ids', $input ) || null === $current;
		$assignments = $assignments_changed
			? self::normalize_assignments( $input['assigned_user_ids'] ?? [] )
			: (array) ( $current['assigned_user_ids'] ?? [] );

		if (
			'' === $title
			|| false === $customer
			|| null === $schedule
			|| null === $assignments
			|| ! WorkItemPriority::is_valid( $priority )
			|| ( '' !== $billing && ! BillingDisposition::is_valid( $billing ) )
			|| $create_ahead_days < 0
			|| $create_ahead_days > 3650
			|| $due_offset_days < 0
			|| $due_offset_days > 3650
		) {
			return null;
		}

		$project = null;
		if ( $project_id > 0 ) {
			$project = Projects::get( $project_id );
			if ( null === $project ) {
				return null;
			}
			if ( '' === $customer['provider'] && '' !== (string) ( $project['customer_provider'] ?? '' ) ) {
				$customer = [
					'provider' => (string) $project['customer_provider'],
					'type'     => (string) $project['customer_type'],
					'id'       => (string) $project['customer_id'],
				];
			}
		}
		if ( $service_id > 0 && null === Services::get( $service_id ) ) {
			return null;
		}
		if ( $work_type_id > 0 && null === WorkTypes::get( $work_type_id ) ) {
			return null;
		}

		$schedule_changed = null === $current
			|| $schedule['frequency'] !== (string) $current['frequency']
			|| $schedule['interval_count'] !== (int) $current['interval_count']
			|| $schedule['start_on'] !== (string) $current['start_on']
			|| $schedule['end_on'] !== $current['end_on'];
		if ( null !== $current && $schedule_changed && self::has_occurrences( (int) $current['id'] ) ) {
			return null;
		}
		$next_occurrence_on = $schedule_changed ? $schedule['start_on'] : $current['next_occurrence_on'];

		return [
			'title'               => $title,
			'description'         => $description,
			'customer_provider'   => $customer['provider'],
			'customer_type'       => $customer['type'],
			'customer_id'         => $customer['id'],
			'project_id'          => $project_id,
			'service_id'          => $service_id,
			'work_type_id'        => $work_type_id,
			'priority'            => $priority,
			'billing_disposition' => $billing,
			'frequency'            => $schedule['frequency'],
			'interval_count'       => $schedule['interval_count'],
			'start_on'             => $schedule['start_on'],
			'end_on'               => $schedule['end_on'],
			'next_occurrence_on'   => $next_occurrence_on,
			'create_ahead_days'    => $create_ahead_days,
			'due_offset_days'      => $due_offset_days,
			'is_active'            => $is_active,
			'created_by'           => max( 0, (int) ( $current['created_by'] ?? ( $input['created_by'] ?? get_current_user_id() ) ) ),
			'updated_by'           => max( 0, (int) ( $input['updated_by'] ?? get_current_user_id() ) ),
			'assignments'          => $assignments,
			'assignments_changed'  => $assignments_changed,
		];
	}

	/** @return array{provider:string,type:string,id:string}|false */
	private static function customer_reference( array $input, ?array $current ): array|false {
		$has_any = array_key_exists( 'customer_provider', $input )
			|| array_key_exists( 'customer_type', $input )
			|| array_key_exists( 'customer_id', $input );
		if ( ! $has_any && null !== $current ) {
			return [
				'provider' => (string) $current['customer_provider'],
				'type'     => (string) $current['customer_type'],
				'id'       => (string) $current['customer_id'],
			];
		}
		$provider = substr( sanitize_key( (string) ( $input['customer_provider'] ?? '' ) ), 0, 64 );
		$type     = substr( sanitize_key( (string) ( $input['customer_type'] ?? '' ) ), 0, 64 );
		$id       = substr( sanitize_text_field( (string) ( $input['customer_id'] ?? '' ) ), 0, 191 );
		if ( '' === $provider && '' === $type && '' === $id ) {
			return [ 'provider' => '', 'type' => '', 'id' => '' ];
		}
		return '' === $provider || '' === $type || '' === $id
			? false
			: [ 'provider' => $provider, 'type' => $type, 'id' => $id ];
	}

	/** @return int[]|null */
	private static function normalize_assignments( mixed $raw ): ?array {
		if ( is_scalar( $raw ) ) {
			$raw = '' === trim( (string) $raw ) ? [] : explode( ',', (string) $raw );
		}
		if ( ! is_array( $raw ) ) {
			return null;
		}
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $raw ) ) ) );
		foreach ( $ids as $user_id ) {
			if ( false === get_userdata( $user_id ) ) {
				return null;
			}
		}
		return $ids;
	}

	/** @param array<string,mixed> $normalized */
	private static function rule_row( array $normalized, string $now, bool $creating ): array {
		$row = [
			'title'               => $normalized['title'],
			'description'         => $normalized['description'],
			'customer_provider'   => $normalized['customer_provider'],
			'customer_type'       => $normalized['customer_type'],
			'customer_id'         => $normalized['customer_id'],
			'project_id'          => $normalized['project_id'],
			'service_id'          => $normalized['service_id'],
			'work_type_id'        => $normalized['work_type_id'],
			'priority'            => $normalized['priority'],
			'billing_disposition' => $normalized['billing_disposition'],
			'frequency'            => $normalized['frequency'],
			'interval_count'       => $normalized['interval_count'],
			'start_on'             => $normalized['start_on'],
			'end_on'               => $normalized['end_on'],
			'next_occurrence_on'   => $normalized['next_occurrence_on'],
			'create_ahead_days'    => $normalized['create_ahead_days'],
			'due_offset_days'      => $normalized['due_offset_days'],
			'is_active'            => $normalized['is_active'] ? 1 : 0,
			'updated_by'           => $normalized['updated_by'],
			'updated_at'           => $now,
		];
		if ( $creating ) {
			$row['created_by'] = $normalized['created_by'];
			$row['created_at'] = $now;
		}
		return $row;
	}

	/** @return string[] */
	private static function rule_formats( bool $creating = true ): array {
		$formats = [
			'%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s',
			'%s', '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s',
		];
		if ( $creating ) {
			$formats[] = '%d';
			$formats[] = '%s';
		}
		return $formats;
	}

	/** @param array<string,mixed> $row */
	private static function hydrate_rule( array $row ): array {
		$id = (int) $row['id'];
		return [
			'id'                  => $id,
			'title'               => (string) $row['title'],
			'description'         => (string) $row['description'],
			'customer_provider'   => (string) $row['customer_provider'],
			'customer_type'       => (string) $row['customer_type'],
			'customer_id'         => (string) $row['customer_id'],
			'project_id'          => (int) $row['project_id'] > 0 ? (int) $row['project_id'] : null,
			'service_id'          => (int) $row['service_id'] > 0 ? (int) $row['service_id'] : null,
			'work_type_id'        => (int) $row['work_type_id'] > 0 ? (int) $row['work_type_id'] : null,
			'priority'            => (string) $row['priority'],
			'billing_disposition' => (string) $row['billing_disposition'],
			'frequency'            => (string) $row['frequency'],
			'interval_count'       => (int) $row['interval_count'],
			'start_on'             => (string) $row['start_on'],
			'end_on'               => null === $row['end_on'] ? null : (string) $row['end_on'],
			'next_occurrence_on'   => null === $row['next_occurrence_on'] ? null : (string) $row['next_occurrence_on'],
			'create_ahead_days'    => (int) $row['create_ahead_days'],
			'due_offset_days'      => (int) $row['due_offset_days'],
			'is_active'            => (bool) $row['is_active'],
			'created_by'           => (int) $row['created_by'],
			'updated_by'           => (int) $row['updated_by'],
			'created_at'           => (string) $row['created_at'],
			'updated_at'           => (string) $row['updated_at'],
			'assigned_user_ids'   => self::assignments( $id ),
		];
	}

	/** @param array<string,mixed> $row */
	private static function hydrate_occurrence( array $row ): array {
		return [
			'id'            => (int) $row['id'],
			'rule_id'       => (int) $row['rule_id'],
			'occurrence_on' => (string) $row['occurrence_on'],
			'work_item_id'  => (int) $row['work_item_id'],
			'created_at'    => (string) $row['created_at'],
			'generated_at'  => null === $row['generated_at'] ? null : (string) $row['generated_at'],
		];
	}

	private static function has_occurrences( int $rule_id ): bool {
		if ( ! self::schema_ready() || $rule_id <= 0 ) {
			return false;
		}
		global $wpdb;
		$exists = $wpdb->get_var(
			$wpdb->prepare( 'SELECT id FROM ' . Schema::recurrence_occurrences_table() . ' WHERE rule_id = %d LIMIT 1', $rule_id )
		);
		return (bool) $exists;
	}

	private static function valid_date( string $value ): bool {
		return null !== RecurrenceSchedule::add_days( $value, 0 );
	}

	private static function schema_ready(): bool {
		return defined( 'CB_WORK_SCHEMA_VERSION' )
			&& CB_WORK_SCHEMA_VERSION === (string) get_option( Schema::OPTION, '0' );
	}
}

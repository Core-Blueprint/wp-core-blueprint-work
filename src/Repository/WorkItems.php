<?php
declare(strict_types=1);

namespace CB\Work\Repository;

use CB\Work\Database\Schema;
use CB\Work\Domain\BillingDisposition;
use CB\Work\Domain\WorkItemPriority;
use CB\Work\Domain\WorkItemStatus;
use CB\Work\PublicApi\Services;

defined( 'ABSPATH' ) || exit;

final class WorkItems {
	/** @return array<int,array<string,mixed>> */
	public static function all( int $limit = 100, array $statuses = [] ): array {
		if ( ! self::schema_ready() ) {
			return [];
		}
		global $wpdb;
		$limit = max( 1, min( 500, $limit ) );
		$statuses = array_values( array_filter( array_map( 'sanitize_key', $statuses ), [ WorkItemStatus::class, 'is_valid' ] ) );
		$sql = 'SELECT * FROM ' . Schema::work_items_table();
		$args = [];
		if ( [] !== $statuses ) {
			$sql .= ' WHERE status IN (' . implode( ',', array_fill( 0, count( $statuses ), '%s' ) ) . ')';
			$args = $statuses;
		}
		$sql .= " ORDER BY CASE status WHEN 'in_progress' THEN 0 WHEN 'planned' THEN 1 ELSE 2 END, COALESCE(due_on, '9999-12-31') ASC, id DESC LIMIT %d";
		$args[] = $limit;
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$args ), ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return [];
		}
		return array_values( array_map( [ self::class, 'hydrate' ], $rows ) );
	}

	/** @return array<string,mixed>|null */
	public static function get( int $id ): ?array {
		if ( ! self::schema_ready() || $id <= 0 ) {
			return null;
		}
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . Schema::work_items_table() . ' WHERE id = %d LIMIT 1', $id ),
			ARRAY_A
		);
		return is_array( $row ) ? self::hydrate( $row ) : null;
	}

	public static function count(): int {
		if ( ! self::schema_ready() ) {
			return 0;
		}
		global $wpdb;
		return max( 0, (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Schema::work_items_table() ) );
	}

	public static function count_for_project( int $project_id ): int {
		if ( ! self::schema_ready() || $project_id <= 0 ) {
			return 0;
		}
		global $wpdb;
		return max( 0, (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Schema::work_items_table() . ' WHERE project_id = %d', $project_id ) ) );
	}

	/** @return array<string,int> */
	public static function counts_by_status(): array {
		$counts = array_fill_keys( WorkItemStatus::all(), 0 );
		if ( ! self::schema_ready() ) {
			return $counts;
		}
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT status, COUNT(*) AS total FROM ' . Schema::work_items_table() . ' GROUP BY status', ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return $counts;
		}
		foreach ( $rows as $row ) {
			$status = (string) ( $row['status'] ?? '' );
			if ( WorkItemStatus::is_valid( $status ) ) {
				$counts[ $status ] = max( 0, (int) ( $row['total'] ?? 0 ) );
			}
		}
		return $counts;
	}

	/** @param array<string,mixed> $input */
	public static function create( array $input ): int {
		if ( ! self::schema_ready() ) {
			return 0;
		}
		global $wpdb;

		$title        = sanitize_text_field( (string) ( $input['title'] ?? '' ) );
		$description  = sanitize_textarea_field( (string) ( $input['description'] ?? '' ) );
		$reference    = self::reference( $input, 'customer_' );
		$project_id   = max( 0, (int) ( $input['project_id'] ?? 0 ) );
		$service_id   = max( 0, (int) ( $input['service_id'] ?? 0 ) );
		$work_type_id = max( 0, (int) ( $input['work_type_id'] ?? 0 ) );
		$priority     = sanitize_key( (string) ( $input['priority'] ?? WorkItemPriority::NORMAL ) );
		$scheduled_on = self::date( (string) ( $input['scheduled_on'] ?? '' ) );
		$due_on       = self::date( (string) ( $input['due_on'] ?? '' ) );
		$billing      = sanitize_key( (string) ( $input['billing_disposition'] ?? '' ) );
		$created_by   = max( 0, (int) ( $input['created_by'] ?? 0 ) );
		$assignments  = self::normalize_assignments( $input['assigned_user_ids'] ?? [] );
		$relation     = self::reference( $input, 'source_' );

		if ( '' === $title || false === $reference || false === $relation || null === $assignments ) {
			return 0;
		}
		if ( $project_id > 0 && null === Projects::get( $project_id ) ) {
			return 0;
		}
		if ( $service_id > 0 && null === Services::get( $service_id ) ) {
			return 0;
		}
		if ( $work_type_id > 0 && null === WorkTypes::get( $work_type_id ) ) {
			return 0;
		}
		if ( ! WorkItemPriority::is_valid( $priority ) ) {
			return 0;
		}
		if ( '' !== $billing && ! BillingDisposition::is_valid( $billing ) ) {
			return 0;
		}
		if ( null !== $scheduled_on && null !== $due_on && $due_on < $scheduled_on ) {
			return 0;
		}

		$now = current_time( 'mysql', true );
		$wpdb->query( 'START TRANSACTION' );
		$ok = $wpdb->insert(
			Schema::work_items_table(),
			[
				'title'               => $title,
				'description'         => $description,
				'customer_provider'   => $reference['provider'],
				'customer_type'       => $reference['type'],
				'customer_id'         => $reference['id'],
				'project_id'          => $project_id > 0 ? $project_id : null,
				'service_id'          => $service_id > 0 ? $service_id : null,
				'work_type_id'        => $work_type_id > 0 ? $work_type_id : null,
				'priority'            => $priority,
				'scheduled_on'        => $scheduled_on,
				'due_on'              => $due_on,
				'status'              => WorkItemStatus::PLANNED,
				'billing_disposition' => $billing,
				'completed_at'        => null,
				'completed_by'        => null,
				'created_by'          => $created_by,
				'created_at'          => $now,
				'updated_at'          => $now,
			],
			[ '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' ]
		);
		if ( false === $ok ) {
			$wpdb->query( 'ROLLBACK' );
			return 0;
		}

		$id = (int) $wpdb->insert_id;
		if ( ! self::replace_assignments( $id, $assignments ) ) {
			$wpdb->query( 'ROLLBACK' );
			return 0;
		}
		if ( '' !== $relation['provider'] && ! self::add_relation( $id, $relation['provider'], $relation['type'], $relation['id'] ) ) {
			$wpdb->query( 'ROLLBACK' );
			return 0;
		}
		$wpdb->query( 'COMMIT' );
		do_action( 'cb_work_work_item_created', $id, self::get( $id ) );
		return $id;
	}

	public static function transition_status( int $id, string $to, int $actor_user_id = 0 ): bool {
		$item = self::get( $id );
		$to = sanitize_key( $to );
		if ( null === $item || ! WorkItemStatus::is_valid( $to ) ) {
			return false;
		}
		$from = (string) $item['status'];
		if ( ! WorkItemStatus::can_transition( $from, $to ) ) {
			return false;
		}
		global $wpdb;
		$data = [
			'status'     => $to,
			'updated_at' => current_time( 'mysql', true ),
		];
		$formats = [ '%s', '%s' ];
		if ( WorkItemStatus::COMPLETED === $to ) {
			$data['completed_at'] = current_time( 'mysql', true );
			$data['completed_by'] = max( 0, $actor_user_id );
			$formats[] = '%s';
			$formats[] = '%d';
		}
		$updated = $wpdb->update( Schema::work_items_table(), $data, [ 'id' => $id ], $formats, [ '%d' ] );
		if ( false === $updated ) {
			return false;
		}
		do_action( 'cb_work_work_item_status_changed', $id, $from, $to, self::get( $id ) );
		return true;
	}

	/** @return int[] */
	public static function assignments( int $work_item_id ): array {
		if ( ! self::schema_ready() || $work_item_id <= 0 ) {
			return [];
		}
		global $wpdb;
		$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT user_id FROM ' . Schema::assignments_table() . ' WHERE work_item_id = %d ORDER BY user_id ASC', $work_item_id ) );
		return is_array( $ids ) ? array_values( array_map( 'intval', $ids ) ) : [];
	}

	/** @return array<int,array<string,mixed>> */
	public static function relations( int $work_item_id ): array {
		if ( ! self::schema_ready() || $work_item_id <= 0 ) {
			return [];
		}
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare( 'SELECT id, provider, relation_type, external_id, created_at FROM ' . Schema::relations_table() . ' WHERE work_item_id = %d ORDER BY id ASC', $work_item_id ),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : [];
	}

	/** @param int[] $user_ids */
	public static function replace_assignments( int $work_item_id, array $user_ids ): bool {
		if ( ! self::schema_ready() || $work_item_id <= 0 ) {
			return false;
		}
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
		if ( ! self::schema_ready() || $work_item_id <= 0 ) {
			return false;
		}
		$provider = substr( sanitize_key( $provider ), 0, 64 );
		$relation_type = substr( sanitize_key( $relation_type ), 0, 64 );
		$external_id = substr( sanitize_text_field( $external_id ), 0, 191 );
		if ( '' === $provider || '' === $relation_type || '' === $external_id ) {
			return false;
		}
		global $wpdb;
		$ok = $wpdb->insert(
			Schema::relations_table(),
			[
				'work_item_id' => $work_item_id,
				'provider' => $provider,
				'relation_type' => $relation_type,
				'external_id' => $external_id,
				'created_at' => current_time( 'mysql', true ),
			],
			[ '%d', '%s', '%s', '%s', '%s' ]
		);
		return false !== $ok;
	}

	/** @param array<string,mixed> $row @return array<string,mixed> */
	private static function hydrate( array $row ): array {
		$id = (int) ( $row['id'] ?? 0 );
		$row['id'] = $id;
		$row['project_id'] = null === ( $row['project_id'] ?? null ) ? null : (int) $row['project_id'];
		$row['service_id'] = null === ( $row['service_id'] ?? null ) ? null : (int) $row['service_id'];
		$row['work_type_id'] = null === ( $row['work_type_id'] ?? null ) ? null : (int) $row['work_type_id'];
		$row['completed_by'] = null === ( $row['completed_by'] ?? null ) ? null : (int) $row['completed_by'];
		$row['created_by'] = (int) ( $row['created_by'] ?? 0 );
		$row['assigned_user_ids'] = self::assignments( $id );
		$row['relations'] = self::relations( $id );
		return $row;
	}

	/** @param array<string,mixed> $input @return array{provider:string,type:string,id:string}|false */
	private static function reference( array $input, string $prefix ): array|false {
		$provider = substr( sanitize_key( (string) ( $input[ $prefix . 'provider' ] ?? '' ) ), 0, 64 );
		$type     = substr( sanitize_key( (string) ( $input[ $prefix . 'type' ] ?? '' ) ), 0, 64 );
		$id       = substr( sanitize_text_field( (string) ( $input[ $prefix . 'id' ] ?? '' ) ), 0, 191 );
		if ( '' === $provider && '' === $type && '' === $id ) {
			return [ 'provider' => '', 'type' => '', 'id' => '' ];
		}
		if ( '' === $provider || '' === $type || '' === $id ) {
			return false;
		}
		return [ 'provider' => $provider, 'type' => $type, 'id' => $id ];
	}

	/** @return int[]|null */
	private static function normalize_assignments( mixed $raw ): ?array {
		if ( ! is_array( $raw ) ) {
			$raw = [];
		}
		$user_ids = array_values( array_unique( array_filter( array_map( 'absint', $raw ) ) ) );
		foreach ( $user_ids as $user_id ) {
			if ( false === get_userdata( $user_id ) ) {
				return null;
			}
		}
		return $user_ids;
	}

	private static function date( string $value ): ?string {
		$value = trim( $value );
		if ( '' === $value ) {
			return null;
		}
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $value );
		return $date && $date->format( 'Y-m-d' ) === $value ? $value : null;
	}

	private static function schema_ready(): bool {
		return defined( 'CB_WORK_SCHEMA_VERSION' )
			&& CB_WORK_SCHEMA_VERSION === (string) get_option( Schema::OPTION, '0' );
	}
}

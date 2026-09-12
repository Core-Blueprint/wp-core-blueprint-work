<?php
declare(strict_types=1);

namespace CB\Work\Repository;

use CB\Work\Database\Schema;
use CB\Work\Domain\BillingUnit;

defined( 'ABSPATH' ) || exit;

final class BillingUnits {
	public static function find( string $unit_type, int $unit_id ): ?array {
		$unit_type = sanitize_key( $unit_type );
		if ( ! BillingUnit::is_type( $unit_type ) || $unit_id <= 0 || ! self::schema_ready() ) {
			return null;
		}
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . Schema::billing_units_table() . ' WHERE unit_type = %s AND unit_id = %d LIMIT 1', $unit_type, $unit_id ),
			ARRAY_A
		);
		return is_array( $row ) ? self::hydrate_unit( $row ) : null;
	}

	public static function get( int $billing_unit_id ): ?array {
		if ( $billing_unit_id <= 0 || ! self::schema_ready() ) { return null; }
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Schema::billing_units_table() . ' WHERE id = %d LIMIT 1', $billing_unit_id ), ARRAY_A );
		return is_array( $row ) ? self::hydrate_unit( $row ) : null;
	}

	public static function snapshot( int $snapshot_id ): ?array {
		if ( $snapshot_id <= 0 || ! self::schema_ready() ) { return null; }
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Schema::billing_snapshots_table() . ' WHERE id = %d LIMIT 1', $snapshot_id ), ARRAY_A );
		return is_array( $row ) ? self::hydrate_snapshot( $row ) : null;
	}

	public static function current_snapshot_for_unit( array $unit ): ?array {
		return (int) ( $unit['current_snapshot_id'] ?? 0 ) > 0 ? self::snapshot( (int) $unit['current_snapshot_id'] ) : null;
	}

	/** @return array<int,array<string,mixed>> */
	public static function snapshots( int $billing_unit_id, int $limit = 50 ): array {
		if ( $billing_unit_id <= 0 || ! self::schema_ready() ) { return []; }
		$limit = max( 1, min( 100, $limit ) );
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM ' . Schema::billing_snapshots_table() . ' WHERE billing_unit_id = %d ORDER BY snapshot_version DESC LIMIT ' . $limit, $billing_unit_id ),
			ARRAY_A
		);
		return is_array( $rows ) ? array_values( array_map( [ self::class, 'hydrate_snapshot' ], $rows ) ) : [];
	}

	/** @return array<int,array<string,mixed>> */
	public static function references( int $billing_unit_id ): array {
		if ( $billing_unit_id <= 0 || ! self::schema_ready() ) { return []; }
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM ' . Schema::billing_external_refs_table() . ' WHERE billing_unit_id = %d ORDER BY id ASC', $billing_unit_id ),
			ARRAY_A
		);
		return is_array( $rows ) ? array_values( array_map( [ self::class, 'hydrate_reference' ], $rows ) ) : [];
	}

	/** @return array<int,array<string,mixed>> */
	public static function ready( int $limit = 100 ): array {
		if ( ! self::schema_ready() ) { return []; }
		$limit = max( 1, min( 250, $limit ) );
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM ' . Schema::billing_units_table() . ' WHERE status = %s ORDER BY ready_at ASC, id ASC LIMIT ' . $limit, BillingUnit::READY ),
			ARRAY_A
		);
		return is_array( $rows ) ? array_values( array_map( [ self::class, 'hydrate_unit' ], $rows ) ) : [];
	}

	/**
	 * Creates or refreshes readiness under a database-unique source-unit lock.
	 * Concurrent first prepares converge on the same billing unit identity.
	 *
	 * @param array<string,mixed> $candidate
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function prepare( array $candidate, int $actor_user_id ): array|\WP_Error {
		if ( ! self::schema_ready() ) { return new \WP_Error( 'work_billing_schema_unavailable' ); }
		$payload_json = wp_json_encode( $candidate['payload'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false === $payload_json ) { return new \WP_Error( 'work_billing_snapshot_encode_failed' ); }

		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );
		$now = current_time( 'mysql', true );
		$identity_write = $wpdb->query(
			$wpdb->prepare(
				'INSERT INTO ' . Schema::billing_units_table() . " (unit_type, unit_id, work_item_id, status, current_snapshot_id, ready_at, linked_at, created_at, updated_at) VALUES (%s, %d, %d, %s, 0, %s, NULL, %s, %s) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)",
				$candidate['unit_type'], $candidate['unit_id'], $candidate['work_item_id'], BillingUnit::READY, $now, $now, $now
			)
		);
		if ( false === $identity_write ) { $wpdb->query( 'ROLLBACK' ); return new \WP_Error( 'work_billing_prepare_failed' ); }
		$billing_unit_id = (int) $wpdb->insert_id;
		if ( $billing_unit_id <= 0 ) { $wpdb->query( 'ROLLBACK' ); return new \WP_Error( 'work_billing_prepare_failed' ); }

		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . Schema::billing_units_table() . ' WHERE id = %d LIMIT 1 FOR UPDATE', $billing_unit_id ),
			ARRAY_A
		);
		if ( ! is_array( $row ) ) { $wpdb->query( 'ROLLBACK' ); return new \WP_Error( 'work_billing_prepare_failed' ); }
		$unit = self::hydrate_unit( $row );
		$current = (int) $unit['current_snapshot_id'] > 0 ? self::snapshot_for_update( (int) $unit['current_snapshot_id'] ) : null;
		if ( is_array( $current ) && hash_equals( (string) $current['fingerprint'], (string) $candidate['fingerprint'] ) ) {
			$wpdb->query( 'COMMIT' );
			return [ 'outcome' => 'reused', 'unit' => $unit, 'snapshot' => $current ];
		}
		if ( BillingUnit::EXTERNALLY_LINKED === $unit['status'] ) { $wpdb->query( 'ROLLBACK' ); return new \WP_Error( 'work_billing_linked_source_changed' ); }

		$is_first_snapshot = null === $current;
		$version = $is_first_snapshot ? 1 : self::next_snapshot_version( $billing_unit_id );
		$outcome = $is_first_snapshot ? 'created' : 'refreshed';
		$ok = $wpdb->insert(
			Schema::billing_snapshots_table(),
			[
				'billing_unit_id' => $billing_unit_id, 'snapshot_version' => $version, 'unit_type' => $candidate['unit_type'],
				'unit_id' => $candidate['unit_id'], 'work_item_id' => $candidate['work_item_id'], 'source_revision' => $candidate['source_revision'],
				'fingerprint' => $candidate['fingerprint'], 'payload' => $payload_json, 'created_by' => max( 0, $actor_user_id ), 'created_at' => $now,
			],
			[ '%d', '%d', '%s', '%d', '%d', '%d', '%s', '%s', '%d', '%s' ]
		);
		if ( false === $ok ) { $wpdb->query( 'ROLLBACK' ); return new \WP_Error( 'work_billing_snapshot_create_failed' ); }
		$snapshot_id = (int) $wpdb->insert_id;
		$updated = $wpdb->update(
			Schema::billing_units_table(),
			[ 'work_item_id' => $candidate['work_item_id'], 'status' => BillingUnit::READY, 'current_snapshot_id' => $snapshot_id, 'ready_at' => $now, 'linked_at' => null, 'updated_at' => $now ],
			[ 'id' => $billing_unit_id ],
			[ '%d', '%s', '%d', '%s', '%s', '%s' ], [ '%d' ]
		);
		if ( false === $updated ) { $wpdb->query( 'ROLLBACK' ); return new \WP_Error( 'work_billing_prepare_failed' ); }
		$wpdb->query( 'COMMIT' );
		$unit = self::get( $billing_unit_id ); $snapshot = self::snapshot( $snapshot_id );
		return null === $unit || null === $snapshot ? new \WP_Error( 'work_billing_resource_unavailable' ) : [ 'outcome' => $outcome, 'unit' => $unit, 'snapshot' => $snapshot ];
	}

	/** @return array<string,mixed>|\WP_Error */
	public static function link_external_reference( string $unit_type, int $unit_id, string $expected_fingerprint, string $provider, string $resource_type, string $resource_id, string $display_reference, string $status_projection ): array|\WP_Error {
		$reference = self::normalize_reference( $provider, $resource_type, $resource_id, $display_reference, $status_projection );
		if ( false === $reference || ! self::schema_ready() ) { return new \WP_Error( 'work_billing_reference_invalid' ); }
		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . Schema::billing_units_table() . ' WHERE unit_type = %s AND unit_id = %d LIMIT 1 FOR UPDATE', sanitize_key( $unit_type ), $unit_id ),
			ARRAY_A
		);
		if ( ! is_array( $row ) ) { $wpdb->query( 'ROLLBACK' ); return new \WP_Error( 'work_billing_not_ready' ); }
		$unit = self::hydrate_unit( $row );
		$current = (int) $unit['current_snapshot_id'] > 0 ? self::snapshot_for_update( (int) $unit['current_snapshot_id'] ) : null;
		if ( ! is_array( $current ) || ! hash_equals( (string) $current['fingerprint'], $expected_fingerprint ) ) { $wpdb->query( 'ROLLBACK' ); return new \WP_Error( 'work_billing_snapshot_stale' ); }
		$existing = self::reference_for_update( (int) $unit['id'], $reference['provider'], $reference['resource_type'], $reference['resource_id'] );
		$now = current_time( 'mysql', true );
		if ( is_array( $existing ) ) {
			$reference_updated = $wpdb->update( Schema::billing_external_refs_table(), [ 'display_reference' => $reference['display_reference'], 'status_projection' => $reference['status_projection'], 'updated_at' => $now ], [ 'id' => (int) $existing['id'] ], [ '%s', '%s', '%s' ], [ '%d' ] );
			if ( false === $reference_updated ) { $wpdb->query( 'ROLLBACK' ); return new \WP_Error( 'work_billing_reference_update_failed' ); }
			if ( BillingUnit::EXTERNALLY_LINKED !== $unit['status'] ) {
				$unit_updated = $wpdb->update( Schema::billing_units_table(), [ 'status' => BillingUnit::EXTERNALLY_LINKED, 'linked_at' => $now, 'updated_at' => $now ], [ 'id' => (int) $unit['id'] ], [ '%s', '%s', '%s' ], [ '%d' ] );
				if ( false === $unit_updated ) { $wpdb->query( 'ROLLBACK' ); return new \WP_Error( 'work_billing_reference_update_failed' ); }
			}
			$wpdb->query( 'COMMIT' );
			return self::linked_result( 'reused', (int) $unit['id'], (int) $current['id'], (int) $existing['id'] );
		}
		if ( BillingUnit::EXTERNALLY_LINKED === $unit['status'] ) { $wpdb->query( 'ROLLBACK' ); return new \WP_Error( 'work_billing_already_linked' ); }
		$ok = $wpdb->insert(
			Schema::billing_external_refs_table(),
			[ 'billing_unit_id' => (int) $unit['id'], 'snapshot_id' => (int) $current['id'], 'provider' => $reference['provider'], 'resource_type' => $reference['resource_type'], 'resource_id' => $reference['resource_id'], 'display_reference' => $reference['display_reference'], 'status_projection' => $reference['status_projection'], 'linked_at' => $now, 'updated_at' => $now ],
			[ '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
		);
		if ( false === $ok ) { $wpdb->query( 'ROLLBACK' ); return new \WP_Error( 'work_billing_reference_create_failed' ); }
		$reference_id = (int) $wpdb->insert_id;
		$updated = $wpdb->update( Schema::billing_units_table(), [ 'status' => BillingUnit::EXTERNALLY_LINKED, 'linked_at' => $now, 'updated_at' => $now ], [ 'id' => (int) $unit['id'] ], [ '%s', '%s', '%s' ], [ '%d' ] );
		if ( false === $updated ) { $wpdb->query( 'ROLLBACK' ); return new \WP_Error( 'work_billing_reference_create_failed' ); }
		$wpdb->query( 'COMMIT' );
		return self::linked_result( 'linked', (int) $unit['id'], (int) $current['id'], $reference_id );
	}

	/** @return array<string,mixed>|\WP_Error */
	public static function update_external_status( string $unit_type, int $unit_id, string $provider, string $resource_type, string $resource_id, string $status_projection, string $display_reference = '' ): array|\WP_Error {
		$reference = self::normalize_reference( $provider, $resource_type, $resource_id, $display_reference, $status_projection );
		$unit = self::find( $unit_type, $unit_id );
		if ( false === $reference || null === $unit ) { return new \WP_Error( 'work_billing_reference_unavailable' ); }
		global $wpdb;
		$existing = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . Schema::billing_external_refs_table() . ' WHERE billing_unit_id = %d AND provider = %s AND resource_type = %s AND resource_id = %s LIMIT 1', (int) $unit['id'], $reference['provider'], $reference['resource_type'], $reference['resource_id'] ),
			ARRAY_A
		);
		if ( ! is_array( $existing ) ) { return new \WP_Error( 'work_billing_reference_unavailable' ); }
		$display = '' !== $reference['display_reference'] ? $reference['display_reference'] : (string) $existing['display_reference'];
		if ( $display === (string) $existing['display_reference'] && $reference['status_projection'] === (string) $existing['status_projection'] ) { return [ 'outcome' => 'reused', 'reference' => self::hydrate_reference( $existing ) ]; }
		$updated = $wpdb->update( Schema::billing_external_refs_table(), [ 'display_reference' => $display, 'status_projection' => $reference['status_projection'], 'updated_at' => current_time( 'mysql', true ) ], [ 'id' => (int) $existing['id'] ], [ '%s', '%s', '%s' ], [ '%d' ] );
		if ( false === $updated ) { return new \WP_Error( 'work_billing_reference_update_failed' ); }
		$fresh = self::reference_by_id( (int) $existing['id'] );
		return null === $fresh ? new \WP_Error( 'work_billing_reference_unavailable' ) : [ 'outcome' => 'updated', 'reference' => $fresh ];
	}

	private static function snapshot_for_update( int $snapshot_id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Schema::billing_snapshots_table() . ' WHERE id = %d LIMIT 1 FOR UPDATE', $snapshot_id ), ARRAY_A );
		return is_array( $row ) ? self::hydrate_snapshot( $row ) : null;
	}

	private static function next_snapshot_version( int $billing_unit_id ): int {
		global $wpdb;
		$value = $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(MAX(snapshot_version), 0) FROM ' . Schema::billing_snapshots_table() . ' WHERE billing_unit_id = %d', $billing_unit_id ) );
		return max( 1, (int) $value + 1 );
	}

	private static function reference_for_update( int $billing_unit_id, string $provider, string $resource_type, string $resource_id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Schema::billing_external_refs_table() . ' WHERE billing_unit_id = %d AND provider = %s AND resource_type = %s AND resource_id = %s LIMIT 1 FOR UPDATE', $billing_unit_id, $provider, $resource_type, $resource_id ), ARRAY_A );
		return is_array( $row ) ? self::hydrate_reference( $row ) : null;
	}

	private static function reference_by_id( int $reference_id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Schema::billing_external_refs_table() . ' WHERE id = %d LIMIT 1', $reference_id ), ARRAY_A );
		return is_array( $row ) ? self::hydrate_reference( $row ) : null;
	}

	/** @return array<string,mixed>|\WP_Error */
	private static function linked_result( string $outcome, int $billing_unit_id, int $snapshot_id, int $reference_id ): array|\WP_Error {
		$unit = self::get( $billing_unit_id ); $snapshot = self::snapshot( $snapshot_id ); $reference = self::reference_by_id( $reference_id );
		return null === $unit || null === $snapshot || null === $reference ? new \WP_Error( 'work_billing_resource_unavailable' ) : [ 'outcome' => $outcome, 'unit' => $unit, 'snapshot' => $snapshot, 'reference' => $reference ];
	}

	/** @return array{provider:string,resource_type:string,resource_id:string,display_reference:string,status_projection:string}|false */
	private static function normalize_reference( string $provider, string $resource_type, string $resource_id, string $display_reference, string $status_projection ): array|false {
		$provider = substr( sanitize_key( $provider ), 0, 64 ); $resource_type = substr( sanitize_key( $resource_type ), 0, 64 ); $resource_id = substr( sanitize_text_field( $resource_id ), 0, 191 ); $display_reference = substr( sanitize_text_field( $display_reference ), 0, 190 ); $status_projection = substr( sanitize_key( $status_projection ), 0, 64 );
		return '' === $provider || '' === $resource_type || '' === $resource_id ? false : compact( 'provider', 'resource_type', 'resource_id', 'display_reference', 'status_projection' );
	}

	/** @return array<string,mixed> */
	private static function hydrate_unit( array $row ): array {
		return [ 'id' => (int) $row['id'], 'unit_type' => (string) $row['unit_type'], 'unit_id' => (int) $row['unit_id'], 'work_item_id' => (int) $row['work_item_id'], 'status' => (string) $row['status'], 'current_snapshot_id' => (int) $row['current_snapshot_id'], 'ready_at' => (string) $row['ready_at'], 'linked_at' => null === $row['linked_at'] ? null : (string) $row['linked_at'], 'created_at' => (string) $row['created_at'], 'updated_at' => (string) $row['updated_at'] ];
	}

	/** @return array<string,mixed> */
	private static function hydrate_snapshot( array $row ): array {
		$payload = json_decode( (string) $row['payload'], true );
		return [ 'id' => (int) $row['id'], 'billing_unit_id' => (int) $row['billing_unit_id'], 'snapshot_version' => (int) $row['snapshot_version'], 'unit_type' => (string) $row['unit_type'], 'unit_id' => (int) $row['unit_id'], 'work_item_id' => (int) $row['work_item_id'], 'source_revision' => (int) $row['source_revision'], 'fingerprint' => (string) $row['fingerprint'], 'payload' => is_array( $payload ) ? $payload : [], 'created_by' => (int) $row['created_by'], 'created_at' => (string) $row['created_at'] ];
	}

	/** @return array<string,mixed> */
	private static function hydrate_reference( array $row ): array {
		return [ 'id' => (int) $row['id'], 'billing_unit_id' => (int) $row['billing_unit_id'], 'snapshot_id' => (int) $row['snapshot_id'], 'provider' => (string) $row['provider'], 'resource_type' => (string) $row['resource_type'], 'resource_id' => (string) $row['resource_id'], 'display_reference' => (string) $row['display_reference'], 'status_projection' => (string) $row['status_projection'], 'linked_at' => (string) $row['linked_at'], 'updated_at' => (string) $row['updated_at'] ];
	}

	private static function schema_ready(): bool {
		return defined( 'CB_WORK_SCHEMA_VERSION' ) && (string) get_option( Schema::OPTION, '0' ) === (string) CB_WORK_SCHEMA_VERSION;
	}
}

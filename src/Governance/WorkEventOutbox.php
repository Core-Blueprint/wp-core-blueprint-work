<?php
declare(strict_types=1);

namespace CB\Work\Governance;

use CB\Work\Database\Schema;
use CoreBlueprint\Core\Governance\Audit;

defined( 'ABSPATH' ) || exit;

/**
 * Durable, transactionally visible Work Project bundle event outbox.
 *
 * Delivery is at-least-once (NOT exactly-once across process crashes).
 * Replayed lifecycle hooks carry the stable event_key as one final argument
 * so integrated consumers can implement idempotent processing.
 */
final class WorkEventOutbox {
	public const HOOK = 'cb_work_bundle_outbox_drain';
	private const MAX_EVENT_BYTES = 131072;
	private const MAX_BUNDLE_BYTES = 8388608;
	private const BATCH_SIZE = 50;
	private const MAX_ATTEMPTS = 12;
	private const RETRY_AFTER_SECONDS = 600;
	private const CRON_RECURRENCE = 'cb_work_five_minutes';

	public static function init(): void {
		add_filter( 'cron_schedules', [ self::class, 'intervals' ] );
		add_action( self::HOOK, [ self::class, 'drain' ] );
		add_action( 'init', [ self::class, 'ensure_scheduled' ], 20 );
		if ( did_action( 'init' ) ) {
			self::ensure_scheduled();
		}
	}

	/** @param array<string,array<string,mixed>> $schedules */
	public static function intervals( array $schedules ): array {
		$schedules[ self::CRON_RECURRENCE ] = [ 'interval' => 300, 'display' => 'Work outbox (5 minutes)' ];
		return $schedules;
	}

	public static function ensure_scheduled(): void {
		if ( false === wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, self::CRON_RECURRENCE, self::HOOK );
		}
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( self::HOOK );
	}

	/**
	 * Must execute inside the Project bundle SAVEPOINT or owned transaction.
	 * @param array<int,array{kind:string,name:string,payload:array}> $events
	 */
	public static function enqueue( array $events ): bool {
		global $wpdb;
		if ( [] === $events ) {
			return true;
		}
		if ( 1 !== (int) $wpdb->get_var( 'SELECT @@in_transaction' ) || count( $events ) > 2000 ) {
			return false;
		}
		$table = Schema::outbox_table();
		$bytes = 0;
		foreach ( $events as $event ) {
			if ( ! in_array( $event['kind'], [ 'audit', 'lifecycle' ], true )
				|| 1 !== preg_match( '/^(?:work\.[a-z0-9_.]+|cb_work_[a-z0-9_]+)$/D', $event['name'] ) ) {
				return false;
			}
			$encoded = wp_json_encode( $event['payload'], JSON_INVALID_UTF8_SUBSTITUTE );
			if ( ! is_string( $encoded ) ) {
				return false;
			}
			$bytes += strlen( $encoded );
			if ( strlen( $encoded ) > self::MAX_EVENT_BYTES || $bytes > self::MAX_BUNDLE_BYTES ) {
				return false;
			}
			if ( 1 !== $wpdb->insert( $table, [
				'event_key' => wp_generate_uuid4(),
				'event_kind' => $event['kind'],
				'event_name' => $event['name'],
				'payload' => $encoded,
				'created_at' => current_time( 'mysql', true ),
			], [ '%s', '%s', '%s', '%s', '%s' ] ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Bounded cron run. Never dispatch while the calling connection has an
	 * open transaction: RELEASE SAVEPOINT does not make rows durable.
	 */
	public static function drain(): int {
		global $wpdb;
		$in_transaction = $wpdb->get_var( 'SELECT @@in_transaction' );
		if ( null === $in_transaction || (int) $in_transaction !== 0
			|| CB_WORK_SCHEMA_VERSION !== (string) get_option( Schema::OPTION, '0' ) ) {
			return 0;
		}
		$table = Schema::outbox_table();
		$stale = gmdate( 'Y-m-d H:i:s', time() - self::RETRY_AFTER_SECONDS );
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, event_key, event_kind, event_name, payload FROM %i
			WHERE attempts < %d AND (state = 'pending' OR (state = 'processing' AND claimed_at < %s))
			ORDER BY id ASC LIMIT %d",
			$table, self::MAX_ATTEMPTS, $stale, self::BATCH_SIZE
		), ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return 0;
		}
		$delivered = 0;
		foreach ( $rows as $row ) {
			$id = (int) $row['id'];
			$now = current_time( 'mysql', true );
			$claimed = $wpdb->query( $wpdb->prepare(
				"UPDATE %i SET state = 'processing', attempts = attempts + 1, claimed_at = %s
				WHERE id = %d AND attempts < %d
				AND (state = 'pending' OR (state = 'processing' AND claimed_at < %s))",
				$table, $now, $id, self::MAX_ATTEMPTS, $stale
			) );
			if ( 1 !== $claimed ) {
				continue;
			}
			$stage = 'decode';
			try {
				$payload = json_decode( (string) $row['payload'], true, 512, JSON_THROW_ON_ERROR );
				if ( ! is_array( $payload ) ) {
					throw new \UnexpectedValueException( 'Invalid outbox payload.' );
				}
				if ( 'audit' === $row['event_kind'] ) {
					$stage = 'audit';
					$context = (array) ( $payload['context'] ?? [] );
					$context['outbox_event_key'] = (string) $row['event_key'];
					if ( ! Audit::record( (string) $row['event_name'], (string) ( $payload['severity'] ?? 'notice' ), $context ) ) {
						$stage = 'audit_refused';
						throw new \RuntimeException( 'Base refused an outbox audit event.' );
					}
				} elseif ( 'lifecycle' === $row['event_kind'] ) {
					$stage = 'lifecycle';
					$args = $payload['args'] ?? null;
					if ( ! is_array( $args ) ) {
						throw new \UnexpectedValueException( 'Invalid outbox lifecycle arguments.' );
					}
					do_action( (string) $row['event_name'], ...array_merge( $args, [ (string) $row['event_key'] ] ) );
				} else {
					throw new \UnexpectedValueException( 'Invalid outbox event kind.' );
				}
				$stage = 'acknowledge';
				$acknowledged = $wpdb->update( $table, [
					'state' => 'delivered',
					'delivered_at' => current_time( 'mysql', true ),
					'last_error_code' => '',
				], [ 'id' => $id, 'state' => 'processing' ], [ '%s', '%s', '%s' ], [ '%d', '%s' ] );
				if ( 1 !== $acknowledged ) {
					throw new \RuntimeException( 'Outbox acknowledgement could not be persisted.' );
				}
				$delivered++;
			} catch ( \Throwable $exception ) {
				// Save only a bounded stage and throwable class. Never log messages,
				// SQL, subscriber payloads or other user-controlled sensitive data.
				$error_code = substr( sanitize_key( $stage . '_' . ( new \ReflectionClass( $exception ) )->getShortName() ), 0, 80 );
				$wpdb->update( $table, [
					'state' => 'pending',
					'last_error_code' => $error_code,
				], [ 'id' => $id, 'state' => 'processing' ], [ '%s', '%s' ], [ '%d', '%s' ] );
			}
		}
		// Bounded, independent retention of successfully published deliveries.
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS );
		$wpdb->query( $wpdb->prepare(
			"DELETE FROM %i WHERE state = 'delivered' AND delivered_at < %s ORDER BY id ASC LIMIT %d",
			$table, $cutoff, self::BATCH_SIZE
		) );
		return $delivered;
	}

	private function __construct() {}
}

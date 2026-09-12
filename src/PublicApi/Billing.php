<?php
declare(strict_types=1);

namespace CB\Work\PublicApi;

use CB\Work\Billing\SnapshotBuilder;
use CB\Work\Capabilities;
use CB\Work\Repository\BillingUnits;

defined( 'ABSPATH' ) || exit;

/** Supported read-only billing-readiness contract for sibling integrations. */
final class Billing {
	public static function inspect( string $unit_type, int $unit_id ): array|\WP_Error {
		$forbidden = self::authorize_manage();
		if ( null !== $forbidden ) { return $forbidden; }

		$unit      = BillingUnits::find( $unit_type, $unit_id );
		$candidate = SnapshotBuilder::build( $unit_type, $unit_id );
		if ( is_wp_error( $candidate ) ) {
			if ( null === $unit ) {
				return $candidate;
			}
			return self::inspection( $unit, null, $candidate->get_error_code() );
		}
		if ( null === $unit ) {
			return [
				'unit_type'             => $candidate['unit_type'],
				'unit_id'               => $candidate['unit_id'],
				'work_item_id'          => $candidate['work_item_id'],
				'status'                => 'not_ready',
				'stale'                 => false,
				'current_snapshot'      => null,
				'external_references'   => [],
				'candidate_fingerprint' => $candidate['fingerprint'],
				'candidate_error'       => null,
			];
		}
		return self::inspection( $unit, $candidate, null );
	}

	/**
	 * Returns one bounded cursor page of persisted ready-state units.
	 *
	 * Stale or currently ineligible units remain visible in the page so they
	 * cannot starve newer units behind them. Consumers must only hand off rows
	 * where `stale` is false, then continue with `next_cursor` when present.
	 *
	 * @return array{items:array<int,array<string,mixed>>,next_cursor:?int}|\WP_Error
	 */
	public static function ready( int $limit = 100, int $after_id = 0 ): array|\WP_Error {
		$forbidden = self::authorize_manage();
		if ( null !== $forbidden ) { return $forbidden; }

		$limit    = max( 1, min( 100, $limit ) );
		$after_id = max( 0, $after_id );
		$units    = BillingUnits::ready( $limit + 1, $after_id );
		$has_more = count( $units ) > $limit;
		if ( $has_more ) {
			array_pop( $units );
		}

		$items = [];
		foreach ( $units as $unit ) {
			$candidate = SnapshotBuilder::build( (string) $unit['unit_type'], (int) $unit['unit_id'] );
			if ( is_wp_error( $candidate ) ) {
				$items[] = self::inspection( $unit, null, $candidate->get_error_code() );
				continue;
			}
			$items[] = self::inspection( $unit, $candidate, null );
		}

		$last = [] === $units ? null : $units[ count( $units ) - 1 ];
		return [
			'items'       => $items,
			'next_cursor' => $has_more && is_array( $last ) ? (int) $last['id'] : null,
		];
	}

	public static function snapshot( int $snapshot_id ): array|\WP_Error {
		$forbidden = self::authorize_manage();
		if ( null !== $forbidden ) { return $forbidden; }
		$snapshot = BillingUnits::snapshot( $snapshot_id );
		return null === $snapshot ? new \WP_Error( 'work_billing_snapshot_unavailable' ) : $snapshot;
	}

	public static function snapshots( string $unit_type, int $unit_id, int $limit = 50 ): array|\WP_Error {
		$forbidden = self::authorize_manage();
		if ( null !== $forbidden ) { return $forbidden; }
		$unit = BillingUnits::find( $unit_type, $unit_id );
		return null === $unit ? [] : BillingUnits::snapshots( (int) $unit['id'], $limit );
	}

	/**
	 * @param array<string,mixed>      $unit
	 * @param array<string,mixed>|null $candidate
	 * @return array<string,mixed>
	 */
	private static function inspection( array $unit, ?array $candidate, ?string $candidate_error ): array {
		$snapshot = BillingUnits::current_snapshot_for_unit( $unit );
		$stale = null === $candidate
			|| null === $snapshot
			|| ! hash_equals( (string) $snapshot['fingerprint'], (string) $candidate['fingerprint'] );

		return [
			'unit_type'             => (string) $unit['unit_type'],
			'unit_id'               => (int) $unit['unit_id'],
			'work_item_id'          => (int) $unit['work_item_id'],
			'status'                => (string) $unit['status'],
			'stale'                 => $stale,
			'current_snapshot'      => $snapshot,
			'external_references'   => BillingUnits::references( (int) $unit['id'] ),
			'candidate_fingerprint' => null === $candidate ? null : (string) $candidate['fingerprint'],
			'candidate_error'       => $candidate_error,
		];
	}

	private static function authorize_manage(): ?\WP_Error {
		return current_user_can( Capabilities::MANAGE ) ? null : new \WP_Error( 'work_billing_forbidden' );
	}
}

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
		$candidate = SnapshotBuilder::build( $unit_type, $unit_id );
		if ( is_wp_error( $candidate ) ) { return $candidate; }
		$unit = BillingUnits::find( $candidate['unit_type'], $candidate['unit_id'] );
		if ( null === $unit ) {
			return [ 'unit_type' => $candidate['unit_type'], 'unit_id' => $candidate['unit_id'], 'work_item_id' => $candidate['work_item_id'], 'status' => 'not_ready', 'stale' => false, 'current_snapshot' => null, 'external_references' => [], 'candidate_fingerprint' => $candidate['fingerprint'] ];
		}
		$snapshot = BillingUnits::current_snapshot_for_unit( $unit );
		$stale = null === $snapshot || ! hash_equals( (string) $snapshot['fingerprint'], $candidate['fingerprint'] );
		return [ 'unit_type' => $candidate['unit_type'], 'unit_id' => $candidate['unit_id'], 'work_item_id' => $candidate['work_item_id'], 'status' => (string) $unit['status'], 'stale' => $stale, 'current_snapshot' => $snapshot, 'external_references' => BillingUnits::references( (int) $unit['id'] ), 'candidate_fingerprint' => $candidate['fingerprint'] ];
	}

	public static function ready( int $limit = 100 ): array|\WP_Error {
		$forbidden = self::authorize_manage();
		if ( null !== $forbidden ) { return $forbidden; }
		$items = [];
		foreach ( BillingUnits::ready( $limit ) as $unit ) {
			$candidate = SnapshotBuilder::build( (string) $unit['unit_type'], (int) $unit['unit_id'] );
			if ( is_wp_error( $candidate ) ) { continue; }
			$snapshot = BillingUnits::current_snapshot_for_unit( $unit );
			if ( null === $snapshot || ! hash_equals( (string) $snapshot['fingerprint'], $candidate['fingerprint'] ) ) { continue; }
			$items[] = [ 'unit' => $unit, 'snapshot' => $snapshot, 'external_references' => BillingUnits::references( (int) $unit['id'] ) ];
		}
		return $items;
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

	private static function authorize_manage(): ?\WP_Error {
		return current_user_can( Capabilities::MANAGE ) ? null : new \WP_Error( 'work_billing_forbidden' );
	}
}

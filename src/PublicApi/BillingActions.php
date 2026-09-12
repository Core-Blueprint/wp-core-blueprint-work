<?php
declare(strict_types=1);

namespace CB\Work\PublicApi;

use CB\Core\Governance\Audit;
use CB\Work\Billing\SnapshotBuilder;
use CB\Work\Capabilities;
use CB\Work\Governance\Events;
use CB\Work\Repository\BillingUnits;

defined( 'ABSPATH' ) || exit;

/** Governed billing-readiness mutations. Financial document truth remains external. */
final class BillingActions {
	public static function prepare( string $unit_type, int $unit_id ): array|\WP_Error {
		$forbidden = self::authorize_manage();
		if ( null !== $forbidden ) { return $forbidden; }
		$candidate = SnapshotBuilder::build( $unit_type, $unit_id );
		if ( is_wp_error( $candidate ) ) { return $candidate; }
		$result = BillingUnits::prepare( $candidate, get_current_user_id() );
		if ( is_wp_error( $result ) ) { return $result; }
		if ( 'created' === $result['outcome'] || 'refreshed' === $result['outcome'] ) {
			$event = 'created' === $result['outcome'] ? Events::BILLING_READY : Events::BILLING_SNAPSHOT_REFRESHED;
			Audit::record( $event, 'notice', [ 'billing_unit_id' => (int) $result['unit']['id'], 'snapshot_id' => (int) $result['snapshot']['id'], 'unit_type' => (string) $result['unit']['unit_type'], 'unit_id' => (int) $result['unit']['unit_id'], 'work_item_id' => (int) $result['unit']['work_item_id'], 'actor_user_id' => get_current_user_id() ] );
			$hook = 'created' === $result['outcome'] ? 'cb_work_billing_ready' : 'cb_work_billing_snapshot_refreshed';
			do_action( $hook, $result['unit'], $result['snapshot'] );
		}
		return $result;
	}

	public static function link_external_reference( string $unit_type, int $unit_id, string $provider, string $resource_type, string $resource_id, string $display_reference = '', string $status_projection = '' ): array|\WP_Error {
		$forbidden = self::authorize_manage();
		if ( null !== $forbidden ) { return $forbidden; }
		$candidate = SnapshotBuilder::build( $unit_type, $unit_id );
		if ( is_wp_error( $candidate ) ) { return $candidate; }
		$result = BillingUnits::link_external_reference( $candidate['unit_type'], $candidate['unit_id'], $candidate['fingerprint'], $provider, $resource_type, $resource_id, $display_reference, $status_projection );
		if ( is_wp_error( $result ) ) { return $result; }
		if ( 'linked' === $result['outcome'] ) {
			Audit::record( Events::BILLING_EXTERNAL_LINKED, 'notice', [ 'billing_unit_id' => (int) $result['unit']['id'], 'snapshot_id' => (int) $result['snapshot']['id'], 'provider' => (string) $result['reference']['provider'], 'resource_type' => (string) $result['reference']['resource_type'], 'resource_id' => (string) $result['reference']['resource_id'], 'actor_user_id' => get_current_user_id() ] );
			do_action( 'cb_work_billing_external_linked', $result['unit'], $result['snapshot'], $result['reference'] );
		}
		return $result;
	}

	public static function update_external_status( string $unit_type, int $unit_id, string $provider, string $resource_type, string $resource_id, string $status_projection, string $display_reference = '' ): array|\WP_Error {
		$forbidden = self::authorize_manage();
		if ( null !== $forbidden ) { return $forbidden; }
		$result = BillingUnits::update_external_status( $unit_type, $unit_id, $provider, $resource_type, $resource_id, $status_projection, $display_reference );
		if ( is_wp_error( $result ) ) { return $result; }
		if ( 'updated' === $result['outcome'] ) {
			Audit::record( Events::BILLING_EXTERNAL_STATUS_UPDATED, 'notice', [ 'provider' => (string) $result['reference']['provider'], 'resource_type' => (string) $result['reference']['resource_type'], 'resource_id' => (string) $result['reference']['resource_id'], 'status_projection' => (string) $result['reference']['status_projection'], 'actor_user_id' => get_current_user_id() ] );
			do_action( 'cb_work_billing_external_status_updated', $result['reference'] );
		}
		return $result;
	}

	private static function authorize_manage(): ?\WP_Error {
		return current_user_can( Capabilities::MANAGE ) ? null : new \WP_Error( 'work_billing_forbidden' );
	}
}

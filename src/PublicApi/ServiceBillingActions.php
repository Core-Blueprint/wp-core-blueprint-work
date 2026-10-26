<?php
declare(strict_types=1);

namespace CB\Work\PublicApi;

use CB\Work\Capabilities;
use CB\Work\ServiceOperations\ServiceBilling as BillingRepository;
use CB\Work\Governance\Events;
use CoreBlueprint\Core\Governance\Audit;

defined( 'ABSPATH' ) || exit;

/** Governed server mutations: never equate user registration to provider verification. */
final class ServiceBillingActions {
    public static function create_terms( int $engagement_id, string $frequency, string $anchor_on, string $timing ): int|\WP_Error {
        if ( ! current_user_can( Capabilities::MANAGE ) ) { return new \WP_Error( 'work_service_forbidden' ); }
        return BillingRepository::create_terms( $engagement_id, $frequency, $anchor_on, $timing, get_current_user_id() );
    }

    public static function record( int $engagement_id, string $month, string $state,
        string $invoiced_on, string $reference, int $expected_revision ): array|\WP_Error {
        if ( ! current_user_can( Capabilities::MANAGE ) ) { return new \WP_Error( 'work_service_forbidden' ); }
        $result = BillingRepository::record( $engagement_id, $month, $state, $invoiced_on, $reference,
            $expected_revision, get_current_user_id() );
        if ( ! is_wp_error( $result ) && 'saved' === $result['outcome'] ) {
            Audit::record( Events::SERVICE_BILLING_RECORDED, 'notice', [
                'registration_id' => (int) $result['registration']['id'],
                'revision' => (int) $result['registration']['revision'],
                'state' => $state, 'actor_user_id' => get_current_user_id(),
            ] );
        }
        return $result;
    }
}

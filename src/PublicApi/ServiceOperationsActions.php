<?php
declare(strict_types=1);

namespace CB\Work\PublicApi;

use CB\Work\Capabilities;
use CB\Work\ServiceOperations\Engagements;
use CoreBlueprint\Core\Governance\Audit;

defined( 'ABSPATH' ) || exit;

/** All mutations are permission checked; transports additionally enforce CSRF. */
final class ServiceOperationsActions {
    public static function create( array $input ): int|\WP_Error {
        if ( ! current_user_can( Capabilities::MANAGE ) ) { return new \WP_Error( 'work_service_forbidden' ); }
        $id = Engagements::create( $input, get_current_user_id() );
        if ( ! is_wp_error( $id ) ) {
            Audit::record( 'work.service.engagement.created', 'notice', [
                'engagement_id' => $id, 'actor_user_id' => get_current_user_id(),
            ] );
        }
        return $id;
    }

    public static function bind_rule( int $engagement_id, int $rule_id, string $effective_from ): bool|\WP_Error {
        if ( ! current_user_can( Capabilities::MANAGE ) ) { return new \WP_Error( 'work_service_forbidden' ); }
        $before = Engagements::rules( $engagement_id );
        if ( is_wp_error( $before ) ) { return $before; }
        $result = Engagements::bind_rule( $engagement_id, $rule_id, $effective_from, get_current_user_id() );
        if ( true === $result && ! in_array( $rule_id, array_map( static fn( array $r ): int => (int) $r['rule_id'], $before ), true ) ) {
            Audit::record( 'work.service.rule.bound', 'notice', [
                'engagement_id' => $engagement_id, 'rule_id' => $rule_id,
                'actor_user_id' => get_current_user_id(),
            ] );
        }
        return $result;
    }
}

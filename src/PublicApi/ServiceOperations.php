<?php
declare(strict_types=1);

namespace CB\Work\PublicApi;

use CB\Work\Capabilities;
use CB\Work\ServiceOperations\Engagements;
use CB\Work\ServiceOperations\Periods;

defined( 'ABSPATH' ) || exit;

/** Authorized provider-neutral recurring-service read contract. */
final class ServiceOperations {
    public static function engagement( int $id ): array|\WP_Error {
        if ( ! current_user_can( Capabilities::MANAGE ) ) { return new \WP_Error( 'work_service_forbidden' ); }
        return Engagements::get( $id ) ?? new \WP_Error( 'work_service_not_found' );
    }

    public static function search( array $filters = [] ): array|\WP_Error {
        if ( ! current_user_can( Capabilities::MANAGE ) ) { return new \WP_Error( 'work_service_forbidden' ); }
        return Engagements::search( $filters );
    }

    public static function period( int $id, string $month ): array|\WP_Error {
        if ( ! current_user_can( Capabilities::MANAGE ) ) { return new \WP_Error( 'work_service_forbidden' ); }
        $engagement = Engagements::get( $id );
        return $engagement ? Periods::resolve( $engagement, $month ) : new \WP_Error( 'work_service_not_found' );
    }
}

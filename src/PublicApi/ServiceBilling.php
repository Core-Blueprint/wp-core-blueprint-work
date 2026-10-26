<?php
declare(strict_types=1);

namespace CB\Work\PublicApi;

use CB\Work\Capabilities;
use CoreBlueprint\Core\Governance\Audit;
use CB\Work\Governance\Events;

defined( 'ABSPATH' ) || exit;

/** No provider-dependent invoicing lifecycle: manually attested coverage only. */
final class ServiceBilling {
    public static function coverage( int $engagement_id, string $month ): array|\WP_Error {
        return current_user_can( Capabilities::MANAGE )
            ? \CB\Work\ServiceOperations\ServiceBilling::for_month( $engagement_id, $month )
            : new \WP_Error( 'work_service_forbidden' );
    }
}

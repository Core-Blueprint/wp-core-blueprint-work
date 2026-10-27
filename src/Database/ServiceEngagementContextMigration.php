<?php
declare(strict_types=1);

namespace CB\Work\Database;

use CB\Work\Domain\WorkContext;

defined( 'ABSPATH' ) || exit;

/**
 * Additive 2.3 -> 2.4 data reconciliation, separately testable from dbDelta.
 * Existing Service Engagements in 2.3 could only have a complete customer
 * reference. Blank or partial legacy rows remain unclassified (fail closed).
 *
 * No DDL, no invoices, no mutations of established context or customer IDs.
 */
final class ServiceEngagementContextMigration {
    public static function apply(): bool {
        global $wpdb;
        $result = $wpdb->query( $wpdb->prepare(
            'UPDATE ' . Schema::engagements_table()
            . ' SET work_context = %s WHERE work_context = %s'
            . ' AND customer_provider <> %s AND customer_type <> %s AND customer_id <> %s',
            WorkContext::CUSTOMER, '', '', '', ''
        ) );
        return false !== $result && '' === (string) $wpdb->last_error;
    }

    private function __construct() {}
}

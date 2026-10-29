<?php
declare(strict_types=1);

namespace CB\Work\Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Stable semantic editor fingerprint. Unlike second-resolution post_modified,
 * this detects changes to Work metadata and Gutenberg content within the
 * same second. The fingerprint is checked again under the mutation row lock.
 */
final class EditorRevisions {
    private const WORK_ITEM_FIELDS = [
        'id', 'title', 'description', 'post_status', 'updated_at',
        'work_context', 'customer_provider', 'customer_type', 'customer_id',
        'project_id', 'service_id', 'work_type_id', 'priority',
        'estimated_minutes', 'scheduled_on', 'due_on', 'status',
        'billing_disposition', 'assigned_user_ids',
    ];
    private const PROJECT_FIELDS = [
        'id', 'title', 'description', 'post_status', 'updated_at',
        'work_context', 'customer_provider', 'customer_type', 'customer_id',
        'starts_on', 'due_on',
    ];

    /** @param array<string,mixed> $item */
    public static function for_work_item( array $item ): string {
        return self::fingerprint( $item, self::WORK_ITEM_FIELDS );
    }

    /** @param array<string,mixed> $project */
    public static function for_project( array $project ): string {
        return self::fingerprint( $project, self::PROJECT_FIELDS );
    }

    /** @param array<string,mixed> $item @param list<string> $fields */
    private static function fingerprint( array $item, array $fields ): string {
        $values = [];
        foreach ( $fields as $field ) {
            $value = $item[ $field ] ?? null;
            if ( 'assigned_user_ids' === $field ) {
                $value = array_values( array_unique( array_map( 'absint', (array) $value ) ) );
                sort( $value, SORT_NUMERIC );
            }
            $values[ $field ] = $value;
        }
        return hash( 'sha256', (string) wp_json_encode( $values ) );
    }

    private function __construct() {}
}

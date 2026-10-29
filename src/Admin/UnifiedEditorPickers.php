<?php
declare(strict_types=1);

namespace CB\Work\Admin;

use CB\Work\Integration\CRMCustomers;
use CB\Work\Repository\WorkItemLinks;

defined( 'ABSPATH' ) || exit;

/**
 * M2 picker bridge. Base supplies selection UI and searches; Work validates
 * CRM identity and WordPress assignees before passing canonical domain inputs.
 */
final class UnifiedEditorPickers {
    private const SEARCH_NONCE = 'cb_work_object_picker';

    /** @param array<string,mixed> $item */
    public static function model( string $entity, array $item ): array {
        $model = [];
        if ( in_array( $entity, [ 'project', 'work-item' ], true ) ) {
            $provider = (string) ( $item['customer_provider'] ?? '' );
            $type = (string) ( $item['customer_type'] ?? '' );
            $id = (string) ( $item['customer_id'] ?? '' );
            $selected = CRMCustomers::selected( $provider, $type, $id );
            $model['customer'] = [
                'enabled' => CRMCustomers::available()
                    && ( '' === $provider || 'crm' === $provider )
                    && ( '' === $id || null !== $selected ),
                'selected' => null === $selected ? [] : [ $selected ],
                'nonce' => wp_create_nonce( self::SEARCH_NONCE ),
            ];
        }
        if ( 'work-item' === $entity ) {
            $selected = [];
            foreach ( (array) ( $item['assigned_user_ids'] ?? [] ) as $user_id ) {
                $user = get_userdata( absint( $user_id ) );
                if ( $user instanceof \WP_User ) {
                    $selected[] = [
                        'id' => (int) $user->ID,
                        'label' => (string) $user->display_name,
                        'meta' => (string) $user->user_login,
                    ];
                }
            }
            // Base transports multiple IDs as a bounded CSV. Existing large
            // assignment sets remain editable in the native advanced editor.
            if ( count( $selected ) <= 100 && strlen( implode( ',', array_column( $selected, 'id' ) ) ) <= 2048 ) {
                $model['assignees'] = [
                    'selected' => $selected,
                    'nonce' => wp_create_nonce( self::SEARCH_NONCE ),
                ];
            }
        }
        return $model;
    }

    /**
     * @param array<string,string> $fields
     * @return array<string,mixed>|\WP_Error
     */
    public static function normalize( string $entity, array $fields ): array|\WP_Error {
        if ( ! in_array( $entity, [ 'project', 'work-item' ], true ) ) {
            return $fields;
        }
        if ( array_key_exists( 'work_context', $fields ) ) {
            if ( ! in_array( $fields['work_context'], [ 'internal', 'customer' ], true ) ) {
                return new \WP_Error( 'work_editor_context_invalid' );
            }
        }
        if ( array_key_exists( 'customer', $fields ) ) {
            // A linked Work Item inherits its customer from its Project.
            if ( 'work-item' === $entity && (int) ( $fields['project_id'] ?? 0 ) > 0 ) {
                return new \WP_Error( 'work_editor_customer_inherited' );
            }
            if ( ! CRMCustomers::available() ) {
                return new \WP_Error( 'work_crm_unavailable' );
            }
            $reference = CRMCustomers::reference( $fields['customer'] );
            if ( is_wp_error( $reference ) ) {
                return $reference;
            }
            $fields['customer_provider'] = null === $reference ? '' : $reference['provider'];
            $fields['customer_type'] = null === $reference ? '' : $reference['type'];
            $fields['customer_id'] = null === $reference ? '' : $reference['id'];
            unset( $fields['customer'] );
        }
        if ( 'work-item' === $entity && array_key_exists( 'assigned_user_ids', $fields ) ) {
            $raw = $fields['assigned_user_ids'];
            if ( '' !== $raw && 1 !== preg_match( '/^[1-9][0-9]*(?:,[1-9][0-9]*){0,99}$/D', $raw ) ) {
                return new \WP_Error( 'work_editor_assignees_invalid' );
            }
            $assignees = WorkItemLinks::normalize_assignments( $raw );
            if ( null === $assignees ) {
                return new \WP_Error( 'work_editor_assignees_invalid' );
            }
            $fields['assigned_user_ids'] = $assignees;
        }
        return $fields;
    }

    private function __construct() {}
}

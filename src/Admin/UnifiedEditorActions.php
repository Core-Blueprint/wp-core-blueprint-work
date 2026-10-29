<?php
declare(strict_types=1);

namespace CB\Work\Admin;

use CB\Work\Capabilities;
use CB\Work\PublicApi\ProjectActions;
use CB\Work\PublicApi\ServiceActions;
use CB\Work\Repository\TaxRates;
use CB\Work\PublicApi\WorkItemActions;
use CB\Work\Repository\Projects;
use CB\Work\Repository\WorkItems;
use CB\Work\PublicApi\Services;
use CB\Work\PublicApi\WorkTypes;

defined( 'ABSPATH' ) || exit;

/**
 * M2: same-origin editor transport. Persistence belongs to existing Work
 * public actions and repositories; this controller never writes metadata.
 *
 * Deliberately limited to non-lossy scalar Work Item / Project fields.
 * Rich Gutenberg descriptions, status transitions, customer bindings and
 * service pricing remain in their existing authoritative editors/handlers.
 */
final class UnifiedEditorActions {
    public const ACTION = 'cb_work_uwe_editor';
    public const NONCE_ACTION = 'cb_work_uwe_editor';

    public static function init(): void {
        add_action( 'wp_ajax_' . self::ACTION, [ self::class, 'handle' ] );
    }

    public static function handle(): void {
        $method = (string) ( $_SERVER['REQUEST_METHOD'] ?? '' );
        if ( ! in_array( $method, [ 'GET', 'POST' ], true ) ) {
            self::fail( 'work_editor_method', 405 );
        }
        if ( ! current_user_can( Capabilities::MANAGE ) ) {
            self::fail( 'work_editor_forbidden', 403 );
        }
        if ( false === check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
            self::fail( 'work_editor_nonce', 403 );
        }
        if ( ! function_exists( 'cb_work_runtime_ready' ) || ! \cb_work_runtime_ready() ) {
            self::fail( 'work_editor_unavailable', 503 );
        }

        $raw = 'POST' === $method ? $_POST : $_GET;
        $entity = isset( $raw['entity'] ) && is_string( $raw['entity'] )
            ? sanitize_key( (string) wp_unslash( $raw['entity'] ) ) : '';
        if ( ! in_array( $entity, [ 'work-item', 'project', 'service', 'work-type' ], true ) ) {
            self::fail( 'work_editor_entity', 400 );
        }
        if ( ! isset( $raw['id'] ) || ! is_scalar( $raw['id'] )
            || ! ctype_digit( (string) $raw['id'] ) || strlen( (string) $raw['id'] ) > 10 ) {
            self::fail( 'work_editor_id', 400 );
        }
        $id = absint( $raw['id'] );
        if ( 'work-type' === $entity ) {
            if ( 'GET' !== $method || 0 !== $id ) {
                self::fail( 'work_editor_entity', 400 );
            }
            $types = [];
            foreach ( WorkTypes::all( true ) as $type ) {
                $type_id = absint( $type['id'] ?? 0 );
                $types[] = [
                    'id' => $type_id,
                    'label' => (string) ( $type['label'] ?? '' ),
                    'code' => (string) ( $type['code'] ?? '' ),
                    'active' => ! empty( $type['is_active'] ),
                    'nonce' => wp_create_nonce( 'cb_work_toggle_work_type_' . $type_id ),
                ];
            }
            wp_send_json_success( [
                'entity' => $entity,
                'item' => [ 'id' => 0 ],
                'types' => $types,
                'create_nonce' => wp_create_nonce( 'cb_work_create_work_type' ),
            ], 200 );
        }
        $existing = $id > 0 ? self::find( $entity, $id ) : null;
        if ( $id > 0 && null === $existing ) {
            self::fail( 'work_editor_not_found', 404 );
        }

        if ( 'GET' === $method ) {
            $item = $existing ?? self::defaults( $entity );
            $result = [ 'entity' => $entity, 'item' => self::projection( $entity, $item ) ];
            if ( 'work-item' === $entity ) {
                $result['projects'] = self::options( Projects::all( 500 ) );
                $result['services'] = self::options( Services::all( 250 ) );
                $result['work_types'] = self::options( WorkTypes::all() );
            }
            if ( 'service' === $entity ) {
                $selected = (int) ( $item['pricing']['tax_rate_id'] ?? 0 );
                $rates = array_values( array_filter( TaxRates::all( true ),
                    static fn( array $rate ): bool => (int) $rate['id'] === $selected || TaxRates::is_available( $rate )
                ) );
                $result['tax_rates'] = array_map( static fn( array $rate ): array => [
                    'id' => (int) $rate['id'],
                    'title' => TaxRates::display_label( $rate ),
                ], $rates );
            }
            wp_send_json_success( $result, 200 );
        }

        $raw_fields = $_POST['fields'] ?? null;
        if ( ! is_array( $raw_fields ) ) {
            self::fail( 'work_editor_fields', 422 );
        }
        $raw_fields = wp_unslash( $raw_fields );
        $allowed = match ( $entity ) {
            'work-item' => [ 'title', 'project_id', 'service_id', 'work_type_id', 'priority', 'estimated_minutes', 'scheduled_on', 'due_on' ],
            'project' => [ 'title', 'starts_on', 'due_on' ],
            'service' => [ 'title', 'amount', 'currency', 'pricing_model', 'recurring_period', 'tax_mode', 'tax_rate_id' ],
        };
        $input = [];
        foreach ( $raw_fields as $name => $value ) {
            if ( ! is_string( $name ) || ! in_array( $name, $allowed, true )
                || ! is_scalar( $value ) || strlen( (string) $value ) > 500 ) {
                self::fail( 'work_editor_fields', 422 );
            }
            $input[ $name ] = sanitize_text_field( (string) $value );
        }
        $title = trim( (string) ( $input['title'] ?? '' ) );
        if ( '' === $title || strlen( $title ) > 200 ) {
            self::fail( 'work_editor_title_required', 422 );
        }
        // No metadata is overwritten on a stale editor. The canonical domain
        // actions still own validation, transaction and event/audit behavior.
        if ( $id > 0 && 'service' !== $entity ) {
            $version = isset( $_POST['expected_updated_at'] ) && is_string( $_POST['expected_updated_at'] )
                ? sanitize_text_field( (string) wp_unslash( $_POST['expected_updated_at'] ) ) : '';
            if ( '' === $version || ! hash_equals( (string) $existing['updated_at'], $version ) ) {
                self::fail( 'work_editor_stale', 409 );
            }
        }
        foreach ( [ 'project_id', 'service_id', 'work_type_id', 'estimated_minutes', 'tax_rate_id' ] as $key ) {
            if ( array_key_exists( $key, $input ) ) {
                if ( ! ctype_digit( (string) $input[ $key ] ) || strlen( (string) $input[ $key ] ) > 10 ) {
                    self::fail( 'work_editor_fields', 422 );
                }
                $input[ $key ] = absint( $input[ $key ] );
            }
        }
        foreach ( [ 'scheduled_on', 'starts_on', 'due_on' ] as $key ) {
            if ( isset( $input[ $key ] ) && '' !== $input[ $key ]
                && ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $input[ $key ] )
                    || ! self::valid_date( $input[ $key ] ) ) ) {
                self::fail( 'work_editor_date', 422 );
            }
        }
        if ( 'work-item' === $entity ) {
            if ( 0 === $id ) {
                $input['work_context'] = 'internal';
            }
            $saved = 0 === $id ? WorkItemActions::create( $input ) : WorkItemActions::update( $id, $input );
        } elseif ( 'project' === $entity ) {
            if ( 0 === $id ) {
                $input['work_context'] = 'internal';
            }
            $saved = 0 === $id ? ProjectActions::create( $input ) : ProjectActions::update( $id, $input );
        } else {
            $revision = isset( $_POST['expected_version'] ) && is_string( $_POST['expected_version'] )
                ? sanitize_text_field( (string) wp_unslash( $_POST['expected_version'] ) ) : '';
            $saved = ServiceActions::save( $id, $input, $revision );
        }
        if ( is_wp_error( $saved ) ) {
            $error = $saved->get_error_data();
            $status = is_array( $error ) ? (int) ( $error['status'] ?? 422 ) : 422;
            self::fail( sanitize_key( $saved->get_error_code() ), in_array( $status, [ 403, 404, 409, 422, 503 ], true ) ? $status : 422 );
        }
        wp_send_json_success( [
            'entity' => $entity,
            'item' => self::projection( $entity, $saved ),
        ], 200 );
    }

    /** @return array<string,mixed>|null */
    private static function find( string $entity, int $id ): ?array {
        return match ( $entity ) {
            'work-item' => WorkItems::get( $id ),
            'project' => Projects::get( $id ),
            'service' => ServiceActions::get( $id ),
        };
    }

    /** @return array<string,mixed> */
    private static function defaults( string $entity ): array {
        return match ( $entity ) {
            'work-item' => [ 'id' => 0, 'title' => '', 'work_context' => 'internal',
                'project_id' => 0, 'service_id' => 0, 'work_type_id' => 0,
                'priority' => 'normal', 'estimated_minutes' => 0,
                'scheduled_on' => '', 'due_on' => '', 'status' => 'planned', 'updated_at' => '' ],
            'project' => [ 'id' => 0, 'title' => '', 'work_context' => 'internal',
                'starts_on' => '', 'due_on' => '', 'updated_at' => '' ],
            'service' => [ 'id' => 0, 'title' => '', 'updated_at' => '', 'version' => '',
                'pricing' => [ 'amount_minor' => null, 'currency' => 'EUR',
                    'pricing_model' => 'fixed', 'recurring_period' => '',
                    'tax_mode' => 'exclusive', 'tax_rate_id' => 0 ] ],
        };
    }

    /** Only fields actually supported by this dialog may be exposed. */
    private static function projection( string $entity, array $item ): array {
        $keys = match ( $entity ) {
            'work-item' => [ 'id', 'title', 'work_context', 'project_id', 'service_id', 'work_type_id',
                'priority', 'estimated_minutes', 'scheduled_on', 'due_on', 'status', 'updated_at' ],
            'project' => [ 'id', 'title', 'work_context', 'starts_on', 'due_on', 'updated_at' ],
            'service' => [ 'id', 'title', 'pricing', 'amount_display', 'version', 'updated_at' ],
        };
        return array_intersect_key( $item, array_flip( $keys ) );
    }

    /** @param array<int,array<string,mixed>> $rows */
    private static function options( array $rows ): array {
        return array_values( array_map(
            static fn( array $row ): array => [
                'id' => (int) ( $row['id'] ?? 0 ),
                'title' => (string) ( $row['title'] ?? $row['label'] ?? $row['name'] ?? '' ),
            ],
            $rows
        ) );
    }

    private static function valid_date( string $date ): bool {
        $parts = array_map( 'intval', explode( '-', $date ) );
        return 3 === count( $parts ) && checkdate( $parts[1], $parts[2], $parts[0] );
    }

    private static function fail( string $code, int $status ): never {
        wp_send_json_error( [ 'code' => $code ], $status );
        exit;
    }

    private function __construct() {}
}

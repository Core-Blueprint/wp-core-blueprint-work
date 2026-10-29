<?php
declare(strict_types=1);

namespace CB\Work\PublicApi;

use CB\Work\Capabilities;
use CB\Work\Content\PostTypes;
use CB\Work\Content\ServicePricing;
use CB\Work\Repository\TaxRates;
use CB\Work\Repository\WorkSqlTransaction;
use CB\Work\Governance\DeferredEvents;
use CB\Work\Governance\Events;

defined( 'ABSPATH' ) || exit;

/**
 * M2 canonical Service write facade. WordPress CPT and ServicePricing remain
 * the only sources of truth; the AJAX editor never persists directly.
 *
 * A locked SQL transaction covers the CPT row and existing postmeta on
 * InnoDB, and the stored values are verified before the transaction commits.
 * Existing Gutenberg content is never rewritten in this stage.
 */
final class ServiceActions {
    /** @return array<string,mixed>|\WP_Error */
    public static function save( int $id, array $input, string $expected_version = '' ): array|\WP_Error {
        if ( ! current_user_can( Capabilities::MANAGE ) ) {
            return new \WP_Error( 'work_service_forbidden', '', [ 'status' => 403 ] );
        }
        $title = isset( $input['title'] ) && is_string( $input['title'] )
            ? sanitize_text_field( $input['title'] ) : '';
        if ( '' === trim( $title ) || strlen( $title ) > 200 ) {
            return new \WP_Error( 'work_service_title_required', '', [ 'status' => 422 ] );
        }

        $amount_raw = isset( $input['amount'] ) && is_string( $input['amount'] )
            ? trim( $input['amount'] ) : '';
        $amount = '' === $amount_raw ? null : ServicePricing::parse_amount_minor( $amount_raw );
        $currency = strtoupper( (string) ( $input['currency'] ?? '' ) );
        $model = (string) ( $input['pricing_model'] ?? '' );
        $period = (string) ( $input['recurring_period'] ?? '' );
        $tax_mode = (string) ( $input['tax_mode'] ?? '' );
        $tax_id = $input['tax_rate_id'] ?? 0;

        if ( ( '' !== $amount_raw && null === $amount ) || 1 !== preg_match( '/^[A-Z]{3}$/D', $currency )
            || ! in_array( $model, [ 'hourly', 'fixed', 'recurring' ], true )
            || ! in_array( $tax_mode, [ 'exclusive', 'inclusive', 'exempt' ], true )
            || ! is_int( $tax_id ) || $tax_id < 0 ) {
            return new \WP_Error( 'work_service_pricing_invalid', '', [ 'status' => 422 ] );
        }
        if ( 'recurring' === $model ) {
            if ( ! in_array( $period, [ 'weekly', 'monthly', 'quarterly', 'yearly' ], true ) ) {
                return new \WP_Error( 'work_service_pricing_invalid', '', [ 'status' => 422 ] );
            }
        } else {
            $period = '';
        }
        if ( 'exempt' === $tax_mode ) {
            if ( 0 !== $tax_id ) {
                return new \WP_Error( 'work_service_pricing_invalid', '', [ 'status' => 422 ] );
            }
        } elseif ( $tax_id > 0 ) {
            $rate = TaxRates::get( $tax_id );
            $previous = $id > 0 ? self::get( $id ) : null;
            $retained_id = (int) ( $previous['pricing']['tax_rate_id'] ?? 0 );
            // Historical inactive rates may remain linked, but a newly
            // selected or substituted rate must be active and valid.
            if ( ! is_array( $rate ) || ( $retained_id !== $tax_id && ! TaxRates::is_available( $rate ) ) ) {
                return new \WP_Error( 'work_service_tax_rate_invalid', '', [ 'status' => 422 ] );
            }
        }

        $description_requested = array_key_exists( 'description', $input );
        $description = $description_requested && is_string( $input['description'] )
            ? $input['description'] : '';
        if ( $description_requested && (
            ! is_string( $input['description'] )
            || strlen( $description ) > 16000
            || str_contains( $description, '<' )
            || ! preg_match( '//u', $description )
        ) ) {
            return new \WP_Error( 'work_service_description_advanced_required', '', [ 'status' => 422 ] );
        }
        if ( $description_requested ) {
            $description = sanitize_textarea_field( $description );
        }

        $expected = [
            'amount_minor' => $amount,
            'currency' => $currency,
            'pricing_model' => $model,
            'recurring_period' => $period,
            'tax_mode' => $tax_mode,
            'tax_rate_id' => $tax_id,
        ];
        $created_id = 0;
        $pricing_changed = false;
        $saved = WorkSqlTransaction::run(
            static function () use ( $id, $title, $expected_version, $expected, $description_requested, $description, &$created_id, &$pricing_changed ): array|false|\WP_Error {
                global $wpdb;
                $original_content = '';
                if ( $id > 0 ) {
                    $locked = $wpdb->get_row( $wpdb->prepare(
                        "SELECT ID FROM {$wpdb->posts} WHERE ID = %d AND post_type = %s AND post_status NOT IN ('trash','auto-draft') FOR UPDATE",
                        $id, PostTypes::SERVICE
                    ), ARRAY_A );
                    if ( ! is_array( $locked ) || (int) $locked['ID'] !== $id ) {
                        return new \WP_Error( 'work_service_not_found', '', [ 'status' => 404 ] );
                    }
                    clean_post_cache( $id );
                    wp_cache_delete( $id, 'post_meta' );
                    $current = self::get( $id );
                    if ( null === $current || '' === $expected_version
                        || ! hash_equals( $current['version'], $expected_version ) ) {
                        return new \WP_Error( 'work_service_stale', '', [ 'status' => 409 ] );
                    }
                    $original_content = (string) $current['description'];
                    if ( $description_requested && ( strlen( $original_content ) > 16000
                        || str_contains( $original_content, '<' )
                        || ! preg_match( '//u', $original_content ) ) ) {
                        return new \WP_Error( 'work_service_description_advanced_required', '', [ 'status' => 422 ] );
                    }
                    if ( $current['title'] !== $title || ( $description_requested && $description !== $original_content ) ) {
                        $post_update = [ 'ID' => $id, 'post_title' => $title ];
                        if ( $description_requested ) {
                            $post_update['post_content'] = $description;
                        }
                        $result = wp_update_post( $post_update, true );
                        if ( is_wp_error( $result ) || (int) $result !== $id ) {
                            return false;
                        }
                    }
                    $service_id = $id;
                } else {
                    $result = wp_insert_post( [
                        'post_type' => PostTypes::SERVICE,
                        'post_status' => 'publish',
                        'post_title' => $title,
                        'post_content' => $description_requested ? $description : '',
                        'post_author' => get_current_user_id(),
                    ], true );
                    if ( is_wp_error( $result ) || (int) $result <= 0 ) {
                        return false;
                    }
                    $created_id = (int) $result;
                    $service_id = $created_id;
                }

                $before = ServicePricing::get( $service_id );
                $pricing_changed = $before !== $expected;
                if ( $pricing_changed ) {
                    $pricing_input = [
                        'amount' => ServicePricing::amount_input( $expected['amount_minor'] ),
                        'currency' => $expected['currency'],
                        'pricing_model' => $expected['pricing_model'],
                        'recurring_period' => $expected['recurring_period'],
                        'tax_mode' => $expected['tax_mode'],
                        'tax_rate_id' => $expected['tax_rate_id'],
                    ];
                    if ( ! ServicePricing::save( $service_id, $pricing_input ) ) {
                        return false;
                    }
                }

                clean_post_cache( $service_id );
                wp_cache_delete( $service_id, 'post_meta' );
                $actual = self::get( $service_id );
                $expected_content = $description_requested ? $description : $original_content;
                if ( null === $actual || $actual['title'] !== $title || $actual['pricing'] !== $expected
                    || $actual['description'] !== $expected_content ) {
                    return false;
                }
                return $actual;
            },
            new \WP_Error( 'work_service_save_uncertain', '', [ 'status' => 503 ] )
        );

        if ( is_wp_error( $saved ) || ! is_array( $saved ) ) {
            if ( $id > 0 ) {
                clean_post_cache( $id );
                wp_cache_delete( $id, 'post_meta' );
            }
            if ( $created_id > 0 ) {
                clean_post_cache( $created_id );
                wp_cache_delete( $created_id, 'post_meta' );
            }
            return is_wp_error( $saved ) ? $saved : new \WP_Error( 'work_service_save_failed', '', [ 'status' => 409 ] );
        }

        if ( $pricing_changed ) {
            DeferredEvents::audit( Events::SERVICE_PRICING_UPDATED, 'notice', [
                'service_id' => (int) $saved['id'], 'channel' => 'unified_editor',
            ] );
        }
        DeferredEvents::lifecycle( $id > 0 ? 'cb_work_service_updated' : 'cb_work_service_created',
            (int) $saved['id'], $saved );
        return $saved;
    }

    /** @return array<string,mixed>|null */
    public static function get( int $id ): ?array {
        if ( ! current_user_can( Capabilities::MANAGE ) ) {
            return null;
        }
        $service = Services::get( $id );
        $post = $id > 0 ? get_post( $id ) : null;
        if ( null === $service || ! $post instanceof \WP_Post || 'trash' === $post->post_status ) {
            return null;
        }
        $service['amount_display'] = ServicePricing::amount_input( $service['pricing']['amount_minor'] );
        $service['updated_at'] = (string) $post->post_modified_gmt;
        $service['version'] = hash( 'sha256', (string) wp_json_encode( [
            $service['title'], $service['description'], $post->post_modified_gmt, $service['pricing'],
        ] ) );
        return $service;
    }

    private function __construct() {}
}

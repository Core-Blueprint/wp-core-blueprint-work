<?php
declare(strict_types=1);

namespace CB\Work\ServiceOperations;

use CB\Work\Content\ProjectMeta;
use CB\Work\Database\Schema;
use CB\Work\PublicApi\Services;
use CB\Work\Repository\Projects;
use CB\Work\Repository\RecurrenceRules;
use CB\Work\Repository\RecurrenceOccurrences;
use CB\Work\Repository\WorkSqlTransaction;

defined( 'ABSPATH' ) || exit;

/**
 * Work-owned identity of one customer's continuing service.
 * Does not generate tasks or create financial documents.
 */
final class Engagements {
    public static function ready(): bool {
        return function_exists( 'cb_work_runtime_ready' ) && \cb_work_runtime_ready()
            && defined( 'CB_WORK_SCHEMA_VERSION' )
            && (string) get_option( Schema::OPTION, '0' ) === CB_WORK_SCHEMA_VERSION;
    }

    public static function get( int $id ): ?array {
        if ( $id <= 0 || ! self::ready() ) { return null; }
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . Schema::engagements_table() . ' WHERE id = %d', $id
        ), ARRAY_A );
        return is_array( $row ) ? self::hydrate( $row ) : null;
    }

    /** A bounded, indexed query; returns total count independently of pagination. */
    public static function search( array $filters = [] ): array|\WP_Error {
        if ( ! self::ready() ) { return new \WP_Error( 'work_service_schema_unavailable' ); }
        global $wpdb;
        $where = [ '1=1' ];
        $params = [];
        foreach ( [ 'project_id', 'service_id' ] as $field ) {
            $value = max( 0, (int) ( $filters[ $field ] ?? 0 ) );
            if ( $value ) { $where[] = $field . ' = %d'; $params[] = $value; }
        }
        $customer = $filters['customer'] ?? null;
        if ( is_array( $customer ) && ! empty( $customer['provider'] ) && ! empty( $customer['type'] ) && ! empty( $customer['id'] ) ) {
            $where[] = 'customer_provider = %s AND customer_type = %s AND customer_id = %s';
            array_push( $params, $customer['provider'], $customer['type'], $customer['id'] );
        }
        if ( isset( $filters['active'] ) && in_array( $filters['active'], [ 0, 1, '0', '1' ], true ) ) {
            $where[] = 'is_active = %d'; $params[] = (int) $filters['active'];
        }
        $search = trim( sanitize_text_field( (string) ( $filters['search'] ?? '' ) ) );
        if ( '' !== $search ) {
            $where[] = 'label LIKE %s';
            $params[] = '%' . $wpdb->esc_like( $search ) . '%';
        }
        $predicate = implode( ' AND ', $where );
        $table = Schema::engagements_table();
        $count_sql = 'SELECT COUNT(*) FROM ' . $table . ' WHERE ' . $predicate;
        $total = $wpdb->get_var( $params ? $wpdb->prepare( $count_sql, ...$params ) : $count_sql );
        if ( '' !== (string) $wpdb->last_error || null === $total ) {
            return new \WP_Error( 'work_service_query_failed' );
        }
        $per_page = max( 1, min( 50, (int) ( $filters['per_page'] ?? 25 ) ) );
        $pages = max( 1, (int) ceil( (int) $total / $per_page ) );
        $page = min( $pages, max( 1, (int) ( $filters['page'] ?? 1 ) ) );
        $sql = 'SELECT * FROM ' . $table . ' WHERE ' . $predicate . ' ORDER BY id DESC LIMIT %d OFFSET %d';
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, ...[ ...$params, $per_page, ( $page - 1 ) * $per_page ] ), ARRAY_A );
        if ( ! is_array( $rows ) || '' !== (string) $wpdb->last_error ) {
            return new \WP_Error( 'work_service_query_failed' );
        }
        return [ 'items' => array_map( [ self::class, 'hydrate' ], $rows ), 'total' => (int) $total,
            'page' => $page, 'pages' => $pages, 'per_page' => $per_page ];
    }

    public static function create( array $input, int $actor ): int|\WP_Error {
        if ( ! self::ready() || $actor <= 0 ) { return new \WP_Error( 'work_service_unavailable' ); }
        $project_id = max( 0, (int) ( $input['project_id'] ?? 0 ) );
        $service_id = max( 0, (int) ( $input['service_id'] ?? 0 ) );
        $project = $project_id ? Projects::get( $project_id ) : null;
        if ( $service_id <= 0 || null === Services::get( $service_id ) || ( $project_id && null === $project ) ) {
            return new \WP_Error( 'work_service_reference_invalid' );
        }
        $provider = $project ? (string) $project['customer_provider'] : ProjectMeta::sanitize_reference_part( $input['customer_provider'] ?? '' );
        $type = $project ? (string) $project['customer_type'] : ProjectMeta::sanitize_reference_part( $input['customer_type'] ?? '' );
        $customer_id = $project ? (string) $project['customer_id'] : ProjectMeta::sanitize_reference_id( $input['customer_id'] ?? '' );
        if ( '' === $provider || '' === $type || '' === $customer_id ) {
            return new \WP_Error( 'work_service_customer_required' );
        }
        $start = ProjectMeta::sanitize_date( $input['starts_on'] ?? '' );
        $end = ProjectMeta::sanitize_date( $input['ends_on'] ?? '' );
        if ( '' === $start || ( '' !== $end && $end < $start ) ) { return new \WP_Error( 'work_service_dates_invalid' ); }
        $label = substr( sanitize_text_field( (string) ( $input['label'] ?? '' ) ), 0, 190 );
        global $wpdb;
        $now = current_time( 'mysql', true );
        $created = $wpdb->insert( Schema::engagements_table(), [
            'project_id' => $project_id, 'service_id' => $service_id,
            'customer_provider' => $provider, 'customer_type' => $type, 'customer_id' => $customer_id,
            'label' => $label, 'starts_on' => $start, 'ends_on' => '' === $end ? null : $end,
            'is_active' => 1, 'revision' => 1, 'created_by' => $actor, 'updated_by' => $actor,
            'created_at' => $now, 'updated_at' => $now,
        ] );
        return false === $created ? new \WP_Error( 'work_service_create_failed' ) : (int) $wpdb->insert_id;
    }

    /** Bind a rule exactly once, without claiming history before the effective date. */
    public static function bind_rule( int $engagement_id, int $rule_id, string $effective_from, int $actor ): bool|\WP_Error {
        $engagement = self::get( $engagement_id );
        $rule = RecurrenceRules::get( $rule_id );
        $date = ProjectMeta::sanitize_date( $effective_from );
        if ( ! $engagement || ! $rule || ! empty( $rule['is_trashed'] ) || $actor <= 0 || '' === $date ) {
            return new \WP_Error( 'work_service_rule_invalid' );
        }
        if ( (int) ( $rule['project_id'] ?? 0 ) !== (int) $engagement['project_id']
            || (int) ( $rule['service_id'] ?? 0 ) !== (int) $engagement['service_id']
            || (string) $rule['customer_provider'] !== $engagement['customer_provider']
            || (string) $rule['customer_type'] !== $engagement['customer_type']
            || (string) $rule['customer_id'] !== $engagement['customer_id']
            || $date < $engagement['starts_on']
            || ( null !== $engagement['ends_on'] && $date > $engagement['ends_on'] ) ) {
            return new \WP_Error( 'work_service_rule_context_mismatch' );
        }
        // Never silently absorb historical occurrences: the effective date is explicit.
        global $wpdb;
        return WorkSqlTransaction::run( static function () use ( $wpdb, $engagement_id, $rule_id, $date, $actor ): bool|\WP_Error {
            $table = Schema::engagement_rules_table();
            $old = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $table . ' WHERE rule_id = %d FOR UPDATE', $rule_id ), ARRAY_A );
            if ( is_array( $old ) ) {
                return (int) $old['engagement_id'] === $engagement_id && (string) $old['effective_from'] === $date
                    ? true : new \WP_Error( 'work_service_rule_already_bound' );
            }
            return 1 === $wpdb->insert( $table, [
                'rule_id' => $rule_id, 'engagement_id' => $engagement_id,
                'effective_from' => $date, 'linked_by' => $actor, 'linked_at' => current_time( 'mysql', true ),
            ] ) ? true : new \WP_Error( 'work_service_rule_bind_failed' );
        }, new \WP_Error( 'work_service_rule_bind_failed' ) );
    }

    public static function rules( int $engagement_id ): array|\WP_Error {
        if ( ! self::ready() ) { return new \WP_Error( 'work_service_schema_unavailable' ); }
        global $wpdb;
        $rows = $wpdb->get_results( $wpdb->prepare(
            'SELECT rule_id, effective_from FROM ' . Schema::engagement_rules_table() . ' WHERE engagement_id = %d ORDER BY rule_id ASC',
            $engagement_id
        ), ARRAY_A );
        if ( ! is_array( $rows ) || '' !== (string) $wpdb->last_error ) { return new \WP_Error( 'work_service_rules_failed' ); }
        return $rows;
    }

    public static function hydrate( array $row ): array {
        return [
            'id' => (int) $row['id'], 'project_id' => (int) $row['project_id'],
            'service_id' => (int) $row['service_id'],
            'customer_provider' => (string) $row['customer_provider'],
            'customer_type' => (string) $row['customer_type'],
            'customer_id' => (string) $row['customer_id'],
            'label' => (string) $row['label'], 'starts_on' => (string) $row['starts_on'],
            'ends_on' => $row['ends_on'] === null ? null : (string) $row['ends_on'],
            'is_active' => (bool) $row['is_active'], 'revision' => (int) $row['revision'],
        ];
    }
}

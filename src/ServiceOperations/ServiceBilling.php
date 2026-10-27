<?php
declare(strict_types=1);

namespace CB\Work\ServiceOperations;

use CB\Work\Content\ProjectMeta;
use CB\Work\Database\Schema;
use CB\Work\Domain\WorkContext;
use CB\Work\Domain\RecurrenceSchedule;
use CB\Work\Repository\WorkSqlTransaction;

defined( 'ABSPATH' ) || exit;

/**
 * Operator-attested external invoicing for independent service coverage windows.
 * These are NOT financial invoices and never modify BillingUnits.
 */
final class ServiceBilling {
    private const FREQUENCIES = [ 'weekly', 'monthly', 'quarterly', 'yearly' ];

    /** Initial terms; later versions remain immutable historical agreements. */
    public static function create_terms( int $engagement_id, string $frequency, string $anchor_on, string $timing, int $actor ): int|\WP_Error {
        $engagement = Engagements::get( $engagement_id );
        if ( ! $engagement || WorkContext::CUSTOMER !== $engagement['work_context'] ) {
            return new \WP_Error( 'work_service_billing_not_applicable' );
        }
        $date = ProjectMeta::sanitize_date( $anchor_on );
        $frequency = sanitize_key( $frequency );
        $timing = sanitize_key( $timing );
        if ( null === $engagement || ! in_array( $frequency, self::FREQUENCIES, true )
            || ! in_array( $timing, [ 'advance', 'arrears' ], true )
            || '' === $date || $date < $engagement['starts_on'] || $actor <= 0 ) {
            return new \WP_Error( 'work_service_terms_invalid' );
        }
        global $wpdb;
        return WorkSqlTransaction::run( static function () use ( $wpdb, $engagement_id, $frequency, $date, $timing, $actor ): int|\WP_Error {
            $locked = $wpdb->get_var( $wpdb->prepare(
                'SELECT id FROM ' . Schema::engagements_table() . ' WHERE id = %d FOR UPDATE', $engagement_id
            ) );
            if ( (int) $locked !== $engagement_id ) { return new \WP_Error( 'work_service_missing' ); }
            $exists = $wpdb->get_var( $wpdb->prepare(
                'SELECT id FROM ' . Schema::service_billing_terms_table() . ' WHERE engagement_id = %d LIMIT 1', $engagement_id
            ) );
            if ( $exists ) { return new \WP_Error( 'work_service_terms_already_set' ); }
            $ok = $wpdb->insert( Schema::service_billing_terms_table(), [
                'engagement_id' => $engagement_id, 'frequency' => $frequency, 'interval_count' => 1,
                'anchor_on' => $date, 'effective_from' => $date, 'effective_until' => null,
                'invoice_timing' => $timing, 'created_by' => $actor, 'created_at' => current_time( 'mysql', true ),
            ] );
            return 1 === $ok ? (int) $wpdb->insert_id : new \WP_Error( 'work_service_terms_save_failed' );
        }, new \WP_Error( 'work_service_terms_save_failed' ) );
    }

    /**
     * Return the one billing window covering the selected month start, or the
     * first window beginning within that month for a newly started engagement.
     * Coverage uses the terms' own anchor, not recurrence rule dates.
     */
    public static function for_month( int $engagement_id, string $month ): array|\WP_Error {
        $bounds = Periods::bounds( $month );
        if ( ! $bounds ) { return new \WP_Error( 'work_service_period_invalid' ); }
        $engagement = Engagements::get( $engagement_id );
        if ( ! $engagement ) { return new \WP_Error( 'work_service_not_found' ); }
        if ( WorkContext::INTERNAL === $engagement['work_context'] ) {
            return [ 'state' => 'not_applicable', 'registration' => null ];
        }
        if ( WorkContext::CUSTOMER !== $engagement['work_context'] ) {
            return new \WP_Error( 'work_service_context_invalid' );
        }
        global $wpdb;
        $terms = $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . Schema::service_billing_terms_table()
            . ' WHERE engagement_id = %d AND effective_from < %s AND (effective_until IS NULL OR effective_until >= %s)'
            . ' ORDER BY effective_from DESC, id DESC LIMIT 1',
            $engagement_id, $bounds[1], $bounds[0]
        ), ARRAY_A );
        if ( '' !== (string) $wpdb->last_error ) { return new \WP_Error( 'work_service_terms_query_failed' ); }
        if ( ! is_array( $terms ) ) { return [ 'state' => 'not_applicable', 'registration' => null ]; }

        $anchor = (string) $terms['anchor_on'];
        // Report the latest coverage START within the selected calendar month,
        // otherwise the cycle covering that month. A 26th-of-month anchor must
        // not keep displaying last month's invoice throughout the next month.
        $last_day = (new \DateTimeImmutable( $bounds[1] ))->modify( '-1 day' )->format( 'Y-m-d' );
        $target = max( $last_day, (string) $terms['effective_from'], $anchor, (string) $engagement['starts_on'] );
        if ( (string) $engagement['starts_on'] >= $bounds[1] || $target >= $bounds[1] ) { return [ 'state' => 'not_applicable', 'registration' => null ]; }
        $freq = (string) $terms['frequency'];
        $interval = (int) $terms['interval_count'];
        $schedule_freq = 'quarterly' === $freq ? RecurrenceSchedule::MONTHLY : $freq;
        $schedule_interval = 'quarterly' === $freq ? $interval * 3 : $interval;
        if ( null === RecurrenceSchedule::normalize( $schedule_freq, $schedule_interval, $anchor ) ) {
            return new \WP_Error( 'work_service_terms_corrupt' );
        }
        $cursor = $anchor;
        $next = RecurrenceSchedule::next_after( $anchor, $cursor, $schedule_freq, $schedule_interval );
        $steps = 0;
        while ( null !== $next && $next <= $target && $steps < 2400 ) {
            $cursor = $next;
            $next = RecurrenceSchedule::next_after( $anchor, $cursor, $schedule_freq, $schedule_interval );
            $steps++;
        }
        if ( null === $next || $next <= $target ) { return new \WP_Error( 'work_service_terms_out_of_range' ); }
        // Term changes cannot retroactively rewrite the coverage of a prior term.
        if ( null !== $terms['effective_until'] && $cursor > (string) $terms['effective_until'] ) {
            return [ 'state' => 'not_applicable', 'registration' => null ];
        }
        $row = $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . Schema::service_billing_registrations_table()
            . ' WHERE billing_terms_id = %d AND period_start = %s',
            (int) $terms['id'], $cursor
        ), ARRAY_A );
        if ( '' !== (string) $wpdb->last_error ) { return new \WP_Error( 'work_service_registration_query_failed' ); }
        $registration = is_array( $row ) ? self::registration( $row ) : null;
        return [
            'state' => $registration['state'] ?? 'not_recorded',
            'terms_id' => (int) $terms['id'], 'engagement_id' => $engagement_id,
            'frequency' => $freq, 'invoice_timing' => (string) $terms['invoice_timing'],
            'period_start' => $cursor, 'period_end_exclusive' => $next,
            'registration' => $registration,
        ];
    }

    /**
     * CAS guard and append-only correction journal are inside one InnoDB transaction.
     * Multiple service coverages may legitimately share one external invoice reference.
     */
    public static function record( int $engagement_id, string $month, string $state, string $invoiced_on,
        string $reference, int $expected_revision, int $actor ): array|\WP_Error {
        if ( $actor <= 0 || ! in_array( $state, [ 'invoiced', 'not_recorded' ], true ) || $expected_revision < 0 ) {
            return new \WP_Error( 'work_service_registration_invalid' );
        }
        $coverage = self::for_month( $engagement_id, $month );
        if ( is_wp_error( $coverage ) ) { return $coverage; }
        if ( 'not_applicable' === $coverage['state'] ) { return new \WP_Error( 'work_service_registration_not_applicable' ); }
        $reference = substr( sanitize_text_field( $reference ), 0, 190 );
        $date = '' === $invoiced_on ? null : ProjectMeta::sanitize_date( $invoiced_on );
        if ( null !== $date && '' === $date ) { return new \WP_Error( 'work_service_invoice_date_invalid' ); }
        if ( 'not_recorded' === $state ) { $date = null; $reference = ''; }
        global $wpdb;
        return WorkSqlTransaction::run( static function () use ( $wpdb, $coverage, $state, $date, $reference, $expected_revision, $actor ): array|\WP_Error {
            $table = Schema::service_billing_registrations_table();
            $row = $wpdb->get_row( $wpdb->prepare(
                'SELECT * FROM ' . $table . ' WHERE billing_terms_id = %d AND period_start = %s FOR UPDATE',
                $coverage['terms_id'], $coverage['period_start']
            ), ARRAY_A );
            if ( '' !== (string) $wpdb->last_error ) { return new \WP_Error( 'work_service_registration_query_failed' ); }
            $old = is_array( $row ) ? self::registration( $row ) : null;
            $revision = $old['revision'] ?? 0;
            $same = $old && $old['state'] === $state && $old['invoiced_on'] === $date
                && $old['external_reference'] === $reference;
            if ( $same && ( $expected_revision === $revision || $expected_revision === $revision - 1 ) ) {
                return [ 'outcome' => 'reused', 'registration' => $old ];
            }
            if ( $revision !== $expected_revision ) { return new \WP_Error( 'work_service_registration_stale' ); }
            $now = current_time( 'mysql', true );
            if ( $old ) {
                $ok = $wpdb->update( $table, [
                    'state' => $state, 'invoiced_on' => $date, 'external_reference' => $reference,
                    'revision' => $revision + 1, 'updated_by' => $actor, 'updated_at' => $now,
                ], [ 'id' => $old['id'], 'revision' => $revision ],
                    [ '%s', '%s', '%s', '%d', '%d', '%s' ], [ '%d', '%d' ] );
                if ( 1 !== $ok ) { return new \WP_Error( 'work_service_registration_conflict' ); }
                $id = (int) $old['id'];
            } else {
                $ok = $wpdb->insert( $table, [
                    'engagement_id' => $coverage['engagement_id'], 'billing_terms_id' => $coverage['terms_id'],
                    'period_start' => $coverage['period_start'], 'period_end_exclusive' => $coverage['period_end_exclusive'],
                    'state' => $state, 'invoiced_on' => $date, 'external_reference' => $reference,
                    'revision' => 1, 'recorded_by' => $actor, 'recorded_at' => $now,
                    'updated_by' => $actor, 'updated_at' => $now,
                ] );
                if ( 1 !== $ok ) { return new \WP_Error( 'work_service_registration_conflict' ); }
                $id = (int) $wpdb->insert_id;
            }
            $new_revision = $revision + 1;
            $journal = $wpdb->insert( Schema::service_billing_events_table(), [
                'registration_id' => $id, 'revision' => $new_revision,
                'previous_state' => $old['state'] ?? 'none', 'new_state' => $state,
                'actor_user_id' => $actor, 'created_at' => $now,
            ] );
            if ( 1 !== $journal ) { return new \WP_Error( 'work_service_registration_audit_failed' ); }
            $fresh = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $table . ' WHERE id = %d', $id ), ARRAY_A );
            if ( ! is_array( $fresh ) ) { return new \WP_Error( 'work_service_registration_readback_failed' ); }
            return [ 'outcome' => 'saved', 'registration' => self::registration( $fresh ) ];
        }, new \WP_Error( 'work_service_registration_failed' ) );
    }

    private static function registration( array $row ): array {
        return [
            'id' => (int) $row['id'], 'engagement_id' => (int) $row['engagement_id'],
            'billing_terms_id' => (int) $row['billing_terms_id'],
            'period_start' => (string) $row['period_start'],
            'period_end_exclusive' => (string) $row['period_end_exclusive'],
            'state' => (string) $row['state'],
            'invoiced_on' => $row['invoiced_on'] === null ? null : (string) $row['invoiced_on'],
            'external_reference' => (string) $row['external_reference'],
            'revision' => (int) $row['revision'],
            'recorded_by' => (int) $row['recorded_by'], 'updated_by' => (int) $row['updated_by'],
        ];
    }
}

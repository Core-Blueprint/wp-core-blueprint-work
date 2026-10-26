<?php
declare(strict_types=1);

namespace CB\Work\ServiceOperations;

use CB\Work\Database\Schema;
use CB\Work\Domain\RecurrenceSchedule;
use CB\Work\Domain\WorkItemStatus;
use CB\Work\Repository\RecurrenceRules;
use CB\Work\Repository\WorkItems;

defined( 'ABSPATH' ) || exit;

/**
 * Deterministic, read-only view of canonical rule occurrences and Work Items.
 * Does not advance a scheduler cursor or mutate Work Items.
 */
final class Periods {
    public static function bounds( string $month ): ?array {
        if ( ! preg_match( '/^[0-9]{4}-(0[1-9]|1[0-2])$/D', $month ) ) { return null; }
        $first = \DateTimeImmutable::createFromFormat( '!Y-m-d', $month . '-01' );
        return $first && $first->format( 'Y-m' ) === $month
            ? [ $first->format( 'Y-m-d' ), $first->modify( '+1 month' )->format( 'Y-m-d' ) ] : null;
    }

    /** Return the first scheduled occurrence on/after a calendar date. */
    public static function first_on_or_after( array $rule, string $date ): ?string {
        $start = (string) ( $rule['start_on'] ?? '' );
        $freq = (string) ( $rule['frequency'] ?? '' );
        $step = (int) ( $rule['interval_count'] ?? 0 );
        $end = $rule['end_on'] ?? null;
        if ( null === RecurrenceSchedule::normalize( $freq, $step, $start, $end ) ) { return null; }
        if ( $start >= $date ) { return $start; }
        $anchor = new \DateTimeImmutable( $start );
        $target = new \DateTimeImmutable( $date );
        if ( in_array( $freq, [ RecurrenceSchedule::DAILY, RecurrenceSchedule::WEEKLY ], true ) ) {
            $stride = $step * ( RecurrenceSchedule::WEEKLY === $freq ? 7 : 1 );
            $days = (int) $anchor->diff( $target )->format( '%a' );
            $cycles = intdiv( $days, $stride );
            $candidate = $anchor->modify( '+' . ( $cycles * $stride ) . ' days' );
            if ( $candidate < $target ) { $candidate = $candidate->modify( '+' . $stride . ' days' ); }
            $on = $candidate->format( 'Y-m-d' );
            return null !== $end && $on > $end ? null : $on;
        }
        // Existing RecurrenceSchedule keeps month-end and leap-day anchoring canonical.
        // Bounded iterations protect the request from pathological historical rules.
        $on = $start;
        for ( $i = 0; $i < 2400 && $on < $date; $i++ ) {
            $on = RecurrenceSchedule::next_after( $start, $on, $freq, $step, $end );
            if ( null === $on ) { return null; }
        }
        return null === $on || $on < $date ? null : $on;
    }

    /** @return array<string,mixed>|\WP_Error */
    public static function resolve( array $engagement, string $month, ?string $as_of = null ): array|\WP_Error {
        $bounds = self::bounds( $month );
        $as_of = $as_of ?? current_time( 'Y-m-d' );
        if ( ! $bounds || null === RecurrenceSchedule::add_days( $as_of, 0 ) ) {
            return new \WP_Error( 'work_service_period_invalid' );
        }
        [ $start, $end ] = $bounds;
        $start = max( $start, (string) $engagement['starts_on'] );
        $effective_end = $engagement['ends_on'] === null ? $end : min( $end, (new \DateTimeImmutable( $engagement['ends_on'] ))->modify( '+1 day' )->format( 'Y-m-d' ) );
        $empty = [ 'status' => 'not_scheduled', 'scheduled' => 0, 'completed' => 0,
            'planned' => 0, 'in_progress' => 0, 'blocked' => 0, 'overdue' => 0,
            'skipped' => 0, 'cancelled' => 0, 'missing' => 0,
            'last_execution' => null, 'next_execution' => null, 'work_item_ids' => [] ];
        if ( $start >= $effective_end ) { return $empty; }
        $links = Engagements::rules( (int) $engagement['id'] );
        if ( is_wp_error( $links ) ) { return $links; }
        $counts = $empty;
        $next = null;
        $last = null;
        $seen = [];
        $unknown = false;
        global $wpdb;
        foreach ( $links as $link ) {
            $rule_id = (int) $link['rule_id'];
            $rule = RecurrenceRules::get( $rule_id );
            if ( null === $rule ) { $unknown = true; continue; }
            if ( (int) ( $rule['service_id'] ?? 0 ) !== (int) $engagement['service_id']
                || (int) ( $rule['project_id'] ?? 0 ) !== (int) $engagement['project_id']
                || (string) $rule['customer_provider'] !== (string) $engagement['customer_provider']
                || (string) $rule['customer_type'] !== (string) $engagement['customer_type']
                || (string) $rule['customer_id'] !== (string) $engagement['customer_id'] ) {
                $unknown = true;
                continue;
            }
            $from = max( $start, (string) $link['effective_from'] );
            $rows = $wpdb->get_results( $wpdb->prepare(
                'SELECT occurrence_on, work_item_id FROM ' . Schema::recurrence_occurrences_table()
                . ' WHERE rule_id = %d AND occurrence_on >= %s AND occurrence_on < %s ORDER BY occurrence_on ASC LIMIT 501',
                $rule_id, $from, $effective_end
            ), ARRAY_A );
            if ( ! is_array( $rows ) || '' !== (string) $wpdb->last_error || count( $rows ) > 500 ) {
                return new \WP_Error( 'work_service_occurrence_query_failed' );
            }
            $ledger = [];
            foreach ( $rows as $row ) { $ledger[ (string) $row['occurrence_on'] ] = (int) $row['work_item_id']; }

            $expected = [];
            if ( ! empty( $rule['is_active'] ) && empty( $rule['is_trashed'] ) ) {
                $cursor = self::first_on_or_after( $rule, $from );
                for ( $i = 0; null !== $cursor && $cursor < $effective_end && $i < 500; $i++ ) {
                    $expected[$cursor] = true;
                    $cursor = RecurrenceSchedule::next_after(
                        $rule['start_on'], $cursor, $rule['frequency'], $rule['interval_count'], $rule['end_on']
                    );
                }
                if ( null !== $cursor && $cursor < $effective_end ) {
                    return new \WP_Error( 'work_service_period_too_dense' );
                }
                $candidate = self::first_on_or_after( $rule, max( $as_of, (string) $link['effective_from'] ) );
                if ( null !== $candidate && ( null === $next || $candidate < $next ) ) {
                    $next = $candidate;
                }
            }
            // Historical generated work must remain visible even after a rule is deactivated.
            foreach ( $ledger as $on => $_ ) { $expected[$on] = true; }
            foreach ( array_keys( $expected ) as $on ) {
                $key = $rule_id . ':' . $on;
                if ( isset( $seen[ $key ] ) ) { continue; }
                $seen[ $key ] = true;
                $counts['scheduled']++;
                $item_id = $ledger[$on] ?? 0;
                $item = $item_id > 0 ? WorkItems::get( $item_id ) : null;
                if ( null === $item ) {
                    $counts['missing']++;
                    if ( $on < $as_of ) { $counts['overdue']++; }
                    continue;
                }
                $counts['work_item_ids'][] = $item_id;
                $status = (string) ( $item['status'] ?? '' );
                switch ( $status ) {
                    case WorkItemStatus::COMPLETED:
                        $counts['completed']++;
                        break;
                    case WorkItemStatus::SKIPPED:
                        $counts['skipped']++;
                        break;
                    case WorkItemStatus::CANCELLED:
                        $counts['cancelled']++;
                        break;
                    default:
                        if ( WorkItemStatus::BLOCKED === $status ) { $counts['blocked']++; }
                        elseif ( WorkItemStatus::IN_PROGRESS === $status ) { $counts['in_progress']++; }
                        else { $counts['planned']++; }
                        $due = (string) ( $item['due_on'] ?? $on );
                        if ( '' === $due ) { $due = $on; }
                        if ( $due < $as_of ) { $counts['overdue']++; }
                }
            }

            $latest = $wpdb->get_var( $wpdb->prepare(
                'SELECT MAX(c.meta_value) FROM ' . Schema::recurrence_occurrences_table() . ' o'
                . ' INNER JOIN ' . $wpdb->posts . ' p ON p.ID = o.work_item_id AND p.post_type = %s AND p.post_status NOT IN (%s,%s)'
                . ' INNER JOIN ' . $wpdb->postmeta . ' s ON s.post_id = p.ID AND s.meta_key = %s AND s.meta_value = %s'
                . ' INNER JOIN ' . $wpdb->postmeta . ' c ON c.post_id = p.ID AND c.meta_key = %s'
                . ' WHERE o.rule_id = %d AND o.occurrence_on >= %s',
                'cb_work_item', 'trash', 'auto-draft', '_cb_work_item_status', WorkItemStatus::COMPLETED,
                '_cb_work_item_completed_at', $rule_id, (string) $link['effective_from']
            ) );
            if ( '' !== (string) $wpdb->last_error ) { return new \WP_Error( 'work_service_last_query_failed' ); }
            if ( is_string( $latest ) && '' !== $latest && ( null === $last || $latest > $last ) ) { $last = $latest; }
        }
        $counts['last_execution'] = $last;
        $counts['next_execution'] = $next;
        if ( $unknown ) { $counts['status'] = 'attention'; }
        elseif ( $counts['overdue'] > 0 ) { $counts['status'] = 'overdue'; }
        elseif ( $counts['blocked'] > 0 ) { $counts['status'] = 'blocked'; }
        elseif ( $counts['in_progress'] > 0 ) { $counts['status'] = 'in_progress'; }
        elseif ( $counts['missing'] > 0 && $start <= $as_of ) { $counts['status'] = 'attention'; }
        elseif ( $counts['planned'] > 0 || $counts['missing'] > 0 ) { $counts['status'] = 'planned'; }
        elseif ( $counts['skipped'] > 0 || $counts['cancelled'] > 0 ) { $counts['status'] = 'not_delivered'; }
        elseif ( $counts['scheduled'] > 0 && $counts['completed'] === $counts['scheduled'] ) { $counts['status'] = 'completed'; }
        return $counts;
    }
}

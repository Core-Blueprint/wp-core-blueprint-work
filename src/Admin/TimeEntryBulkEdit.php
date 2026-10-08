<?php
declare(strict_types=1);

namespace CB\Work\Admin;

use CoreBlueprint\Core\Governance\Audit;
use CB\Work\Governance\Events;
use CB\Work\Repository\TimeEntries;
use CB\Work\Time\Access;

defined( 'ABSPATH' ) || exit;

/**
 * T2-C: bounded, revision-guarded bulk correction of completed Time entries.
 * All changes use the canonical single-entry repository compare-and-swap.
 * Work Item and notes are the only bulk-editable fields. Timestamps and
 * owner, and therefore second precision, are never rewritten semantically.
 */
final class TimeEntryBulkEdit {
    public const ACTION = 'cb_work_bulk_update_time_entries';
    private const LIMIT = 25;

    public static function init(): void {
        add_action( 'admin_post_' . self::ACTION, [ self::class, 'handle' ] );
    }

    public static function handle(): never {
        if ( ! Access::can_track() ) {
            wp_die( esc_html__( 'You do not have permission to track Work time.', 'core-blueprint-work' ) );
        }
        check_admin_referer( self::ACTION );

        $state = TimeEntryListState::from_request(
            isset( $_POST['time_list'] ) && is_array( $_POST['time_list'] )
                ? wp_unslash( $_POST['time_list'] )
                : [],
            Access::can_manage()
        );

        $raw_ids = $_POST['entry_ids'] ?? [];
        if ( ! is_array( $raw_ids ) || [] === $raw_ids || count( $raw_ids ) > self::LIMIT ) {
            self::redirect( $state, 'time-bulk-invalid' );
        }
        $ids = [];
        foreach ( $raw_ids as $value ) {
            if ( ! is_scalar( $value ) || ! ctype_digit( (string) $value ) || (int) $value <= 0 ) {
                self::redirect( $state, 'time-bulk-invalid' );
            }
            $id = (int) $value;
            if ( isset( $ids[ $id ] ) ) {
                self::redirect( $state, 'time-bulk-invalid' );
            }
            $ids[ $id ] = $id;
        }

        $raw_revisions = $_POST['entry_revisions'] ?? [];
        if ( ! is_array( $raw_revisions ) ) {
            self::redirect( $state, 'time-bulk-invalid' );
        }
        $mode_raw = $_POST['note_mode'] ?? '';
        $mode = is_scalar( $mode_raw ) ? sanitize_key( (string) wp_unslash( $mode_raw ) ) : '';
        $note_raw = $_POST['bulk_note'] ?? '';
        $note = is_string( $note_raw ) ? sanitize_textarea_field( wp_unslash( $note_raw ) ) : '';
        $target_raw = $_POST['bulk_work_item_id'] ?? '0';
        if ( ! is_scalar( $target_raw ) || ! ctype_digit( (string) $target_raw ) ) {
            self::redirect( $state, 'time-bulk-invalid' );
        }
        $target_id = (int) $target_raw;

        if ( ! in_array( $mode, [ 'keep', 'append', 'replace' ], true )
            || ( 'append' === $mode && '' === trim( $note ) )
            || ( 'keep' === $mode && 0 === $target_id )
            || ( $target_id > 0 && ! Access::can_manage() ) ) {
            self::redirect( $state, 'time-bulk-invalid' );
        }

        // Preflight *all* selected entries before the first write, so a stale
        // or unauthorized row cannot silently trigger a partial operation.
        $updates = [];
        foreach ( $ids as $id ) {
            $revision = $raw_revisions[ $id ] ?? null;
            if ( ! is_scalar( $revision ) || ! ctype_digit( (string) $revision ) || (int) $revision <= 0 ) {
                self::redirect( $state, 'time-bulk-invalid' );
            }
            $entry = TimeEntries::get( $id );
            if ( ! is_array( $entry ) || null === $entry['ended_at'] ) {
                self::redirect( $state, 'time-bulk-invalid' );
            }
            $work_item_id = $target_id > 0 ? $target_id : (int) $entry['work_item_id'];
            if ( ! Access::can_edit_entry( $entry, $work_item_id )
                || ! Access::can_track_work_item( $work_item_id, (int) $entry['user_id'] ) ) {
                self::redirect( $state, 'time-not-authorized' );
            }
            if ( (int) $entry['revision'] !== (int) $revision ) {
                self::redirect( $state, 'time-bulk-conflict' );
            }

            $updated_note = (string) $entry['note'];
            if ( 'replace' === $mode ) {
                $updated_note = $note;
            } elseif ( 'append' === $mode ) {
                $updated_note = '' === $updated_note ? $note : $updated_note . "\n" . $note;
            }
            $length = function_exists( 'mb_strlen' ) ? mb_strlen( $updated_note ) : strlen( $updated_note );
            if ( $length > 4000 ) {
                self::redirect( $state, 'time-bulk-invalid' );
            }
            if ( $work_item_id !== (int) $entry['work_item_id'] || $updated_note !== (string) $entry['note'] ) {
                $updates[] = [ 'entry' => $entry, 'revision' => (int) $revision, 'work_item_id' => $work_item_id, 'note' => $updated_note ];
            }
        }

        if ( [] === $updates ) {
            self::redirect( $state, 'time-bulk-invalid' );
        }

        $updated = 0;
        $failed = 0;
        $actor = get_current_user_id();
        foreach ( $updates as $change ) {
            $entry = $change['entry'];
            $id = (int) $entry['id'];
            if ( ! TimeEntries::update_completed(
                $id,
                $change['revision'],
                $change['work_item_id'],
                (int) $entry['user_id'],
                (string) $entry['started_at'],
                (string) $entry['ended_at'],
                (string) $change['note'],
                $actor
            ) ) {
                ++$failed;
                continue;
            }
            ++$updated;
            $fresh = TimeEntries::get( $id );
            Audit::record( Events::TIME_ENTRY_UPDATED, 'notice', [
                'time_entry_id'   => $id,
                'work_item_id'    => $change['work_item_id'],
                'user_id'         => (int) $entry['user_id'],
                'duration_seconds' => (int) ( $fresh['duration_seconds'] ?? $entry['duration_seconds'] ),
                'revision'        => (int) ( $fresh['revision'] ?? $change['revision'] + 1 ),
                'entry_source'    => (string) $entry['entry_source'],
                'bulk'            => true,
            ] );
        }

        self::redirect(
            $state,
            $failed > 0 ? ( $updated > 0 ? 'time-bulk-partial' : 'time-bulk-conflict' ) : 'time-bulk-updated',
            $updated,
            $failed
        );
    }

    /** @param array<string,mixed> $state */
    private static function redirect( array $state, string $notice, int $updated = 0, int $failed = 0 ): never {
        wp_safe_redirect( Menu::time_url( [
            ...TimeEntryListState::url_args( $state ),
            'cb-work-notice' => $notice,
            'te_updated' => $updated,
            'te_failed' => $failed,
        ] ) );
        exit;
    }

    private function __construct() {}
}

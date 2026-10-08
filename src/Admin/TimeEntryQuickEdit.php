<?php
declare(strict_types=1);

namespace CB\Work\Admin;

use CB\Work\Domain\TimeRange;

defined( 'ABSPATH' ) || exit;

/**
 * Edit a single completed entry inline, using the canonical revision-guarded
 * admin-post correction action. No duplicate write path is introduced.
 */
final class TimeEntryQuickEdit {
    /** @param array<string,mixed> $entry @param array<string,mixed> $state */
    public static function render( array $entry, array $state ): void {
        $id = (int) $entry['id'];
        if ( $id <= 0 || null === $entry['ended_at'] ) {
            return;
        }

        $start = self::local_parts( (string) $entry['started_at'] );
        $end = self::local_parts( (string) $entry['ended_at'] );
        if ( null === $start || null === $end ) {
            return;
        }

        $cancel = Menu::time_url( TimeEntryListState::url_args( $state ) );
        ?>
        <div id="cb-work-time-quick-edit-<?php echo esc_attr( (string) $id ); ?>" class="cb-work-time-quick-edit" data-cb-work-time-quick-edit>
            <h3><?php esc_html_e( 'Quick Edit', 'core-blueprint-work' ); ?></h3>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="cb_work_update_time_entry">
                <input type="hidden" name="cb_work_quick_edit" value="1">
                <input type="hidden" name="entry_id" value="<?php echo esc_attr( (string) $id ); ?>">
                <input type="hidden" name="time[revision]" value="<?php echo esc_attr( (string) $entry['revision'] ); ?>">
                <?php foreach ( TimeEntryListState::url_args( $state ) as $key => $value ) : ?>
                    <input type="hidden" name="time_list[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( (string) $value ); ?>">
                <?php endforeach; ?>
                <?php wp_nonce_field( 'cb_work_update_time_entry_' . $id ); ?>
                <div class="cb-work-time-quick-edit-fields">
                    <div class="cb-work-time-quick-edit-field">
                        <label for="cb-work-qe-start-date-<?php echo esc_attr( (string) $id ); ?>"><?php esc_html_e( 'Start', 'core-blueprint-work' ); ?></label>
                        <div class="cb-work-time-quick-edit-date-time">
                            <input type="date" id="cb-work-qe-start-date-<?php echo esc_attr( (string) $id ); ?>" name="time[start_date]" value="<?php echo esc_attr( $start['date'] ); ?>" required>
                            <input type="time" name="time[start_time]" step="1" value="<?php echo esc_attr( $start['time'] ); ?>" aria-label="<?php esc_attr_e( 'Start', 'core-blueprint-work' ); ?>" required>
                        </div>
                    </div>
                    <div class="cb-work-time-quick-edit-field">
                        <label for="cb-work-qe-end-date-<?php echo esc_attr( (string) $id ); ?>"><?php esc_html_e( 'End', 'core-blueprint-work' ); ?></label>
                        <div class="cb-work-time-quick-edit-date-time">
                            <input type="date" id="cb-work-qe-end-date-<?php echo esc_attr( (string) $id ); ?>" name="time[end_date]" value="<?php echo esc_attr( $end['date'] ); ?>" required>
                            <input type="time" name="time[end_time]" step="1" value="<?php echo esc_attr( $end['time'] ); ?>" aria-label="<?php esc_attr_e( 'End', 'core-blueprint-work' ); ?>" required>
                        </div>
                    </div>
                    <div class="cb-work-time-quick-edit-field cb-work-time-quick-edit-field--note">
                        <label for="cb-work-qe-note-<?php echo esc_attr( (string) $id ); ?>"><?php esc_html_e( 'Note', 'core-blueprint-work' ); ?></label>
                        <textarea id="cb-work-qe-note-<?php echo esc_attr( (string) $id ); ?>" name="time[note]" rows="2" maxlength="4000"><?php echo esc_textarea( (string) $entry['note'] ); ?></textarea>
                    </div>
                </div>
                <div class="cb-work-time-quick-edit-footer">
                    <span class="cb-work-time-quick-edit-duration">
                        <?php esc_html_e( 'Duration', 'core-blueprint-work' ); ?>:
                        <output data-cb-work-time-quick-edit-duration><?php echo esc_html( TimeEntryList::duration( (int) $entry['duration_seconds'] ) ); ?></output>
                    </span>
                    <div class="cb-work-time-quick-edit-actions">
                        <a class="button" href="<?php echo esc_url( $cancel ); ?>"><?php esc_html_e( 'Cancel', 'core-blueprint-work' ); ?></a>
                        <button type="submit" class="button button-primary"><?php esc_html_e( 'Save changes', 'core-blueprint-work' ); ?></button>
                    </div>
                </div>
            </form>
        </div>
        <?php
    }

    /** @return array{date:string,time:string}|null */
    private static function local_parts( string $value ): ?array {
        if ( ! TimeRange::valid_utc( $value ) ) {
            return null;
        }
        $local = ( new \DateTimeImmutable( $value, new \DateTimeZone( 'UTC' ) ) )->setTimezone( wp_timezone() );
        return [ 'date' => $local->format( 'Y-m-d' ), 'time' => $local->format( 'H:i:s' ) ];
    }
}

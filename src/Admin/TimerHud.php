<?php
declare(strict_types=1);

namespace CB\Work\Admin;

use CB\Work\Database\Schema;
use CB\Work\Governance\Events;
use CB\Work\Repository\Timers;
use CB\Work\Repository\WorkItems;
use CB\Work\Time\Access;
use CoreBlueprint\Core\Governance\Audit;

defined( 'ABSPATH' ) || exit;

/**
 * One global, self-user Time HUD in wp-admin.
 *
 * Timer state is always re-read from the server. The client is a display and
 * interaction layer; this class does not implement or emulate pause/resume.
 */
final class TimerHud {
    private const NONCE = 'cb_work_timer_hud';

    public static function init(): void {
        add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue' ] );
        add_action( 'admin_footer', [ self::class, 'render' ] );
        add_action( 'wp_ajax_cb_work_timer_hud_state', [ self::class, 'state' ] );
        add_action( 'wp_ajax_cb_work_timer_hud_stop', [ self::class, 'stop' ] );
        add_action( 'wp_ajax_cb_work_timer_hud_note', [ self::class, 'save_note' ] );
    }

    private static function available(): bool {
        return Access::can_track()
            && defined( 'CB_WORK_SCHEMA_VERSION' )
            && CB_WORK_SCHEMA_VERSION === (string) get_option( Schema::OPTION, '0' );
    }

    public static function enqueue(): void {
        if ( ! self::available() ) {
            return;
        }
        $css = CB_WORK_DIR . 'assets/global-time-hud.css';
        $js = CB_WORK_DIR . 'assets/global-time-hud.js';
        if ( ! is_file( $css ) || ! is_file( $js ) ) {
            return;
        }
        wp_enqueue_style( 'cb-work-global-time-hud', CB_WORK_URL . 'assets/global-time-hud.css', [], (string) filemtime( $css ) );
        wp_enqueue_script( 'cb-work-global-time-hud', CB_WORK_URL . 'assets/global-time-hud.js', [], (string) filemtime( $js ), true );
        wp_localize_script( 'cb-work-global-time-hud', 'cbWorkTimerHud', [
            'endpoint' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( self::NONCE ),
            'timeUrl' => Menu::time_url( [ 'view' => Time::VIEW_TIMER ] ),
            'initial' => self::payload(),
            'strings' => [
                'active' => __( 'Active timer', 'core-blueprint-work' ),
                'open' => __( 'Open timer controls', 'core-blueprint-work' ),
                'stopConfirm' => __( 'Stop this timer and save the time entry?', 'core-blueprint-work' ),
                'failed' => __( 'Timer could not be updated. Please try again.', 'core-blueprint-work' ),
                'noteSaved' => __( 'Note saved.', 'core-blueprint-work' ),
                'stopped' => __( 'Timer stopped and Time entry completed.', 'core-blueprint-work' ),
                'discardNote' => __( 'Discard unsaved note changes?', 'core-blueprint-work' ),
                'saveBeforeStop' => __( 'Save the note before stopping this timer.', 'core-blueprint-work' ),
                'syncFailed' => __( 'Timer status could not be refreshed.', 'core-blueprint-work' ),
            ],
        ] );
    }

    /** @return array<string, mixed> */
    private static function payload(): array {
        if ( ! self::available() ) {
            return [ 'active' => false ];
        }
        $timer = Timers::active_for_user( get_current_user_id() );
        if ( ! is_array( $timer ) ) {
            return [ 'active' => false ];
        }
        $id = (int) $timer['work_item_id'];
        $item = WorkItems::get( $id );
        $utc = (string) $timer['started_at'];
        // Timer records are stored as canonical UTC SQL datetime strings.
        $date = \DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $utc, new \DateTimeZone( 'UTC' ) );
        return [
            'active' => true,
            'entryId' => (int) $timer['time_entry_id'],
            'workItemId' => $id,
            'title' => (string) ( $item['title'] ?? sprintf( __( 'Work Item #%d', 'core-blueprint-work' ), $id ) ),
            'note' => (string) $timer['note'],
            'startedEpoch' => false !== $date ? $date->getTimestamp() : 0,
            'serverEpoch' => time(),
        ];
    }

    public static function render(): void {
        if ( ! self::available() ) {
            return;
        }
        $data = self::payload();
        ?>
        <aside class="cb-work-global-time-hud" data-cb-time-hud <?php if ( empty( $data['active'] ) ) : ?>hidden<?php endif; ?> aria-label="<?php esc_attr_e( 'Active timer', 'core-blueprint-work' ); ?>">
            <button class="cb-work-time-hud-chip" type="button" data-cb-time-hud-toggle aria-expanded="false" aria-controls="cb-work-time-hud-panel" aria-label="<?php esc_attr_e( 'Open timer controls', 'core-blueprint-work' ); ?>">
                <span class="cb-work-time-hud-dot" aria-hidden="true"></span>
                <span class="cb-work-time-hud-name" data-cb-time-hud-title><?php echo esc_html( (string) ( $data['title'] ?? '' ) ); ?></span>
                <time class="cb-work-time-hud-clock" data-cb-time-hud-clock aria-label="<?php esc_attr_e( 'Elapsed time', 'core-blueprint-work' ); ?>">00:00:00</time>
                <span aria-hidden="true">⌃</span>
            </button>
            <div class="cb-work-time-hud-panel" id="cb-work-time-hud-panel" data-cb-time-hud-panel hidden>
                <div class="cb-work-time-hud-heading">
                    <strong><?php esc_html_e( 'Active timer', 'core-blueprint-work' ); ?></strong>
                    <button type="button" class="cb-work-time-hud-close" data-cb-time-hud-close aria-label="<?php esc_attr_e( 'Close', 'core-blueprint-work' ); ?>">×</button>
                </div>
                <p class="cb-work-time-hud-project" data-cb-time-hud-detail-title><?php echo esc_html( (string) ( $data['title'] ?? '' ) ); ?></p>
                <time class="cb-work-time-hud-elapsed" data-cb-time-hud-elapsed aria-label="<?php esc_attr_e( 'Elapsed time', 'core-blueprint-work' ); ?>">00:00:00</time>
                <form data-cb-time-hud-note-form>
                    <label for="cb-work-time-hud-note"><?php esc_html_e( 'Note', 'core-blueprint-work' ); ?></label>
                    <textarea id="cb-work-time-hud-note" data-cb-time-hud-note rows="2" maxlength="4000"><?php echo esc_textarea( (string) ( $data['note'] ?? '' ) ); ?></textarea>
                    <button type="submit" class="button button-secondary" data-cb-time-hud-note-save><?php esc_html_e( 'Save note', 'core-blueprint-work' ); ?></button>
                </form>
                <div class="cb-work-time-hud-actions">
                    <a class="button" href="<?php echo esc_url( Menu::time_url( [ 'view' => Time::VIEW_TIMER ] ) ); ?>"><?php esc_html_e( 'Open Time workspace', 'core-blueprint-work' ); ?></a>
                    <button type="button" class="button button-primary" data-cb-time-hud-stop><?php esc_html_e( 'Stop Timer', 'core-blueprint-work' ); ?></button>
                </div>
                <p class="cb-work-time-hud-feedback" data-cb-time-hud-feedback role="status" aria-live="polite"></p>
            </div>
        </aside>
        <div class="cb-work-time-hud-toast" data-cb-time-hud-toast role="status" aria-live="polite" hidden></div>
        <?php
    }

    private static function guard(): void {
        if ( ! self::available() ) {
            wp_send_json_error( [ 'message' => __( 'You do not have permission to track Work time.', 'core-blueprint-work' ) ], 403 );
        }
        check_ajax_referer( self::NONCE, 'nonce' );
        nocache_headers();
    }

    public static function state(): void {
        self::guard();
        wp_send_json_success( self::payload() );
    }

    public static function stop(): void {
        self::guard();
        $actor = get_current_user_id();
        if ( ! Access::can_stop_user_timer( $actor ) ) {
            wp_send_json_error( [ 'message' => __( 'You do not have permission to track Work time.', 'core-blueprint-work' ) ], 403 );
        }
        $entry = Timers::stop( $actor, $actor );
        if ( ! is_array( $entry ) ) {
            wp_send_json_error( [ 'message' => __( 'The active timer could not be stopped safely.', 'core-blueprint-work' ) ], 409 );
        }
        Audit::record( Events::TIMER_STOPPED, 'notice', [
            'time_entry_id' => (int) $entry['id'],
            'work_item_id' => (int) $entry['work_item_id'],
            'user_id' => (int) $entry['user_id'],
            'duration_seconds' => (int) $entry['duration_seconds'],
        ] );
        wp_send_json_success( self::payload() );
    }

    public static function save_note(): void {
        self::guard();
        $actor = get_current_user_id();
        $entry_id = isset( $_POST['entry_id'] ) ? absint( $_POST['entry_id'] ) : 0;
        $note = isset( $_POST['note'] ) && is_string( $_POST['note'] )
            ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) )
            : '';
        if ( ! Timers::update_active_note( $actor, $entry_id, $note, $actor ) ) {
            wp_send_json_error( [ 'message' => __( 'Timer could not be updated. Please try again.', 'core-blueprint-work' ) ], 409 );
        }
        Audit::record( Events::TIME_ENTRY_UPDATED, 'notice', [
            'time_entry_id' => $entry_id,
            'user_id' => $actor,
            'field' => 'note',
        ] );
        wp_send_json_success( self::payload() );
    }
}

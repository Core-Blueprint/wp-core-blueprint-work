<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$hud = file_get_contents( $root . '/src/Admin/TimerHud.php' );
$timers = file_get_contents( $root . '/src/Repository/Timers.php' );
$plugin = file_get_contents( $root . '/src/Plugin.php' );
$js = file_get_contents( $root . '/assets/global-time-hud.js' );
$css = file_get_contents( $root . '/assets/global-time-hud.css' );
$time = file_get_contents( $root . '/src/Admin/Time.php' );
$actions = file_get_contents( $root . '/src/Admin/TimeActions.php' );

$checks = [
    'global wp-admin HUD registers authenticated server actions and presentation hooks' =>
        str_contains( $plugin, 'TimerHud::init();' )
        && str_contains( $hud, "add_action( 'admin_enqueue_scripts'" )
        && str_contains( $hud, "add_action( 'admin_footer'" )
        && str_contains( $hud, "add_action( 'wp_ajax_cb_work_timer_hud_state'" )
        && str_contains( $hud, "add_action( 'wp_ajax_cb_work_timer_hud_stop'" )
        && str_contains( $hud, "add_action( 'wp_ajax_cb_work_timer_hud_note'" )
        && ! str_contains( $hud, "wp_ajax_nopriv_" ),
    'HUD remains capability- and schema-bound on every wp-admin screen' =>
        str_contains( $hud, 'Access::can_track()' )
        && str_contains( $hud, "defined( 'CB_WORK_SCHEMA_VERSION' )" )
        && str_contains( $hud, "get_option( Schema::OPTION, '0' )" )
        && str_contains( $hud, 'if ( ! self::available() )' ),
    'server state belongs to the current user and UTC remains authoritative' =>
        str_contains( $hud, 'Timers::active_for_user( get_current_user_id() )' )
        && str_contains( $hud, "new \\DateTimeZone( 'UTC' )" )
        && str_contains( $hud, "'serverEpoch' => time()" )
        && str_contains( $hud, "'startedEpoch' =>" )
        && str_contains( $js, 'elapsedAtSync = Math.max(0, Number(next.serverEpoch) - Number(next.startedEpoch));' ),
    'all AJAX mutations have own-user, nonce and explicit permission checks' =>
        str_contains( $hud, "check_ajax_referer( self::NONCE, 'nonce' )" )
        && str_contains( $hud, 'Access::can_stop_user_timer( $actor )' )
        && str_contains( $hud, 'Timers::stop( $actor, $actor )' )
        && str_contains( $hud, 'Timers::update_active_note( $actor, $entry_id, $note, $actor )' )
        && str_contains( $hud, 'wp_send_json_error' )
        && str_contains( $hud, 'Audit::record( Events::TIMER_STOPPED' )
        && str_contains( $hud, 'Audit::record( Events::TIME_ENTRY_UPDATED' )
        && ! preg_match( "/Audit::record\\([^;]*['\"]note['\"]\\s*=>/s", $hud ),
    'active note edit uses transaction locks, entry matching and a safe revision increment' =>
        str_contains( $timers, 'public static function update_active_note(' )
        && str_contains( $timers, '$actor_user_id !== $user_id' )
        && str_contains( $timers, "FOR UPDATE" )
        && str_contains( $timers, '(int) $timer[\'time_entry_id\'] !== $entry_id' )
        && str_contains( $timers, "TimeEntries::SOURCE_TIMER" )
        && str_contains( $timers, 'null !== $entry[' )
        && str_contains( $timers, '1 + (int) $entry[\'revision\']' )
        && str_contains( $timers, "'ROLLBACK'" )
        && str_contains( $timers, "'COMMIT'" ),
    'HUD is keyboard operable, focus restored and note edits cannot be silently discarded' =>
        str_contains( $hud, 'aria-controls="cb-work-time-hud-panel"' )
        && str_contains( $hud, 'aria-expanded="false"' )
        && str_contains( $hud, 'aria-live="polite"' )
        && str_contains( $js, "event.key === 'Escape'" )
        && str_contains( $js, "toggle.focus();" )
        && str_contains( $js, 'noteDirty && !window.confirm' )
        && str_contains( $js, 'if (noteDirty)' )
        && str_contains( $js, 'if (!window.confirm(config.strings.stopConfirm)) return;' ),
    'state refresh, error handling and click-away closure reconcile multiple tabs' =>
        str_contains( $js, "document.visibilityState === 'hidden'" )
        && str_contains( $js, 'window.setInterval(clock, 1000)' )
        && str_contains( $js, 'window.addEventListener(\'focus\', sync)' )
        && str_contains( $js, "document.addEventListener('visibilitychange'" )
        && str_contains( $js, "document.addEventListener('pointerdown'" )
        && str_contains( $js, 'credentials: \'same-origin\'' )
        && str_contains( $js, "cache: 'no-store'" ),
    'HUD owns its styling and stays docked above existing global utility' =>
        str_contains( $css, '.cb-work-global-time-hud {' )
        && str_contains( $css, 'inset-block-end: 88px' )
        && str_contains( $css, '.cb-work-global-time-hud :is(button, a, textarea):focus-visible' )
        && str_contains( $css, '@media (max-width: 782px)' ),
    'Time workspace and existing server start/stop are preserved; pause is not simulated' =>
        str_contains( $time, 'self::render_active_timer_status( $active, $view );' )
        && str_contains( $actions, "Timers::stop( $user_id, get_current_user_id() )" )
        && ! str_contains( $hud, 'pause_timer' )
        && ! str_contains( $js, 'pauseTimer' ),
];

foreach ( $checks as $label => $passed ) {
    if ( ! $passed ) {
        fwrite( STDERR, "Global Timer HUD smoke FAILED: {$label}\n" );
        exit( 1 );
    }
}
echo "Global Timer HUD smoke passed.\n";

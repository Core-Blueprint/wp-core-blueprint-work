<?php
declare(strict_types=1);

define( 'ABSPATH', '/tmp/wp/' );
function wp_unslash( string $value ): string { return $value; }
function sanitize_key( string $value ): string {
    return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ) ?? '';
}
function absint( mixed $value ): int { return abs( (int) $value ); }

$root = dirname( __DIR__ );
require $root . '/src/Admin/Time.php';

use CB\Work\Admin\Time;

$admin = file_get_contents( $root . '/src/Admin/Time.php' );
$entries = file_get_contents( $root . '/src/Admin/TimeEntryList.php' );
$actions = file_get_contents( $root . '/src/Admin/TimeActions.php' );
$menu = file_get_contents( $root . '/src/Admin/Menu.php' );
$style = file_get_contents( $root . '/assets/time-workspace.css' );
$method = new ReflectionMethod( Time::class, 'requested_view' );

$view_cases = [
    [ [], Time::VIEW_TIMER ],
    [ [ 'view' => 'timer' ], Time::VIEW_TIMER ],
    [ [ 'view' => 'manual' ], Time::VIEW_MANUAL ],
    [ [ 'view' => 'entries' ], Time::VIEW_ENTRIES ],
    [ [ 'view' => 'unknown' ], Time::VIEW_TIMER ],
    [ [ 'view' => '<script>' ], Time::VIEW_TIMER ],
    [ [ 'view' => [ 'entries' ] ], Time::VIEW_TIMER ],
    [ [ 'view' => 'entries', 'entry_id' => '12' ], Time::VIEW_MANUAL ],
    [ [ 'entry_id' => '9' ], Time::VIEW_MANUAL ],
    [ [ 'entry_id' => [ '9' ] ], Time::VIEW_TIMER ],
    [ [ 'entry_id' => '-9' ], Time::VIEW_TIMER ],
    [ [ 'entry_id' => '9x' ], Time::VIEW_TIMER ],
];
$view_cases_pass = true;
foreach ( $view_cases as [ $request, $expected ] ) {
    $_GET = $request;
    if ( $method->invoke( null ) !== $expected ) {
        $view_cases_pass = false;
        break;
    }
}
$_GET = [];

// Stale redirects must never contradict the current server-owned timer.
$notice_method = new ReflectionMethod( Time::class, 'notice_matches_timer_state' );
$running = [ 'time_entry_id' => 19 ];
$notice_cases = [
    [ 'timer-stopped', $running, false ],
    [ 'timer-stopped', null, true ],
    [ 'timer-started', $running, true ],
    [ 'timer-started', null, false ],
    [ 'timer-stop-failed', $running, true ],
    [ 'timer-start-failed', null, true ],
    [ 'time-created', null, true ],
];
$notice_cases_pass = true;
foreach ( $notice_cases as [ $notice, $active, $expected ] ) {
    if ( $notice_method->invoke( null, $notice, $active ) !== $expected ) {
        $notice_cases_pass = false;
        break;
    }
}

$checks = [
    'T1-B old start and stop redirect notices never contradict server timer state' => $notice_cases_pass
        && str_contains( $admin, 'self::render_notice( $active );' )
        && strpos( $admin, 'self::render_notice( $active );' ) > strpos( $admin, '$active = Timers::active_for_user( $user_id );' )
        && str_contains( $admin, 'self::notice_matches_timer_state( $notice, $active )' ),
    'view routing recognizes only three canonical views and forces correction context' => $view_cases_pass,
    'entry correction preserves original Work Item beyond the first 500' =>
        str_contains( $admin, "array_column( \$items, 'id' )" )
        && str_contains( $admin, 'WorkItems::get( $selected_id )' )
        && str_contains( $admin, '$items[] = $original_item;' )
        && str_contains( $admin, 'self::requested_entry_id()' ),
    'Time tabs are links with distinct URLs and an accessible active state' => str_contains( $admin, 'cb-work-time-navigation' )
        && str_contains( $admin, 'aria-label="<?php esc_attr_e( \'Time views\'' )
        && str_contains( $admin, 'aria-current="page"' )
        && str_contains( $admin, "self::url( [ 'view' => \$view ] )" )
        && str_contains( $admin, 'self::VIEW_TIMER, self::VIEW_MANUAL, self::VIEW_ENTRIES' ),
    'only the chosen panel is rendered and entries are fetched only in its view' => str_contains( $admin, 'switch ( $view )' )
        && str_contains( $admin, 'case self::VIEW_MANUAL:' )
        && str_contains( $admin, 'case self::VIEW_ENTRIES:' )
        && str_contains( $admin, 'TimeEntryList::render( $manager );' )
        && str_contains( $admin, 'self::render_timer( self::available_work_items( $manager, $user_id ), $active, $user_id );' ),
    'active timer is surfaced above view navigation using server data' => str_contains( $admin, 'self::render_active_timer_status( $active, $view );' )
        && strpos( $admin, 'self::render_active_timer_status( $active, $view );' ) < strpos( $admin, 'self::render_navigation( $view );' )
        && str_contains( $admin, 'Timers::active_for_user( $user_id )' )
        && str_contains( $admin, 'Running since %1$s %2$s.' )
        && str_contains( $admin, 'if ( self::VIEW_TIMER !== $view )' ),
    'correction links and cancel navigation go to their intended views' => str_contains( $entries, "Menu::time_url( [ 'view' => Time::VIEW_MANUAL, 'entry_id' => (int) \$entry['id'] ] )" )
        && str_contains( $entries, "Access::can_edit_entry( \$entry, (int) \$entry['work_item_id'] )" )
        && str_contains( $admin, "self::url( [ 'view' => self::VIEW_ENTRIES ] )" ),
    'post-action redirects preserve the correct view and tracker landing' => str_contains( $actions, "Menu::time_url( [" )
        && str_contains( $actions, "Time::VIEW_TIMER" )
        && str_contains( $actions, "Time::VIEW_ENTRIES" )
        && str_contains( $actions, "Time::VIEW_MANUAL" )
        && str_contains( $menu, "current_user_can( Capabilities::MANAGE ) ? self::TIME_SLUG : self::TOP_LEVEL_SLUG" )
        && str_contains( $admin, 'return Menu::time_url( $args );' ),
    'original nonces and Base pickers remain untouched' => str_contains( $admin, "wp_nonce_field( 'cb_work_start_timer' )" )
        && str_contains( $admin, "wp_nonce_field( 'cb_work_create_time_entry' )" )
        && str_contains( $admin, "Pickers::assignee( 'time[user_id]'" )
        && str_contains( $admin, 'data-cb-time-picker' )
        && str_contains( $admin, "Assets::enqueue_time_picker();" ),
    'Time style loads only on Time screens and versioning follows file contents' => str_contains( $admin, "Menu::TIME_SLUG === \$page || \$tracker_landing" )
        && str_contains( $admin, 'assets/time-workspace.css' )
        && str_contains( $admin, 'filemtime( $css_file )' ),
    'Time layout uses wide cards, scrollable entries and responsive accessible navigation' => str_contains( $style, '.cb-work-time-navigation-link:focus-visible' )
        && str_contains( $style, '.cb-work-time-view > .card' )
        && str_contains( $style, 'max-width: 1160px' )
        && str_contains( $style, '.cb-work-time-entries-scroll' )
        && str_contains( $style, '@media (max-width: 782px)' )
        && str_contains( file_get_contents( $root . '/src/Admin/TimeEntryList.php' ), 'cb-work-time-entries-scroll' ),
];

foreach ( $checks as $label => $passed ) {
    if ( ! $passed ) {
        fwrite( STDERR, "Time workspace smoke FAILED: {$label}\n" );
        exit( 1 );
    }
}
echo "Time workspace smoke passed.\n";

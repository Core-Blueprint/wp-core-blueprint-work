<?php
declare(strict_types=1);

namespace {
    define( 'ABSPATH', '/tmp/wp/' );
    function wp_timezone(): \DateTimeZone { return new \DateTimeZone( 'Europe/Amsterdam' ); }
    function esc_attr( mixed $value ): string { return htmlspecialchars( (string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ); }
    function esc_url( mixed $value ): string { return esc_attr( $value ); }
    function esc_html( mixed $value ): string { return esc_attr( $value ); }
    function esc_textarea( mixed $value ): string { return esc_attr( $value ); }
    function esc_html_e( string $text, string $domain ): void { echo esc_html( $text ); }
    function esc_attr_e( string $text, string $domain ): void { echo esc_attr( $text ); }
    function admin_url( string $path ): string { return '/wp-admin/' . $path; }
    function wp_nonce_field( string $action ): void { echo '<input type="hidden" data-nonce-action="' . esc_attr( $action ) . '">'; }
}

namespace CB\Work\Admin {
    final class Time { public const VIEW_ENTRIES = 'entries'; }
    final class Menu { public static function time_url( array $args ): string { return '/wp-admin/admin.php?' . http_build_query( $args ); } }
    final class TimeEntryList { public static function duration( int $seconds ): string {
        return sprintf( '%02d:%02d:%02d', intdiv( $seconds, 3600 ), intdiv( $seconds % 3600, 60 ), $seconds % 60 );
    } }
}

namespace {
    require dirname( __DIR__ ) . '/src/Domain/TimeRange.php';
    require dirname( __DIR__ ) . '/src/Admin/TimeEntryListState.php';
    require dirname( __DIR__ ) . '/src/Admin/TimeEntryQuickEdit.php';

    use CB\Work\Admin\TimeEntryQuickEdit;
    use CB\Work\Admin\TimeEntryListState;
    use CB\Work\Domain\TimeRange;

    $fail = static function ( string $detail ): never {
        fwrite( STDERR, "Time Quick Edit smoke FAILED: {$detail}\n" );
        exit( 1 );
    };

    // Existing minute-based callers still work, while short timer entries retain seconds.
    if ( '2026-10-08 13:28:13' !== TimeRange::local_to_utc( '2026-10-08', '15:28:13' )
        || '2026-10-08 13:28:00' !== TimeRange::local_to_utc( '2026-10-08', '15:28' )
        || null !== TimeRange::local_to_utc( '2026-10-08', '15:28:99' )
        || null !== TimeRange::local_to_utc( '2026-02-30', '15:28:13' ) ) {
        $fail( 'second-precision conversion or backward compatibility' );
    }

    $state = TimeEntryListState::from_request( [
        'te_search' => 'Acquisition',
        'te_source' => 'timer',
        'te_sort' => 'longest',
        'te_page' => '2',
    ], true );
    $entry = [
        'id' => 42, 'revision' => 7,
        'started_at' => '2026-10-08 13:28:13',
        'ended_at' => '2026-10-08 13:28:26',
        'note' => '<img src=x onerror=alert(1)>',
        'duration_seconds' => 13,
    ];
    ob_start();
    TimeEntryQuickEdit::render( $entry, $state );
    $html = (string) ob_get_clean();
    foreach ( [
        'cb-work-time-quick-edit-42',
        'name="action" value="cb_work_update_time_entry"',
        'name="entry_id" value="42"',
        'name="time[revision]" value="7"',
        'data-nonce-action="cb_work_update_time_entry_42"',
        'name="time[start_date]" value="2026-10-08"',
        'name="time[start_time]" step="1" value="15:28:13"',
        'name="time[end_date]" value="2026-10-08"',
        'name="time[end_time]" step="1" value="15:28:26"',
        'name="time_list[te_search]" value="Acquisition"',
        'name="time_list[te_source]" value="timer"',
        'name="time_list[te_sort]" value="longest"',
        'name="time_list[te_page]" value="2"',
        'data-cb-work-time-quick-edit-duration',
        '00:00:13',
    ] as $needle ) {
        if ( ! str_contains( $html, $needle ) ) {
            $fail( "rendered editor missing: {$needle}" );
        }
    }
    if ( str_contains( $html, '<img ' ) || ! str_contains( $html, '&lt;img' )
        || str_contains( $html, 'name="time[user_id]"' )
        || str_contains( $html, 'name="time[work_item_id]"' )
        || str_contains( $html, '<form', strpos( $html, '<form' ) + 1 ) ) {
        $fail( 'escaping, locked context or form nesting' );
    }

    $list = (string) file_get_contents( dirname( __DIR__ ) . '/src/Admin/TimeEntryList.php' );
    $actions = (string) file_get_contents( dirname( __DIR__ ) . '/src/Admin/TimeActions.php' );
    $styles = (string) file_get_contents( dirname( __DIR__ ) . '/assets/time-workspace.css' );
    $script = (string) file_get_contents( dirname( __DIR__ ) . '/assets/time-entry-quick-edit.js' );
    $time = (string) file_get_contents( dirname( __DIR__ ) . '/src/Admin/Time.php' );
    if ( ! str_contains( $list, "TimeEntryQuickEdit::render( \$entry, \$state );" )
        || ! str_contains( $list, "'te_edit' => (int) \$entry['id']" )
        || ! str_contains( $list, "Access::can_edit_entry( \$entry, (int) \$entry['work_item_id'] )" )
        || ! str_contains( $list, "'view' => Time::VIEW_MANUAL, 'entry_id' => (int) \$entry['id']" )
        || ! str_contains( $actions, '$quick_edit ? (int) $entry[' . "'work_item_id']" )
        || ! str_contains( $actions, '$quick_edit' )
        || ! str_contains( $actions, "TimeEntries::update_completed(" )
        || ! str_contains( $actions, 'Audit::record( Events::TIME_ENTRY_UPDATED' )
        || ! str_contains( $actions, "TimeEntryListState::from_request( \$raw_state, Access::can_manage() )" )
        || ! str_contains( $actions, "\$args['te_edit'] = (int) \$extra['entry_id'];" )
        || ! str_contains( $styles, '.cb-work-time-quick-edit-fields' )
        || ! str_contains( $styles, '.cb-work-time-quick-edit-row' )
        || ! str_contains( $script, "form.addEventListener('input'" )
        || ! str_contains( $time, "assets/time-entry-quick-edit.js" ) ) {
        $fail( 'editor routing, permissions, auditing, responsive presentation or progressive preview contract' );
    }

    echo "Time Quick Edit smoke passed.\n";
}

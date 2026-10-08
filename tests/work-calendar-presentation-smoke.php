<?php
declare(strict_types=1);

// Calendar Golden: read-only contracts for scoped responsive calendar UI.
// Existing Calendar/Board tests own the status-move runtime contract.
$root     = dirname( __DIR__ );
$view     = (string) file_get_contents( $root . '/src/Admin/WorkItemCalendarView.php' );
$assets   = (string) file_get_contents( $root . '/src/Admin/Assets.php' );
$style    = (string) file_get_contents( $root . '/assets/work-calendar-golden.css' );
$calendar = (string) file_get_contents( $root . '/assets/work-calendar.js' );
$legacy   = (string) file_get_contents( $root . '/assets/work-admin.css' );

$checks = [
    'Calendar month navigation remains state-based, balanced and translated' =>
        str_contains( $view, 'cb-work-calendar-navigation__controls' )
        && str_contains( $view, 'cb-work-calendar-navigation__actions' )
        && str_contains( $view, 'cb-work-calendar-navigation__month' )
        && str_contains( $view, "'calendar_month' => $previous_month" )
        && str_contains( $view, "'calendar_month' => $current_month" )
        && str_contains( $view, "'calendar_month' => $next_month" )
        && str_contains( $view, "'Today', 'core-blueprint-work'" )
        && str_contains( $style, 'justify-content: space-between;' ),
    'Calendar viewport scroll is keyboard-operable and contains the seven-column table' =>
        str_contains( $view, 'cb-work-calendar__viewport cb-scrollbar' )
        && str_contains( $view, 'role="region" tabindex="0" aria-label="' )
        && str_contains( $view, '<caption class="screen-reader-text">' )
        && str_contains( $style, '.cb-work-calendar__viewport' )
        && str_contains( $style, 'overflow-x: auto;' )
        && str_contains( $style, 'overscroll-behavior-inline: contain;' )
        && str_contains( $style, 'min-width: 840px;' )
        && str_contains( $style, '@media screen and (max-width: 782px)' ),
    'Calendar marks WordPress-local today without hardcoded text or browser timezone dependence' =>
        str_contains( $view, "$today_date     = current_time( 'Y-m-d' );" )
        && str_contains( $view, 'cb-work-calendar-day--today' )
        && str_contains( $view, 'datetime="<?php echo esc_attr( $date ); ?>"' )
        && str_contains( $view, 'aria-current="date"' )
        && str_contains( $style, '.cb-work-calendar-day--today' ),
    'Month and day titles use timezone-robust midday localization' =>
        str_contains( $view, "$first->setTime( 12, 0 )->getTimestamp()" )
        && str_contains( $view, "$value->setTime( 12, 0 )->getTimestamp()" ),
    'Base workspace dialog stays the one modal; board can scroll independently' =>
        str_contains( $calendar, "import '@cb-core/modal';" )
        && str_contains( $calendar, "size: 'workspace'" )
        && str_contains( $calendar, 'expandable: true' )
        && str_contains( $calendar, 'reloadAfterMove: false' )
        && str_contains( $view, 'cb-work-day-board__viewport cb-scrollbar' )
        && str_contains( $style, '.cb-work-day-modal .cb-work-day-board__viewport' )
        && str_contains( $style, 'padding-inline-end: var(--cb-space-3);' )
        && ! str_contains( $style, 'dialog.cb-core-modal--workspace {' ),
    'Modal board Golden layout and persisted status transitions stay intact' =>
        str_contains( $view, 'data-cb-work-board-reorder' )
        && str_contains( $view, 'WorkItemBoardActions::ACTION' )
        && str_contains( $calendar, 'syncTemplateMove(template, move)' )
        && str_contains( $legacy, 'grid-template-columns: repeat(3, minmax(320px, 1fr));' ),
    'Calendar-only CSS opt-in follows existing refinement stylesheet' =>
        str_contains( $assets, 'if ( WorkItemViewState::VIEW_CALENDAR === WorkItemViewPreferences::resolve_request_view(' )
        && str_contains( $assets, 'self::enqueue_calendar_assets();' )
        && str_contains( $assets, "assets/work-calendar-golden.css" )
        && str_contains( $assets, "wp_enqueue_style( 'cb-work-calendar-golden'" )
        && str_contains( $assets, '[ self::REFINEMENT_STYLE_HANDLE ]' ),
];

foreach ( $checks as $name => $passed ) {
    if ( ! $passed ) {
        fwrite( STDERR, "Work Calendar presentation smoke FAILED: {$name}\n" );
        exit( 1 );
    }
}

echo "Work Calendar presentation smoke passed (responsive, today, Base Modal, scoped assets).\n";

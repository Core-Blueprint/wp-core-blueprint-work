<?php
declare(strict_types=1);

// Read-only Calendar Golden composition contracts. The existing day-modal
// reorder smoke still owns the persisted Board state/interaction contract.
$root = dirname( __DIR__ );
$view = (string) file_get_contents( $root . '/src/Admin/WorkItemCalendarView.php' );
$css = (string) file_get_contents( $root . '/assets/work-admin.css' );
$js = (string) file_get_contents( $root . '/assets/work-calendar.js' );
$assets = (string) file_get_contents( $root . '/src/Admin/Assets.php' );
$pot = (string) file_get_contents( $root . '/languages/core-blueprint-work.pot' );

$checks = [
    'Calendar toolbar retains previous/next/current month without dropping active filters' =>
        str_contains( $view, 'cb-work-calendar-navigation__previous' )
        && str_contains( $view, 'cb-work-calendar-navigation__today' )
        && str_contains( $view, 'cb-work-calendar-navigation__next' )
        && str_contains( $view, "'calendar_month' => \$previous_month, 'page' => 1" )
        && str_contains( $view, "'calendar_month' => \$next_month, 'page' => 1" )
        && str_contains( $view, "'calendar_month' => \$current_month, 'page' => 1" )
        && str_contains( $view, 'WorkItemViewState::query_args( $state, $overrides )' ),
    'Today is resolved in WordPress site timezone and announced accessibly' =>
        str_contains( $view, "\$today          = current_time( 'Y-m-d' );" )
        && str_contains( $view, "\$date === \$today ? ' is-today' : ''" )
        && str_contains( $view, 'aria-current="date"' )
        && str_contains( $view, "esc_html_e( 'Today', 'core-blueprint-work' )" )
        && str_contains( $css, '.cb-work-calendar-day.is-today' ),
    'Month is a labelled semantic table inside keyboard-scrollable viewport' =>
        str_contains( $view, 'cb-work-calendar__viewport cb-scrollbar" role="region" tabindex="0"' )
        && str_contains( $view, '<caption class="screen-reader-text">' )
        && str_contains( $view, '<th scope="col">' )
        && str_contains( $view, 'aria-haspopup="dialog"' )
        && str_contains( $view, "self::day_label( \$date ) . ': ' . count( \$day_entries )" )
        && str_contains( $css, '.cb-work-calendar__viewport {' )
        && str_contains( $css, 'scrollbar-gutter: stable;' ),
    'Responsive view keeps comfortable seven-column day widths and useful navigation' =>
        str_contains( $css, 'grid-template-columns: max-content minmax(0, 1fr) max-content max-content;' )
        && str_contains( $css, '@media screen and (max-width: 782px)' )
        && str_contains( $css, 'min-width: 840px;' )
        && str_contains( $css, '@media screen and (max-width: 480px)' )
        && str_contains( $css, '.cb-work-calendar-navigation__today {' ),
    'Transplanted Base modal has local dark/light Work tokens and scrollable board' =>
        str_contains( $css, '.cb-work-day-modal {' )
        && str_contains( $css, '--cb-work-surface-raised: var(--cb-surface-2);' )
        && str_contains( $css, '--cb-work-border: var(--cb-border);' )
        && str_contains( $view, 'cb-work-day-board__viewport cb-scrollbar" role="region" tabindex="0"' )
        && str_contains( $css, 'scroll-padding-inline: var(--cb-space-2);' )
        && str_contains( $css, 'padding-inline-end: var(--cb-space-3);' )
        && str_contains( $css, '.cb-work-day-board__viewport:focus-visible' ),
    'Day modal retains Base workspace expandable size and existing Board status persistence' =>
        str_contains( $js, "import '@cb-core/modal';" )
        && str_contains( $js, "size: 'workspace'" )
        && str_contains( $js, 'expandable: true' )
        && str_contains( $js, 'reloadAfterMove: false' )
        && str_contains( $js, 'onPersistedMove: (move) => syncTemplateMove(template, move)' )
        && str_contains( $view, 'data-cb-work-board-reorder' )
        && str_contains( $css, 'grid-template-columns: repeat(3, minmax(320px, 1fr));' ),
    'Existing Calendar CSS and JS asset loading stays canonical' =>
        str_contains( $assets, "CB_WORK_URL . 'assets/work-admin.css'" )
        && str_contains( $assets, "CB_WORK_URL . 'assets/work-calendar.js'" ),
    'No new translation strings are needed for Calendar accessibility' =>
        str_contains( $pot, 'msgid "Calendar"' )
        && str_contains( $pot, 'msgid "Today"' )
        && str_contains( $pot, 'msgid "Work Items"' )
        && str_contains( $pot, 'msgid "Previous month"' )
        && str_contains( $pot, 'msgid "Next month"' ),
];

foreach ( $checks as $name => $passed ) {
    if ( ! $passed ) {
        fwrite( STDERR, "Work Calendar final UX smoke FAILED: {$name}\n" );
        exit( 1 );
    }
}

echo "Work Calendar final UX smoke passed (calendar navigation, responsive scroll, modal and tokens).\n";

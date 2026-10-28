<?php
declare(strict_types=1);

// UX-00B: Work-owned screen headers share presentation only. All existing
// WordPress, Base, Work domain actions and capability boundaries remain intact.
$root = dirname( __DIR__ );

$files = [
    'overview'   => 'src/Admin/Overview.php',
    'items'      => 'src/Admin/Operations.php',
    'recurrence' => 'src/Admin/Recurrence.php',
    'services'   => 'src/Admin/ServiceOperations.php',
    'time'       => 'src/Admin/Time.php',
];
$source = [];
foreach ( $files as $name => $path ) {
    $text = file_get_contents( $root . '/' . $path );
    if ( false === $text ) {
        fwrite( STDERR, "UX-00B: missing {$name} source.\n" );
        exit( 1 );
    }
    $source[ $name ] = $text;
}
$css = file_get_contents( $root . '/assets/work-workspace.css' );
$assets = file_get_contents( $root . '/src/Admin/Assets.php' );
$workspace = file_get_contents( $root . '/src/Admin/Workspace.php' );

$fail = static function ( string $label ): never {
    fwrite( STDERR, "UX-00B experience smoke FAILED: {$label}\n" );
    exit( 1 );
};

if ( false === $css || false === $assets || false === $workspace ) {
    $fail( 'shared Work assets unavailable' );
}

// Confirm exactly one Work heading per operational surface, including Work Types.
$headings = [
    'overview'   => [ 'cb-work-daily-overview__header', 'Work' ],
    'recurrence' => [ 'cb-work-recurrence-header', 'Recurring Work' ],
    'services'   => [ 'cb-work-so-header', 'Service Operations' ],
    'time'       => [ 'cb-work-time-header', 'Time' ],
];
foreach ( $headings as $name => [ $original_class, $title ] ) {
    $pattern = '~<header\\s+class="(?=[^"]*\\b' . preg_quote( $original_class, '~' ) . '\\b)(?=[^"]*\\bcb-work-page-heading\\b)[^"]*">(?:(?!</header>).)*</header>~s';
    if ( 1 !== preg_match_all( $pattern, $source[ $name ], $matches ) ) {
        $fail( "{$name} must have one semantic shared header, retaining its original CSS class" );
    }
    $header = $matches[0][0];
    if ( ! str_contains( $header, '<h1>' ) || ! str_contains( $header, $title ) || ! str_contains( $header, 'class="description"' ) ) {
        $fail( "{$name} header title or description missing" );
    }
}
// G0 Table/List/Board uses an immutable byte-for-byte Operations.php contract.
// This UX patch must style its existing header and Work Types title by class,
// NEVER modify frozen renderer markup or rewrite the B1.2 source hash fixture.
$operations_blob = sha1( 'blob ' . strlen( $source['items'] ) . "\0" . $source['items'] );
if ( '92a4814f24b43f96d16e3d96ce61a49ee9f21500' !== $operations_blob
    || ! str_contains( $source['items'], '<header class="cb-work-page-header">' )
    || ! str_contains( $source['items'], "<h1><?php esc_html_e( 'Work Types', 'core-blueprint-work' ); ?></h1>" ) ) {
    $fail( 'G0-frozen Work Items header and Work Types markup must remain exact' );
}

$invariants = [
    'overview keeps primary and secondary canonical actions' =>
        str_contains( $source['overview'], 'cb-work-daily-overview__actions cb-work-page-heading__actions' )
        && str_contains( $source['overview'], 'Menu::new_work_item_url()' )
        && str_contains( $source['overview'], 'Menu::time_url()' )
        && str_contains( $source['overview'], "self::queue( __( 'Today'" )
        && str_contains( $source['overview'], "self::queue( __( 'Needs attention'" ),
    'work items keeps its single primary canonical create action' =>
        str_contains( $source['items'], 'cb-work-page-header__actions' )
        && str_contains( $source['items'], 'Menu::new_work_item_url( $project_filter )' )
        && str_contains( $source['items'], 'self::render_work_item_filters(' ),
    'recurrence retains add/back action and separate activation' =>
        str_contains( $source['recurrence'], 'cb-work-recurrence-header-actions cb-work-page-heading__actions' )
        && str_contains( $source['recurrence'], 'data-cb-recurrence-back' )
        && str_contains( $source['recurrence'], "self::url( [ 'mode' => 'add' ] )" )
        && str_contains( $source['recurrence'], 'data-cb-editor-toggle' )
        && str_contains( $source['recurrence'], 'cb_work_toggle_recurrence_rule' ),
    'service operations keeps contextual CTA and first-run states' =>
        str_contains( $source['services'], "'operational' === \$display_state" )
        && str_contains( $source['services'], 'data-cb-so-open' )
        && str_contains( $source['services'], "self::onboarding(\$eligible_projects,\$services)" )
        && str_contains( $source['services'], 'self::ready_state();' ),
    'time keeps all three views and server authority' =>
        str_contains( $source['time'], 'self::render_active_timer_status( $active, $view );' )
        && str_contains( $source['time'], 'self::render_navigation( $view );' )
        && str_contains( $source['time'], 'self::VIEW_TIMER, self::VIEW_MANUAL, self::VIEW_ENTRIES' ),
    'work types remain governed simple form' =>
        str_contains( $source['items'], 'cb_work_create_work_type' )
        && str_contains( $source['items'], 'cb_work_toggle_work_type' )
        && str_contains( $source['items'], 'wp_nonce_field(' ),
    'shared style uses existing WordPress/Base screen context' =>
        str_contains( $assets, "self::enqueue_workspace_style();" )
        && str_contains( $workspace, 'cb-work-workspace-screen' )
        && str_contains( $css, '.cb-work-workspace-screen .wrap > .cb-work-page-heading' )
        && str_contains( $css, '.cb-work-workspace-screen .wrap.cb-work-items-page > .cb-work-page-header' )
        && str_contains( $css, '.cb-work-workspace-screen .wrap.cb-work-types-page > h1' ),
    'Base owns native controls and theme' =>
        str_contains( $css, 'var(--cb-space-4, 16px)' )
        && ! str_contains( $css, 'body.dark' )
        && ! str_contains( $css, '.button-primary {' )
        && ! str_contains( $css, 'data-cb-theme' ),
    'responsive actions remain visible without special JavaScript' =>
        str_contains( $css, '@media screen and (max-width: 782px)' )
        && str_contains( $css, '@media screen and (max-width: 480px)' )
        && str_contains( $css, '.cb-work-page-heading__actions > .button' ),
];
foreach ( $invariants as $label => $pass ) {
    if ( ! $pass ) {
        $fail( $label );
    }
}

echo "Work UX-00B experience smoke passed (five shared headers, frozen Work Items/Types, actions and responsive Base tokens).\n";

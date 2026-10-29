<?php
declare(strict_types=1);

// UX-00A/M4: two manager navigation entries, permission-protected contextual
// routes remain registered for advanced editors and unfinished M3 services.
$menu = (string) file_get_contents( dirname( __DIR__ ) . '/src/Admin/Menu.php' );
$start = strpos( $menu, "\t\tif ( \$can_manage ) {\n" );
$end = false === $start ? false : strpos( $menu, "\t\t\treturn;\n\t\t}\n", $start );
if ( false === $start || false === $end ) {
    fwrite( STDERR, "UX-00A/M4 manager boundary missing.\n" );
    exit( 1 );
}
$manager = substr( $menu, $start, $end - $start );
preg_match_all( '/^[ \t]*add_submenu_page\([^\r\n]*\);[ \t]*$/m', $manager, $matches );
$rows = $matches[0];

$primary = [
    [ 'Overview', 'self::TOP_LEVEL_SLUG', 5 ],
    [ 'Work', 'self::UNIFIED_WORKSPACE_SLUG', 10 ],
];
$contextual = [
    'self::projects_path()',
    'self::WORK_ITEMS_SLUG',
    'self::TIME_SLUG',
    'self::SERVICE_OPERATIONS_SLUG',
    'self::RECURRENCE_SLUG',
    'self::services_path()',
    'self::WORK_TYPES_SLUG',
    'self::PROJECT_WORKSPACE_SLUG',
    'self::PROJECT_IMPORT_SLUG',
];
if ( count( $rows ) !== count( $primary ) + count( $contextual ) ) {
    fwrite( STDERR, "UX-00A/M4 expected two primary and nine contextual routes.\n" );
    exit( 1 );
}
foreach ( $primary as $index => [ $label, $route, $position ] ) {
    $row = trim( $rows[ $index ] );
    if ( ! str_contains( $row, "__( '" . $label . "', 'core-blueprint-work' )" )
        || ! str_contains( $row, ', Capabilities::MANAGE, ' . $route . ',' )
        || ! str_ends_with( $row, ', ' . $position . ' );' ) ) {
        fwrite( STDERR, "UX-00A/M4 incorrect primary menu: {$label}.\n" );
        exit( 1 );
    }
}
foreach ( $contextual as $index => $route ) {
    if ( ! str_contains( $rows[ $index + 2 ], ', Capabilities::MANAGE, ' . $route . ',' ) ) {
        fwrite( STDERR, "UX-00A/M4 contextual route missing: {$route}.\n" );
        exit( 1 );
    }
}
foreach ( $contextual as $route ) {
    if ( ! str_contains( $menu, 'remove_submenu_page( self::TOP_LEVEL_SLUG, ' . $route . ' )' ) ) {
        fwrite( STDERR, "UX-00A/M4 contextual route visible: {$route}.\n" );
        exit( 1 );
    }
}
$checks = [
    'Work remains visible in submenu' =>
        ! str_contains( $menu, 'remove_submenu_page( self::TOP_LEVEL_SLUG, self::UNIFIED_WORKSPACE_SLUG )' ),
    'Native/advanced contexts highlight Work' =>
        str_contains( $menu, 'self::CONTEXT_UNIFIED_WORKSPACE => self::UNIFIED_WORKSPACE_SLUG' )
        && str_contains( $menu, 'self::CONTEXT_WORK_ITEMS => self::UNIFIED_WORKSPACE_SLUG' )
        && str_contains( $menu, 'self::CONTEXT_PROJECTS   => self::UNIFIED_WORKSPACE_SLUG' ),
    'Time-only roles retain the original landing and no manager menu' =>
        str_contains( $menu, "Capabilities::TRACK_TIME, self::TOP_LEVEL_SLUG, [ Time::class, 'render' ]" )
        && str_contains( $menu, 'if ( ! $can_track )' )
        && str_contains( $menu, "self::CONTEXT_TIME       => current_user_can( Capabilities::MANAGE ) ? self::UNIFIED_WORKSPACE_SLUG : self::TOP_LEVEL_SLUG" ),
    'Context routes remain WordPress-native and capability checked' =>
        str_contains( $menu, "add_action( 'admin_head', [ self::class, 'hide_contextual_submenu_pages' ] )" )
        && ! str_contains( $menu, 'add_submenu_page( null' )
        && str_contains( $menu, "PostTypes::WORK_ITEM === (string) \$screen->post_type" )
        && str_contains( $menu, "add_filter( 'submenu_file'" ),
];
foreach ( $checks as $label => $passed ) {
    if ( ! $passed ) {
        fwrite( STDERR, "UX-00A/M4 navigation FAIL: {$label}.\n" );
        exit( 1 );
    }
}
echo "Work UX-00A/M4 navigation: PASS (Overview, Work, and nine contextual manager routes).\n";

<?php
declare(strict_types=1);

// UX-00A regression: menu presentation may change, but canonical routes,
// capabilities and contextual WordPress admin pages must remain unchanged.
$menu = (string) file_get_contents( dirname( __DIR__ ) . '/src/Admin/Menu.php' );

$start = strpos( $menu, "\t\tif ( \$can_manage ) {\n" );
$end   = false !== $start ? strpos( $menu, "\t\t\treturn;\n\t\t}\n", $start ) : false;
if ( false === $start || false === $end ) {
    fwrite( STDERR, "UX-00A navigation: manager menu boundary missing.\n" );
    exit( 1 );
}

$manager = substr( $menu, $start, $end - $start );
preg_match_all( '/^[ \t]*add_submenu_page\([^\r\n]*\);[ \t]*$/m', $manager, $matches );
$rows = $matches[0];

$expected = [
    [ 'Overview',          'self::TOP_LEVEL_SLUG',         5 ],
    [ 'Projects',          'self::projects_path()',       10 ],
    [ 'Work Items',        'self::WORK_ITEMS_SLUG',        15 ],
    [ 'Time',              'self::TIME_SLUG',              20 ],
    [ 'Service Operations','self::SERVICE_OPERATIONS_SLUG',25 ],
    [ 'Recurring Work',    'self::RECURRENCE_SLUG',        30 ],
    [ 'Services',          'self::services_path()',       35 ],
    [ 'Work Types',        'self::WORK_TYPES_SLUG',        40 ],
];

if ( count( $rows ) !== 11 ) {
    fwrite( STDERR, 'UX-00A navigation: expected 8 visible and 3 contextual manager entries, got ' . count( $rows ) . ".\n" );
    exit( 1 );
}

foreach ( $expected as $index => [ $label, $route, $position ] ) {
    $row = trim( $rows[ $index ] );
    $valid_label = 1 === preg_match( "/__\\(\\s*'" . preg_quote( $label, '/' ) . "'\\s*,\\s*'core-blueprint-work'/", $row );
    $valid_route = str_contains( $row, ', Capabilities::MANAGE, ' . $route . ',' );
    $valid_position = str_ends_with( $row, ', ' . $position . ' );' );

    if ( ! $valid_label || ! $valid_route || ! $valid_position ) {
        fwrite( STDERR, "UX-00A navigation: invalid manager order/route/capability/position for {$label}.\n" );
        exit( 1 );
    }
}

foreach ( [
    [ 'Project Workspace', 'self::PROJECT_WORKSPACE_SLUG' ],
    [ 'Import Work Project', 'self::PROJECT_IMPORT_SLUG' ],
] as $index => [ $label, $route ] ) {
    $row = trim( $rows[ $index + 8 ] );
    if ( ! str_contains( $row, "__( '" . $label ) ) {
        fwrite( STDERR, "UX-00A navigation: contextual {$label} is missing.\n" );
        exit( 1 );
    }
    if ( ! str_contains( $row, ', Capabilities::MANAGE, ' . $route . ',' ) ) {
        fwrite( STDERR, "UX-00A navigation: contextual {$label} route/capability changed.\n" );
        exit( 1 );
    }
}

if (
	! str_contains( $rows[10], "__( 'Work', 'core-blueprint-work' )" )
	|| ! str_contains( $rows[10], ', Capabilities::MANAGE, self::UNIFIED_WORKSPACE_SLUG, [ UnifiedWorkspace::class, \'render\' ]' )
) {
	fwrite( STDERR, "UX-00A navigation: additional hidden UWE route is missing or incorrectly authorized.\n" );
	exit( 1 );
}

$invariants = [
    'tracker-only Time landing' => str_contains( $menu, "Capabilities::TRACK_TIME, self::TOP_LEVEL_SLUG, [ Time::class, 'render' ]" ),
    'manager and tracker separation' => str_contains( $menu, 'if ( $can_manage )' ) && str_contains( $menu, 'if ( ! $can_track )' ),
    'hidden contextual routes' => str_contains( $menu, 'remove_submenu_page( self::TOP_LEVEL_SLUG, self::PROJECT_WORKSPACE_SLUG )' )
        && str_contains( $menu, 'remove_submenu_page( self::TOP_LEVEL_SLUG, self::PROJECT_IMPORT_SLUG )' )
		&& str_contains( $menu, 'remove_submenu_page( self::TOP_LEVEL_SLUG, self::UNIFIED_WORKSPACE_SLUG )' ),
    'WordPress active menu mapping' => str_contains( $menu, "add_filter( 'parent_file'" )
        && str_contains( $menu, "add_filter( 'submenu_file'" )
        && str_contains( $menu, 'self::CONTEXT_PROJECTS' )
        && str_contains( $menu, 'self::CONTEXT_SERVICES' ),
];
foreach ( $invariants as $name => $valid ) {
    if ( ! $valid ) {
        fwrite( STDERR, "UX-00A navigation: invariant failed: {$name}.\n" );
        exit( 1 );
    }
}

echo "Work UX-00A navigation smoke passed (order, routes, permissions and contextual screens).\n";

<?php
declare(strict_types=1);

/**
 * M2 FIELD regression: a Workspace view tab must never be a direct link
 * back to the retired native Work Items route, even if JS is unavailable.
 * Contextual advanced routes remain accessible from within Work.
 */
$root = dirname( __DIR__ );
$shell = (string) file_get_contents( $root . '/src/Admin/UnifiedWorkspace.php' );
$mount = (string) file_get_contents( $root . '/assets/js/workspace/canonical-mount.js' );
$app = (string) file_get_contents( $root . '/assets/js/workspace/app.js' );
$check = (string) file_get_contents( $root . '/tools/check' );
$start = strpos( $shell, '<nav class="cb-work-uwe__view-switcher"' );
$end = false === $start ? false : strpos( $shell, '</nav>', $start );
$nav = false === $start || false === $end ? '' : substr( $shell, $start, $end + 6 - $start );
$related = strpos( $nav, 'cb-work-uwe__related' );
$view_links = false === $related ? '' : substr( $nav, 0, $related );

$checks = [
    'All four view tabs point back to the persistent Work workspace' =>
        str_contains( $view_links, 'foreach ( $preferred_order as $view_id )' )
        && str_contains( $view_links, "'page' => Menu::UNIFIED_WORKSPACE_SLUG" )
        && str_contains( $view_links, "'section' => 'work-items'" )
        && str_contains( $view_links, "'view' => $view_id" )
        && ! str_contains( $view_links, "Menu::WORK_ITEMS_SLUG" ),
    'M3 contextual operations remain accessible without legacy main navigation' =>
        str_contains( $nav, 'cb-work-uwe__related' )
        && str_contains( $nav, 'Menu::time_url()' )
        && str_contains( $nav, 'Menu::SERVICE_OPERATIONS_SLUG' )
        && str_contains( $nav, 'Menu::RECURRENCE_SLUG' )
        && ! str_contains( $nav, 'cb-work-uwe__legacy-link' ),
    'Canonical view clicks are intercepted for same-page navigation' =>
        str_contains( $mount, "switcher.addEventListener('click', (event) => {" )
        && str_contains( $mount, "event.target?.closest?.('[data-cb-work-uwe-view]')" )
        && str_contains( $mount, 'event.preventDefault();' )
        && str_contains( $mount, 'browser.history.pushState({ cbWorkUweView: view }' )
        && str_contains( $mount, 'void notify();' ),
    'App uses the canonical mount rather than redirecting to native Work Items' =>
        str_contains( $app, 'createCanonicalViewMount(' )
        && str_contains( $app, "if (section === 'work-items' && canonicalMount)" )
        && ! str_contains( $app, 'window.location.reload()' ),
    'M2 routing and M1 canonical runtime tests run in Level 1' =>
        str_contains( $check, 'work-m2-workspace-view-routing-smoke.php' )
        && str_contains( $check, 'work-uwe-03c2-runtime.mjs' ),
];
foreach ( $checks as $name => $valid ) {
    if ( ! $valid ) {
        fwrite( STDERR, "M2 Workspace view routing FAILED: {$name}\n" );
        exit( 1 );
    }
}
echo "M2 Workspace view routing: PASS (SPA interception and safe fallback URLs).\n";

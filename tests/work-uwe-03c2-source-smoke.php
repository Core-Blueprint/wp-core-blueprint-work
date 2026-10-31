<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$shell = (string) file_get_contents( $root . '/src/Admin/UnifiedWorkspace.php' );
$assets = (string) file_get_contents( $root . '/src/Admin/Assets.php' );
$app = (string) file_get_contents( $root . '/assets/js/workspace/app.js' );
$bridge = (string) file_get_contents( $root . '/src/Admin/WorkItemViewBridge.php' );
$module = (string) file_get_contents( $root . '/assets/js/workspace/canonical-mount.js' );
$css = (string) file_get_contents( $root . '/assets/css/unified-workspace.css' );
$operations = (string) file_get_contents( $root . '/src/Admin/Operations.php' );
$check = (string) file_get_contents( $root . '/tools/check' );

// Navigation was intentionally unified on the M4 feature branch. The
// canonical renderer stays protected; the primary tabs must NEVER escape
// to the old native Work Items page, even when JS does not hydrate.
$switcher_start = strpos( $shell, '<nav class="cb-work-uwe__view-switcher"' );
$switcher_end = false === $switcher_start ? false : strpos( $shell, '</nav>', $switcher_start );
$switcher = false === $switcher_start || false === $switcher_end
	? '' : substr( $shell, $switcher_start, $switcher_end - $switcher_start + strlen( '</nav>' ) );
$primary_tabs = $switcher;

$checks = [
	'canonical Work Item views declared once with safe Unified Workspace URLs' =>
		str_contains( $shell, "'table' => __( 'Table'" )
		&& str_contains( $shell, "'list' => __( 'List'" )
		&& str_contains( $shell, "'kanban' => __( 'Board'" )
		&& str_contains( $shell, "'calendar' => __( 'Calendar'" )
		&& str_contains( $shell, 'WorkItemViewPreferences::resolve_request_view' )
		&& str_contains( $shell, 'data-cb-work-uwe-view-switcher' )
		&& str_contains( $primary_tabs, 'foreach ( $preferred_order as $view_id )' )
		&& str_contains( $primary_tabs, "'page' => Menu::UNIFIED_WORKSPACE_SLUG" )
		&& str_contains( $primary_tabs, "'section' => 'work-items'" )
		&& str_contains( $primary_tabs, "'view' => \$view_id" )
		&& ! str_contains( $primary_tabs, 'Menu::WORK_ITEMS_SLUG' )
		&& ! str_contains( $switcher, 'cb-work-uwe__related' )
		&& str_contains( $shell, 'WorkspaceOperationsPanel::render_navigation();' ),
	'canonical HTML mount point is Work Items-only and does not remove SSR' =>
		str_contains( $shell, "if ( 'work-items' === \$key )" )
		&& str_contains( $shell, 'data-cb-work-uwe-canonical hidden' )
		&& str_contains( $shell, 'WorkItems::all( 25 )' )
		&& str_contains( $shell, 'Projects::all( 25 )' )
		&& str_contains( $shell, 'Services::all( 25 )' ),
	'canonical HTML is fetched through the protected 03C.1 bridge' =>
		str_contains( $module, 'canonicalViewParameters(href, action, nonce' )
		&& str_contains( $module, "method: 'GET'" )
		&& str_contains( $module, "'same-origin'" )
		&& str_contains( $bridge, 'Operations::render_work_items();' )
		&& ! str_contains( $module, "'POST'" ),
	'mount uses canonical HTML, not a second renderer or unsafe script evaluation' =>
		str_contains( $module, "parseFromString(payload.data.html, 'text/html')" )
		&& str_contains( $module, "parsed.querySelector('.cb-work-items-page')" )
		&& str_contains( $module, "document.importNode(canonical, true)" )
		&& str_contains( $module, "querySelector('script, iframe, object, embed')" )
		&& ! str_contains( $module, 'eval(' )
		&& ! str_contains( $module, 'WorkItems::search(' ),
	'frontend routing preserves WordPress reserved page and allows no-refresh view switch' =>
		str_contains( $module, "url.searchParams.set('view', view)" )
		&& str_contains( $module, "url.searchParams.delete('uwe_page')" )
		&& ! str_contains( $module, "url.searchParams.delete('page')" )
		&& str_contains( $module, 'browser.history.pushState(' )
		&& str_contains( $app, 'createCanonicalViewMount(' ),
	'outdated requests abort, invalid responses keep accessible fallback' =>
		str_contains( $module, 'current.abort()' )
		&& str_contains( $module, 'ticket !== serial' )
		&& str_contains( $module, 'showFallback();' )
		&& str_contains( $module, "panel.setAttribute('aria-busy', 'true')" )
		&& str_contains( $module, "panel.removeAttribute('aria-busy')" ),
	'all runtime modules resolve to real packaged assets' =>
		is_file( $root . '/assets/js/workspace/canonical-mount.js' )
		&& str_contains( $app, "from './canonical-mount.js'" )
		&& str_contains( $assets, "CB_WORK_DIR . 'assets/js/workspace/canonical-mount.js'" )
		&& str_contains( $assets, 'is_file( $canonical )' ),
	'work-specific CSS is loaded without uninitialized legacy action JS' =>
		str_contains( $assets, 'enqueue_unified_canonical_view_styles()' )
		&& str_contains( $assets, "'assets/work-items-refinement.css'" )
		&& str_contains( $assets, "'assets/work-calendar-golden.css'" )
		&& str_contains( $css, '.cb-work-unified .cb-work-uwe__canonical-view' )
		&& str_contains( $css, '.cb-work-unified .cb-work-uwe__view-switcher' ),
	'protected G0/B1 renderer remains exact and original admin actions remain unchanged' =>
		'92a4814f24b43f96d16e3d96ce61a49ee9f21500' === sha1( 'blob ' . strlen( $operations ) . "\0" . $operations )
		&& ! str_contains( $app, "method: 'POST'" ),
	'Level 1 includes both 03C.2 regression gates' =>
		str_contains( $check, 'tests/work-uwe-03c2-source-smoke.php' )
		&& str_contains( $check, 'tests/work-uwe-03c2-runtime.mjs' ),
];
foreach ( $checks as $label => $pass ) {
	if ( ! $pass ) {
		fwrite( STDERR, "UWE-03C.2 canonical view mounting FAIL: {$label}\n" );
		exit( 1 );
	}
}
echo "UWE-03C.2 canonical view mounting source: PASS.\n";

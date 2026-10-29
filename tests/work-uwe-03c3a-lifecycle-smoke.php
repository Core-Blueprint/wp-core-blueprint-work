<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$assets = (string) file_get_contents( $root . '/src/Admin/Assets.php' );
$mount = (string) file_get_contents( $root . '/assets/js/workspace/canonical-mount.js' );
$board = (string) file_get_contents( $root . '/assets/work-items-reorder.js' );
$calendar = (string) file_get_contents( $root . '/assets/work-calendar.js' );
$refine = (string) file_get_contents( $root . '/assets/work-items-refinement.js' );
$css = (string) file_get_contents( $root . '/assets/css/unified-workspace.css' );
$operations = (string) file_get_contents( $root . '/src/Admin/Operations.php' );
$check = (string) file_get_contents( $root . '/tools/check' );

$helper_start = strpos( $assets, 'private static function enqueue_unified_canonical_view_styles(): void' );
$helper_end = strpos( $assets, '/** Shared Base Toast Foundation', $helper_start ?: 0 );
$helper = false !== $helper_start && false !== $helper_end
	? substr( $assets, $helper_start, $helper_end - $helper_start ) : '';

$checks = [
	'native Work scripts and Base modal/reorder are enqueued only through the Unified Workspace asset path' =>
		str_contains( $assets, 'Menu::CONTEXT_UNIFIED_WORKSPACE === $context' )
		&& str_contains( $assets, 'self::enqueue_unified_canonical_view_styles();' )
		&& str_contains( $helper, 'self::enqueue_script();' )
		&& str_contains( $helper, 'self::enqueue_refinement_assets();' )
		&& str_contains( $helper, 'self::enqueue_reorder_assets();' )
		&& str_contains( $helper, 'self::enqueue_calendar_assets();' )
		&& str_contains( $assets, "'@cb-core/modal'" )
		&& str_contains( $assets, "'@cb-core/reorder'" ),
	'canonical fragment dispatches mount and unmount in DOM insertion order' =>
		str_contains( $mount, "new CustomEvent('cb-work:canonical-view-unmount'" )
		&& str_contains( $mount, "new CustomEvent('cb-work:canonical-view-mounted'" )
		&& strpos( $mount, "canonical-view-unmount" ) < strpos( $mount, 'destination.replaceChildren(inserted);' )
		&& strpos( $mount, 'destination.replaceChildren(inserted);' ) < strpos( $mount, "canonical-view-mounted" ),
	'Board uses the same authenticated, guarded Base reorder controller without forced Workspace reload' =>
		str_contains( $board, 'const reorder = window.cbCore?.reorder;' )
		&& str_contains( $board, 'persistBoardTransition(root, card, move.to.listId)' )
		&& str_contains( $board, "root.closest('[data-cb-work-uwe-canonical]')" )
		&& str_contains( $board, 'reloadAfterMove: !inWorkspace' )
		&& str_contains( $board, "cb-work:canonical-view-mounted" )
		&& str_contains( $board, "cb-work:canonical-view-unmount" )
		&& str_contains( $board, 'boardControllers.get(root)?.destroy()' ),
	'Calendar reuses existing Base modal and day templates after an AJAX mount' =>
		str_contains( $calendar, "import '@cb-core/modal';" )
		&& str_contains( $calendar, "modal.show({" )
		&& str_contains( $calendar, 'openDayModal(trigger)' )
		&& str_contains( $calendar, "cb-work:canonical-view-mounted" )
		&& str_contains( $calendar, 'initCalendarTriggers(document);' ),
	'List progressive disclosure reuses original Work refinement code on each new fragment' =>
		str_contains( $refine, 'initListProgressiveDisclosure( page );' )
		&& str_contains( $refine, 'refineBoard( page, form, strings );' )
		&& str_contains( $refine, "cb-work:canonical-view-mounted" )
		&& str_contains( $refine, "page.dataset.cbWorkRefinementInitialized === '1'" )
		&& str_contains( $refine, 'ready( init );' ),
	'Calendar prev/next month stays on the same WordPress workspace route' =>
		str_contains( $mount, 'export function calendarMonthUrl(' )
		&& str_contains( $mount, "current.searchParams.set('calendar_month', month)" )
		&& str_contains( $mount, "browser.history.pushState({ cbWorkUweCalendarMonth: true }" ),
	'unsupported edits remain held for the next UWE-03C.3B stage' =>
		str_contains( $css, '.cb-work-unified .cb-work-uwe__canonical-view form,' )
		&& str_contains( $css, '.cb-work-unified .cb-work-uwe__canonical-view .cb-work-view-preferences__trigger' ),
	'G0 renderer byte-exact, no data migrations or duplicate Work Item renderers' =>
		'92a4814f24b43f96d16e3d96ce61a49ee9f21500' === sha1( 'blob ' . strlen( $operations ) . "\0" . $operations )
		&& ! str_contains( $mount, 'wp_insert_post' )
		&& ! str_contains( $mount, 'innerHTML' ),
	'canonical Level 1 contains both new lifecycle regression gates' =>
		str_contains( $check, 'tests/work-uwe-03c3a-lifecycle-smoke.php' )
		&& str_contains( $check, 'tests/work-uwe-03c3a-runtime.mjs' ),
];
foreach ( $checks as $label => $pass ) {
	if ( ! $pass ) {
		fwrite( STDERR, "UWE-03C.3A lifecycle smoke FAIL: {$label}\n" );
		exit( 1 );
	}
}
echo "UWE-03C.3A dynamic interaction lifecycle source: PASS.\n";

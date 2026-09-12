<?php
declare(strict_types=1);

$root   = dirname( __DIR__ );
$assets = file_get_contents( $root . '/src/Admin/Assets.php' );
$plugin = file_get_contents( $root . '/src/Plugin.php' );
$css    = file_get_contents( $root . '/assets/work-admin.css' );
$js     = file_get_contents( $root . '/assets/work-admin.js' );
$ops    = file_get_contents( $root . '/src/Admin/Operations.php' );

$checks = [
	'admin asset loader exists' => str_contains( $assets, "STYLE_HANDLE  = 'cb-work-admin'" ) && str_contains( $assets, "SCRIPT_HANDLE = 'cb-work-admin'" ),
	'asset loader is Work Items scoped' => str_contains( $assets, 'Menu::WORK_ITEMS_SLUG !== $page' ),
	'asset loader fingerprints same-version RC assets' => substr_count( $assets, 'filemtime( $file )' ) >= 2,
	'asset loader localizes presentation labels' => str_contains( $assets, "'moreFilters'") && str_contains( $assets, "'noItemsYet'" ),
	'plugin wires admin presentation assets' => str_contains( $plugin, 'Assets::init();' ),
	'Work Items keep WordPress native tabs' => str_contains( $ops, 'nav-tab-wrapper' ) && str_contains( $ops, 'nav-tab-active' ),
	'Work Items keep WordPress native table surface' => str_contains( $ops, 'widefat striped' ),
	'Work Items keep WordPress native buttons' => str_contains( $ops, 'class="button"' ),
	'WP Pro toolbar uses progressive disclosure' => str_contains( $js, 'cb-work-more-filters-toggle' ) && str_contains( $js, "setAttribute( 'aria-expanded'" ),
	'WP Pro toolbar keeps search and primary filters visible' => str_contains( $js, "select[name=\"status\"]" ) && str_contains( $js, "select[name=\"project_id\"]" ) && str_contains( $js, "select[name=\"service_id\"]" ) && str_contains( $js, 'cb-work-toolbar__search' ),
	'advanced filter UI reuses Base ObjectPicker nodes' => str_contains( $js, "closest( '.cb-core-object-picker' )" ) && ! str_contains( $js, 'innerHTML' ),
	'Kanban is presented as Board without changing canonical view state' => str_contains( $js, "view=kanban" ) && str_contains( $js, 'strings.board' ),
	'empty states distinguish no data from filtered results' => str_contains( $js, 'noItemsYet' ) && str_contains( $js, 'noMatchingItems' ),
	'presentation script does not own network transport' => ! str_contains( $js, 'fetch(' ) && ! str_contains( $js, 'XMLHttpRequest' ) && ! str_contains( $js, 'admin-post.php' ),
	'presentation script does not persist private UI state' => ! str_contains( $js, 'localStorage' ) && ! str_contains( $js, 'sessionStorage' ),
	'workspace stylesheet scopes itself to Work Items' => str_contains( $css, '.cb-work-items-page' ) && str_contains( $css, '.cb-work-toolbar' ),
	'workspace stylesheet does not replace WordPress typography family' => ! preg_match( '/font-family\s*:/i', $css ),
	'workspace stylesheet contains no hardcoded presentation colours' => ! preg_match( '/#[0-9a-f]{3,8}\b/i', $css ) && ! preg_match( '/\brgba?\s*\(/i', $css ),
	'workspace stylesheet does not reskin WordPress buttons' => ! preg_match( '/\.button\s*\{/i', $css ) && ! preg_match( '/\.button[^,{]*,?\s*\{[^}]*background/im', $css ),
	'workspace stylesheet leaves Base ObjectPicker internals intact' => ! str_contains( $css, '.cb-core-object-picker__selected' ) && ! str_contains( $css, '.cb-core-object-picker__results' ),
	'Board and Calendar custom layout are explicitly scoped' => str_contains( $css, '.cb-work-items-kanban' ) && str_contains( $css, '.cb-work-items-calendar' ),
];

$failed = false;
foreach ( $checks as $label => $passed ) {
	if ( ! $passed ) {
		fwrite( STDERR, "D3.5 WP Pro admin UX smoke failed: {$label}\n" );
		$failed = true;
	}
}

exit( $failed ? 1 : 0 );

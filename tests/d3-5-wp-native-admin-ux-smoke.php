<?php
declare(strict_types=1);

$root   = dirname( __DIR__ );
$assets = file_get_contents( $root . '/src/Admin/Assets.php' );
$plugin = file_get_contents( $root . '/src/Plugin.php' );
$css    = file_get_contents( $root . '/assets/work-admin.css' );
$js     = file_get_contents( $root . '/assets/work-admin.js' );
$ops    = file_get_contents( $root . '/src/Admin/Operations.php' );

$buttonReskin = false;
if ( preg_match_all( '/([^{}]*\.button[^{}]*)\{([^{}]*)\}/im', $css, $buttonBlocks, PREG_SET_ORDER ) ) {
	foreach ( $buttonBlocks as $block ) {
		$declarations = (string) ( $block[2] ?? '' );
		if ( preg_match( '/(?:^|;)\s*(?:background(?:-[a-z-]+)?|border(?:-[a-z-]+)?|color|box-shadow|font(?:-[a-z-]+)?|border-radius)\s*:/i', $declarations ) ) {
			$buttonReskin = true;
			break;
		}
	}
}

$checks = [
	'admin asset loader exists' => 1 === preg_match( "/const\\s+STYLE_HANDLE\\s*=\\s*'cb-work-admin'/", $assets ) && 1 === preg_match( "/const\\s+SCRIPT_HANDLE\\s*=\\s*'cb-work-admin'/", $assets ),
	'asset loader is Work Items scoped' => str_contains( $assets, 'Menu::screen_context()' ) && str_contains( $assets, 'Menu::CONTEXT_WORK_ITEMS !== $context' ),
	'asset loader fingerprints same-version RC assets' => substr_count( $assets, 'filemtime( $file )' ) >= 2,
	'asset loader localizes presentation labels' => str_contains( $assets, "'moreFilters'") && str_contains( $assets, "'noItemsYet'" ),
	'plugin wires admin presentation assets' => str_contains( $plugin, 'Assets::init();' ),
	'Work Items use the public Base Segmented Control for view switching' => str_contains( $ops, 'cb-core-segmented-control' ) && str_contains( $ops, 'cb-core-segmented-control__option' ) && str_contains( $assets, 'enqueue_segmented_control()' ),
	'Work Items keep WordPress native table surface' => str_contains( $ops, 'widefat striped' ),
	'Work Items keep WordPress native buttons' => str_contains( $ops, 'class="button"' ),
	'WP Pro toolbar uses progressive disclosure' => str_contains( $js, 'cb-work-more-filters-toggle' ) && str_contains( $js, "setAttribute( 'aria-expanded'" ),
	'WP Pro toolbar keeps search and primary filters server-rendered' => str_contains( $ops, 'id="cb-work-filter-status"' ) && str_contains( $ops, 'id="cb-work-filter-project"' ) && str_contains( $ops, 'cb-work-toolbar__search' ),
	'advanced filter UI reuses Base ObjectPicker server markup' => str_contains( $ops, "Pickers::customer( 'customer', 'cb-work-filter-customer'" ) && str_contains( $ops, "Pickers::assignee( 'assignee_id', 'cb-work-filter-assignee'" ) && ! str_contains( $js, 'innerHTML' ),
	'Kanban is presented as Board without changing canonical view state' => str_contains( $ops, "WorkItemViewState::VIEW_KANBAN" ) && str_contains( $ops, "=> __( 'Board', 'core-blueprint-work' )" ),
	'empty states distinguish no data from filtered results' => str_contains( $assets, "'noItemsYet'" ) && str_contains( $assets, "'noMatchingItems'" ),
	'presentation script does not own network transport' => ! str_contains( $js, 'fetch(' ) && ! str_contains( $js, 'XMLHttpRequest' ) && ! str_contains( $js, 'admin-post.php' ),
	'presentation script does not persist private UI state' => ! str_contains( $js, 'localStorage' ) && ! str_contains( $js, 'sessionStorage' ),
	'workspace stylesheet scopes itself to Work Items' => str_contains( $css, '.cb-work-items-page' ) && str_contains( $css, '.cb-work-toolbar' ),
	'workspace stylesheet does not replace WordPress typography family' => ! preg_match( '/font-family\s*:/i', $css ),
	'workspace stylesheet contains no hardcoded presentation colours' => ! preg_match( '/#[0-9a-f]{3,8}\b/i', $css ) && ! preg_match( '/\brgba?\s*\(/i', $css ),
	'workspace stylesheet does not reskin WordPress buttons' => ! $buttonReskin,
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

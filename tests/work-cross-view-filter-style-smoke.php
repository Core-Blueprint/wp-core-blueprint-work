<?php
declare(strict_types=1);

// CV-G-002: read-only design-token, source and asset-loading contract.
$root       = dirname( __DIR__ );
$css        = (string) file_get_contents( $root . '/assets/work-fast-paths.css' );
$operations = (string) file_get_contents( $root . '/src/Admin/Operations.php' );
$state      = (string) file_get_contents( $root . '/src/Admin/WorkItemViewState.php' );
$assets     = (string) file_get_contents( $root . '/src/Admin/Assets.php' );

$checks = [
	'All uses view-aware explicit sort state rather than workload comparison' =>
		str_contains( $operations, "! empty( \$state['sort_explicit'] )" )
		&& ! str_contains( $operations, "WorkItemQuery::SORT_WORKLOAD !== (string) \$state['sort']" )
		&& str_contains( $state, "self::VIEW_LIST === \$view ? WorkItemQuery::SORT_TITLE : WorkItemQuery::SORT_WORKLOAD" )
		&& str_contains( $state, "'sort_explicit'  => \$sort_explicit" ),
	'Invalid customer state cannot look like an unfiltered All preset' =>
		str_contains( $operations, "'' !== (string) \$state['customer']" ),
	'Preset links use the same server-owned class and semantic current indicator' =>
		str_contains( $operations, "cb-work-fast-path <?php echo ! empty( \$link['current'] ) ? 'is-current' : ''; ?>" )
		&& str_contains( $operations, "aria-current=\"page\"" ),
	'Selected styling follows both class and semantic aria-current in WordPress' =>
		str_contains( $css, 'body.wp-admin .cb-work-items-page--refined .cb-work-fast-path.is-current' )
		&& str_contains( $css, 'body.wp-admin .cb-work-items-page--refined .cb-work-fast-path[aria-current="page"]' )
		&& str_contains( $css, 'var(--cb-accent)' )
		&& str_contains( $css, 'var(--cb-on-accent)' ),
	'Inactive chips remain readable and focus indicators are visible in both themes' =>
		str_contains( $css, '.cb-work-fast-path:visited' )
		&& str_contains( $css, 'color: var(--cb-text-strong);' )
		&& str_contains( $css, 'background: var(--cb-interactive-hover);' )
		&& str_contains( $css, '.cb-work-fast-path:focus-visible' )
		&& str_contains( $css, 'outline: 2px solid var(--cb-interactive-focus);' )
		&& str_contains( $css, 'outline-offset: 3px;' )
		&& str_contains( $css, '@media (forced-colors: active)' ),
	'CSS scoped to Work Items and enqueued after refinement in all views' =>
		str_contains( $assets, "assets/work-fast-paths.css" )
		&& str_contains( $assets, "[ self::REFINEMENT_STYLE_HANDLE ]" )
		&& str_contains( $assets, 'self::enqueue_fast_path_assets();' )
		&& ! preg_match( '/#[0-9a-fA-F]{3,8}\b/', $css ),
];

foreach ( $checks as $label => $ok ) {
	if ( ! $ok ) {
		fwrite( STDERR, "Cross-view filter style smoke FAILED: {$label}\n" );
		exit( 1 );
	}
}

echo "Cross-view filter style smoke passed (semantic active, Light/Dark tokens, focus and Work-only assets).\n";

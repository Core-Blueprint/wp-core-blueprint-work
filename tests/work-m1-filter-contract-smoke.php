<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$router = (string) file_get_contents( $root . '/assets/js/workspace/canonical-filters.js' );
$mount = (string) file_get_contents( $root . '/assets/js/workspace/canonical-mount.js' );
$style = (string) file_get_contents( $root . '/assets/css/unified-workspace.css' );
$assets = (string) file_get_contents( $root . '/src/Admin/Assets.php' );
$canonical = (string) file_get_contents( $root . '/src/Admin/Operations.php' );
$check = (string) file_get_contents( $root . '/tools/check' );

$rules = [
	'Work Items native renderer source remains protected' =>
		'92a4814f24b43f96d16e3d96ce61a49ee9f21500'
			=== sha1( 'blob ' . strlen( $canonical ) . "\0" . $canonical ),
	'canonically rendered filter form is visible; mutation forms remain held' =>
		str_contains( $style, '.cb-work-unified .cb-work-uwe__canonical-view form,' )
		&& str_contains( $style, '.cb-work-unified .cb-work-uwe__canonical-view form.cb-work-items-filters {' )
		&& str_contains( $style, 'display: block;' ),
	'new filter adapter is packaged and imported by canonical mount' =>
		is_file( $root . '/assets/js/workspace/canonical-filters.js' )
		&& str_contains( $mount, "from './canonical-filters.js'" )
		&& str_contains( $assets, "CB_WORK_DIR . 'assets/js/workspace/canonical-filters.js'" )
		&& str_contains( $assets, 'is_file( $canonical_filters )' ),
	'native filter submissions stay within the Workspace URL and are read only' =>
		str_contains( $mount, "destination.addEventListener('submit'" )
		&& str_contains( $mount, "form.cb-work-items-filters" )
		&& str_contains( $mount, 'canonicalFilterUrl(' )
		&& str_contains( $mount, 'canonicalLinkUrl(' )
		&& str_contains( $mount, 'browser.history.pushState(' )
		&& ! str_contains( $mount, "method: 'POST'" ),
	'WordPress admin route and AJAX actions are excluded from link interception' =>
		str_contains( $router, "native.searchParams.get('page') !== NATIVE_PAGE" )
		&& str_contains( $router, "native.searchParams.has('action')" )
		&& str_contains( $router, "CANONICAL_FIELD_MAP = Object.freeze({ s: 'search', paged: 'uwe_page' })" ),
	'new runtime behavioral test is wired into canonical Level 1' =>
		str_contains( $check, 'work-m1-filter-contract-smoke.php' )
		&& str_contains( $check, 'work-m1-canonical-filters-runtime.mjs' ),
];
foreach ( $rules as $label => $passed ) {
	if ( ! $passed ) {
		fwrite( STDERR, "Work M1 filter router contract FAILED: {$label}\n" );
		exit( 1 );
	}
}
echo "Work M1 canonical filter/router source contract: PASS.\n";

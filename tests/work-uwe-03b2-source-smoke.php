<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$shell = (string) file_get_contents( $root . '/src/Admin/UnifiedWorkspace.php' );
$assets = (string) file_get_contents( $root . '/src/Admin/Assets.php' );
$app = (string) file_get_contents( $root . '/assets/js/workspace/app.js' );
$client = (string) file_get_contents( $root . '/assets/js/workspace/data-client.js' );
$state = (string) file_get_contents( $root . '/assets/js/workspace/state.js' );
$css = (string) file_get_contents( $root . '/assets/css/unified-workspace.css' );
$operations = (string) file_get_contents( $root . '/src/Admin/Operations.php' );

$checks = [
	'manager-only SSR fallback stays present' =>
		str_contains( $shell, 'current_user_can( Capabilities::MANAGE )' )
		&& str_contains( $shell, 'WorkItems::all( 25 )' )
		&& str_contains( $shell, 'Projects::all( 25 )' )
		&& str_contains( $shell, 'Services::all( 25 )' ),
	'private REST nonce is attribute-only; endpoints resolved by WordPress for each section' =>
		str_contains( $shell, "wp_create_nonce( 'wp_rest' )" )
		&& str_contains( $shell, "rest_url( 'cb-work/v1/workspace/' . \$key )" )
		&& str_contains( $shell, 'data-cb-work-uwe-endpoint=' )
		&& ! str_contains( $shell, 'admin-ajax.php' ),
	'accessible progressive-enhancement controls and server fallback' =>
		str_contains( $shell, 'role="search"' )
		&& str_contains( $shell, 'data-cb-work-uwe-controls hidden' )
		&& str_contains( $shell, 'data-cb-work-uwe-list' )
		&& str_contains( $shell, 'data-cb-work-uwe-empty' )
		&& str_contains( $shell, 'data-cb-work-uwe-pagination' )
		&& str_contains( $shell, 'aria-live="polite"' )
		&& str_contains( $shell, 'role="alert"' ),
	'all new assets physically exist and are import-connected' =>
		is_file( $root . '/assets/js/workspace/state.js' )
		&& is_file( $root . '/assets/js/workspace/data-client.js' )
		&& str_contains( $app, "from './state.js'" )
		&& str_contains( $app, "from './data-client.js'" )
		&& str_contains( $assets, "CB_WORK_DIR . 'assets/js/workspace/state.js'" )
		&& str_contains( $assets, "CB_WORK_DIR . 'assets/js/workspace/data-client.js'" )
		&& str_contains( $assets, 'is_file( $state )' )
		&& str_contains( $assets, 'is_file( $data_client )' ),
	'private GET with nonce header and no HTML interpolation' =>
		str_contains( $client, "'X-WP-Nonce': nonce" )
		&& str_contains( $client, "'same-origin'" )
		&& str_contains( $client, "method: 'GET'" )
		&& str_contains( $app, 'title.textContent = String(' )
		&& ! str_contains( $app, 'innerHTML' ),
	'responsive, accessible loading and reduced motion' =>
		str_contains( $css, '.cb-work-unified [hidden]' )
		&& str_contains( $css, '@media (prefers-reduced-motion: reduce)' )
		&& str_contains( $app, "panel.setAttribute('aria-busy', 'true')" )
		&& str_contains( $app, "panel.removeAttribute('aria-busy')" ),
	'filter URL and history support' =>
		str_contains( $state, 'URLSearchParams' )
		&& str_contains( $state, 'dataStateUrl' )
		&& str_contains( $app, 'browser.history.pushState' )
		&& str_contains( $app, 'browser.history.replaceState' )
		&& str_contains( $app, "'popstate'" ),
	'WordPress page route is reserved and pagination is namespaced' =>
		str_contains( $state, "params.get('uwe_page')" )
		&& str_contains( $state, "url.searchParams.set('uwe_page', String(state.page))" )
		&& ! str_contains( $state, "url.searchParams.delete('page')" )
		&& ! str_contains( $state, "url.searchParams.set('page', String(state.page))" )
		&& str_contains( $state, "parameters.set('page', String(state.page))" ),
	'overlapping requests cancelled with sequence guard' =>
		str_contains( $client, 'current.abort()' )
		&& str_contains( $app, 'refreshSequence' )
		&& str_contains( $app, 'sequence !== refreshSequence' ),
	'G0 preserved and no Work mutations added' =>
		'92a4814f24b43f96d16e3d96ce61a49ee9f21500' === sha1( 'blob ' . strlen( $operations ) . "\0" . $operations )
		&& ! str_contains( $app, 'method: "POST"' )
		&& ! str_contains( $app, 'method: \'POST\'' )
		&& ! str_contains( $operations, 'UnifiedWorkspace' ),
];

foreach ( $checks as $label => $pass ) {
	if ( ! $pass ) {
		fwrite( STDERR, "UWE-03B.2 source regression FAILED: {$label}\n" );
		exit( 1 );
	}
}
echo "UWE-03B.2 reactive source regression: PASS.\n";

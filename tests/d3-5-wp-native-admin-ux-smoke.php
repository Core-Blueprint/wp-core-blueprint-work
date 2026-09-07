<?php
declare(strict_types=1);

$root   = dirname( __DIR__ );
$assets = file_get_contents( $root . '/src/Admin/Assets.php' );
$plugin = file_get_contents( $root . '/src/Plugin.php' );
$css    = file_get_contents( $root . '/assets/work-admin.css' );
$ops    = file_get_contents( $root . '/src/Admin/Operations.php' );

$checks = [
	'admin asset loader exists' => str_contains( $assets, "STYLE_HANDLE = 'cb-work-admin'" ),
	'asset loader is Work Items scoped' => str_contains( $assets, 'Menu::WORK_ITEMS_SLUG !== $page' ),
	'asset loader fingerprints same-version RC assets' => str_contains( $assets, 'filemtime( $file )' ),
	'plugin wires admin presentation assets' => str_contains( $plugin, 'Assets::init();' ),
	'Work Items keep WordPress native tabs' => str_contains( $ops, 'nav-tab-wrapper' ) && str_contains( $ops, 'nav-tab-active' ),
	'Work Items keep WordPress native table surface' => str_contains( $ops, 'widefat striped' ),
	'Work Items keep WordPress native buttons' => str_contains( $ops, 'class="button"' ),
	'layout stylesheet scopes itself to Work Items' => str_contains( $css, '.cb-work-items-page' ) && str_contains( $css, '.cb-work-items-filters' ),
	'layout stylesheet does not replace WordPress typography' => ! preg_match( '/font-family\s*:/i', $css ),
	'layout stylesheet does not introduce custom text colors' => ! preg_match( '/(^|[;{])\s*color\s*:/im', $css ),
	'layout stylesheet does not reskin button backgrounds' => ! preg_match( '/background(?:-color)?\s*:/i', $css ),
	'layout stylesheet leaves Base ObjectPicker internals intact' => ! str_contains( $css, '.cb-core-object-picker__selected' ) && ! str_contains( $css, '.cb-core-object-picker__results' ),
];

$failed = false;
foreach ( $checks as $label => $passed ) {
	if ( ! $passed ) {
		fwrite( STDERR, "D3.5 admin UX smoke failed: {$label}\n" );
		$failed = true;
	}
}

exit( $failed ? 1 : 0 );

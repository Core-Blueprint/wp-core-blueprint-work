<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$page = (string) file_get_contents( $root . '/src/Admin/Page.php' );
$taxActions = (string) file_get_contents( $root . '/src/Admin/TaxRateActions.php' );
$bootstrap = (string) file_get_contents( $root . '/core-blueprint-work.php' );
$menu = (string) file_get_contents( $root . '/src/Admin/Menu.php' );

$checks = [
	'Work registers settings on the Extensions Hub hook' => str_contains( $page, "add_action( 'core_blueprint_register_settings'" ),
	'Work settings use the canonical extension identity' => str_contains( $page, 'SettingsRegistry::register' ) && str_contains( $page, 'Suite::EXTENSION_ID' ),
	'Work settings are grouped under Business' => str_contains( $page, 'SettingsRegistry::GROUP_BUSINESS' ),
	'Work settings retain semantic Base component requirements' => str_contains( $page, "'panels'" ) && str_contains( $page, "'notices'" ) && str_contains( $page, "'fields'" ) && str_contains( $page, "'form-controls'" ),
	'Work provider body does not redraw the Core Admin shell' => ! str_contains( $page, '<div class="wrap' ) && ! str_contains( $page, '<h1 class="cb-core-title"' ) && ! str_contains( $page, 'cb-core-intro' ),
	'Work no longer registers a legacy Core Admin page' => ! str_contains( $page, 'cb_core_register_pages' ) && ! str_contains( $page, 'PageRegistry' ) && ! str_contains( $page, "core-blueprint-work-settings" ),
	'VAT redirects use the canonical provider URL and preserve notice state' => str_contains( $taxActions, 'Page::settings_url(' ) && str_contains( $taxActions, "'cb-work-notice'" ) && ! str_contains( $taxActions, 'Page::SLUG' ),
	'Work product runtime requires SettingsRegistry instead of PageRegistry' => str_contains( $bootstrap, '\\\\CoreBlueprint\\\\Core\\\\Admin\\\\SettingsRegistry' ) && ! str_contains( $bootstrap, 'PageRegistry' ),
	'Operational Work navigation remains independent from settings' => str_contains( $menu, 'TOP_LEVEL_SLUG' ) && str_contains( $menu, "'core-blueprint-work'" ) && ! str_contains( $menu, 'SettingsRegistry' ),
];

foreach ( $checks as $label => $passed ) {
	if ( ! $passed ) {
		fwrite( STDERR, "Settings Hub smoke failed: {$label}\n" );
		exit( 1 );
	}
}

echo "Settings Hub smoke passed.\n";

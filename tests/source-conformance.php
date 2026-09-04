<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$required = [
	'core-blueprint-work.php',
	'src/Admin/Page.php',
	'src/Admin/ServicePricing.php',
	'src/Admin/TaxRateActions.php',
	'src/Capabilities.php',
	'src/Content/PostTypes.php',
	'src/Content/ServicePricing.php',
	'src/Database/Schema.php',
	'src/Governance/Events.php',
	'src/Integration/Suite.php',
	'src/Lifecycle.php',
	'src/Plugin.php',
	'src/Pricing/Resolver.php',
	'src/PublicApi/Pricing.php',
	'src/PublicApi/PricingProviders.php',
	'src/PublicApi/Services.php',
	'src/PublicApi/TaxRates.php',
	'src/Repository/TaxRates.php',
	'src/Support/Requirements.php',
	'docs/ARCHITECTURE.md',
	'assets/service-pricing.js',
];

$failed = false;
foreach ( $required as $relative ) {
	if ( ! is_file( $root . '/' . $relative ) ) {
		fwrite( STDERR, "Missing required file: {$relative}\n" );
		$failed = true;
	}
}

$bootstrap  = file_get_contents( $root . '/core-blueprint-work.php' );
$suite      = file_get_contents( $root . '/src/Integration/Suite.php' );
$page       = file_get_contents( $root . '/src/Admin/Page.php' );
$taxActions = file_get_contents( $root . '/src/Admin/TaxRateActions.php' );
$pricingUi  = file_get_contents( $root . '/src/Admin/ServicePricing.php' );
$arch       = file_get_contents( $root . '/docs/ARCHITECTURE.md' );
$service    = file_get_contents( $root . '/src/Content/PostTypes.php' );
$public     = file_get_contents( $root . '/src/PublicApi/Services.php' )
	. file_get_contents( $root . '/src/PublicApi/TaxRates.php' )
	. file_get_contents( $root . '/src/PublicApi/Pricing.php' )
	. file_get_contents( $root . '/src/PublicApi/PricingProviders.php' );
$resolver   = file_get_contents( $root . '/src/Pricing/Resolver.php' );
$allSource  = '';
foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/src' ) ) as $file ) {
	if ( $file->isFile() && 'php' === strtolower( $file->getExtension() ) ) {
		$allSource .= file_get_contents( $file->getPathname() );
	}
}

$settingsPos = strpos( $page, 'private static function render_settings' );
$vatFormPos  = strpos( $page, 'name="action" value="cb_work_add_tax_rate"' );

$checks = [
	'candidate version is rc2.1' => str_contains( $bootstrap, "Version:           1.0.0-rc2.1" ) && str_contains( $bootstrap, "CB_WORK_VERSION', '1.0.0-rc2.1'" ),
	'bootstrap registers Work schema before Base sweep' => str_contains( $bootstrap, "}, 4 );" ) && str_contains( $bootstrap, 'Database\\Schema::register();' ),
	'bootstrap waits for public Base boot signal' => str_contains( $bootstrap, "add_action( 'cb_core_booted'" ),
	'bootstrap does not pin an internal Base RC' => ! str_contains( $bootstrap, 'CB_WORK_REQUIRED_BASE' ),
	'suite registers through canonical extension hook' => str_contains( $suite, 'cb_core_register_extensions' ),
	'suite relies on Core API rather than requires_base' => ! str_contains( $suite, "'requires_base'" ),
	'suite exposes factual service and VAT health' => str_contains( $suite, 'services · %2$d VAT rates' ),
	'Work owns canonical service post type' => str_contains( $service, "SERVICE = 'cb_work_service'" ),
	'public sibling contracts exist' => str_contains( $public, 'Supported read-only' ) && str_contains( $public, 'Supported effective-pricing contract' ),
	'pricing provider seam is Work-owned and lazy' => str_contains( $public, 'cb_work_register_pricing_providers' ) && str_contains( $public, 'PricingProviders' ),
	'pricing resolver owns explicit-agreement-default precedence' => str_contains( $resolver, "'explicit_override'" ) && str_contains( $resolver, "'customer_agreement'" ) && str_contains( $resolver, "'service_default'" ),
	'pricing resolver validates tax through Work public API' => str_contains( $resolver, 'TaxRates::is_available' ),
	'admin page requests Base nav-tabs component' => str_contains( $page, "'nav-tabs'" ),
	'admin page uses Base nav-tab markup' => str_contains( $page, 'nav-tab-wrapper cb-core-tab-wrapper' ) && str_contains( $page, 'nav-tab-active' ),
	'VAT form is isolated in Settings render route' => false !== $settingsPos && false !== $vatFormPos && $vatFormPos > $settingsPos,
	'VAT configured heading uses panel-safe h2 rather than raw h3' => ! str_contains( $page, '<h3' ) && str_contains( $page, "Configured VAT rates" ),
	'VAT actions return to Work Settings' => str_contains( $taxActions, "'view'           => Page::VIEW_SETTINGS" ),
	'service editor links to Work Settings for VAT' => str_contains( $pricingUi, 'Page::settings_url()' ),
	'Work does not register standalone product submenus' => ! str_contains( $allSource, 'add_submenu_page(' ),
	'admin page avoids unsupported Base tables requirement' => ! str_contains( $page, "'tables'" ),
	'architecture records VAT as Work Settings' => str_contains( $arch, 'VAT rates live under `Work → Settings`' ),
	'architecture separates commercial recurrence from work recurrence' => str_contains( $arch, 'commercial cadence, not Work Item recurrence' ),
	'architecture forbids direct sibling SQL' => str_contains( $arch, 'No direct sibling-table reads/writes.' ),
	'no CRM private namespace dependency in Work source' => ! str_contains( $allSource, 'CB\\CRM\\' ),
	'no Helpdesk private namespace dependency in Work source' => ! str_contains( $allSource, 'CB\\Helpdesk\\' ),
	'no jQuery dependency' => ! str_contains( strtolower( file_get_contents( $root . '/assets/service-pricing.js' ) ), 'jquery' ),
];

foreach ( $checks as $label => $passed ) {
	if ( ! $passed ) {
		fwrite( STDERR, "Conformance failed: {$label}\n" );
		$failed = true;
	}
}

exit( $failed ? 1 : 0 );

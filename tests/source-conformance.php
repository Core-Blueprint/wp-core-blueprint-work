<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$required = [
	'core-blueprint-work.php',
	'src/Admin/Menu.php',
	'src/Admin/Operations.php',
	'src/Admin/OperationalActions.php',
	'src/Admin/Page.php',
	'src/Admin/Pickers.php',
	'src/Admin/Projects.php',
	'src/Admin/ServicePricing.php',
	'src/Admin/TaxRateActions.php',
	'src/Capabilities.php',
	'src/Content/PostTypes.php',
	'src/Content/ProjectMeta.php',
	'src/Content/ServicePricing.php',
	'src/Database/Schema.php',
	'src/Domain/BillingDisposition.php',
	'src/Domain/WorkItemPriority.php',
	'src/Domain/WorkItemStatus.php',
	'src/Governance/Events.php',
	'src/Integration/CRMCustomers.php',
	'src/Integration/Suite.php',
	'src/Lifecycle.php',
	'src/Plugin.php',
	'src/Pricing/Resolver.php',
	'src/PublicApi/Pricing.php',
	'src/PublicApi/PricingProviders.php',
	'src/PublicApi/Projects.php',
	'src/PublicApi/Services.php',
	'src/PublicApi/TaxRates.php',
	'src/PublicApi/WorkItems.php',
	'src/PublicApi/WorkTypes.php',
	'src/Repository/Projects.php',
	'src/Repository/TaxRates.php',
	'src/Repository/WorkItems.php',
	'src/Repository/WorkTypes.php',
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
$menu       = file_get_contents( $root . '/src/Admin/Menu.php' );
$operations = file_get_contents( $root . '/src/Admin/Operations.php' );
$operationalActions = file_get_contents( $root . '/src/Admin/OperationalActions.php' );
$projectsAdmin = file_get_contents( $root . '/src/Admin/Projects.php' );
$pickers    = file_get_contents( $root . '/src/Admin/Pickers.php' );
$page       = file_get_contents( $root . '/src/Admin/Page.php' );
$taxActions = file_get_contents( $root . '/src/Admin/TaxRateActions.php' );
$pricingUi  = file_get_contents( $root . '/src/Admin/ServicePricing.php' );
$arch       = file_get_contents( $root . '/docs/ARCHITECTURE.md' );
$schema     = file_get_contents( $root . '/src/Database/Schema.php' );
$postTypes  = file_get_contents( $root . '/src/Content/PostTypes.php' );
$projectMeta= file_get_contents( $root . '/src/Content/ProjectMeta.php' );
$workItems  = file_get_contents( $root . '/src/Repository/WorkItems.php' );
$projects   = file_get_contents( $root . '/src/Repository/Projects.php' );
$crm        = file_get_contents( $root . '/src/Integration/CRMCustomers.php' );
$public     = file_get_contents( $root . '/src/PublicApi/Services.php' )
	. file_get_contents( $root . '/src/PublicApi/TaxRates.php' )
	. file_get_contents( $root . '/src/PublicApi/Pricing.php' )
	. file_get_contents( $root . '/src/PublicApi/PricingProviders.php' )
	. file_get_contents( $root . '/src/PublicApi/Projects.php' )
	. file_get_contents( $root . '/src/PublicApi/WorkItems.php' )
	. file_get_contents( $root . '/src/PublicApi/WorkTypes.php' );
$resolver   = file_get_contents( $root . '/src/Pricing/Resolver.php' );

$allSource = '';
foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/src' ) ) as $file ) {
	if ( $file->isFile() && 'php' === strtolower( $file->getExtension() ) ) {
		$allSource .= file_get_contents( $file->getPathname() );
	}
}

$settingsPos = strpos( $page, 'private static function render_settings' );
$vatFormPos  = strpos( $page, 'name="action" value="cb_work_add_tax_rate"' );

$checks = [
	'candidate version is rc4' => str_contains( $bootstrap, 'Version:           1.0.0-rc4' ) && str_contains( $bootstrap, "CB_WORK_VERSION', '1.0.0-rc4'" ),
	'D1.1 schema version is 1.2' => str_contains( $bootstrap, "CB_WORK_SCHEMA_VERSION', '1.2'" ),
	'bootstrap registers Work schema before Base sweep' => str_contains( $bootstrap, "}, 4 );" ) && str_contains( $bootstrap, 'Database\\Schema::register();' ),
	'bootstrap waits for public Base boot signal' => str_contains( $bootstrap, "add_action( 'cb_core_booted'" ),
	'bootstrap does not pin an internal Base RC' => ! str_contains( $bootstrap, 'CB_WORK_REQUIRED_BASE' ),
	'suite registers through canonical extension hook' => str_contains( $suite, 'cb_core_register_extensions' ),
	'suite relies on Core API rather than requires_base' => ! str_contains( $suite, "'requires_base'" ),
	'suite exposes factual operational health' => str_contains( $suite, 'work items · %2$d projects · %3$d services · %4$d VAT rates' ),
	'suite Project shortcut uses native Project URL' => str_contains( $suite, 'Menu::projects_url()' ),
	'Work owns canonical Service and Project post types' => str_contains( $postTypes, "SERVICE = 'cb_work_service'" ) && str_contains( $postTypes, "PROJECT = 'cb_work_project'" ),
	'Project CPT is private by default' => substr_count( $postTypes, "'publicly_queryable'  => false" ) >= 2,
	'Project metadata is registered Work post meta' => str_contains( $projectMeta, 'register_post_meta( PostTypes::PROJECT' ),
	'D1.1 owns relational Work Items but not relational Projects' => str_contains( $schema, "'cb_work_items'" ) && ! str_contains( $schema, 'projects_table' ),
	'D1.1 destructively removes the transitional Project table' => 1 === substr_count( $schema, 'cb_work_projects' ) && str_contains( $schema, 'DROP TABLE IF EXISTS' ),
	'D1.1 retains relational assignments and generic external relations' => str_contains( $schema, "'cb_work_item_assignments'" ) && str_contains( $schema, "'cb_work_item_relations'" ),
	'D1.1 has no cross-plugin SQL foreign keys' => ! str_contains( strtoupper( $schema ), 'FOREIGN KEY' ),
	'Project repository uses WordPress-native storage only' => str_contains( $projects, 'WP_Query' ) && str_contains( $projects, 'get_post(' ) && ! str_contains( $projects, 'Schema::' ) && ! str_contains( $projects, '$wpdb' ),
	'Work Item validates optional Project against Project CPT repository' => str_contains( $workItems, 'Projects::get( $project_id )' ),
	'Work Item keeps completion and billing separate' => str_contains( $schema, 'completed_at datetime NULL' ) && str_contains( $schema, 'billing_disposition varchar(32)' ),
	'Work Item emits create update and lifecycle hooks' => str_contains( $workItems, 'cb_work_work_item_created' ) && str_contains( $workItems, 'cb_work_work_item_updated' ) && str_contains( $workItems, 'cb_work_work_item_status_changed' ),
	'public sibling contracts include Projects Work Items and Work Types' => str_contains( $public, 'Supported read-only Project contract' ) && str_contains( $public, 'Supported read-only Work Item contract' ) && str_contains( $public, 'Supported read-only Work Type contract' ),
	'pricing provider seam is Work-owned and lazy' => str_contains( $public, 'cb_work_register_pricing_providers' ) && str_contains( $public, 'PricingProviders' ),
	'pricing resolver owns explicit-agreement-default precedence' => str_contains( $resolver, "'explicit_override'" ) && str_contains( $resolver, "'customer_agreement'" ) && str_contains( $resolver, "'service_default'" ),
	'pricing resolver validates tax through Work public API' => str_contains( $resolver, 'TaxRates::is_available' ),
	'Work owns a standalone operational top-level menu' => str_contains( $menu, 'add_menu_page(' ) && str_contains( $menu, "TOP_LEVEL_SLUG   = 'core-blueprint-work'" ),
	'operational menu mounts Work Items native Projects native Services and Work Types' => str_contains( $menu, 'WORK_ITEMS_SLUG' ) && str_contains( $menu, 'PostTypes::PROJECT' ) && str_contains( $menu, 'PostTypes::SERVICE' ) && str_contains( $menu, 'WORK_TYPES_SLUG' ),
	'Project admin has contextual Work Item management' => str_contains( $projectsAdmin, 'WorkItems::for_project' ) && str_contains( $projectsAdmin, 'Add Work Item' ) && str_contains( $projectsAdmin, 'View all Work Items' ),
	'Work Items support create edit and governed status transitions' => str_contains( $operations, 'Add Work Item' ) && str_contains( $operations, 'Edit Work Item' ) && str_contains( $operationalActions, 'WorkItems::update' ) && str_contains( $operationalActions, 'transition_status' ),
	'raw customer/source provider type id controls are absent from primary Work UI' => ! str_contains( $operations, 'reference_fields' ) && ! str_contains( $operations, 'External/source relation' ) && ! str_contains( $operations, 'placeholder="provider"' ),
	'CRM customer picker uses documented public Frontend Queries only' => str_contains( $crm, '\\CB\\CRM\\Frontend\\Queries\\Contacts' ) && str_contains( $crm, '\\CB\\CRM\\Frontend\\Queries\\Organizations' ) && ! str_contains( $crm, 'CB\\CRM\\Repository' ) && ! str_contains( $crm, '$wpdb' ),
	'CRM customer integration remains fail-soft' => str_contains( $crm, 'class_exists' ) && str_contains( $crm, 'public static function available' ),
	'Base Object Picker provides customer and multi-user UX' => str_contains( $pickers, 'CB\\Core\\UI\\ObjectPicker' ) && str_contains( $pickers, 'Assets::enqueue_object_picker' ) && str_contains( $pickers, "'multiple'      => true" ),
	'Core Blueprint Work page is settings-only' => str_contains( $page, "SLUG = 'core-blueprint-work-settings'" ) && ! str_contains( $page, 'render_services' ) && ! str_contains( $page, 'VIEW_SERVICES' ),
	'Core Blueprint settings page does not request operational nav tabs' => ! str_contains( $page, "'nav-tabs'" ) && ! str_contains( $page, 'nav-tab-wrapper' ),
	'VAT form is isolated in Settings render route' => false !== $settingsPos && false !== $vatFormPos && $vatFormPos > $settingsPos,
	'VAT configured heading uses panel-safe h2 rather than raw h3' => ! str_contains( $page, '<h3' ) && str_contains( $page, 'Configured VAT rates' ),
	'VAT actions return to Work Settings without legacy view routing' => str_contains( $taxActions, "'page'           => Page::SLUG" ) && ! str_contains( $taxActions, "'view'" ),
	'Service editor links to Work Settings for VAT' => str_contains( $pricingUi, 'Page::settings_url()' ),
	'admin settings page avoids unsupported Base tables requirement' => ! str_contains( $page, "'tables'" ),
	'architecture documents Project CPT and relational Work Item hybrid' => str_contains( $arch, '`cb_work_project`' ) && str_contains( $arch, '`cb_work_items`' ) && str_contains( $arch, 'no legacy bridges' ),
	'architecture separates commercial recurrence from work recurrence' => str_contains( $arch, 'recurring commercial pricing is not Work Item recurrence' ),
	'architecture forbids direct sibling SQL' => str_contains( $arch, 'No cross-plugin SQL foreign keys' ),
	'no CRM private repository dependency in Work source' => ! str_contains( $allSource, 'CB\\CRM\\Repository' ) && ! str_contains( $allSource, 'cb_crm_' ),
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

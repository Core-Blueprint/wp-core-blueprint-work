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
	'src/Admin/WorkItems.php',
	'src/Admin/ServicePricing.php',
	'src/Admin/TaxRateActions.php',
	'src/Capabilities.php',
	'src/Content/PostTypes.php',
	'src/Content/ProjectMeta.php',
	'src/Content/ProjectRestController.php',
	'src/Content/WorkItemMeta.php',
	'src/Content/WorkItemRestController.php',
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
$workItemsAdmin = file_get_contents( $root . '/src/Admin/WorkItems.php' );
$pickers    = file_get_contents( $root . '/src/Admin/Pickers.php' );
$page       = file_get_contents( $root . '/src/Admin/Page.php' );
$taxActions = file_get_contents( $root . '/src/Admin/TaxRateActions.php' );
$pricingUi  = file_get_contents( $root . '/src/Admin/ServicePricing.php' );
$arch       = file_get_contents( $root . '/docs/ARCHITECTURE.md' );
$schema     = file_get_contents( $root . '/src/Database/Schema.php' );
$postTypes  = file_get_contents( $root . '/src/Content/PostTypes.php' );
$projectMeta= file_get_contents( $root . '/src/Content/ProjectMeta.php' );
$workItemMeta = file_get_contents( $root . '/src/Content/WorkItemMeta.php' );
$projectRest = file_get_contents( $root . '/src/Content/ProjectRestController.php' );
$workItemRest = file_get_contents( $root . '/src/Content/WorkItemRestController.php' );
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
	'candidate version is rc5' => 1 === preg_match( '/Version:\s+1\.0\.0-rc5/', $bootstrap ) && str_contains( $bootstrap, "CB_WORK_VERSION', '1.0.0-rc5'" ),
	'D1.2 schema version is 1.3' => str_contains( $bootstrap, "CB_WORK_SCHEMA_VERSION', '1.3'" ),
	'bootstrap registers Work schema before Base sweep' => str_contains( $bootstrap, "}, 4 );" ) && str_contains( $bootstrap, 'Database\\Schema::register();' ),
	'bootstrap waits for public Base boot signal' => str_contains( $bootstrap, "add_action( 'cb_core_booted'" ),
	'bootstrap does not pin an internal Base RC' => ! str_contains( $bootstrap, 'CB_WORK_REQUIRED_BASE' ),
	'suite registers through canonical extension hook' => str_contains( $suite, 'cb_core_register_extensions' ),
	'suite relies on Core API rather than requires_base' => ! str_contains( $suite, "'requires_base'" ),
	'suite exposes factual operational health' => str_contains( $suite, 'work items · %2$d projects · %3$d services · %4$d VAT rates' ),
	'suite Project shortcut uses native Project URL' => str_contains( $suite, 'Menu::projects_url()' ),
	'Work owns canonical Service Project and Work Item post types' => 1 === preg_match( "/const\s+SERVICE\s*=\s*'cb_work_service'/", $postTypes ) && 1 === preg_match( "/const\s+PROJECT\s*=\s*'cb_work_project'/", $postTypes ) && 1 === preg_match( "/const\s+WORK_ITEM\s*=\s*'cb_work_item'/", $postTypes ),
	'Project and Work Item CPTs are private by default' => preg_match_all( "/'publicly_queryable'\s*=>\s*false/", $postTypes ) >= 2,
	'Project metadata is registered Work post meta' => str_contains( $projectMeta, 'register_post_meta( PostTypes::PROJECT' ),
	'Work Item metadata is registered Work post meta' => str_contains( $workItemMeta, 'register_post_meta( PostTypes::WORK_ITEM' ) && str_contains( $workItemMeta, '_cb_work_item_status' ),
	'Project Gutenberg REST route remains capability-gated' => str_contains( $projectRest, 'current_user_can( Capabilities::MANAGE )' ),
	'Work Item Gutenberg REST route remains capability-gated' => str_contains( $workItemRest, 'current_user_can( Capabilities::MANAGE )' ),
	'D1.2 owns CPT Work Items not relational Work Item table' => ! str_contains( $schema, 'work_items_table' ) && str_contains( $schema, "cb_work_items'" ) && str_contains( $schema, 'DROP TABLE IF EXISTS' ),
	'D1.2 destructively removes transitional Project and Work Item tables' => str_contains( $schema, 'cb_work_projects' ) && str_contains( $schema, 'cb_work_items' ) && substr_count( $schema, 'DROP TABLE IF EXISTS' ) >= 2,
	'D1.2 retains relational assignments and generic external relations' => str_contains( $schema, "'cb_work_item_assignments'" ) && str_contains( $schema, "'cb_work_item_relations'" ),
	'D1.2 has no cross-plugin SQL foreign keys' => ! str_contains( strtoupper( $schema ), 'FOREIGN KEY' ),
	'Project repository uses WordPress-native storage only' => str_contains( $projects, 'WP_Query' ) && str_contains( $projects, 'get_post(' ) && ! str_contains( $projects, 'Schema::' ) && ! str_contains( $projects, '$wpdb' ),
	'Work Item repository uses WordPress-native root storage' => str_contains( $workItems, 'WP_Query' ) && str_contains( $workItems, 'wp_insert_post(' ) && str_contains( $workItems, 'wp_update_post(' ) && ! str_contains( $workItems, 'Schema::work_items_table' ),
	'Work Item validates optional Project against Project CPT repository' => str_contains( $workItems, 'Projects::get( $project_id )' ),
	'Work Item keeps completion and billing separate' => str_contains( $workItemMeta, 'COMPLETED_AT' ) && str_contains( $workItemMeta, 'BILLING_DISPOSITION' ),
	'Work Item emits create update and lifecycle hooks' => str_contains( $workItems, 'cb_work_work_item_created' ) && str_contains( $workItems, 'cb_work_work_item_updated' ) && str_contains( $workItems, 'cb_work_work_item_status_changed' ),
	'public sibling contracts include Projects Work Items and Work Types' => str_contains( $public, 'Supported read-only Project contract' ) && str_contains( $public, 'Supported read-only Work Item contract' ) && str_contains( $public, 'Supported read-only Work Type contract' ),
	'pricing provider seam is Work-owned and lazy' => str_contains( $public, 'cb_work_register_pricing_providers' ) && str_contains( $public, 'PricingProviders' ),
	'pricing resolver owns explicit-agreement-default precedence' => str_contains( $resolver, "'explicit_override'" ) && str_contains( $resolver, "'customer_agreement'" ) && str_contains( $resolver, "'service_default'" ),
	'pricing resolver validates tax through Work public API' => str_contains( $resolver, 'TaxRates::is_available' ),
	'Work owns a standalone operational top-level menu' => str_contains( $menu, 'add_menu_page(' ) && str_contains( $menu, "TOP_LEVEL_SLUG     = 'core-blueprint-work'" ),
	'operational menu mounts Work Items native Projects native Services and Work Types' => str_contains( $menu, 'WORK_ITEMS_SLUG' ) && str_contains( $menu, 'PostTypes::PROJECT' ) && str_contains( $menu, 'PostTypes::SERVICE' ) && str_contains( $menu, 'WORK_TYPES_SLUG' ),
	'native Work Item editor stays visually under Work navigation' => str_contains( $menu, 'PostTypes::WORK_ITEM === (string) $screen->post_type' ) && str_contains( $menu, 'CONTEXT_WORK_ITEMS' ),
	'Project admin has contextual Work Item management' => str_contains( $projectsAdmin, 'WorkItems::for_project' ) && str_contains( $projectsAdmin, 'Menu::new_work_item_url( $project_id )' ) && str_contains( $projectsAdmin, 'Menu::edit_work_item_url' ),
	'Work Items use global workspace plus native Gutenberg editor and governed transitions' => str_contains( $operations, 'Add Work Item' ) && str_contains( $operations, 'Menu::edit_work_item_url' ) && str_contains( $workItemsAdmin, 'Work Item Details' ) && str_contains( $operationalActions, 'transition_status' ),
	'custom CRUD create update handlers are removed' => ! str_contains( $operationalActions, 'cb_work_create_work_item' ) && ! str_contains( $operationalActions, 'cb_work_update_work_item' ) && ! str_contains( $operations, 'render_work_item_form' ),
	'raw customer/source provider type id controls are absent from primary Work UI' => ! str_contains( $workItemsAdmin, 'source_provider' ) && ! str_contains( $workItemsAdmin, 'source_type' ) && ! str_contains( $workItemsAdmin, 'source_id' ),
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
	'architecture documents Project and Work Item CPT model' => str_contains( $arch, '`cb_work_project`' ) && str_contains( $arch, '`cb_work_item`' ) && str_contains( $arch, 'no legacy bridges' ),
	'architecture keeps high-volume child facts relational' => str_contains( $arch, 'high-volume child facts' ) && str_contains( $arch, 'time entries' ),
	'architecture separates commercial recurrence from work recurrence' => str_contains( $arch, 'recurring commercial pricing is not Work Item recurrence' ),
	'architecture forbids direct sibling SQL' => str_contains( $arch, 'No cross-plugin SQL foreign keys' ),
	'architecture keeps D3 builder-neutral and D4 Bricks thin' => str_contains( $arch, '**D3 — Builder-neutral Frontend Resource Contracts:**' ) && str_contains( $arch, '**D4 — Bricks Adapter:**' ),
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

<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$failed = false;

$required = [
	'core-blueprint-work.php',
	'src/Support/Requirements.php',
	'src/Plugin.php',
	'src/Lifecycle.php',
	'src/Database/Schema.php',
	'src/Capabilities.php',
	'src/Integration/Suite.php',
	'src/Integration/CRMCustomers.php',
	'src/Integration/DataExchange.php',
	'src/Frontend/Access.php',
	'src/Frontend/Actions/WorkItems.php',
	'src/PublicApi/Projects.php',
	'src/PublicApi/Services.php',
	'src/PublicApi/TaxRates.php',
	'src/PublicApi/Pricing.php',
	'src/PublicApi/PricingProviders.php',
	'src/PublicApi/WorkItems.php',
	'src/PublicApi/WorkTypes.php',
	'src/PublicApi/WorkItemActions.php',
	'src/PublicApi/TaxRateActions.php',
	'src/PublicApi/Billing.php',
	'src/PublicApi/BillingActions.php',
	'src/Content/ProjectRestController.php',
	'src/Content/WorkItemRestController.php',
	'src/Admin/OperationalActions.php',
	'src/Admin/RecurrenceActions.php',
	'src/Admin/TimeActions.php',
	'src/Admin/TaxRateActions.php',
	'src/Admin/Workspace.php',
	'src/Admin/ProjectWorkspace.php',
	'src/Admin/DailyOverview.php',
	'src/Admin/QuickAdd.php',
	'assets/work-workspace.css',
	'assets/project-workspace.css',
	'assets/work-fast-paths.js',
	'assets/work-quick-add.js',
	'tools/check',
	'tools/build-release',
];
foreach ( $required as $file ) {
	if ( ! is_file( $root . '/' . $file ) ) {
		fwrite( STDERR, "Missing required file: {$file}\n" );
		$failed = true;
	}
}

$read = static function ( string $path ) use ( $root ): string {
	$value = file_get_contents( $root . '/' . $path );
	return is_string( $value ) ? $value : '';
};

$bootstrap = $read( 'core-blueprint-work.php' );
$requirements = $read( 'src/Support/Requirements.php' );
$plugin = $read( 'src/Plugin.php' );
$lifecycle = $read( 'src/Lifecycle.php' );
$schema = $read( 'src/Database/Schema.php' );
$suite = $read( 'src/Integration/Suite.php' );
$crm = $read( 'src/Integration/CRMCustomers.php' );
$dataExchange = $read( 'src/Integration/DataExchange.php' );
$frontendAccess = $read( 'src/Frontend/Access.php' );
$pricingProviders = $read( 'src/PublicApi/PricingProviders.php' );
$projectRest = $read( 'src/Content/ProjectRestController.php' );
$workItemRest = $read( 'src/Content/WorkItemRestController.php' );
$buildRelease = $read( 'tools/build-release' );
$checkRunner = $read( 'tools/check' );

$publicReads = [
	'src/PublicApi/Projects.php',
	'src/PublicApi/Services.php',
	'src/PublicApi/TaxRates.php',
	'src/PublicApi/Pricing.php',
	'src/PublicApi/PricingProviders.php',
	'src/PublicApi/WorkItems.php',
	'src/PublicApi/WorkTypes.php',
];
foreach ( $publicReads as $path ) {
	if ( ! str_contains( $read( $path ), 'cb_work_runtime_ready' ) ) {
		fwrite( STDERR, "Public Work contract is not readiness-gated: {$path}\n" );
		$failed = true;
	}
}

$adminMutations = [
	'src/Admin/OperationalActions.php',
	'src/Admin/RecurrenceActions.php',
	'src/Admin/TimeActions.php',
	'src/Admin/TaxRateActions.php',
];
foreach ( $adminMutations as $path ) {
	$source = $read( $path );

	$has_capability_guard = str_contains(
		$source,
		'current_user_can( Capabilities::MANAGE )'
	);

	// Time has an intentional separate tracker capability. Its canonical
	// Access policy grants managers or cb_track_work_time and applies the
	// finer-grained ownership/assignment checks at the mutation boundary.
	if ( 'src/Admin/TimeActions.php' === $path ) {
		$has_capability_guard =
			str_contains( $source, 'Access::can_track()' )
			&& str_contains( $source, 'use CB\\Work\\Time\\Access;' );
	}

	if (
		! $has_capability_guard
		|| ! str_contains( $source, 'check_admin_referer(' )
	) {
		fwrite(
			STDERR,
			"Admin mutation boundary lost capability/nonce protection: {$path}\n"
		);
		$failed = true;
	}
}

$schemaTables = [
	'cb_work_tax_rates', 'cb_work_types', 'cb_work_item_assignments', 'cb_work_item_relations',
	'cb_work_item_sources', 'cb_work_billing_units', 'cb_work_billing_snapshots',
	'cb_work_billing_external_refs', 'cb_work_recurrence_rules', 'cb_work_recurrence_rule_assignments',
	'cb_work_recurrence_occurrences', 'cb_work_time_entries', 'cb_work_active_timers',
];
foreach ( $schemaTables as $table ) {
	if ( ! str_contains( $schema, $table ) ) {
		fwrite( STDERR, "Canonical Work schema table missing: {$table}\n" );
		$failed = true;
	}
}

$checks = [
	'launch candidate version is rc1' => str_contains( $bootstrap, 'Version:           1.0.0-rc1' ) && str_contains( $bootstrap, "CB_WORK_VERSION', '1.0.0-rc1'" ),
	'canonical native Base dependency is exact' => 1 === preg_match( '/^[ \t]*\*[ \t]*Requires Plugins:[ \t]*core-blueprint[ \t]*$/m', $bootstrap ),
	'PHP and Core API launch boundaries are explicit' => str_contains( $bootstrap, "CB_WORK_MIN_PHP', '8.4'" ) && str_contains( $bootstrap, "CB_WORK_REQUIRED_API', '1.1'" ),
	'generic Bootstrap stays implementation agnostic' => ! str_contains( $requirements, 'class_exists(' ) && ! str_contains( $requirements, 'SchemaRegistry' ),
	'canonical Bootstrap copy is present' => str_contains( $requirements, 'Core Blueprint must be installed and active.' ) && str_contains( $requirements, 'Available Core API: %2$s.' ),
	'Work owns explicit product-contract readiness' => str_contains( $bootstrap, 'function cb_work_product_contracts_ready(): bool' ) && str_contains( $bootstrap, 'SchemaRegistry' ) && str_contains( $bootstrap, 'ExtensionRegistry' ) && str_contains( $bootstrap, 'Governance\\\\Audit' ) && str_contains( $bootstrap, 'SettingsRegistry' ),
	'Work exposes one current-request runtime gate' => str_contains( $bootstrap, 'function cb_work_runtime_ready(): bool' ) && str_contains( $bootstrap, 'Requirements::runtime_ready() && cb_work_product_contracts_ready()' ),
	'Work capabilities fail closed outside readiness' => str_contains( $bootstrap, "'map_meta_cap'" ) && str_contains( $bootstrap, "'cb_manage_work'" ) && str_contains( $bootstrap, "'cb_track_work_time'" ) && str_contains( $bootstrap, "return [ 'do_not_allow' ];" ),
	'canonical ordering reaches integrations before feature boot' => strpos( $bootstrap, 'cb_work_product_contracts_ready()' ) < strpos( $bootstrap, 'Database\\Schema::register();' ) && strpos( $bootstrap, 'Database\\Schema::register();' ) < strpos( $bootstrap, 'Integration\\Suite::init();' ) && strpos( $bootstrap, 'Integration\\Suite::init();' ) < strpos( $bootstrap, "add_action( 'core_blueprint_booted'" ),
	'Work schema remains priority 4 before Base migration collection' => str_contains( $bootstrap, '}, 4 );' ),
	'direct Plugin boot rechecks full runtime readiness' => str_contains( $plugin, "function_exists( 'cb_work_runtime_ready' )" ) && str_contains( $plugin, '! \\cb_work_runtime_ready()' ),
	'Suite is idempotent and self-gated' => str_contains( $suite, 'private static bool $initialized = false;' ) && str_contains( $suite, '! self::runtime_ready()' ) && str_contains( $suite, 'cb_work_runtime_ready' ),
	'Frontend access is current-time readiness gated' => str_contains( $frontendAccess, 'cb_work_runtime_ready' ),
	'CRM adapter uses documented CRM query contracts only' => str_contains( $crm, 'private const CONTACT_QUERY' ) && str_contains( $crm, 'private const ORGANIZATION_QUERY' ) && str_contains( $crm, 'CB\\\\CRM\\\\Frontend\\\\Queries\\\\Contacts' ) && str_contains( $crm, 'CB\\\\CRM\\\\Frontend\\\\Queries\\\\Organizations' ) && ! str_contains( $crm, '$wpdb' ) && ! str_contains( $crm, 'CB\\CRM\\Repository' ),
	'CRM adapter is Work-readiness gated and fail soft' => str_contains( $crm, 'cb_work_runtime_ready' ) && str_contains( $crm, 'class_exists' ),
	'Data Exchange remains optional and Work-readiness gated' => str_contains( $dataExchange, 'cb_work_runtime_ready' ) && str_contains( $dataExchange, 'class_exists( Registry::class )' ) && str_contains( $dataExchange, 'interface_exists( CsvEntityInterface::class )' ),
	'no direct Docs Helpdesk or Commerce adapter is part of Work launch runtime' => ! is_file( $root . '/src/Integration/Docs.php' ) && ! is_file( $root . '/src/Integration/Helpdesk.php' ) && ! is_file( $root . '/src/Integration/Commerce.php' ),
	'Project REST remains capability gated' => str_contains( $projectRest, 'current_user_can( Capabilities::MANAGE )' ),
	'Work Item REST remains capability gated' => str_contains( $workItemRest, 'current_user_can( Capabilities::MANAGE )' ),
	'pre-v1 relational Project Work Item schema is absent' => ! str_contains( $schema, 'cb_work_projects' ) && ! str_contains( $schema, 'cb_work_items' ) && ! str_contains( $schema, 'projects_table' ) && ! str_contains( $schema, 'work_items_table' ),
	'pre-v1 schema migration code is absent' => ! str_contains( $schema, 'previous_version' ) && ! str_contains( $schema, 'DROP TABLE IF EXISTS' ) && ! str_contains( $schema, 'DELETE FROM' ),
	'cross-plugin SQL foreign keys remain forbidden' => ! str_contains( strtoupper( $schema ), 'FOREIGN KEY' ),
	'pricing provider seam no longer labels active CRM input as legacy' => ! str_contains( $pricingProviders, 'legacy numeric compatibility field' ),
	'failed activation uses canonical requirements title and Plugins return' => str_contains( $lifecycle, 'Core Blueprint requirements not met' ) && str_contains( $lifecycle, "admin_url( 'plugins.php' )" ) && str_contains( $lifecycle, 'deactivate_plugins( CB_WORK_BASENAME )' ),
	'release builder is deterministic-authority aware' => str_contains( $buildRelease, 'core-blueprint-work' ),
	'release builder defaults to canonical dist output' => str_contains( $buildRelease, 'DIST="${CB_RELEASE_DIST:-$ROOT/dist}"' ),
	'check runner retains workspace and Data Exchange regressions' => str_contains( $checkRunner, 'workspace-foundation-smoke.php' ) && str_contains( $checkRunner, 'project-workspace-smoke.php' ) && str_contains( $checkRunner, 'data-exchange-tax-rates-smoke.php' ),
];

foreach ( $checks as $label => $passed ) {
	if ( ! $passed ) {
		fwrite( STDERR, "Conformance failed: {$label}\n" );
		$failed = true;
	}
}

exit( $failed ? 1 : 0 );

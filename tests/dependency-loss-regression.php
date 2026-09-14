<?php
declare(strict_types=1);

namespace {
	define( 'ABSPATH', '/tmp/wp/' );

	final class WP_Error {
		public function __construct( public string $code = '', public string $message = '' ) {}
	}

	function cb_work_runtime_ready(): bool { return false; }
	function assert_true( bool $condition, string $message ): void {
		if ( ! $condition ) {
			fwrite( STDERR, "Dependency-loss regression failed: {$message}\n" );
			exit( 1 );
		}
	}

	$root = dirname( __DIR__ );
	require $root . '/src/Plugin.php';
	require $root . '/src/Integration/Suite.php';
	require $root . '/src/Integration/DataExchange.php';
	require $root . '/src/Integration/CRMCustomers.php';
	require $root . '/src/PublicApi/Projects.php';
	require $root . '/src/PublicApi/Services.php';
	require $root . '/src/PublicApi/WorkItems.php';
	require $root . '/src/PublicApi/WorkTypes.php';
	require $root . '/src/PublicApi/TaxRates.php';
	require $root . '/src/PublicApi/Pricing.php';
	require $root . '/src/PublicApi/PricingProviders.php';
	require $root . '/src/Frontend/Access.php';

	\CB\Work\Plugin::boot();
	assert_true( ! \CB\Work\Plugin::is_booted(), 'Direct Plugin::boot() must stay inert after readiness is lost.' );

	\CB\Work\Integration\Suite::init();
	\CB\Work\Integration\DataExchange::register();
	assert_true( ! \CB\Work\Integration\CRMCustomers::available(), 'CRM adapter must become unavailable after readiness is lost.' );

	assert_true( null === \CB\Work\PublicApi\Projects::get( 1 ), 'Project public read must fail closed.' );
	assert_true( [] === \CB\Work\PublicApi\Projects::all(), 'Project list must fail closed.' );
	assert_true( null === \CB\Work\PublicApi\Services::get( 1 ), 'Service public read must fail closed.' );
	assert_true( [] === \CB\Work\PublicApi\Services::all(), 'Service list must fail closed.' );
	assert_true( null === \CB\Work\PublicApi\WorkItems::get( 1 ), 'Work Item public read must fail closed.' );
	assert_true( [] === \CB\Work\PublicApi\WorkItems::all(), 'Work Item list must fail closed.' );
	assert_true( null === \CB\Work\PublicApi\WorkItems::source( 1 ), 'Work Item source read must fail closed.' );
	assert_true( [] === \CB\Work\PublicApi\WorkItems::by_relation( 'crm', 'contact', '1' ), 'Relation read must fail closed.' );
	assert_true( null === \CB\Work\PublicApi\WorkTypes::get( 1 ), 'Work Type public read must fail closed.' );
	assert_true( [] === \CB\Work\PublicApi\WorkTypes::all(), 'Work Type list must fail closed.' );
	assert_true( null === \CB\Work\PublicApi\TaxRates::get( 1 ), 'VAT read must fail closed.' );
	assert_true( [] === \CB\Work\PublicApi\TaxRates::all(), 'VAT list must fail closed.' );
	assert_true( false === \CB\Work\PublicApi\TaxRates::is_available( 1 ), 'VAT availability must fail closed.' );
	assert_true( null === \CB\Work\PublicApi\Pricing::resolve( 1 ), 'Pricing resolver must fail closed.' );
	assert_true( false === \CB\Work\PublicApi\PricingProviders::register( 'test', static fn(): array => [] ), 'Pricing provider registration must fail closed.' );
	assert_true( null === \CB\Work\PublicApi\PricingProviders::resolve( [] ), 'Pricing provider resolution must fail closed.' );
	assert_true( false === \CB\Work\Frontend\Access::can_read_service( 1 ), 'Frontend Service access must fail closed.' );
	assert_true( false === \CB\Work\Frontend\Access::can_read_project( 1 ), 'Frontend Project access must fail closed.' );
	assert_true( false === \CB\Work\Frontend\Access::can_read_work_item( 1 ), 'Frontend Work Item access must fail closed.' );
	assert_true( false === \CB\Work\Frontend\Access::can_transition_work_item( 1, 'done' ), 'Frontend mutation authorization must fail closed.' );

	$bootstrap = file_get_contents( $root . '/core-blueprint-work.php' );
	assert_true( is_string( $bootstrap ) && str_contains( $bootstrap, "'map_meta_cap'" ) && str_contains( $bootstrap, 'cb_work_runtime_ready()' ) && str_contains( $bootstrap, "return [ 'do_not_allow' ];" ), 'Canonical Work capabilities must be denied when current-time readiness is lost.' );

	$public_mutations = [
		'src/PublicApi/WorkItemActions.php',
		'src/PublicApi/Billing.php',
		'src/PublicApi/BillingActions.php',
		'src/PublicApi/TaxRateActions.php',
	];
	foreach ( $public_mutations as $relative ) {
		$source = file_get_contents( $root . '/' . $relative );
		assert_true( is_string( $source ) && str_contains( $source, 'current_user_can( Capabilities::MANAGE )' ), "{$relative} must authorize through the readiness-gated Work capability." );
	}

	$admin_mutations = [
		'src/Admin/OperationalActions.php',
		'src/Admin/RecurrenceActions.php',
		'src/Admin/TaxRateActions.php',
	];
	foreach ( $admin_mutations as $relative ) {
		$source = file_get_contents( $root . '/' . $relative );
		assert_true( is_string( $source ) && str_contains( $source, 'current_user_can( Capabilities::MANAGE )' ) && str_contains( $source, 'check_admin_referer(' ), "{$relative} must keep capability and nonce boundaries." );
	}

	$time_actions = file_get_contents( $root . '/src/Admin/TimeActions.php' );
	$time_access  = file_get_contents( $root . '/src/Time/Access.php' );
	assert_true( is_string( $time_actions ) && str_contains( $time_actions, 'Access::can_track()' ) && str_contains( $time_actions, 'check_admin_referer(' ), 'Time mutations must keep access and nonce boundaries.' );
	assert_true( is_string( $time_access ) && str_contains( $time_access, 'current_user_can(' ), 'Time access must delegate to WordPress capabilities.' );

	foreach ( [ 'src/Content/ProjectRestController.php', 'src/Content/WorkItemRestController.php' ] as $relative ) {
		$source = file_get_contents( $root . '/' . $relative );
		assert_true( is_string( $source ) && str_contains( $source, 'current_user_can( Capabilities::MANAGE )' ), "{$relative} must remain capability-gated." );
	}

	echo "Dependency-loss regression passed.\n";
}

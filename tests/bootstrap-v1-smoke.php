<?php
declare(strict_types=1);

$root         = dirname( __DIR__ );
$bootstrap    = file_get_contents( $root . '/core-blueprint-work.php' );
$requirements = file_get_contents( $root . '/src/Support/Requirements.php' );
$lifecycle    = file_get_contents( $root . '/src/Lifecycle.php' );

function assert_bootstrap( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "Bootstrap v1 smoke failed: {$message}\n" );
		exit( 1 );
	}
}

assert_bootstrap( is_string( $bootstrap ) && is_string( $requirements ) && is_string( $lifecycle ), 'Bootstrap sources are readable.' );

$php_guard = strpos( $bootstrap, "version_compare( PHP_VERSION, '8.4', '<' )" );
$autoload  = strpos( $bootstrap, 'spl_autoload_register' );
$gate      = strpos( $bootstrap, 'Requirements::runtime_ready()' );
$schema    = strpos( $bootstrap, 'Database\\Schema::register();' );
$suite     = strpos( $bootstrap, 'Integration\\Suite::init();' );
$boot      = strpos( $bootstrap, "add_action( 'cb_core_booted'" );

assert_bootstrap( false !== $php_guard && false !== $autoload && $php_guard < $autoload, 'PHP guard runs before the Work autoloader.' );
assert_bootstrap( false !== $gate && false !== $schema && false !== $suite && false !== $boot, 'Dependency gate, schema, Suite and product boot are present.' );
assert_bootstrap( $gate < $schema && $gate < $suite && $gate < $boot, 'Base-dependent runtime is attached only after the Bootstrap dependency gate.' );
assert_bootstrap( str_contains( $bootstrap, "}, 4 );" ), 'Work dependency/schema gate remains priority 4 before Base migration priority 5.' );
assert_bootstrap( str_contains( $bootstrap, 'class_exists' ) && str_contains( $bootstrap, 'SchemaRegistry' ), 'Work keeps a defensive schema-service runtime gate outside Requirements.' );
assert_bootstrap( ! str_contains( $bootstrap, 'Requires Plugins:' ), 'Work does not use the WordPress Requires Plugins header.' );
assert_bootstrap( ! str_contains( $requirements, 'class_exists(' ), 'Requirements stays implementation-agnostic.' );
assert_bootstrap( ! str_contains( $requirements, 'base-contract-unavailable' ), 'Bootstrap issue model contains no product-specific Base class checks.' );
assert_bootstrap( str_contains( $lifecycle, "admin_url( 'plugins.php' )" ), 'Failed activation returns to the canonical Plugins screen.' );
assert_bootstrap( str_contains( $lifecycle, 'deactivate_plugins( CB_WORK_BASENAME )' ), 'Failed activation explicitly deactivates Work.' );

echo "Bootstrap v1 smoke passed.\n";

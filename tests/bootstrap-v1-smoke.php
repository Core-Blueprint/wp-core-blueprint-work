<?php
declare(strict_types=1);

$root         = dirname( __DIR__ );
$bootstrap    = file_get_contents( $root . '/core-blueprint-work.php' );
$requirements = file_get_contents( $root . '/src/Support/Requirements.php' );
$lifecycle    = file_get_contents( $root . '/src/Lifecycle.php' );
$plugin       = file_get_contents( $root . '/src/Plugin.php' );
$suite        = file_get_contents( $root . '/src/Integration/Suite.php' );
$frontend     = file_get_contents( $root . '/src/Frontend/Access.php' );

function assert_bootstrap( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "Bootstrap v1 smoke failed: {$message}\n" );
		exit( 1 );
	}
}

assert_bootstrap( is_string( $bootstrap ) && is_string( $requirements ) && is_string( $lifecycle ) && is_string( $plugin ) && is_string( $suite ) && is_string( $frontend ), 'Bootstrap sources are readable.' );

$php_guard = strpos( $bootstrap, 'version_compare( PHP_VERSION, CB_WORK_MIN_PHP' );
$autoload  = strpos( $bootstrap, 'spl_autoload_register' );
$generic_gate = strpos( $bootstrap, 'Requirements::runtime_ready()', strpos( $bootstrap, "add_action( 'plugins_loaded'" ) );
$product_gate = strpos( $bootstrap, 'if ( ! cb_work_product_contracts_ready() )', false !== $generic_gate ? $generic_gate : 0 );
$schema    = strpos( $bootstrap, 'Database\\Schema::register();', false !== $product_gate ? $product_gate : 0 );
$suite_init = strpos( $bootstrap, 'Integration\\Suite::init();', false !== $schema ? $schema : 0 );
$boot      = strpos( $bootstrap, "add_action( 'core_blueprint_booted'", false !== $suite_init ? $suite_init : 0 );

assert_bootstrap( false !== $php_guard && false !== $autoload && $php_guard < $autoload, 'PHP guard runs before the Work autoloader.' );
assert_bootstrap( false !== $generic_gate && false !== $product_gate && false !== $schema && false !== $suite_init && false !== $boot, 'Generic gate, product gate, schema, Suite and product boot are present.' );
assert_bootstrap( $generic_gate < $product_gate && $product_gate < $schema && $schema < $suite_init && $suite_init < $boot, 'Bootstrap ordering is PHP -> Base/Core -> product contracts -> integration -> feature runtime.' );
assert_bootstrap( str_contains( $bootstrap, '}, 4 );' ), 'Work dependency/schema gate remains priority 4 before Base migration priority 5.' );
assert_bootstrap( 1 === preg_match( '/^[ \t]*\*[ \t]*Requires Plugins:[ \t]*core-blueprint[ \t]*$/m', $bootstrap ), 'Work declares exact canonical native Base dependency metadata.' );
assert_bootstrap( str_contains( $bootstrap, 'function cb_work_runtime_ready(): bool' ) && str_contains( $bootstrap, 'cb_work_product_contracts_ready' ), 'Work owns one full runtime readiness gate.' );
assert_bootstrap( str_contains( $bootstrap, "'map_meta_cap'" ) && str_contains( $bootstrap, "return [ 'do_not_allow' ];" ), 'Work capabilities fail closed outside readiness.' );
assert_bootstrap( ! str_contains( $requirements, 'class_exists(' ), 'Requirements stays implementation-agnostic.' );
assert_bootstrap( str_contains( $requirements, 'Core Blueprint must be installed and active.' ), 'Requirements uses canonical Base-missing copy.' );
assert_bootstrap( str_contains( $lifecycle, 'Requirements::activation_message()' ) && str_contains( $lifecycle, 'Required Core Blueprint Base contracts are unavailable.' ), 'Activation separates generic dependency issues from Work product contracts.' );
assert_bootstrap( str_contains( $lifecycle, 'Core Blueprint requirements not met' ) && str_contains( $lifecycle, "admin_url( 'plugins.php' )" ), 'Failed activation uses canonical title and Plugins return.' );
assert_bootstrap( str_contains( $plugin, "function_exists( 'cb_work_runtime_ready' )" ) && str_contains( $plugin, '! \\cb_work_runtime_ready()' ), 'Direct Plugin boot rechecks full readiness.' );
assert_bootstrap( str_contains( $suite, 'private static bool $initialized = false;' ) && str_contains( $suite, '! self::runtime_ready()' ), 'Suite is idempotent and self-gated.' );
assert_bootstrap( str_contains( $frontend, 'cb_work_runtime_ready' ), 'Frontend resource authorization rechecks current readiness.' );

echo "Bootstrap v1 smoke passed.\n";

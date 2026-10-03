<?php
declare(strict_types=1);

namespace {
	define( 'ABSPATH', '/tmp/wp/' );
	$GLOBALS['hooks'] = [];
	$GLOBALS['deactivated_plugins'] = [];
	$GLOBALS['wp_die_args'] = null;

	function add_action( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		$GLOBALS['hooks'][ $hook ][ $priority ][] = [ $callback, $accepted_args ];
		return true;
	}
	function add_filter( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool { return add_action( $hook, $callback, $priority, $accepted_args ); }
	function do_action( string $hook, mixed ...$args ): void {
		$priorities = $GLOBALS['hooks'][ $hook ] ?? [];
		ksort( $priorities );
		foreach ( $priorities as $callbacks ) {
			foreach ( $callbacks as [ $callback, $accepted_args ] ) {
				$callback( ...array_slice( $args, 0, $accepted_args ) );
			}
		}
	}
	function plugin_dir_path( string $file ): string { return dirname( $file ) . '/'; }
	function plugin_dir_url( string $file ): string { return 'https://example.test/wp-content/plugins/core-blueprint-work/'; }
	function plugin_basename( string $file ): string { return 'core-blueprint-work/' . basename( $file ); }
	function register_activation_hook( string $file, callable $callback ): void {}
	function register_deactivation_hook( string $file, callable $callback ): void {}
	function load_plugin_textdomain( string $domain, bool $deprecated = false, string $path = '' ): bool { return true; }
	function is_admin(): bool { return true; }
	function current_user_can( string $capability ): bool { return true; }
	function esc_html__( string $text, string $domain = 'default' ): string { return $text; }
	function esc_html( string $text ): string { return $text; }
	function __( string $text, string $domain = 'default' ): string { return $text; }
	function did_action( string $hook ): int { return 0; }
	function doing_action( ?string $hook = null ): bool { return false; }
	function admin_url( string $path = '' ): string { return 'https://example.test/wp-admin/' . ltrim( $path, '/' ); }
	function deactivate_plugins( $plugins ): void { $GLOBALS['deactivated_plugins'][] = $plugins; }
	function wp_die( $message = '', $title = '', $args = [] ): void {
		$GLOBALS['wp_die_args'] = is_array( $args ) ? $args : [];
		throw new \RuntimeException( 'wp_die' );
	}

	function assert_true( bool $condition, string $message ): void {
		if ( ! $condition ) {
			fwrite( STDERR, "FAIL: {$message}\n" );
			exit( 1 );
		}
	}

	require dirname( __DIR__ ) . '/core-blueprint-work.php';
	do_action( 'plugins_loaded' );

	assert_true( ! isset( $GLOBALS['hooks']['core_blueprint_register_extensions'] ), 'Suite integration stays inert without Base.' );
	assert_true( ! isset( $GLOBALS['hooks']['core_blueprint_booted'] ), 'Product boot is not attached without Base.' );
	assert_true( isset( $GLOBALS['hooks']['admin_notices'] ), 'Missing Base schedules the canonical operator notice.' );
	assert_true( \CB\Work\Support\Requirements::issues() === [ 'base-missing' ], 'Missing Base is classified deterministically.' );
	assert_true( ! isset( $GLOBALS['hooks']['init'][6] ), 'Work product domain remains inert without Base.' );

	try {
		\CB\Work\Lifecycle::activate();
		assert_true( false, 'Activation must terminate when Base is missing.' );
	} catch ( \RuntimeException $error ) {
		assert_true( 'wp_die' === $error->getMessage(), 'Activation must terminate through wp_die().' );
	}

	assert_true( [ CB_WORK_BASENAME ] === $GLOBALS['deactivated_plugins'], 'Failed activation must explicitly deactivate Work before terminating.' );
	assert_true( is_array( $GLOBALS['wp_die_args'] ), 'Failed activation must pass explicit wp_die arguments.' );
	assert_true( 'https://example.test/wp-admin/plugins.php' === ( $GLOBALS['wp_die_args']['link_url'] ?? '' ), 'Failed activation must return to the canonical Plugins screen.' );
	assert_true( 'Plugins' === ( $GLOBALS['wp_die_args']['link_text'] ?? '' ), 'Failed activation must label the canonical Plugins link.' );
	assert_true( ! isset( $GLOBALS['wp_die_args']['back_link'] ), 'Failed activation must not use browser-history back navigation.' );

	echo "Missing-Base smoke passed.\n";
}

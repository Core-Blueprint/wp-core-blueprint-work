<?php
declare(strict_types=1);

namespace {
	define( 'ABSPATH', '/tmp/wp/' );
	$GLOBALS['hooks'] = [];

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

	function assert_true( bool $condition, string $message ): void {
		if ( ! $condition ) {
			fwrite( STDERR, "FAIL: {$message}\n" );
			exit( 1 );
		}
	}

	require dirname( __DIR__ ) . '/core-blueprint-work.php';
	do_action( 'plugins_loaded' );

	assert_true( isset( $GLOBALS['hooks']['cb_core_register_extensions'] ), 'Lightweight suite integration remains attached without Base.' );
	assert_true( isset( $GLOBALS['hooks']['admin_notices'] ), 'Missing Base schedules an operator notice.' );
	assert_true( \CB\Work\Support\Requirements::issues() === [ 'base-missing' ], 'Missing Base is classified deterministically.' );
	assert_true( ! isset( $GLOBALS['hooks']['init'][6] ), 'Work product domain remains inert without Base.' );

	echo "Missing-Base smoke passed.\n";
}

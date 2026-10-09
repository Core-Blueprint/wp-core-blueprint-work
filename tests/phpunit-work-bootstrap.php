<?php
declare(strict_types=1);

/**
 * Canonical Level 2 integration bootstrap. Product-private WordPress runtime,
 * disposable database and Base source authority are prepared by check-integration.
 */
$tests_dir = rtrim( (string) getenv( 'WP_TESTS_DIR' ), '/\\' );
$base_source = rtrim( (string) getenv( 'CB_TEST_BASE_SOURCE' ), '/\\' );
$base_plugin = (string) getenv( 'CB_TEST_BASE_PLUGIN_FILE' );
$work_plugin = (string) getenv( 'CB_TEST_WORK_PLUGIN_FILE' );
if ( ! is_file( $tests_dir . '/includes/functions.php' ) ) {
	throw new RuntimeException( 'Work wp-phpunit library was not provisioned by tools/check-integration.' );
}
if ( ! is_file( $base_source . '/vendor/autoload.php' )
	|| ! is_dir( $base_source . '/vendor/yoast/phpunit-polyfills' )
	|| ! is_file( $base_plugin ) || ! is_file( $work_plugin )
) {
	throw new RuntimeException( 'Work integration requires the pinned Base vendor dependencies and staged plugins.' );
}
if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $base_source . '/vendor/yoast/phpunit-polyfills' );
}
require_once $base_source . '/vendor/autoload.php';

putenv( 'WP_PHPUNIT__TESTS_CONFIG=' . __DIR__ . '/wp-tests-config.php' );
putenv( 'WP_PHPUNIT__TABLE_PREFIX=cbworktests_' );
require_once $tests_dir . '/includes/functions.php';

tests_add_filter( 'pre_wp_mail', static fn(): bool => true );
tests_add_filter( 'muplugins_loaded', static function () use ( $base_plugin, $work_plugin ): void {
	require_once $base_plugin;
	require_once $work_plugin;

	// Match the canonical Base PHPUnit activation lifecycle exactly.
	// Fresh wp-phpunit databases lack Base's first-install schema; loading
	// the plugin entrypoint alone does NOT install its audit log table.
	// Activation must precede plugins_loaded permission/schema observers.
	add_action( 'plugins_loaded', static function (): void {
		\CoreBlueprint\Core\Core::activate();

		global $wpdb;
		$audit_table = \CoreBlueprint\Core\DB::audit_log_table();
		$installed = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $audit_table ) )
		);
		if ( $installed !== $audit_table || '' !== (string) $wpdb->last_error ) {
			throw new RuntimeException(
				'Base activation failed to provision its audit log in the isolated Work test database.'
			);
		}
	}, 2 );
} );
require_once $tests_dir . '/includes/bootstrap.php';

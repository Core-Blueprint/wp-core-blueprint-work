<?php
declare(strict_types=1);

/**
 * Reuse the existing Core Blueprint Base WordPress/PHPUnit bootstrap.
 *
 * Unlike wp eval-file, this requires no standalone wp-config.php or
 * independently installed WordPress site.
 *
 * CB_BASE_SOURCE_DIR, WP_CORE_DIR, WP_TESTS_DIR and CB_PLUGIN_FILE
 * must be set by the operator's established local Base test environment.
 */
$base_source = rtrim( (string) getenv( 'CB_BASE_SOURCE_DIR' ), '/\\' );
if ( '' === $base_source || ! is_file( $base_source . '/tests/bootstrap.php' ) ) {
    throw new RuntimeException( 'Set CB_BASE_SOURCE_DIR to the existing Core Blueprint Base source checkout.' );
}
require_once $base_source . '/tests/bootstrap.php';

$work_plugin = dirname( __DIR__ ) . '/core-blueprint-work.php';
if ( ! is_file( $work_plugin ) ) {
    throw new RuntimeException( 'Missing Work plugin entrypoint for CAS tests.' );
}

// Base's WP test harness has already loaded. Register Work's own
// autoloader/contracts without creating a second bootstrap or plugin install.
require_once $work_plugin;

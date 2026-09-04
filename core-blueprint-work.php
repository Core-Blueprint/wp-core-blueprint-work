<?php
/**
 * Plugin Name:       Core Blueprint Work
 * Plugin URI:        https://coreblueprint.io
 * Description:       First-party work management for services, projects, work items, time tracking and billing-ready reporting.
 * Version:           1.0.0-rc2.1
 * Author:            Core Blueprint
 * Author URI:        https://coreblueprint.io
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       core-blueprint-work
 * Domain Path:       /languages
 * Requires at least: 7.0
 * Requires PHP:      8.4
 *
 * @package CB_Work
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

if ( defined( 'CB_WORK_FILE' ) ) {
	return;
}

define( 'CB_WORK_VERSION', '1.0.0-rc2.1' );
define( 'CB_WORK_SCHEMA_VERSION', '1.0' );
define( 'CB_WORK_REQUIRED_API', '1.0' );
define( 'CB_WORK_FILE', __FILE__ );
define( 'CB_WORK_DIR', plugin_dir_path( __FILE__ ) );
define( 'CB_WORK_URL', plugin_dir_url( __FILE__ ) );
define( 'CB_WORK_BASENAME', plugin_basename( __FILE__ ) );

spl_autoload_register( static function ( string $class ): void {
	$prefix = 'CB\\Work\\';
	if ( ! str_starts_with( $class, $prefix ) ) {
		return;
	}

	$relative = substr( $class, strlen( $prefix ) );
	$file     = CB_WORK_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';
	if ( is_file( $file ) ) {
		require_once $file;
	}
} );

/* Keep first-party inventory and health registration independent of runtime gates. */
\CB\Work\Integration\Suite::init();

register_activation_hook( __FILE__, [ \CB\Work\Lifecycle::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ \CB\Work\Lifecycle::class, 'deactivate' ] );

/* Register Work-owned schemas before Base's central migration sweep. */
add_action( 'plugins_loaded', static function (): void {
	if (
		defined( 'CB_CORE_API_VERSION' )
		&& \CB\Work\Support\Requirements::api_compatible( (string) CB_CORE_API_VERSION, CB_WORK_REQUIRED_API )
		&& class_exists( '\\CB\\Core\\Database\\SchemaRegistry' )
	) {
		\CB\Work\Database\Schema::register();
	}
}, 4 );

add_action( 'init', static function (): void {
	load_plugin_textdomain(
		'core-blueprint-work',
		false,
		dirname( CB_WORK_BASENAME ) . '/languages'
	);
}, 0 );

add_action( 'cb_core_booted', [ \CB\Work\Plugin::class, 'boot' ] );

add_action( 'plugins_loaded', static function (): void {
	if ( \CB\Work\Support\Requirements::runtime_ready() || ! is_admin() ) {
		return;
	}

	add_action( 'admin_notices', static function (): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p><strong>%s</strong> %s</p></div>',
			esc_html__( 'Core Blueprint Work:', 'core-blueprint-work' ),
			esc_html( \CB\Work\Support\Requirements::operator_message() )
		);
	} );
}, 30 );

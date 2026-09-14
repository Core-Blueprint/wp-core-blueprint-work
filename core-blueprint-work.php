<?php
/**
 * Plugin Name:       Core Blueprint Work
 * Plugin URI:        https://coreblueprint.io
 * Description:       First-party work management for services, projects, work items, time tracking and billing-ready reporting.
 * Version:           1.0.0-rc1
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

define( 'CB_WORK_VERSION', '1.0.0-rc1' );
define( 'CB_WORK_SCHEMA_VERSION', '1.8' );
define( 'CB_WORK_REQUIRED_API', '1.0' );
define( 'CB_WORK_FILE', __FILE__ );
define( 'CB_WORK_DIR', plugin_dir_path( __FILE__ ) );
define( 'CB_WORK_URL', plugin_dir_url( __FILE__ ) );
define( 'CB_WORK_BASENAME', plugin_basename( __FILE__ ) );

/* Bootstrap v1: fail before loading Work classes on an unsupported PHP runtime. */
if ( version_compare( PHP_VERSION, '8.4', '<' ) ) {
	register_activation_hook( __FILE__, static function (): void {
		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		deactivate_plugins( CB_WORK_BASENAME );
		wp_die(
			esc_html( sprintf( 'Core Blueprint Work requires PHP 8.4 or newer. This server runs PHP %s.', PHP_VERSION ) ),
			esc_html( 'Core Blueprint dependency required' ),
			[
				'link_url'  => admin_url( 'plugins.php' ),
				'link_text' => __( 'Plugins' ),
			]
		);
	} );

	add_action( 'admin_notices', static function (): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		printf(
			'<div class="notice notice-error"><p><strong>%s</strong> %s</p></div>',
			esc_html__( 'Core Blueprint Work:', 'core-blueprint-work' ),
			esc_html( sprintf( __( 'PHP 8.4 or newer is required. This server runs PHP %s.', 'core-blueprint-work' ), PHP_VERSION ) )
		);
	} );
	return;
}

spl_autoload_register( static function ( string $class ): void {
	$prefix = 'CB\\Work\\';
	if ( 0 !== strncmp( $class, $prefix, strlen( $prefix ) ) ) {
		return;
	}

	$relative = substr( $class, strlen( $prefix ) );
	$file     = CB_WORK_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';
	if ( is_file( $file ) ) {
		require_once $file;
	}
} );

register_activation_hook( __FILE__, [ \CB\Work\Lifecycle::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ \CB\Work\Lifecycle::class, 'deactivate' ] );

add_action( 'init', static function (): void {
	load_plugin_textdomain(
		'core-blueprint-work',
		false,
		dirname( CB_WORK_BASENAME ) . '/languages'
	);
}, 0 );

/*
 * Bootstrap v1 dependency boundary.
 *
 * Work remains inert until Base exposes a compatible public Core API. The
 * Work-owned schema registration stays at priority 4 so Base can include it in
 * its central migration sweep at priority 5. Product runtime attaches only
 * after the same gate has passed.
 */
add_action( 'plugins_loaded', static function (): void {
	if ( ! \CB\Work\Support\Requirements::runtime_ready() ) {
		if ( is_admin() ) {
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
		}
		return;
	}

	/* Base owns this service; if its own bootstrap did not complete, stay inert. */
	if ( ! class_exists( '\\CB\\Core\\Database\\SchemaRegistry' ) ) {
		return;
	}

	\CB\Work\Database\Schema::register();
	\CB\Work\Integration\Suite::init();
	add_action( 'cb_core_booted', [ \CB\Work\Plugin::class, 'boot' ] );
}, 4 );

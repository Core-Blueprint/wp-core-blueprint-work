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
 * Requires Plugins: core-blueprint
 *
 * @package CB_Work
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

if ( defined( 'CB_WORK_FILE' ) ) {
	return;
}

define( 'CB_WORK_VERSION', '1.0.0-rc1' );
define( 'CB_WORK_SCHEMA_VERSION', '1.9' );
define( 'CB_WORK_MIN_PHP', '8.4' );
define( 'CB_WORK_REQUIRED_API', '1.0' );
define( 'CB_WORK_FILE', __FILE__ );
define( 'CB_WORK_DIR', plugin_dir_path( __FILE__ ) );
define( 'CB_WORK_URL', plugin_dir_url( __FILE__ ) );
define( 'CB_WORK_BASENAME', plugin_basename( __FILE__ ) );

/* Bootstrap v1 earliest-safe PHP boundary. */
if ( version_compare( PHP_VERSION, CB_WORK_MIN_PHP, '<' ) ) {
	register_activation_hook( __FILE__, static function (): void {
		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		deactivate_plugins( CB_WORK_BASENAME );
		wp_die(
			esc_html( sprintf( 'PHP %1$s or newer is required. This server runs PHP %2$s.', CB_WORK_MIN_PHP, PHP_VERSION ) ),
			esc_html( 'Core Blueprint requirements not met' ),
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
			esc_html( sprintf( 'PHP %1$s or newer is required. This server runs PHP %2$s.', CB_WORK_MIN_PHP, PHP_VERSION ) )
		);
	} );
	return;
}

spl_autoload_register( static function ( string $class ): void {
	$prefix = 'CB\\Work\\';
	$length = strlen( $prefix );
	if ( 0 !== strncmp( $class, $prefix, $length ) ) {
		return;
	}
	$relative = substr( $class, $length );
	$file     = CB_WORK_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';
	if ( is_file( $file ) ) {
		require_once $file;
	}
} );

/** Base services that are required by the canonical Work product runtime. */
function cb_work_product_contracts_ready(): bool {
	return class_exists( '\\CoreBlueprint\\Core\\Database\\SchemaRegistry' )
		&& class_exists( '\\CoreBlueprint\\Core\\ExtensionRegistry' )
		&& class_exists( '\\CoreBlueprint\\Core\\Governance\\Audit' )
		&& class_exists( '\\CoreBlueprint\\Core\\Governance\\EventRegistry' )
		&& class_exists( '\\CoreBlueprint\\Core\\Admin\\SettingsRegistry' )
		&& class_exists( '\\CoreBlueprint\\Core\\Dashboard\\CardRegistry' )
		&& class_exists( '\\CoreBlueprint\\Core\\UI\\Assets' )
		&& class_exists( '\\CoreBlueprint\\Core\\UI\\ObjectPicker' )
		&& class_exists( '\\CoreBlueprint\\Core\\UI\\Notice' );
}

/** Canonical current-request readiness for Work feature/public runtime. */
function cb_work_runtime_ready(): bool {
	return \CB\Work\Support\Requirements::runtime_ready() && cb_work_product_contracts_ready();
}

/** Translation-safe operator message for the current dependency state. */
function cb_work_dependency_message(): string {
	if ( ! \CB\Work\Support\Requirements::runtime_ready() ) {
		return \CB\Work\Support\Requirements::operator_message();
	}
	return __( 'Required Core Blueprint Base contracts are unavailable.', 'core-blueprint-work' );
}

/* Every Work capability path fails closed while product runtime is unavailable. */
add_filter( 'map_meta_cap', static function ( array $caps, string $cap ): array {
	if ( in_array( $cap, [ 'cb_manage_work', 'cb_track_work_time' ], true ) && ! cb_work_runtime_ready() ) {
		return [ 'do_not_allow' ];
	}
	return $caps;
}, 10, 2 );

register_activation_hook( __FILE__, [ \CB\Work\Lifecycle::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ \CB\Work\Lifecycle::class, 'deactivate' ] );

add_action( 'init', static function (): void {
	load_plugin_textdomain( 'core-blueprint-work', false, dirname( CB_WORK_BASENAME ) . '/languages' );
}, 0 );

/*
 * Canonical ordering:
 * PHP -> Base/Core API -> Work product contracts -> integrations -> feature runtime.
 * Schema remains priority 4 so Base can collect it during its priority-5 sweep.
 */
add_action( 'plugins_loaded', static function (): void {
	if ( ! \CB\Work\Support\Requirements::runtime_ready() ) {
		if ( is_admin() ) {
			add_action( 'admin_notices', static function (): void {
				if ( current_user_can( 'activate_plugins' ) ) {
					printf(
						'<div class="notice notice-error"><p><strong>%s</strong> %s</p></div>',
						esc_html__( 'Core Blueprint Work:', 'core-blueprint-work' ),
						esc_html( \CB\Work\Support\Requirements::operator_message() )
					);
				}
			} );
		}
		return;
	}

	if ( ! cb_work_product_contracts_ready() ) {
		if ( is_admin() ) {
			add_action( 'admin_notices', static function (): void {
				if ( current_user_can( 'activate_plugins' ) ) {
					printf(
						'<div class="notice notice-error"><p><strong>%s</strong> %s</p></div>',
						esc_html__( 'Core Blueprint Work:', 'core-blueprint-work' ),
						esc_html__( 'Required Core Blueprint Base contracts are unavailable.', 'core-blueprint-work' )
					);
				}
			} );
		}
		return;
	}

	\CB\Work\Database\Schema::register();
	\CB\Work\Integration\Suite::init();
	add_action( 'core_blueprint_booted', [ \CB\Work\Plugin::class, 'boot' ] );
}, 4 );

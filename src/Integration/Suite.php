<?php
declare(strict_types=1);

namespace CB\Work\Integration;

use CoreBlueprint\Core\Dashboard\CardRegistry;
use CoreBlueprint\Core\ExtensionRegistry;
use CB\Work\Admin\Menu;
use CB\Work\Content\PostTypes;
use CB\Work\Database\Schema;
use CB\Work\Plugin;
use CB\Work\Repository\Projects;
use CB\Work\Repository\TaxRates;
use CB\Work\Repository\WorkItems;
use CB\Work\Support\Requirements;

defined( 'ABSPATH' ) || exit;

final class Suite {
	public const EXTENSION_ID = 'core-blueprint-work';
	public const STATUS_ID    = 'work';

	private static bool $initialized = false;

	public static function init(): void {
		if ( self::$initialized || ! self::runtime_ready() ) {
			return;
		}
		self::$initialized = true;
		add_action( 'core_blueprint_register_extensions', [ self::class, 'register_extension' ] );
		add_action( 'core_blueprint_register_interoperability_implementations', [ DataExchange::class, 'register' ] );
		add_filter( 'core_blueprint_module_status_definitions', [ self::class, 'register_status_definition' ] );
		add_action( 'core_blueprint_dashboard_register_cards', [ self::class, 'register_dashboard_shortcuts' ] );
	}

	public static function register_extension(): void {
		if ( ! self::runtime_ready() ) {
			return;
		}
		ExtensionRegistry::register( [
			'id'           => self::EXTENSION_ID,
			'plugin_file'  => CB_WORK_BASENAME,
			'requires_api' => CB_WORK_REQUIRED_API,
			'menu_url'     => admin_url( 'admin.php?page=' . Menu::TOP_LEVEL_SLUG ),
			'status_id'    => self::STATUS_ID,
		] );
	}

	/** @param array<string,array<string,mixed>> $definitions @return array<string,array<string,mixed>> */
	public static function register_status_definition( array $definitions ): array {
		if ( ! self::runtime_ready() ) {
			return $definitions;
		}
		$definitions[ self::STATUS_ID ] = [
			'provider' => [ self::class, 'status' ],
			'label'    => self::i18n_ready() ? __( 'Work', 'core-blueprint-work' ) : 'Work',
			'url'      => admin_url( 'admin.php?page=' . Menu::TOP_LEVEL_SLUG ),
		];
		return $definitions;
	}

	/** @return array{state:string,detail:string,url:string} */
	public static function status(): array {
		$url = admin_url( 'admin.php?page=' . Menu::TOP_LEVEL_SLUG );
		if ( ! Requirements::runtime_ready() ) {
			return [ 'state' => 'err', 'detail' => self::i18n_ready() ? Requirements::operator_message() : Requirements::activation_message(), 'url' => $url ];
		}
		if ( ! function_exists( 'cb_work_product_contracts_ready' ) || ! \cb_work_product_contracts_ready() ) {
			return [ 'state' => 'err', 'detail' => self::i18n_ready() ? __( 'Required Core Blueprint Base contracts are unavailable.', 'core-blueprint-work' ) : 'Required Core Blueprint Base contracts are unavailable.', 'url' => $url ];
		}

		$installed_schema = (string) get_option( Schema::OPTION, '0' );
		if ( version_compare( $installed_schema, CB_WORK_SCHEMA_VERSION, '<' ) ) {
			return [ 'state' => 'warn', 'detail' => self::i18n_ready() ? __( 'Work database upgrade pending.', 'core-blueprint-work' ) : 'Work database upgrade pending.', 'url' => $url ];
		}
		if ( version_compare( $installed_schema, CB_WORK_SCHEMA_VERSION, '>' ) ) {
			return [ 'state' => 'warn', 'detail' => self::i18n_ready() ? __( 'Work database schema is newer than this plugin build.', 'core-blueprint-work' ) : 'Work database schema is newer than this plugin build.', 'url' => $url ];
		}
		if ( ! Plugin::is_booted() ) {
			return [ 'state' => 'warn', 'detail' => self::i18n_ready() ? __( 'Runtime is not initialized', 'core-blueprint-work' ) : 'Runtime is not initialized', 'url' => $url ];
		}
		return [
			'state'  => 'ok',
			'detail' => sprintf(
				self::i18n_ready()
					? /* translators: 1: Work Item count, 2: Project count, 3: Service count, 4: VAT rate count. */
					__( '%1$d work items · %2$d projects · %3$d services · %4$d VAT rates', 'core-blueprint-work' )
					: '%1$d work items · %2$d projects · %3$d services · %4$d VAT rates',
				WorkItems::count(), Projects::count(), self::service_count(), TaxRates::count()
			),
			'url' => $url,
		];
	}

	public static function register_dashboard_shortcuts(): void {
		if ( ! self::runtime_ready() ) {
			return;
		}
		CardRegistry::register_shortcut( self::EXTENSION_ID, [ 'id' => 'workspace', 'label' => self::i18n_ready() ? __( 'Open Work', 'core-blueprint-work' ) : 'Open Work', 'url' => admin_url( 'admin.php?page=' . Menu::TOP_LEVEL_SLUG ), 'capability' => \CB\Work\Capabilities::MANAGE, 'order' => 10 ] );
		CardRegistry::register_shortcut( self::EXTENSION_ID, [ 'id' => 'work-items', 'label' => self::i18n_ready() ? __( 'Work Items', 'core-blueprint-work' ) : 'Work Items', 'url' => admin_url( 'admin.php?page=' . Menu::WORK_ITEMS_SLUG ), 'capability' => \CB\Work\Capabilities::MANAGE, 'order' => 20 ] );
		CardRegistry::register_shortcut( self::EXTENSION_ID, [ 'id' => 'projects', 'label' => self::i18n_ready() ? __( 'Projects', 'core-blueprint-work' ) : 'Projects', 'url' => Menu::projects_url(), 'capability' => \CB\Work\Capabilities::MANAGE, 'order' => 30 ] );
		CardRegistry::register_shortcut( self::EXTENSION_ID, [ 'id' => 'services', 'label' => self::i18n_ready() ? __( 'Services', 'core-blueprint-work' ) : 'Services', 'url' => admin_url( 'edit.php?post_type=' . PostTypes::SERVICE ), 'capability' => \CB\Work\Capabilities::MANAGE, 'order' => 40 ] );
	}

	private static function runtime_ready(): bool {
		return function_exists( 'cb_work_runtime_ready' ) && \cb_work_runtime_ready();
	}

	private static function service_count(): int {
		$counts = wp_count_posts( PostTypes::SERVICE );
		$total = 0;
		if ( is_object( $counts ) ) {
			foreach ( get_object_vars( $counts ) as $status => $count ) {
				if ( ! in_array( $status, [ 'trash', 'auto-draft' ], true ) ) {
					$total += (int) $count;
				}
			}
		}
		return $total;
	}

	private static function i18n_ready(): bool {
		return did_action( 'init' ) > 0 || doing_action( 'init' );
	}
}

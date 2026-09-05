<?php
declare(strict_types=1);

namespace CB\Work\Integration;

use CB\Core\Dashboard\CardRegistry;
use CB\Core\ExtensionRegistry;
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
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;

		add_action( 'cb_core_register_extensions', [ self::class, 'register_extension' ] );
		add_filter( 'cb_core_module_status_definitions', [ self::class, 'register_status_definition' ] );
		add_action( 'cb_core_dashboard_register_cards', [ self::class, 'register_dashboard_shortcuts' ] );
	}

	public static function register_extension(): void {
		ExtensionRegistry::register( [
			'id'           => self::EXTENSION_ID,
			'plugin_file'  => CB_WORK_BASENAME,
			'requires_api' => CB_WORK_REQUIRED_API,
			'menu_url'     => admin_url( 'admin.php?page=' . Menu::TOP_LEVEL_SLUG ),
			'status_id'    => self::STATUS_ID,
		] );
	}

	/** @param array<string,array<string,mixed>> $definitions
	 *  @return array<string,array<string,mixed>>
	 */
	public static function register_status_definition( array $definitions ): array {
		$definitions[ self::STATUS_ID ] = [
			'provider' => [ self::class, 'status' ],
			'label'    => self::i18n_ready() ? __( 'Work', 'core-blueprint-work' ) : 'Work',
			'url'      => admin_url( 'admin.php?page=' . Menu::TOP_LEVEL_SLUG ),
		];
		return $definitions;
	}

	/** @return array{state:string,detail:string,url:string} */
	public static function status(): array {
		$url    = admin_url( 'admin.php?page=' . Menu::TOP_LEVEL_SLUG );
		$issues = Requirements::issues();

		if ( [] !== $issues ) {
			return [
				'state'  => 'err',
				'detail' => self::i18n_ready() ? Requirements::health_detail() : 'Runtime requirements unavailable',
				'url'    => $url,
			];
		}

		$installed_schema = (string) get_option( Schema::OPTION, '0' );
		if ( version_compare( $installed_schema, CB_WORK_SCHEMA_VERSION, '<' ) ) {
			return [
				'state'  => 'warn',
				'detail' => self::i18n_ready() ? __( 'Work database upgrade pending.', 'core-blueprint-work' ) : 'Work database upgrade pending.',
				'url'    => $url,
			];
		}
		if ( version_compare( $installed_schema, CB_WORK_SCHEMA_VERSION, '>' ) ) {
			return [
				'state'  => 'warn',
				'detail' => self::i18n_ready() ? __( 'Work database schema is newer than this plugin build.', 'core-blueprint-work' ) : 'Work database schema is newer than this plugin build.',
				'url'    => $url,
			];
		}

		if ( ! Plugin::is_booted() ) {
			return [
				'state'  => 'warn',
				'detail' => self::i18n_ready() ? __( 'Runtime is not initialized', 'core-blueprint-work' ) : 'Runtime is not initialized',
				'url'    => $url,
			];
		}

		return [
			'state'  => 'ok',
			'detail' => sprintf(
				self::i18n_ready() ? __( '%1$d work items · %2$d projects · %3$d services · %4$d VAT rates', 'core-blueprint-work' ) : '%1$d work items · %2$d projects · %3$d services · %4$d VAT rates',
				WorkItems::count(),
				Projects::count(),
				self::service_count(),
				TaxRates::count()
			),
			'url' => $url,
		];
	}

	public static function register_dashboard_shortcuts(): void {
		if ( ! class_exists( CardRegistry::class ) || ! Requirements::runtime_ready() ) {
			return;
		}

		CardRegistry::register_shortcut( self::EXTENSION_ID, [
			'id'         => 'workspace',
			'label'      => self::i18n_ready() ? __( 'Open Work', 'core-blueprint-work' ) : 'Open Work',
			'url'        => admin_url( 'admin.php?page=' . Menu::TOP_LEVEL_SLUG ),
			'capability' => \CB\Work\Capabilities::MANAGE,
			'order'      => 10,
		] );
		CardRegistry::register_shortcut( self::EXTENSION_ID, [
			'id'         => 'work-items',
			'label'      => self::i18n_ready() ? __( 'Work Items', 'core-blueprint-work' ) : 'Work Items',
			'url'        => admin_url( 'admin.php?page=' . Menu::WORK_ITEMS_SLUG ),
			'capability' => \CB\Work\Capabilities::MANAGE,
			'order'      => 20,
		] );
		CardRegistry::register_shortcut( self::EXTENSION_ID, [
			'id'         => 'projects',
			'label'      => self::i18n_ready() ? __( 'Projects', 'core-blueprint-work' ) : 'Projects',
			'url'        => admin_url( 'admin.php?page=' . Menu::PROJECTS_SLUG ),
			'capability' => \CB\Work\Capabilities::MANAGE,
			'order'      => 30,
		] );
		CardRegistry::register_shortcut( self::EXTENSION_ID, [
			'id'         => 'services',
			'label'      => self::i18n_ready() ? __( 'Services', 'core-blueprint-work' ) : 'Services',
			'url'        => admin_url( 'edit.php?post_type=' . PostTypes::SERVICE ),
			'capability' => \CB\Work\Capabilities::MANAGE,
			'order'      => 40,
		] );
	}

	private static function service_count(): int {
		$counts = wp_count_posts( PostTypes::SERVICE );
		$total  = 0;
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

<?php
declare(strict_types=1);

namespace CB\Work\Integration;

use CB\Core\Dashboard\CardRegistry;
use CB\Core\ExtensionRegistry;
use CB\Work\Admin\Page;
use CB\Work\Plugin;
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
			'id'            => self::EXTENSION_ID,
			'plugin_file'   => CB_WORK_BASENAME,
			'requires_api'  => CB_WORK_REQUIRED_API,
			'menu_url'      => admin_url( 'admin.php?page=' . Page::SLUG ),
			'status_id'     => self::STATUS_ID,
		] );
	}

	/** @param array<string,array<string,mixed>> $definitions
	 *  @return array<string,array<string,mixed>>
	 */
	public static function register_status_definition( array $definitions ): array {
		$definitions[ self::STATUS_ID ] = [
			'provider' => [ self::class, 'status' ],
			'label'    => self::i18n_ready() ? __( 'Work', 'core-blueprint-work' ) : 'Work',
			'url'      => admin_url( 'admin.php?page=' . Page::SLUG ),
		];
		return $definitions;
	}

	/** @return array{state:string,detail:string,url:string} */
	public static function status(): array {
		$url    = admin_url( 'admin.php?page=' . Page::SLUG );
		$issues = Requirements::issues();

		if ( [] !== $issues ) {
			return [
				'state'  => 'err',
				'detail' => self::i18n_ready() ? Requirements::health_detail() : 'Runtime requirements unavailable',
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
			'detail' => self::i18n_ready() ? __( 'Work workspace available', 'core-blueprint-work' ) : 'Work workspace available',
			'url'    => $url,
		];
	}

	public static function register_dashboard_shortcuts(): void {
		if ( ! class_exists( CardRegistry::class ) || ! Requirements::runtime_ready() ) {
			return;
		}

		CardRegistry::register_shortcut( self::EXTENSION_ID, [
			'id'         => 'workspace',
			'label'      => self::i18n_ready() ? __( 'Open Work', 'core-blueprint-work' ) : 'Open Work',
			'url'        => admin_url( 'admin.php?page=' . Page::SLUG ),
			'capability' => \CB\Work\Capabilities::MANAGE,
			'order'      => 10,
		] );
	}

	private static function i18n_ready(): bool {
		return did_action( 'init' ) > 0 || doing_action( 'init' );
	}
}

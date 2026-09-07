<?php
declare(strict_types=1);

namespace CB\Work\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Work-owned wp-admin presentation assets.
 *
 * WordPress remains the visual system. This loader only scopes small layout
 * refinements to Work screens where core provides no equivalent layout utility.
 */
final class Assets {
	private const STYLE_HANDLE = 'cb-work-admin';

	public static function init(): void {
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue' ] );
	}

	public static function enqueue(): void {
		$page = isset( $_GET['page'] ) ? sanitize_key( (string) wp_unslash( $_GET['page'] ) ) : '';
		if ( Menu::WORK_ITEMS_SLUG !== $page ) {
			return;
		}

		$file = CB_WORK_DIR . 'assets/work-admin.css';
		if ( ! is_file( $file ) ) {
			return;
		}

		$modified = filemtime( $file );
		$version  = false === $modified ? CB_WORK_VERSION : (string) $modified;

		wp_enqueue_style(
			self::STYLE_HANDLE,
			CB_WORK_URL . 'assets/work-admin.css',
			[],
			$version
		);
	}
}

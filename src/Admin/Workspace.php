<?php
declare(strict_types=1);

namespace CB\Work\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Shared Work admin context marker.
 *
 * WordPress owns the canonical admin shell and navigation. Work keeps only a
 * scoped body class for domain-specific presentation tokens; it deliberately
 * does not render a second in-page application header or hide native submenu
 * routes.
 */
final class Workspace {
	public static function init(): void {
		add_filter( 'admin_body_class', [ self::class, 'body_class' ] );
	}

	public static function body_class( string $classes ): string {
		if ( '' === Menu::screen_context() ) {
			return $classes;
		}

		return trim( $classes . ' cb-work-workspace-screen' );
	}
}

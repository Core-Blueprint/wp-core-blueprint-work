<?php
declare(strict_types=1);

namespace CB\Work;

use CB\Work\Admin\Page;
use CB\Work\Support\Requirements;

defined( 'ABSPATH' ) || exit;

final class Plugin {
	private static bool $booted = false;

	public static function boot(): void {
		if ( self::$booted || ! Requirements::runtime_ready() ) {
			return;
		}
		self::$booted = true;

		Capabilities::init();

		if ( is_admin() ) {
			Page::init();
		}
	}

	public static function is_booted(): bool {
		return self::$booted;
	}
}

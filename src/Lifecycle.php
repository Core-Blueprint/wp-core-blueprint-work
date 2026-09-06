<?php
declare(strict_types=1);

namespace CB\Work;

use CB\Work\Database\Schema;
use CB\Work\Recurrence\Scheduler;
use CB\Work\Support\Requirements;
defined( 'ABSPATH' ) || exit;

final class Lifecycle {
	public static function activate(): void {
		if ( ! Requirements::runtime_ready() ) {
			if ( ! function_exists( 'deactivate_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			deactivate_plugins( CB_WORK_BASENAME );
			wp_die(
				esc_html( 'Core Blueprint Work requires PHP 8.4 and a compatible Core Blueprint Base installation.' ),
				esc_html( 'Core Blueprint dependency required' ),
				[ 'back_link' => true ]
			);
		}

		Capabilities::install();
		Schema::register();
		Scheduler::ensure_scheduled();
	}

	public static function deactivate(): void {
		Scheduler::deactivate();
		// Persistent Work data and capabilities are deliberately preserved.
	}
}

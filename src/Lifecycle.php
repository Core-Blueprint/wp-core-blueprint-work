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
			self::fail_activation( Requirements::activation_message() );
		}
		if ( ! function_exists( 'cb_work_product_contracts_ready' ) || ! \cb_work_product_contracts_ready() ) {
			self::fail_activation( 'Required Core Blueprint Base contracts are unavailable.' );
		}
		Capabilities::install();
		Schema::register();
		Scheduler::ensure_scheduled();
	}

	public static function deactivate(): void {
		Scheduler::deactivate();
		// Persistent Work data and capabilities are deliberately preserved.
	}

	private static function fail_activation( string $message ): never {
		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		deactivate_plugins( CB_WORK_BASENAME );
		wp_die(
			esc_html( $message ),
			esc_html( 'Core Blueprint requirements not met' ),
			[
				'link_url'  => admin_url( 'plugins.php' ),
				'link_text' => __( 'Plugins' ),
			]
		);
	}
}

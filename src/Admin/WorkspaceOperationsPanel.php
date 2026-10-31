<?php
declare(strict_types=1);

namespace CB\Work\Admin;

use CB\Work\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * M3-A: persistent, progressively enhanced operations access in Work.
 *
 * During M3-B/C implementation the real legacy Work screens stay reachable,
 * governed by the existing capabilities and WordPress action handlers.
 */
final class WorkspaceOperationsPanel {
	public static function render(): void {
		self::render_navigation();
		self::render_panels();
	}

	public static function render_navigation(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			return;
		}
		?>
		<nav class="cb-work-m3-operations" aria-label="<?php esc_attr_e( 'Work', 'core-blueprint-work' ); ?>"
			data-cb-work-m3-operations
			data-cb-work-m3-endpoint="<?php echo esc_url( rest_url( 'cb-work/v1/workspace/operations' ) ); ?>"
			data-cb-work-m3-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
			data-cb-work-m3-unavailable="<?php esc_attr_e( 'Work storage unavailable', 'core-blueprint-work' ); ?>"
			data-cb-work-m3-active="<?php esc_attr_e( 'Active', 'core-blueprint-work' ); ?>">
			<a class="cb-work-m3-operations__item" data-cb-work-m3-service-link href="<?php echo esc_url( add_query_arg( [ 'page' => Menu::SERVICE_OPERATIONS_SLUG ], admin_url( 'admin.php' ) ) ); ?>">
				<span class="dashicons dashicons-chart-line" aria-hidden="true"></span><span><?php esc_html_e( 'Service Operations', 'core-blueprint-work' ); ?></span>
				<span class="cb-work-m3-operations__metric" data-cb-work-m3-count="services" hidden></span>
			</a>
			<a class="cb-work-m3-operations__item" href="<?php echo esc_url( add_query_arg( [ 'page' => Menu::RECURRENCE_SLUG ], admin_url( 'admin.php' ) ) ); ?>">
				<span class="dashicons dashicons-update" aria-hidden="true"></span><span><?php esc_html_e( 'Recurring Work', 'core-blueprint-work' ); ?></span>
				<span class="cb-work-m3-operations__metric" data-cb-work-m3-count="recurrence" hidden></span>
			</a>
			<a class="cb-work-m3-operations__item" data-cb-work-m3-time-link href="<?php echo esc_url( Menu::time_url() ); ?>">
				<span class="dashicons dashicons-clock" aria-hidden="true"></span><span><?php esc_html_e( 'Time', 'core-blueprint-work' ); ?></span>
				<span class="cb-work-m3-operations__metric" data-cb-work-m3-timer hidden><?php esc_html_e( 'Timer running', 'core-blueprint-work' ); ?></span>
			</a>
			<span class="screen-reader-text" role="status" aria-live="polite" data-cb-work-m3-status></span>
		</nav>
		<?php
	}

	public static function render_panels(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) { return; }
		WorkspaceEngagementsPanel::render();
		WorkspaceTimePanel::render();
	}

	private function __construct() {}
}

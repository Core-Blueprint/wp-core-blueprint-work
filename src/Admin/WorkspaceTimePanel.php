<?php
declare(strict_types=1);

namespace CB\Work\Admin;

use CB\Work\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * M3-C1: progressively enhanced Time within the manager Work shell.
 * Native Time remains accessible for manual, CAS edit and tracker roles.
 */
final class WorkspaceTimePanel {
	public static function render(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) { return; }
		?>
		<section class="cb-work-m3-time" data-cb-work-m3-time hidden
			aria-label="<?php esc_attr_e( 'Time', 'core-blueprint-work' ); ?>"
			data-cb-work-m3-time-endpoint="<?php echo esc_url( rest_url( 'cb-work/v1/workspace/time' ) ); ?>"
			data-cb-work-m3-time-search="<?php echo esc_url( rest_url( 'cb-work/v1/workspace/time/work-items' ) ); ?>"
			data-cb-work-m3-time-timer="<?php echo esc_url( rest_url( 'cb-work/v1/workspace/time/timer' ) ); ?>"
			data-cb-work-m3-time-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
			data-cb-work-m3-time-error="<?php esc_attr_e( 'Timer could not be updated. Please try again.', 'core-blueprint-work' ); ?>"
			data-cb-work-m3-time-select="<?php esc_attr_e( 'Select a Work Item', 'core-blueprint-work' ); ?>"
			data-cb-work-m3-time-active="<?php esc_attr_e( 'Active timer', 'core-blueprint-work' ); ?>"
			data-cb-work-m3-time-start="<?php esc_attr_e( 'Start Timer', 'core-blueprint-work' ); ?>"
			data-cb-work-m3-time-stop="<?php esc_attr_e( 'Stop Timer', 'core-blueprint-work' ); ?>">
			<header class="cb-work-m3-time__header">
				<h2><?php esc_html_e( 'Time', 'core-blueprint-work' ); ?></h2>
				<button type="button" class="button" data-cb-work-m3-time-close><?php esc_html_e( 'Close', 'core-blueprint-work' ); ?></button>
			</header>
			<p role="alert" data-cb-work-m3-time-error-message hidden><?php esc_html_e( 'Work storage unavailable', 'core-blueprint-work' ); ?></p>
			<div data-cb-work-m3-time-active-zone hidden>
				<p data-cb-work-m3-time-active-label role="status"></p>
				<button type="button" class="button button-primary" data-cb-work-m3-time-stop><?php esc_html_e( 'Stop Timer', 'core-blueprint-work' ); ?></button>
			</div>
			<form data-cb-work-m3-time-start-form hidden>
				<label><?php esc_html_e( 'Search Work Items', 'core-blueprint-work' ); ?>
					<input type="search" name="search" autocomplete="off" maxlength="80">
				</label>
				<label><?php esc_html_e( 'Work Item', 'core-blueprint-work' ); ?>
					<select name="work_item_id" required disabled><option value=""><?php esc_html_e( 'Select a Work Item', 'core-blueprint-work' ); ?></option></select>
				</label>
				<label><?php esc_html_e( 'Note', 'core-blueprint-work' ); ?>
					<textarea name="note" rows="2" maxlength="4000"></textarea>
				</label>
				<button type="submit" class="button button-primary" data-cb-work-m3-time-start><?php esc_html_e( 'Start Timer', 'core-blueprint-work' ); ?></button>
			</form>
			<section aria-label="<?php esc_attr_e( 'Time Entries', 'core-blueprint-work' ); ?>">
				<h3><?php esc_html_e( 'Time Entries', 'core-blueprint-work' ); ?></h3>
				<ul data-cb-work-m3-time-entries class="cb-work-m3-time__entries"></ul>
				<div data-cb-work-m3-time-paging hidden>
					<button type="button" class="button" data-cb-work-m3-time-prev><?php esc_html_e( 'Previous', 'core-blueprint-work' ); ?></button>
					<output data-cb-work-m3-time-page aria-live="polite"></output>
					<button type="button" class="button" data-cb-work-m3-time-next><?php esc_html_e( 'Next', 'core-blueprint-work' ); ?></button>
				</div>
			</section>
			<a class="button" href="<?php echo esc_url( Menu::time_url() ); ?>"><?php esc_html_e( 'Open Time workspace', 'core-blueprint-work' ); ?></a>
		</section>
		<?php
	}

	private function __construct() {}
}

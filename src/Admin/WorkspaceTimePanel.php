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
			data-cb-work-m3-time-entry="<?php echo esc_url( rest_url( 'cb-work/v1/workspace/time/entry' ) ); ?>"
			data-cb-work-m3-time-native="<?php echo esc_url( Menu::time_url( [ 'view' => Time::VIEW_MANUAL ] ) ); ?>"
			data-cb-work-m3-time-today="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>"
			data-cb-work-m3-time-add="<?php esc_attr_e( 'Add Time Entry', 'core-blueprint-work' ); ?>"
			data-cb-work-m3-time-correct="<?php esc_attr_e( 'Correct Time Entry', 'core-blueprint-work' ); ?>"
			data-cb-work-m3-time-save="<?php esc_attr_e( 'Save Correction', 'core-blueprint-work' ); ?>"
			data-cb-work-m3-time-cancel="<?php esc_attr_e( 'Cancel', 'core-blueprint-work' ); ?>"
			data-cb-work-m3-time-item="<?php esc_attr_e( 'Work Item', 'core-blueprint-work' ); ?>"
			data-cb-work-m3-time-search-label="<?php esc_attr_e( 'Search Work Items', 'core-blueprint-work' ); ?>"
			data-cb-work-m3-time-begin="<?php esc_attr_e( 'Start', 'core-blueprint-work' ); ?>"
			data-cb-work-m3-time-end="<?php esc_attr_e( 'End', 'core-blueprint-work' ); ?>"
			data-cb-work-m3-time-note-label="<?php esc_attr_e( 'Note', 'core-blueprint-work' ); ?>"
			data-cb-work-m3-time-conflict="<?php esc_attr_e( 'This Time entry changed after you opened it. Reload the latest entry before editing again.', 'core-blueprint-work' ); ?>"
			data-cb-work-m3-time-invalid="<?php esc_attr_e( 'The Time entry could not be saved. Check the Work Item and time range.', 'core-blueprint-work' ); ?>"
			data-cb-work-m3-time-denied="<?php esc_attr_e( 'You are not authorized to track time for that Work Item or user.', 'core-blueprint-work' ); ?>"
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
				<div class="cb-work-m3-time__entries-heading">
					<h3><?php esc_html_e( 'Time Entries', 'core-blueprint-work' ); ?></h3>
					<a class="button button-primary" href="<?php echo esc_url( Menu::time_url( [ 'view' => Time::VIEW_MANUAL ] ) ); ?>" data-cb-work-m3-time-add-entry><?php esc_html_e( 'Add Time Entry', 'core-blueprint-work' ); ?></a>
				</div>
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

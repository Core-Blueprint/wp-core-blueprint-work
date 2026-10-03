<?php
declare(strict_types=1);

namespace CB\Work\Admin;

use CoreBlueprint\Core\UI\Assets;
use CB\Work\Capabilities;
use CB\Work\Database\Schema;
use CB\Work\Domain\TimeRange;
use CB\Work\Repository\TimeEntries;
use CB\Work\Repository\Timers;
use CB\Work\Repository\WorkItems;
use CB\Work\Time\Access;

defined( 'ABSPATH' ) || exit;

final class Time {
	public static function init(): void {
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue' ] );
	}

	public static function enqueue(): void {
		$page = isset( $_GET['page'] ) ? sanitize_key( (string) wp_unslash( $_GET['page'] ) ) : '';
		$tracker_landing = Menu::TOP_LEVEL_SLUG === $page
			&& ! current_user_can( Capabilities::MANAGE )
			&& current_user_can( Capabilities::TRACK_TIME );
		if ( Menu::TIME_SLUG === $page || $tracker_landing ) {
			Assets::enqueue_time_picker();
		}
	}

	public static function render(): void {
		self::guard();
		$manager = Access::can_manage();
		$user_id = get_current_user_id();
		?>
		<div class="wrap cb-work-time-page">
			<h1><?php esc_html_e( 'Time', 'core-blueprint-work' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Register actual Work time. Work Item estimates and billing classification remain separate planning and commercial facts.', 'core-blueprint-work' ); ?></p>
			<?php self::render_notice(); ?>
			<?php if ( ! self::schema_ready() ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'Work Time storage is not ready yet. Complete the Work schema upgrade first.', 'core-blueprint-work' ); ?></p></div>
			</div>
			<?php return; ?>
			<?php endif; ?>
			<?php
			$items  = self::available_work_items( $manager, $user_id );
			$active = Timers::active_for_user( $user_id );
			$edit_id = isset( $_GET['entry_id'] ) ? absint( $_GET['entry_id'] ) : 0;
			$editing = $edit_id > 0 ? TimeEntries::get( $edit_id ) : null;
			if ( is_array( $editing ) && ! Access::can_view_entry( $editing ) ) {
				wp_die( esc_html__( 'You do not have permission to view this Time entry.', 'core-blueprint-work' ) );
			}
			$entries = TimeEntries::all( 200, $manager ? 0 : $user_id );
			self::render_timer( $items, $active, $user_id );
			self::render_entry_form( $items, $editing, $manager, $user_id );
			self::render_entries( $entries, $manager );
			?>
		</div>
		<?php
	}

	/** @param array<int,array<string,mixed>> $items @param array<string,mixed>|null $active */
	private static function render_timer( array $items, ?array $active, int $user_id ): void {
		?>
		<div class="card">
			<h2><?php esc_html_e( 'Timer', 'core-blueprint-work' ); ?></h2>
			<?php if ( is_array( $active ) ) : ?>
				<?php
				$item = WorkItems::get( (int) $active['work_item_id'] );
				$parts = TimeRange::utc_to_local_parts( (string) $active['started_at'] );
				?>
				<p><strong><?php
				/* translators: %d: Work Item ID. */
				echo esc_html( (string) ( $item['title'] ?? sprintf( __( 'Work Item #%d', 'core-blueprint-work' ), (int) $active['work_item_id'] ) ) );
				?></strong></p>
				<p><?php
				/* translators: 1: local start date, 2: local start time. */
				echo esc_html( sprintf( __( 'Running since %1$s %2$s.', 'core-blueprint-work' ), (string) ( $parts['date'] ?? '' ), (string) ( $parts['time'] ?? '' ) ) );
				?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="cb_work_stop_timer">
					<input type="hidden" name="user_id" value="<?php echo esc_attr( (string) $user_id ); ?>">
					<?php wp_nonce_field( 'cb_work_stop_timer_' . $user_id ); ?>
					<?php submit_button( __( 'Stop Timer', 'core-blueprint-work' ), 'primary', 'submit', false ); ?>
				</form>
			<?php elseif ( [] === $items ) : ?>
				<p><?php esc_html_e( 'No Work Items are available for time tracking.', 'core-blueprint-work' ); ?></p>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="cb_work_start_timer">
					<?php wp_nonce_field( 'cb_work_start_timer' ); ?>
					<p><label for="cb-work-timer-item"><strong><?php esc_html_e( 'Work Item', 'core-blueprint-work' ); ?></strong></label><br><?php self::work_item_select( $items, 0, 'time[work_item_id]', 'cb-work-timer-item' ); ?></p>
					<p><label for="cb-work-timer-note"><strong><?php esc_html_e( 'Note', 'core-blueprint-work' ); ?></strong></label><br><textarea id="cb-work-timer-note" class="large-text" rows="2" name="time[note]"></textarea></p>
					<?php submit_button( __( 'Start Timer', 'core-blueprint-work' ), 'primary', 'submit', false ); ?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/** @param array<int,array<string,mixed>> $items @param array<string,mixed>|null $entry */
	private static function render_entry_form( array $items, ?array $entry, bool $manager, int $current_user_id ): void {
		$editing = is_array( $entry );
		$selected_item = $editing ? (int) $entry['work_item_id'] : 0;
		$selected_user = $editing ? (int) $entry['user_id'] : $current_user_id;
		$start = $editing ? TimeRange::utc_to_local_parts( (string) $entry['started_at'] ) : null;
		$end   = $editing && null !== $entry['ended_at'] ? TimeRange::utc_to_local_parts( (string) $entry['ended_at'] ) : null;
		$date  = current_time( 'Y-m-d' );
		?>
		<div class="card">
			<h2><?php echo esc_html( $editing ? __( 'Correct Time Entry', 'core-blueprint-work' ) : __( 'Add Time Entry', 'core-blueprint-work' ) ); ?></h2>
			<?php if ( $editing ) : ?><p class="description"><?php esc_html_e( 'Corrections use revision checks. If someone saved this entry first, your stale form will not overwrite their change.', 'core-blueprint-work' ); ?></p><?php endif; ?>
			<?php if ( [] === $items ) : ?>
				<p><?php esc_html_e( 'No Work Items are available for this entry.', 'core-blueprint-work' ); ?></p>
			<?php else : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo $editing ? 'cb_work_update_time_entry' : 'cb_work_create_time_entry'; ?>">
				<?php if ( $editing ) : ?>
					<input type="hidden" name="entry_id" value="<?php echo esc_attr( (string) $entry['id'] ); ?>">
					<input type="hidden" name="time[revision]" value="<?php echo esc_attr( (string) $entry['revision'] ); ?>">
					<?php wp_nonce_field( 'cb_work_update_time_entry_' . (int) $entry['id'] ); ?>
				<?php else : ?>
					<?php wp_nonce_field( 'cb_work_create_time_entry' ); ?>
				<?php endif; ?>
				<table class="form-table" role="presentation"><tbody>
				<tr><th scope="row"><label for="cb-work-time-item"><?php esc_html_e( 'Work Item', 'core-blueprint-work' ); ?></label></th><td><?php self::work_item_select( $items, $selected_item, 'time[work_item_id]', 'cb-work-time-item' ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'User', 'core-blueprint-work' ); ?></th><td>
					<?php if ( $manager ) : ?>
						<?php Pickers::assignee( 'time[user_id]', 'cb-work-time-user', $selected_user ); ?>
					<?php else : ?>
						<input type="hidden" name="time[user_id]" value="<?php echo esc_attr( (string) $current_user_id ); ?>">
						<?php $user = get_userdata( $current_user_id ); echo esc_html( $user ? (string) $user->display_name : (string) $current_user_id ); ?>
					<?php endif; ?>
				</td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Start', 'core-blueprint-work' ); ?></th><td><input type="date" name="time[start_date]" value="<?php echo esc_attr( (string) ( $start['date'] ?? $date ) ); ?>" required> <?php self::time_picker( 'time[start_time]', 'cb-work-time-start', (string) ( $start['time'] ?? '' ) ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'End', 'core-blueprint-work' ); ?></th><td><input type="date" name="time[end_date]" value="<?php echo esc_attr( (string) ( $end['date'] ?? $date ) ); ?>" required> <?php self::time_picker( 'time[end_time]', 'cb-work-time-end', (string) ( $end['time'] ?? '' ) ); ?></td></tr>
				<tr><th scope="row"><label for="cb-work-time-note"><?php esc_html_e( 'Note', 'core-blueprint-work' ); ?></label></th><td><textarea id="cb-work-time-note" class="large-text" rows="3" name="time[note]"><?php echo esc_textarea( $editing ? (string) $entry['note'] : '' ); ?></textarea></td></tr>
				</tbody></table>
				<?php submit_button( $editing ? __( 'Save Correction', 'core-blueprint-work' ) : __( 'Add Time Entry', 'core-blueprint-work' ) ); ?>
				<?php if ( $editing ) : ?><a class="button" href="<?php echo esc_url( self::url() ); ?>"><?php esc_html_e( 'Cancel', 'core-blueprint-work' ); ?></a><?php endif; ?>
			</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/** @param array<int,array<string,mixed>> $entries */
	private static function render_entries( array $entries, bool $manager ): void {
		?>
		<h2><?php esc_html_e( 'Recent Time Entries', 'core-blueprint-work' ); ?></h2>
		<?php if ( [] === $entries ) : ?>
			<p><?php esc_html_e( 'No completed Time entries yet.', 'core-blueprint-work' ); ?></p>
			<?php return; ?>
		<?php endif; ?>
		<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'When', 'core-blueprint-work' ); ?></th><th><?php esc_html_e( 'Work Item', 'core-blueprint-work' ); ?></th><?php if ( $manager ) : ?><th><?php esc_html_e( 'User', 'core-blueprint-work' ); ?></th><?php endif; ?><th><?php esc_html_e( 'Duration', 'core-blueprint-work' ); ?></th><th><?php esc_html_e( 'Source', 'core-blueprint-work' ); ?></th><th><?php esc_html_e( 'Note', 'core-blueprint-work' ); ?></th><th><?php esc_html_e( 'Actions', 'core-blueprint-work' ); ?></th></tr></thead><tbody>
		<?php foreach ( $entries as $entry ) : ?>
			<?php
			$item = WorkItems::get( (int) $entry['work_item_id'] );
			$user = get_userdata( (int) $entry['user_id'] );
			$parts = TimeRange::utc_to_local_parts( (string) $entry['started_at'] );
			?>
			<tr>
				<td><?php echo esc_html( trim( (string) ( $parts['date'] ?? '' ) . ' ' . (string) ( $parts['time'] ?? '' ) ) ); ?></td>
				<td><?php echo esc_html( (string) ( $item['title'] ?? sprintf( __( 'Work Item #%d', 'core-blueprint-work' ), (int) $entry['work_item_id'] ) ) ); ?></td>
				<?php if ( $manager ) : ?><td><?php echo esc_html( $user ? (string) $user->display_name : (string) $entry['user_id'] ); ?></td><?php endif; ?>
				<td><?php echo esc_html( self::duration_label( (int) $entry['duration_seconds'] ) ); ?></td>
				<td><?php echo esc_html( ucfirst( (string) $entry['entry_source'] ) ); ?></td>
				<td><?php echo esc_html( (string) $entry['note'] ); ?></td>
				<td><?php if ( Access::can_view_entry( $entry ) ) : ?><a class="button button-small" href="<?php echo esc_url( self::url( [ 'entry_id' => (int) $entry['id'] ] ) ); ?>"><?php esc_html_e( 'Edit', 'core-blueprint-work' ); ?></a><?php endif; ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody></table>
		<?php
	}

	/** @return array<int,array<string,mixed>> */
	private static function available_work_items( bool $manager, int $user_id ): array {
		$items = WorkItems::all( 500 );
		if ( $manager ) {
			return $items;
		}
		return array_values( array_filter(
			$items,
			static fn( array $item ): bool => in_array( $user_id, (array) ( $item['assigned_user_ids'] ?? [] ), true )
		) );
	}

	/** @param array<int,array<string,mixed>> $items */
	private static function work_item_select( array $items, int $selected, string $name, string $id ): void {
		echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" required>';
		echo '<option value="">' . esc_html__( 'Select a Work Item', 'core-blueprint-work' ) . '</option>';
		foreach ( $items as $item ) {
			printf( '<option value="%1$d" %2$s>%3$s</option>', (int) $item['id'], selected( $selected, (int) $item['id'], false ), esc_html( (string) $item['title'] ) );
		}
		echo '</select>';
	}

	private static function time_picker( string $name, string $id, string $value ): void {
		?>
		<div class="cb-core-time-picker" data-cb-time-picker style="display:inline-flex;vertical-align:middle;">
			<input id="<?php echo esc_attr( $id ); ?>" type="text" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" inputmode="numeric" autocomplete="off" placeholder="HH:MM" required>
			<button type="button" class="button" data-cb-time-picker-toggle aria-label="<?php esc_attr_e( 'Choose time', 'core-blueprint-work' ); ?>"></button>
		</div>
		<?php
	}

	private static function duration_label( int $seconds ): string {
		$seconds = max( 0, $seconds );
		$hours   = intdiv( $seconds, 3600 );
		$minutes = intdiv( $seconds % 3600, 60 );
		return sprintf( '%d:%02d', $hours, $minutes );
	}

	/** @param array<string,int|string> $args */
	private static function url( array $args = [] ): string {
		return add_query_arg( [ 'page' => Menu::TIME_SLUG, ...$args ], admin_url( 'admin.php' ) );
	}

	private static function render_notice(): void {
		$notice = isset( $_GET['cb-work-notice'] ) ? sanitize_key( (string) wp_unslash( $_GET['cb-work-notice'] ) ) : '';
		$messages = [
			'timer-started'       => [ 'success', __( 'Timer started.', 'core-blueprint-work' ) ],
			'timer-stopped'       => [ 'success', __( 'Timer stopped and Time entry completed.', 'core-blueprint-work' ) ],
			'timer-start-failed'  => [ 'error', __( 'The timer could not be started. You may already have an active timer.', 'core-blueprint-work' ) ],
			'timer-stop-failed'   => [ 'error', __( 'The active timer could not be stopped safely.', 'core-blueprint-work' ) ],
			'time-created'        => [ 'success', __( 'Time entry added.', 'core-blueprint-work' ) ],
			'time-updated'        => [ 'success', __( 'Time entry updated.', 'core-blueprint-work' ) ],
			'time-invalid'        => [ 'error', __( 'The Time entry could not be saved. Check the Work Item and time range.', 'core-blueprint-work' ) ],
			'time-conflict'       => [ 'error', __( 'This Time entry changed after you opened it. Reload the latest entry before editing again.', 'core-blueprint-work' ) ],
			'time-not-authorized' => [ 'error', __( 'You are not authorized to track time for that Work Item or user.', 'core-blueprint-work' ) ],
		];
		if ( isset( $messages[ $notice ] ) ) {
			[ $type, $message ] = $messages[ $notice ];
			printf( '<div class="notice notice-%1$s inline"><p>%2$s</p></div>', esc_attr( $type ), esc_html( $message ) );
		}
	}

	private static function guard(): void {
		if ( ! Access::can_track() ) {
			wp_die( esc_html__( 'You do not have permission to track Work time.', 'core-blueprint-work' ) );
		}
	}

	private static function schema_ready(): bool {
		return defined( 'CB_WORK_SCHEMA_VERSION' ) && CB_WORK_SCHEMA_VERSION === (string) get_option( Schema::OPTION, '0' );
	}
}

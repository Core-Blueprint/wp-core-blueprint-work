<?php
declare(strict_types=1);

namespace CB\Work\Admin;

use CoreBlueprint\Core\Governance\Audit;
use CB\Work\Domain\TimeRange;
use CB\Work\Governance\Events;
use CB\Work\Repository\TimeEntries;
use CB\Work\Repository\Timers;
use CB\Work\Time\Access;

defined( 'ABSPATH' ) || exit;

final class TimeActions {
	public static function init(): void {
		add_action( 'admin_post_cb_work_start_timer', [ self::class, 'start_timer' ] );
		add_action( 'admin_post_cb_work_stop_timer', [ self::class, 'stop_timer' ] );
		add_action( 'admin_post_cb_work_create_time_entry', [ self::class, 'create_entry' ] );
		add_action( 'admin_post_cb_work_update_time_entry', [ self::class, 'update_entry' ] );
		TimeEntryBulkEdit::init();
	}

	public static function start_timer(): never {
		self::guard( 'cb_work_start_timer' );
		$actor = get_current_user_id();
		$input = self::input();
		$work_item_id = absint( $input['work_item_id'] ?? 0 );
		$note = is_scalar( $input['note'] ?? '' ) ? (string) $input['note'] : '';
		if ( ! Access::can_track_work_item( $work_item_id, $actor ) ) {
			self::redirect( 'time-not-authorized' );
		}
		$entry_id = Timers::start( $work_item_id, $actor, $note, $actor );
		if ( $entry_id <= 0 ) {
			self::redirect( 'timer-start-failed' );
		}
		Audit::record( Events::TIMER_STARTED, 'notice', [
			'time_entry_id' => $entry_id,
			'work_item_id'  => $work_item_id,
			'user_id'       => $actor,
		] );
		self::redirect( 'timer-started' );
	}

	public static function stop_timer(): never {
		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : get_current_user_id();
		self::guard( 'cb_work_stop_timer_' . $user_id );
		if ( ! Access::can_stop_user_timer( $user_id ) ) {
			self::redirect( 'time-not-authorized' );
		}
		$entry = Timers::stop( $user_id, get_current_user_id() );
		if ( null === $entry ) {
			self::redirect( 'timer-stop-failed' );
		}
		Audit::record( Events::TIMER_STOPPED, 'notice', [
			'time_entry_id'     => (int) $entry['id'],
			'work_item_id'      => (int) $entry['work_item_id'],
			'user_id'           => (int) $entry['user_id'],
			'duration_seconds'  => (int) $entry['duration_seconds'],
		] );
		self::redirect( 'timer-stopped' );
	}

	public static function create_entry(): never {
		self::guard( 'cb_work_create_time_entry' );
		$actor = get_current_user_id();
		$input = self::input();
		$work_item_id = absint( $input['work_item_id'] ?? 0 );
		$user_id = Access::can_manage()
			? absint( $input['user_id'] ?? $actor )
			: $actor;
		if ( ! Access::can_track_work_item( $work_item_id, $user_id ) ) {
			self::redirect( 'time-not-authorized' );
		}

		$range = self::range_from_input( $input );
		if ( null === $range ) {
			self::redirect( 'time-invalid' );
		}
		$note = is_scalar( $input['note'] ?? '' ) ? (string) $input['note'] : '';
		$entry_id = TimeEntries::create_manual(
			$work_item_id,
			$user_id,
			$range['started_at'],
			$range['ended_at'],
			$note,
			$actor
		);
		if ( $entry_id <= 0 ) {
			self::redirect( 'time-invalid' );
		}
		$entry = TimeEntries::get( $entry_id );
		Audit::record( Events::TIME_ENTRY_CREATED, 'notice', [
			'time_entry_id'    => $entry_id,
			'work_item_id'     => $work_item_id,
			'user_id'          => $user_id,
			'duration_seconds' => (int) ( $entry['duration_seconds'] ?? 0 ),
			'entry_source'     => TimeEntries::SOURCE_MANUAL,
		] );
		self::redirect( 'time-created' );
	}

	public static function update_entry(): never {
		$entry_id = isset( $_POST['entry_id'] ) ? absint( $_POST['entry_id'] ) : 0;
		self::guard( 'cb_work_update_time_entry_' . $entry_id );
		$entry = TimeEntries::get( $entry_id );
		if ( null === $entry || null === $entry['ended_at'] ) {
			self::redirect( 'time-invalid' );
		}

		$input = self::input();
		$quick_edit = self::quick_edit_request();
		// Quick Edit changes only actual timestamps and notes. Work Item and
		// owner stay authoritative from the stored entry, not from POST data.
		$work_item_id = $quick_edit ? (int) $entry['work_item_id'] : absint( $input['work_item_id'] ?? 0 );
		$user_id = $quick_edit
			? (int) $entry['user_id']
			: ( Access::can_manage()
				? absint( $input['user_id'] ?? (int) $entry['user_id'] )
				: (int) $entry['user_id'] );
		if ( ! Access::can_edit_entry( $entry, $work_item_id ) ) {
			self::redirect( 'time-not-authorized' );
		}
		$range = self::range_from_input( $input );
		if ( null === $range ) {
			self::redirect( 'time-invalid', [ 'entry_id' => $entry_id ] );
		}
		$revision = absint( $input['revision'] ?? 0 );
		$note = is_scalar( $input['note'] ?? '' ) ? (string) $input['note'] : '';
		if ( ! TimeEntries::update_completed(
			$entry_id,
			$revision,
			$work_item_id,
			$user_id,
			$range['started_at'],
			$range['ended_at'],
			$note,
			get_current_user_id()
		) ) {
			$fresh = TimeEntries::get( $entry_id );
			$notice = is_array( $fresh ) && (int) $fresh['revision'] !== $revision ? 'time-conflict' : 'time-invalid';
			self::redirect( $notice, [ 'entry_id' => $entry_id ] );
		}
		$fresh = TimeEntries::get( $entry_id );
		Audit::record( Events::TIME_ENTRY_UPDATED, 'notice', [
			'time_entry_id'    => $entry_id,
			'work_item_id'     => $work_item_id,
			'user_id'          => $user_id,
			'duration_seconds' => (int) ( $fresh['duration_seconds'] ?? 0 ),
			'revision'         => (int) ( $fresh['revision'] ?? 0 ),
			'entry_source'     => (string) ( $fresh['entry_source'] ?? '' ),
		] );
		self::redirect( 'time-updated' );
	}

	/** @return array<string,mixed> */
	private static function input(): array {
		return isset( $_POST['time'] ) && is_array( $_POST['time'] )
			? wp_unslash( $_POST['time'] )
			: [];
	}

	/** @param array<string,mixed> $input @return array{started_at:string,ended_at:string}|null */
	private static function range_from_input( array $input ): ?array {
		$start_date = is_scalar( $input['start_date'] ?? '' ) ? sanitize_text_field( (string) $input['start_date'] ) : '';
		$start_time = is_scalar( $input['start_time'] ?? '' ) ? sanitize_text_field( (string) $input['start_time'] ) : '';
		$end_date   = is_scalar( $input['end_date'] ?? '' ) ? sanitize_text_field( (string) $input['end_date'] ) : '';
		$end_time   = is_scalar( $input['end_time'] ?? '' ) ? sanitize_text_field( (string) $input['end_time'] ) : '';
		$started_at = TimeRange::local_to_utc( $start_date, $start_time );
		$ended_at   = TimeRange::local_to_utc( $end_date, $end_time );
		if ( null === $started_at || null === $ended_at ) {
			return null;
		}
		$duration = TimeRange::duration_seconds( $started_at, $ended_at );
		return null !== $duration && $duration > 0
			? [ 'started_at' => $started_at, 'ended_at' => $ended_at ]
			: null;
	}

	private static function guard( string $nonce_action ): void {
		if ( ! Access::can_track() ) {
			wp_die( esc_html__( 'You do not have permission to track Work time.', 'core-blueprint-work' ) );
		}
		check_admin_referer( $nonce_action );
	}

	private static function quick_edit_request(): bool {
		return isset( $_POST['cb_work_quick_edit'] )
			&& is_scalar( $_POST['cb_work_quick_edit'] )
			&& '1' === (string) $_POST['cb_work_quick_edit'];
	}

	/** @param array<string,int|string> $extra */
	private static function redirect( string $notice, array $extra = [] ): never {
		// Redirect into the view associated with the completed action, preserving
		// the tracker-only Work landing and correction context.
		$action = isset( $_POST['action'] ) && is_string( $_POST['action'] )
			? sanitize_key( wp_unslash( $_POST['action'] ) )
			: '';
		if ( 'cb_work_update_time_entry' === $action && self::quick_edit_request() ) {
			$raw_state = isset( $_POST['time_list'] ) && is_array( $_POST['time_list'] )
				? wp_unslash( $_POST['time_list'] )
				: [];
			$state = TimeEntryListState::from_request( $raw_state, Access::can_manage() );
			$args = TimeEntryListState::url_args( $state );
			$args['cb-work-notice'] = sanitize_key( $notice );
			if ( 'time-updated' !== $notice && ! empty( $extra['entry_id'] ) ) {
				$args['te_edit'] = (int) $extra['entry_id'];
			}
			wp_safe_redirect( Menu::time_url( $args ) );
			exit;
		}
		$view = in_array( $action, [ 'cb_work_start_timer', 'cb_work_stop_timer' ], true )
			? Time::VIEW_TIMER
			: ( in_array( $notice, [ 'time-created', 'time-updated' ], true ) ? Time::VIEW_ENTRIES : Time::VIEW_MANUAL );
		wp_safe_redirect( Menu::time_url( [
			'view' => $view,
			'cb-work-notice' => sanitize_key( $notice ),
			...$extra,
		] ) );
		exit;
	}
}

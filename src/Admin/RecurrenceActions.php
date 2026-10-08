<?php
declare(strict_types=1);

namespace CB\Work\Admin;

use CoreBlueprint\Core\Governance\Audit;
use CB\Work\Capabilities;
use CB\Work\Governance\Events;
use CB\Work\Integration\CRMCustomers;
use CB\Work\Recurrence\Scheduler;
use CB\Work\Repository\RecurrenceRules;

defined( 'ABSPATH' ) || exit;

final class RecurrenceActions {
	public static function init(): void {
		add_action( 'admin_post_cb_work_create_recurrence_rule', [ self::class, 'create' ] );
		add_action( 'admin_post_cb_work_update_recurrence_rule', [ self::class, 'update' ] );
		add_action( 'admin_post_cb_work_toggle_recurrence_rule', [ self::class, 'toggle' ] );
		add_action( 'admin_post_cb_work_run_recurrence_generator', [ self::class, 'run_generator' ] );
		add_action( 'wp_ajax_cb_work_quick_edit_recurrence_rule', [ self::class, 'quick_edit' ] );
		add_action( 'wp_ajax_cb_work_toggle_recurrence_rule_inline', [ self::class, 'toggle_inline' ] );
		add_action( 'wp_ajax_cb_work_preview_recurrence_rule', [ self::class, 'preview' ] );
	}

	public static function create(): never {
		self::guard( 'cb_work_create_recurrence_rule' );
		$input = self::input();
		if ( null === $input ) {
			self::redirect( 'recurrence-invalid' );
		}
		$input['created_by'] = get_current_user_id();
		$input['updated_by'] = get_current_user_id();
		$rule_id = RecurrenceRules::create( $input );
		if ( $rule_id <= 0 ) {
			self::redirect( 'recurrence-invalid' );
		}
		Audit::record( Events::RECURRENCE_RULE_CREATED, 'notice', [ 'rule_id' => $rule_id ] );
		self::redirect( 'recurrence-created', [ 'rule_id' => $rule_id ] );
	}

	public static function update(): never {
		$rule_id = isset( $_POST['rule_id'] ) ? absint( $_POST['rule_id'] ) : 0;
		self::guard( 'cb_work_update_recurrence_rule_' . $rule_id );
		$current = RecurrenceRules::get( $rule_id );
		if ( null === $current ) {
			self::redirect( 'recurrence-invalid' );
		}
		$input = self::input( $current );
		if ( null === $input ) {
			self::redirect( 'recurrence-invalid', [ 'rule_id' => $rule_id ] );
		}
		$input['updated_by'] = get_current_user_id();
		if ( ! RecurrenceRules::update( $rule_id, $input ) ) {
			self::redirect( 'recurrence-invalid', [ 'rule_id' => $rule_id ] );
		}
		Audit::record( Events::RECURRENCE_RULE_UPDATED, 'notice', [ 'rule_id' => $rule_id ] );
		self::redirect( 'recurrence-updated', [ 'rule_id' => $rule_id ] );
	}

	public static function toggle(): never {
		$rule_id = isset( $_POST['rule_id'] ) ? absint( $_POST['rule_id'] ) : 0;
		self::guard( 'cb_work_toggle_recurrence_rule_' . $rule_id );
		$active = isset( $_POST['active'] ) && '1' === sanitize_text_field( wp_unslash( (string) $_POST['active'] ) );
		if ( $rule_id <= 0 || ! RecurrenceRules::update( $rule_id, [ 'is_active' => $active, 'updated_by' => get_current_user_id() ] ) ) {
			self::redirect( 'recurrence-invalid' );
		}
		Audit::record( Events::RECURRENCE_RULE_STATUS_CHANGED, 'notice', [ 'rule_id' => $rule_id, 'active' => $active ] );
		self::redirect( 'recurrence-status-updated' );
	}

	public static function run_generator(): never {
		self::guard( 'cb_work_run_recurrence_generator' );
		$stats = Scheduler::run( get_current_user_id(), 'manual' );
		self::redirect( 'recurrence-run', [
			'generated' => (int) $stats['generated'],
			'recovered' => (int) $stats['recovered'],
			'failed'    => (int) $stats['failed'],
		] );
	}

	/** Governed inline activation; the legacy admin-post action remains the no-JS fallback. */
	public static function toggle_inline(): void {
		$rule_id = isset( $_POST['rule_id'] ) ? absint( $_POST['rule_id'] ) : 0;
		if ( ! current_user_can( Capabilities::MANAGE ) || $rule_id <= 0 ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission to manage Work.', 'core-blueprint-work' ) ], 403 );
		}
		check_ajax_referer( 'cb_work_toggle_recurrence_rule_' . $rule_id );
		$active = isset( $_POST['active'] ) && '1' === sanitize_text_field( wp_unslash( (string) $_POST['active'] ) );
		if ( null === RecurrenceRules::get( $rule_id ) || ! RecurrenceRules::update( $rule_id, [ 'is_active' => $active, 'updated_by' => get_current_user_id() ] ) ) {
			wp_send_json_error( [ 'message' => __( 'The Recurring Work rule could not be saved. Check the supplied values and whether its schedule is already locked by history.', 'core-blueprint-work' ) ], 409 );
		}
		Audit::record( Events::RECURRENCE_RULE_STATUS_CHANGED, 'notice', [ 'rule_id' => $rule_id, 'active' => $active, 'source' => 'inline' ] );
		wp_send_json_success( [
			'active' => $active,
			'status' => $active ? __( 'Active', 'core-blueprint-work' ) : __( 'Inactive', 'core-blueprint-work' ),
			'action' => $active ? __( 'Deactivate', 'core-blueprint-work' ) : __( 'Activate', 'core-blueprint-work' ),
		] );
	}

	/** Quick Edit changes the template title and priority, never the schedule. */
	public static function quick_edit(): void {
		$rule_id = isset( $_POST['rule_id'] ) ? absint( $_POST['rule_id'] ) : 0;
		if ( ! current_user_can( Capabilities::MANAGE ) || $rule_id <= 0 ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission to manage Work.', 'core-blueprint-work' ) ], 403 );
		}
		check_ajax_referer( 'cb_work_quick_edit_recurrence_rule_' . $rule_id );
		$rule = RecurrenceRules::get( $rule_id );
		$title = isset( $_POST['title'] ) && is_scalar( $_POST['title'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['title'] ) ) : '';
		$priority = isset( $_POST['priority'] ) && is_scalar( $_POST['priority'] ) ? sanitize_key( wp_unslash( (string) $_POST['priority'] ) ) : '';
		if ( null === $rule || '' === $title || ! \CB\Work\Domain\WorkItemPriority::is_valid( $priority ) ) {
			wp_send_json_error( [ 'message' => __( 'The Recurring Work rule could not be saved. Check the supplied values and whether its schedule is already locked by history.', 'core-blueprint-work' ) ], 400 );
		}
		if ( ! RecurrenceRules::update( $rule_id, [ 'title' => $title, 'priority' => $priority, 'updated_by' => get_current_user_id() ] ) ) {
			wp_send_json_error( [ 'message' => __( 'The Recurring Work rule could not be saved. Check the supplied values and whether its schedule is already locked by history.', 'core-blueprint-work' ) ], 409 );
		}
		Audit::record( Events::RECURRENCE_RULE_UPDATED, 'notice', [ 'rule_id' => $rule_id, 'source' => 'quick-edit' ] );
		wp_send_json_success( [ 'title' => $title, 'priority' => $priority ] );
	}

	/** Read-only forecast using the same recurrence domain as the scheduler. */
	public static function preview(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission to manage Work.', 'core-blueprint-work' ) ], 403 );
		}
		check_ajax_referer( 'cb_work_recurrence_preview' );
		$input = isset( $_POST['recurrence'] ) && is_array( $_POST['recurrence'] ) ? wp_unslash( $_POST['recurrence'] ) : [];
		$rule_id = isset( $_POST['rule_id'] ) ? absint( $_POST['rule_id'] ) : 0;
		$current = $rule_id > 0 ? RecurrenceRules::get( $rule_id ) : null;
		if ( $rule_id > 0 && null === $current ) {
			wp_send_json_error( [ 'message' => __( 'The Recurring Work rule could not be saved. Check the supplied values and whether its schedule is already locked by history.', 'core-blueprint-work' ) ], 404 );
		}
		$locked = null !== $current && \CB\Work\Repository\RecurrenceOccurrences::count_for_rule( $rule_id ) > 0;
		$frequency = $locked ? (string) $current['frequency'] : sanitize_key( (string) ( $input['frequency'] ?? '' ) );
		$interval = $locked ? (int) $current['interval_count'] : (int) ( $input['interval_count'] ?? 0 );
		$start = $locked ? (string) $current['start_on'] : sanitize_text_field( (string) ( $input['start_on'] ?? '' ) );
		$end = $locked ? $current['end_on'] : sanitize_text_field( (string) ( $input['end_on'] ?? '' ) );
		// Use the saved occurrence cursor only while the schedule identity remains unchanged.
		// Changing a draft schedule previews that new schedule, never stale persisted dates.
		$unchanged = null !== $current
			&& $frequency === (string) $current['frequency']
			&& $interval === (int) $current['interval_count']
			&& $start === (string) $current['start_on']
			&& (string) $end === (string) ( $current['end_on'] ?? '' );
		$use_cursor = $locked || $unchanged;
		$next = $use_cursor && null !== $current ? $current['next_occurrence_on'] : null;
		$dates = Recurrence::preview_dates( $frequency, $interval, $start, $end, $next, $use_cursor, current_time( 'Y-m-d' ) );
		if ( null === $dates ) {
			wp_send_json_error( [ 'message' => __( 'The Recurring Work rule could not be saved. Check the supplied values and whether its schedule is already locked by history.', 'core-blueprint-work' ) ], 400 );
		}
		wp_send_json_success( [ 'dates' => array_map( static fn( string $date ): array => [
			'iso' => $date,
			'label' => mysql2date( get_option( 'date_format' ), $date . ' 12:00:00' ),
		], $dates ) ] );
	}

	/**
	 * @param array<string,mixed>|null $current
	 * @return array<string,mixed>|null
	 */
	private static function input( ?array $current = null ): ?array {
		$input = isset( $_POST['recurrence'] ) && is_array( $_POST['recurrence'] )
			? wp_unslash( $_POST['recurrence'] )
			: [];
		// Saving a rule never changes its activation state. Use the dedicated governed toggle.
		$input['is_active'] = null === $current ? false : ! empty( $current['is_active'] );

		if ( array_key_exists( 'customer_object_id', $input ) ) {
			$identifier = is_scalar( $input['customer_object_id'] )
				? sanitize_text_field( (string) $input['customer_object_id'] )
				: '';
			$reference = CRMCustomers::reference( $identifier );
			if ( is_wp_error( $reference ) ) {
				return null;
			}
			$input['customer_provider'] = null === $reference ? '' : $reference['provider'];
			$input['customer_type']     = null === $reference ? '' : $reference['type'];
			$input['customer_id']       = null === $reference ? '' : $reference['id'];
			unset( $input['customer_object_id'] );
		} elseif ( null !== $current ) {
			$input['customer_provider'] = (string) ( $current['customer_provider'] ?? '' );
			$input['customer_type']     = (string) ( $current['customer_type'] ?? '' );
			$input['customer_id']       = (string) ( $current['customer_id'] ?? '' );
		} else {
			$input['customer_provider'] = '';
			$input['customer_type']     = '';
			$input['customer_id']       = '';
		}
		return $input;
	}

	private static function guard( string $nonce_action ): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Work.', 'core-blueprint-work' ) );
		}
		check_admin_referer( $nonce_action );
	}

	/** @param array<string,int|string> $extra */
	private static function redirect( string $notice, array $extra = [] ): never {
		wp_safe_redirect( add_query_arg(
			[ 'page' => Menu::RECURRENCE_SLUG, 'cb-work-notice' => sanitize_key( $notice ), ...$extra ],
			admin_url( 'admin.php' )
		) );
		exit;
	}
}

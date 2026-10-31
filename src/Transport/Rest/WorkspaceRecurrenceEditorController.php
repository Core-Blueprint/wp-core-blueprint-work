<?php
declare(strict_types=1);

namespace CB\Work\Transport\Rest;

use CB\Work\Admin\Recurrence;
use CB\Work\Capabilities;
use CB\Work\Database\Schema;
use CB\Work\Domain\RecurrenceSchedule;
use CB\Work\Integration\CRMCustomers;
use CB\Work\Repository\RecurrenceOccurrences;
use CB\Work\Repository\RecurrenceRules;

defined( 'ABSPATH' ) || exit;

/**
 * M3-B4-B: private, read-only rule editor transport and schedule preview.
 *
 * All writes and lifecycle actions still belong to the canonical native
 * RecurrenceActions/RecurrenceRules flow until versioned edit parity is proven.
 * This intentionally has no scheduler, duplicate recurrence state or writer.
 */
final class WorkspaceRecurrenceEditorController {
	public const NAMESPACE = 'cb-work/v1';
	public const DETAIL_ROUTE = '/workspace/recurring/rules/(?P<id>[1-9][0-9]*)';
	public const PREVIEW_ROUTE = '/workspace/recurring/preview';

	public static function init(): void {
		add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
	}

	public static function register_routes(): void {
		$permission = [ self::class, 'can_manage' ];
		register_rest_route( self::NAMESPACE, self::DETAIL_ROUTE, [
			'methods' => \WP_REST_Server::READABLE,
			'callback' => [ self::class, 'detail' ],
			'permission_callback' => $permission,
			'args' => [ 'id' => [ 'type' => 'integer', 'minimum' => 1, 'required' => true ] ],
		] );
		register_rest_route( self::NAMESPACE, self::PREVIEW_ROUTE, [
			'methods' => \WP_REST_Server::CREATABLE,
			'callback' => [ self::class, 'preview' ],
			'permission_callback' => $permission,
		] );
	}

	public static function can_manage( \WP_REST_Request $request ): true|\WP_Error {
		unset( $request );
		if (
			current_user_can( Capabilities::MANAGE )
			&& get_current_user_id() > 0
			&& function_exists( 'cb_work_runtime_ready' )
			&& \cb_work_runtime_ready()
			&& defined( 'CB_WORK_SCHEMA_VERSION' )
			&& CB_WORK_SCHEMA_VERSION === (string) get_option( Schema::OPTION, '0' )
		) {
			return true;
		}
		return new \WP_Error(
			'rest_forbidden',
			__( 'You do not have permission to manage Work.', 'core-blueprint-work' ),
			[ 'status' => rest_authorization_required_code() ]
		);
	}

	public static function detail( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$allowed = self::can_manage( $request );
		if ( true !== $allowed ) {
			return $allowed;
		}
		$id = self::positive_id( $request->get_param( 'id' ) );
		if ( null === $id ) {
			return self::error( 400 );
		}
		$rule = RecurrenceRules::get( $id );
		if ( ! is_array( $rule ) || ! empty( $rule['is_trashed'] ) ) {
			return self::error( 404 );
		}
		$customer = CRMCustomers::selected(
			(string) ( $rule['customer_provider'] ?? '' ),
			(string) ( $rule['customer_type'] ?? '' ),
			(string) ( $rule['customer_id'] ?? '' )
		);
		// A linked customer which cannot be resolved is not silently discarded.
		// The editor must not accidentally turn a private customer rule into
		// a different association on save.
		$has_customer = '' !== (string) ( $rule['customer_provider'] ?? '' );
		if ( $has_customer && null === $customer ) {
			return self::error( 409 );
		}
		return rest_ensure_response( [
			'id' => $id,
			'title' => (string) $rule['title'],
			'description' => (string) $rule['description'],
			'work_context' => (string) $rule['work_context'],
			'customer' => $customer,
			'project_id' => (int) ( $rule['project_id'] ?? 0 ),
			'service_id' => (int) ( $rule['service_id'] ?? 0 ),
			'work_type_id' => (int) ( $rule['work_type_id'] ?? 0 ),
			'priority' => (string) $rule['priority'],
			'estimated_minutes' => (int) $rule['estimated_minutes'],
			'billing_disposition' => (string) $rule['billing_disposition'],
			'assigned_user_ids' => array_values( array_map( 'intval', (array) $rule['assigned_user_ids'] ) ),
			'frequency' => (string) $rule['frequency'],
			'interval_count' => (int) $rule['interval_count'],
			'start_on' => (string) $rule['start_on'],
			'end_on' => $rule['end_on'],
			'next_occurrence_on' => $rule['next_occurrence_on'],
			'create_ahead_days' => (int) $rule['create_ahead_days'],
			'due_offset_days' => (int) $rule['due_offset_days'],
			'is_active' => (bool) $rule['is_active'],
			'schedule_locked' => RecurrenceOccurrences::count_for_rule( $id ) > 0,
			'updated_at' => (string) $rule['updated_at'],
			'revision' => (int) $rule['revision'],
		] );
	}

	/** POST is used only for a non-mutating preview of an unsaved form. */
	public static function preview( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$allowed = self::can_manage( $request );
		if ( true !== $allowed ) {
			return $allowed;
		}
		$input = $request->get_json_params();
		if ( ! is_array( $input ) ) {
			return self::error( 400 );
		}
		$id = self::nonnegative_id( $input['rule_id'] ?? 0 );
		$frequency = $input['frequency'] ?? null;
		$interval = self::positive_id( $input['interval_count'] ?? null );
		$start = $input['start_on'] ?? null;
		$end = $input['end_on'] ?? null;
		if (
			null === $id
			|| ! is_string( $frequency )
			|| null === $interval
			|| ! is_string( $start )
			|| ( null !== $end && ! is_string( $end ) )
		) {
			return self::error( 400 );
		}
		$schedule = RecurrenceSchedule::normalize( $frequency, $interval, $start, $end );
		if ( null === $schedule ) {
			return self::error( 400 );
		}
		$current = $id > 0 ? RecurrenceRules::get( $id ) : null;
		if ( $id > 0 && ( ! is_array( $current ) || ! empty( $current['is_trashed'] ) ) ) {
			return self::error( 404 );
		}
		$locked = null !== $current && RecurrenceOccurrences::count_for_rule( $id ) > 0;
		$unchanged = null !== $current
			&& $schedule['frequency'] === (string) $current['frequency']
			&& $schedule['interval_count'] === (int) $current['interval_count']
			&& $schedule['start_on'] === (string) $current['start_on']
			&& $schedule['end_on'] === $current['end_on'];
		if ( $locked && ! $unchanged ) {
			return self::error( 409 );
		}
		$use_cursor = $locked || $unchanged;
		$cursor = $use_cursor && null !== $current ? $current['next_occurrence_on'] : null;
		$dates = Recurrence::preview_dates(
			$schedule['frequency'],
			$schedule['interval_count'],
			$schedule['start_on'],
			$schedule['end_on'],
			$cursor,
			$use_cursor,
			current_time( 'Y-m-d' )
		);
		if ( null === $dates ) {
			return self::error( 400 );
		}
		return rest_ensure_response( [
			'dates' => array_map( static fn( string $date ): array => [
				'iso' => $date,
				'label' => mysql2date( get_option( 'date_format' ), $date . ' 12:00:00' ),
			], $dates ),
			'schedule_locked' => $locked,
		] );
	}

	private static function positive_id( mixed $value ): ?int {
		if ( ! is_int( $value ) && ! is_string( $value ) ) {
			return null;
		}
		if ( ! ctype_digit( (string) $value ) || (string) (int) $value !== (string) $value || (int) $value < 1 ) {
			return null;
		}
		return (int) $value;
	}

	private static function nonnegative_id( mixed $value ): ?int {
		return 0 === $value || '0' === $value ? 0 : self::positive_id( $value );
	}

	private static function error( int $status ): \WP_Error {
		return new \WP_Error(
			'work_recurrence_editor_unavailable',
			__( 'The Recurring Work rule could not be saved. Check the supplied values and whether its schedule is already locked by history.', 'core-blueprint-work' ),
			[ 'status' => $status ]
		);
	}
}

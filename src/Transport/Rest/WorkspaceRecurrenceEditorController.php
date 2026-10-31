<?php
declare(strict_types=1);

namespace CB\Work\Transport\Rest;

use CB\Work\Admin\Recurrence;
use CB\Work\Domain\BillingDisposition;
use CB\Work\Domain\WorkContext;
use CB\Work\Domain\WorkItemPriority;
use CB\Work\Governance\Events;
use CoreBlueprint\Core\Governance\Audit;
use CB\Work\Capabilities;
use CB\Work\Database\Schema;
use CB\Work\Domain\RecurrenceSchedule;
use CB\Work\Integration\CRMCustomers;
use CB\Work\Repository\RecurrenceOccurrences;
use CB\Work\Repository\RecurrenceRules;
use CB\Work\Repository\Projects;
use CB\Work\Repository\WorkTypes;
use CB\Work\PublicApi\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Recurring Work REST transport: private rule detail, forecast and canonical
 * mutations. RecurrenceRules remains the sole transactional domain writer.
 */
final class WorkspaceRecurrenceEditorController {
	public const NAMESPACE = 'cb-work/v1';
	public const DETAIL_ROUTE = '/workspace/recurring/rules/(?P<id>[1-9][0-9]*)';
	public const PREVIEW_ROUTE = '/workspace/recurring/preview';
	public const CREATE_ROUTE = '/workspace/recurring/rules';
	public const OPTIONS_ROUTE = '/workspace/recurring/options';
	public const LIFECYCLE_ROUTE = '/workspace/recurring/rules/(?P<id>[1-9][0-9]*)/lifecycle';

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
		register_rest_route( self::NAMESPACE, self::OPTIONS_ROUTE, [
			'methods' => \WP_REST_Server::READABLE,
			'callback' => [ self::class, 'options' ],
			'permission_callback' => $permission,
		] );
		register_rest_route( self::NAMESPACE, self::CREATE_ROUTE, [
			'methods' => \WP_REST_Server::CREATABLE,
			'callback' => [ self::class, 'create' ],
			'permission_callback' => $permission,
		] );
		register_rest_route( self::NAMESPACE, self::DETAIL_ROUTE, [
			'methods' => \WP_REST_Server::EDITABLE,
			'callback' => [ self::class, 'update' ],
			'permission_callback' => $permission,
			'args' => [ 'id' => [ 'type' => 'integer', 'minimum' => 1, 'required' => true ] ],
		] );
		register_rest_route( self::NAMESPACE, self::LIFECYCLE_ROUTE, [
			'methods' => \WP_REST_Server::CREATABLE,
			'callback' => [ self::class, 'lifecycle' ],
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
			'assignees' => self::selected_assignees( (array) $rule['assigned_user_ids'] ),
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



	/** The Base Object Picker owns UI; Work supplies trusted selected data. */
	private static function selected_assignees( array $ids ): array {
		$selected = [];
		foreach ( $ids as $raw_id ) {
			$user = get_userdata( (int) $raw_id );
			if ( ! $user ) { continue; }
			$selected[] = [
				'id' => (int) $user->ID,
				'label' => (string) $user->display_name,
				'meta' => (string) $user->user_login,
			];
		}
		return $selected;
	}

	/** Bounded catalogs, with canonical single-record fallback for linked items. */
	public static function options( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$allowed = self::can_manage( $request );
		if ( true !== $allowed ) { return $allowed; }
		$id = self::nonnegative_id( $request->get_param( 'rule_id' ) ?? 0 );
		if ( null === $id ) { return self::error( 400 ); }
		$rule = $id > 0 ? RecurrenceRules::get( $id ) : null;
		if ( $id > 0 && ( null === $rule || ! empty( $rule['is_trashed'] ) ) ) { return self::error( 404 ); }
		$projects = Projects::all( 500 );
		$services = Services::all( 250 );
		$types = WorkTypes::all();
		if ( $rule ) {
			$linked = [
				'project_id' => [ &$projects, [ Projects::class, 'get' ] ],
				'service_id' => [ &$services, [ Services::class, 'get' ] ],
				'work_type_id' => [ &$types, [ WorkTypes::class, 'get' ] ],
			];
			foreach ( $linked as $field => &$source ) {
				$selected = (int) ( $rule[$field] ?? 0 );
				if ( $selected > 0 && ! in_array( $selected, array_map( 'intval', array_column( $source[0], 'id' ) ), true ) ) {
					$item = call_user_func( $source[1], $selected );
					if ( is_array( $item ) ) { $source[0][] = $item; }
				}
			}
			unset( $source );
		}
		return rest_ensure_response( [
			'projects' => array_map( static fn( array $r ): array => [
				'id' => (int) $r['id'], 'title' => (string) $r['title'],
				'work_context' => (string) ( $r['work_context'] ?? '' ),
			], $projects ),
			'services' => array_map( static fn( array $r ): array => [
				'id' => (int) $r['id'], 'title' => (string) $r['title'],
			], $services ),
			'work_types' => array_map( static fn( array $r ): array => [
				'id' => (int) $r['id'], 'title' => (string) $r['label'],
			], $types ),
			'picker_nonce' => wp_create_nonce( 'cb_work_object_picker' ),
			'customer_enabled' => CRMCustomers::available(),
		] );
	}

	/** Strict transport allowlist. All business invariants are rechecked by RecurrenceRules. */
	private static function fields( mixed $input, bool $creating ): array|\WP_Error {
		if ( ! is_array( $input ) || array_is_list( $input ) ) { return self::error( 400 ); }
		$strings = [ 'title', 'description', 'work_context', 'customer_object_id', 'priority', 'billing_disposition', 'frequency', 'start_on', 'end_on' ];
		$numbers = [ 'project_id', 'service_id', 'work_type_id', 'estimated_minutes', 'interval_count', 'create_ahead_days', 'due_offset_days' ];
		$allowed = array_merge( $strings, $numbers, [ 'assigned_user_ids' ] );
		if ( $creating && count( array_intersect( [ 'title', 'work_context', 'frequency', 'interval_count', 'start_on' ], array_keys( $input ) ) ) !== 5 ) {
			return self::error( 400 );
		}
		$out = [];
		foreach ( $input as $key => $value ) {
			if ( ! in_array( $key, $allowed, true ) ) { return self::error( 400 ); }
			if ( in_array( $key, $strings, true ) ) {
				if ( null === $value && 'end_on' === $key ) { $out[$key] = null; continue; }
				if ( ! is_string( $value ) || strlen( $value ) > ( 'description' === $key ? 65535 : 2048 ) ) { return self::error( 400 ); }
				$out[$key] = $value;
			} elseif ( in_array( $key, $numbers, true ) ) {
				if ( ! is_int( $value ) || $value < 0 || $value > 2147483647 ) { return self::error( 400 ); }
				if ( 'interval_count' === $key && ( $value < 1 || $value > 999 ) ) { return self::error( 400 ); }
				if ( in_array( $key, [ 'create_ahead_days', 'due_offset_days' ], true ) && $value > 3650 ) { return self::error( 400 ); }
				$out[$key] = $value;
			} else {
				if ( ! is_array( $value ) || count( $value ) > 100 ) { return self::error( 400 ); }
				$ids = [];
				foreach ( $value as $id ) {
					if ( ! is_int( $id ) || $id < 1 || in_array( $id, $ids, true ) ) { return self::error( 400 ); }
					$ids[] = $id;
				}
				$out[$key] = $ids;
			}
		}
		if ( isset( $out['title'] ) ) {
			$out['title'] = sanitize_text_field( $out['title'] );
			if ( '' === trim( $out['title'] ) || strlen( $out['title'] ) > 200 ) { return self::error( 400 ); }
		}
		if ( isset( $out['description'] ) ) { $out['description'] = wp_kses_post( $out['description'] ); }
		if ( isset( $out['work_context'] ) && ! in_array( $out['work_context'], [ WorkContext::INTERNAL, WorkContext::CUSTOMER ], true ) ) { return self::error( 400 ); }
		if ( isset( $out['priority'] ) && ! WorkItemPriority::is_valid( $out['priority'] ) ) { return self::error( 400 ); }
		if ( isset( $out['billing_disposition'] ) && '' !== $out['billing_disposition'] && ! BillingDisposition::is_valid( $out['billing_disposition'] ) ) { return self::error( 400 ); }
		if ( isset( $out['frequency'] ) && ! in_array( $out['frequency'], RecurrenceSchedule::frequencies(), true ) ) { return self::error( 400 ); }
		foreach ( [ 'start_on', 'end_on' ] as $date_key ) {
			if ( isset( $out[$date_key] ) && '' !== $out[$date_key] ) {
				$date = $out[$date_key];
				if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $date )
					|| ! checkdate( (int) substr( $date, 5, 2 ), (int) substr( $date, 8, 2 ), (int) substr( $date, 0, 4 ) ) ) {
					return self::error( 400 );
				}
			}
		}
		if ( array_key_exists( 'customer_object_id', $out ) ) {
			$reference = CRMCustomers::reference( $out['customer_object_id'] );
			if ( is_wp_error( $reference ) ) { return self::error( 400 ); }
			$out['customer_provider'] = $reference['provider'] ?? '';
			$out['customer_type'] = $reference['type'] ?? '';
			$out['customer_id'] = $reference['id'] ?? '';
			unset( $out['customer_object_id'] );
		}
		$out['updated_by'] = get_current_user_id();
		if ( $creating ) {
			$out['created_by'] = get_current_user_id();
			$out['is_active'] = false;
			$out['customer_provider'] ??= '';
			$out['customer_type'] ??= '';
			$out['customer_id'] ??= '';
		}
		return $out;
	}

	public static function create( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$allowed = self::can_manage( $request );
		if ( true !== $allowed ) { return $allowed; }
		$body = $request->get_json_params();
		if ( ! is_array( $body ) || count( $body ) !== 1 || ! array_key_exists( 'fields', $body ) ) { return self::error( 400 ); }
		$fields = self::fields( $body['fields'], true );
		if ( is_wp_error( $fields ) ) { return $fields; }
		$id = RecurrenceRules::create( $fields );
		if ( $id < 1 ) { return self::error( 409 ); }
		Audit::record( Events::RECURRENCE_RULE_CREATED, 'notice', [ 'rule_id' => $id, 'source' => 'workspace' ] );
		$rule = RecurrenceRules::get( $id );
		$response = rest_ensure_response( [ 'id' => $id, 'revision' => (int) ( $rule['revision'] ?? 1 ), 'is_active' => false ] );
		$response->set_status( 201 );
		return $response;
	}

	public static function update( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$allowed = self::can_manage( $request );
		if ( true !== $allowed ) { return $allowed; }
		$id = self::positive_id( $request->get_param( 'id' ) );
		$body = $request->get_json_params();
		if ( null === $id || ! is_array( $body ) || count( $body ) !== 2
			|| ! array_key_exists( 'revision', $body ) || ! array_key_exists( 'fields', $body ) ) { return self::error( 400 ); }
		$revision = self::positive_id( $body['revision'] );
		if ( null === $revision ) { return self::error( 400 ); }
		$current = RecurrenceRules::get( $id );
		if ( null === $current || ! empty( $current['is_trashed'] ) ) { return self::error( 404 ); }
		$fields = self::fields( $body['fields'], false );
		if ( is_wp_error( $fields ) ) { return $fields; }
		$result = RecurrenceRules::update_if_revision( $id, $fields, $revision );
		if ( is_wp_error( $result ) ) { return $result; }
		Audit::record( Events::RECURRENCE_RULE_UPDATED, 'notice', [ 'rule_id' => $id, 'source' => 'workspace' ] );
		$saved = RecurrenceRules::get( $id );
		return rest_ensure_response( [ 'id' => $id, 'revision' => (int) $saved['revision'] ] );
	}

	public static function lifecycle( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$allowed = self::can_manage( $request );
		if ( true !== $allowed ) { return $allowed; }
		$id = self::positive_id( $request->get_param( 'id' ) );
		$body = $request->get_json_params();
		if ( null === $id || ! is_array( $body ) || ! isset( $body['action'], $body['revision'] )
			|| ! is_string( $body['action'] ) || count( $body ) > 3 ) { return self::error( 400 ); }
		$revision = self::positive_id( $body['revision'] );
		if ( null === $revision ) { return self::error( 400 ); }
		$rule = RecurrenceRules::get( $id );
		if ( null === $rule ) { return self::error( 404 ); }
		$actor = get_current_user_id();
		$meta = [ 'rule_id' => $id, 'actor_user_id' => $actor, 'source' => 'workspace' ];
		switch ( $body['action'] ) {
			case 'activate':
			case 'deactivate':
				if ( ! empty( $rule['is_trashed'] ) || array_key_exists( 'confirmed', $body ) ) { return self::error( 409 ); }
				$active = 'activate' === $body['action'];
				$result = RecurrenceRules::update_if_revision( $id, [ 'is_active' => $active, 'updated_by' => $actor ], $revision );
				if ( is_wp_error( $result ) ) { return $result; }
				Audit::record( Events::RECURRENCE_RULE_STATUS_CHANGED, 'notice', $meta + [ 'active' => $active ] );
				break;
			case 'trash':
				if ( ! RecurrenceRules::trash( $id, $actor, $revision ) ) { return self::error( 409 ); }
				Audit::record( Events::RECURRENCE_RULE_TRASHED, 'notice', $meta );
				break;
			case 'restore':
				if ( ! RecurrenceRules::restore( $id, $actor, $revision ) ) { return self::error( 409 ); }
				Audit::record( Events::RECURRENCE_RULE_RESTORED, 'notice', $meta );
				break;
			case 'delete_permanently':
				if ( count( $body ) !== 3 || true !== ( $body['confirmed'] ?? null ) ) { return self::error( 400 ); }
				if ( ! RecurrenceRules::delete_permanently( $id, $revision ) ) { return self::error( 409 ); }
				Audit::record( Events::RECURRENCE_RULE_DELETED, 'notice', $meta );
				return rest_ensure_response( [ 'id' => $id, 'deleted' => true ] );
			default:
				return self::error( 400 );
		}
		$saved = RecurrenceRules::get( $id );
		return rest_ensure_response( [ 'id' => $id, 'revision' => (int) $saved['revision'],
			'is_active' => (bool) $saved['is_active'], 'is_trashed' => (bool) $saved['is_trashed'] ] );
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

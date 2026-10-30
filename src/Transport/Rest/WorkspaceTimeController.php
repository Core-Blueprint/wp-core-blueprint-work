<?php
declare(strict_types=1);

namespace CB\Work\Transport\Rest;

use CB\Work\Capabilities;
use CB\Work\Database\Schema;
use CB\Work\Governance\Events;
use CB\Work\Repository\TimeEntries;
use CB\Work\Repository\Timers;
use CB\Work\Repository\WorkItems;
use CB\Work\Time\Access;
use CB\Work\Time\WorkItemPickerSearch;
use CoreBlueprint\Core\Governance\Audit;

defined( 'ABSPATH' ) || exit;

/**
 * M3-C1: manager's own timer and bounded recent entries inside Work.
 *
 * No parallel timer store or new entry engine. The native Time workspace
 * retains manual creation, CAS edits, bulk editing and tracker-only access.
 */
final class WorkspaceTimeController {
	public const NAMESPACE = 'cb-work/v1';
	public const ROUTE = '/workspace/time';
	public const SEARCH_ROUTE = '/workspace/time/work-items';
	public const TIMER_ROUTE = '/workspace/time/timer';

	public static function init(): void {
		add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
	}

	public static function register_routes(): void {
		$permission = [ self::class, 'can_manage' ];
		register_rest_route( self::NAMESPACE, self::ROUTE, [
			'methods' => \WP_REST_Server::READABLE,
			'callback' => [ self::class, 'read' ],
			'permission_callback' => $permission,
			'args' => [
				'page' => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 100000, 'default' => 1 ],
			],
		] );
		register_rest_route( self::NAMESPACE, self::SEARCH_ROUTE, [
			'methods' => \WP_REST_Server::READABLE,
			'callback' => [ self::class, 'search_items' ],
			'permission_callback' => $permission,
			'args' => [
				'search' => [ 'type' => 'string', 'maxLength' => 80, 'required' => true ],
			],
		] );
		register_rest_route( self::NAMESPACE, self::TIMER_ROUTE, [
			'methods' => \WP_REST_Server::CREATABLE,
			'callback' => [ self::class, 'timer' ],
			'permission_callback' => $permission,
			'args' => [
				'operation' => [ 'type' => 'string', 'enum' => [ 'start', 'stop' ], 'required' => true ],
				'work_item_id' => [ 'type' => 'integer', 'minimum' => 1 ],
				'note' => [ 'type' => 'string', 'maxLength' => 4000, 'default' => '' ],
			],
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
		return new \WP_Error( 'rest_forbidden',
			__( 'You do not have permission to track Work time.', 'core-blueprint-work' ),
			[ 'status' => rest_authorization_required_code() ] );
	}

	private static function actor( \WP_REST_Request $request ): int|\WP_Error {
		$allowed = self::can_manage( $request );
		return true === $allowed ? get_current_user_id() : $allowed;
	}

	/** @return array<string,mixed>|null */
	private static function timer_payload( int $actor ): ?array {
		$running = Timers::active_for_user( $actor );
		if ( ! is_array( $running ) ) {
			return null;
		}
		$item_id = (int) $running['work_item_id'];
		$item = WorkItems::get( $item_id );
		return [
			'entry_id' => (int) $running['time_entry_id'],
			'work_item_id' => $item_id,
			'title' => is_array( $item ) ? (string) $item['title'] : '',
			'started_at' => (string) $running['started_at'],
			'note' => (string) $running['note'],
		];
	}

	public static function read( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$actor = self::actor( $request );
		if ( is_wp_error( $actor ) ) { return $actor; }
		$raw_page = $request->get_param( 'page' ) ?? 1;
		if ( ! ( is_int( $raw_page ) || is_string( $raw_page ) )
			|| ! ctype_digit( (string) $raw_page ) || (int) $raw_page <= 0 ) {
			return new \WP_Error( 'work_time_input_invalid', '', [ 'status' => 400 ] );
		}
		$entries = TimeEntries::query_completed( [
			'user_id' => $actor, 'page' => min( 100000, (int) $raw_page ),
			'per_page' => 10, 'sort' => 'newest',
		] );
		$items = [];
		foreach ( $entries['items'] as $entry ) {
			// Defence in depth: the owner-filtered SQL result must never
			// disclose any other actor's entries to this workspace.
			if ( (int) $entry['user_id'] !== $actor
				|| ! Access::can_view_entry( $entry ) ) {
				return new \WP_Error( 'work_time_data_invalid', '', [ 'status' => 503 ] );
			}
			$item = WorkItems::get( (int) $entry['work_item_id'] );
			$items[] = [
				'id' => (int) $entry['id'],
				'work_item_id' => (int) $entry['work_item_id'],
				'title' => is_array( $item ) ? (string) $item['title'] : '',
				'started_at' => (string) $entry['started_at'],
				'ended_at' => (string) $entry['ended_at'],
				'duration_seconds' => (int) $entry['duration_seconds'],
				'source' => (string) $entry['entry_source'],
				'revision' => (int) $entry['revision'],
			];
		}
		return rest_ensure_response( [
			'actor_id' => $actor,
			'timer' => self::timer_payload( $actor ),
			'entries' => $items,
			'page' => (int) $entries['page'],
			'pages' => (int) $entries['pages'],
			'total' => (int) $entries['total'],
		] );
	}

	public static function search_items( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$actor = self::actor( $request );
		if ( is_wp_error( $actor ) ) { return $actor; }
		$raw = $request->get_param( 'search' );
		if ( ! is_string( $raw ) || strlen( $raw ) > 80 ) {
			return new \WP_Error( 'work_time_input_invalid', '', [ 'status' => 400 ] );
		}
		return rest_ensure_response( [
			'items' => WorkItemPickerSearch::results( sanitize_text_field( $raw ), $actor, true ),
		] );
	}

	public static function timer( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$actor = self::actor( $request );
		if ( is_wp_error( $actor ) ) { return $actor; }
		$operation = $request->get_param( 'operation' );
		if ( ! is_string( $operation ) || ! in_array( $operation, [ 'start', 'stop' ], true ) ) {
			return new \WP_Error( 'work_time_input_invalid', '', [ 'status' => 400 ] );
		}
		if ( 'stop' === $operation ) {
			if ( ! Access::can_stop_user_timer( $actor ) ) {
				return new \WP_Error( 'rest_forbidden', '', [ 'status' => 403 ] );
			}
			$entry = Timers::stop( $actor, $actor );
			if ( ! is_array( $entry ) ) {
				return new \WP_Error( 'work_timer_stop_failed', '', [ 'status' => 409 ] );
			}
			Audit::record( Events::TIMER_STOPPED, 'notice', [
				'time_entry_id' => (int) $entry['id'],
				'work_item_id' => (int) $entry['work_item_id'],
				'user_id' => (int) $entry['user_id'],
				'duration_seconds' => (int) $entry['duration_seconds'],
			] );
			return rest_ensure_response( [ 'stopped' => true, 'entry_id' => (int) $entry['id'] ] );
		}
		$raw_id = $request->get_param( 'work_item_id' );
		$note = $request->get_param( 'note' ) ?? '';
		if ( ! ( is_int( $raw_id ) || is_string( $raw_id ) )
			|| ! ctype_digit( (string) $raw_id ) || (int) $raw_id <= 0
			|| ! is_string( $note ) || strlen( $note ) > 4000 ) {
			return new \WP_Error( 'work_time_input_invalid', '', [ 'status' => 400 ] );
		}
		$id = (int) $raw_id;
		if ( ! Access::can_track_work_item( $id, $actor ) ) {
			return new \WP_Error( 'rest_forbidden', '', [ 'status' => 403 ] );
		}
		$entry_id = Timers::start( $id, $actor, $note, $actor );
		if ( $entry_id <= 0 ) {
			return new \WP_Error( 'work_timer_start_failed', '', [ 'status' => 409 ] );
		}
		Audit::record( Events::TIMER_STARTED, 'notice', [
			'time_entry_id' => $entry_id,
			'work_item_id' => $id,
			'user_id' => $actor,
		] );
		return rest_ensure_response( [ 'started' => true, 'entry_id' => $entry_id ] );
	}

	private function __construct() {}
}

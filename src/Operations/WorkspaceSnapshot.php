<?php
declare(strict_types=1);

namespace CB\Work\Operations;

use CB\Work\Capabilities;
use CB\Work\PublicApi\ServiceOperations;
use CB\Work\Repository\RecurrenceRules;
use CB\Work\Repository\Timers;
use CB\Work\ServiceOperations\Engagements;

defined( 'ABSPATH' ) || exit;

/**
 * M3-A: bounded, actor-scoped read projection for the shared Work shell.
 *
 * Never owns service, scheduling or timer state. Each value comes from the
 * existing canonical domain reader; no background jobs or mutations run here.
 */
final class WorkspaceSnapshot {
	/** @return array<string,mixed>|\WP_Error */
	public static function read( int $actor_id ): array|\WP_Error {
		if (
			$actor_id <= 0
			|| $actor_id !== get_current_user_id()
			|| ! current_user_can( Capabilities::MANAGE )
		) {
			return new \WP_Error( 'rest_forbidden', '', [ 'status' => rest_authorization_required_code() ] );
		}
		if ( ! Engagements::ready() ) {
			return new \WP_Error( 'work_operations_unavailable', '', [ 'status' => 503 ] );
		}

		// The existing indexed Engagements reader does the COUNT independently
		// of the requested page; hydrate no more than one row per query.
		$engagements = ServiceOperations::search( [ 'page' => 1, 'per_page' => 1 ] );
		$active = ServiceOperations::search( [ 'page' => 1, 'per_page' => 1, 'active' => 1 ] );
		if ( is_wp_error( $engagements ) || is_wp_error( $active ) ) {
			return new \WP_Error( 'work_operations_unavailable', '', [ 'status' => 503 ] );
		}

		// search_page() is the existing bounded/counted recurrence read path.
		// Never call the scheduler or advance its occurrence cursor.
		$rules = RecurrenceRules::search_page();
		$active_rules = RecurrenceRules::search_page( [ 'status' => 'active' ] );
		$timer = Timers::active_for_user( $actor_id );

		return [
			'services' => [
				'total'  => max( 0, (int) ( $engagements['total'] ?? 0 ) ),
				'active' => max( 0, (int) ( $active['total'] ?? 0 ) ),
			],
			'recurrence' => [
				'total'  => max( 0, (int) ( $rules['total'] ?? 0 ) ),
				'active' => max( 0, (int) ( $active_rules['total'] ?? 0 ) ),
			],
			'time' => [
				'running'      => is_array( $timer ),
				'work_item_id' => is_array( $timer ) ? max( 0, (int) ( $timer['work_item_id'] ?? 0 ) ) : 0,
			],
		];
	}

	private function __construct() {}
}

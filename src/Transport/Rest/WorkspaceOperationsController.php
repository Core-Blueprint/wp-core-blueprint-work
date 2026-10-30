<?php
declare(strict_types=1);

namespace CB\Work\Transport\Rest;

use CB\Work\Capabilities;
use CB\Work\Operations\WorkspaceSnapshot;

defined( 'ABSPATH' ) || exit;

/**
 * M3-A: manager-only, read-only operational Work shell state.
 * No alternate REST mutations, cron runners or service-state storage.
 */
final class WorkspaceOperationsController {
	public const NAMESPACE = 'cb-work/v1';
	public const ROUTE = '/workspace/operations';

	public static function init(): void {
		add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
	}

	public static function register_routes(): void {
		register_rest_route( self::NAMESPACE, self::ROUTE, [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ self::class, 'read' ],
			'permission_callback' => [ self::class, 'can_read' ],
		] );
	}

	public static function can_read( \WP_REST_Request $request ): bool|\WP_Error {
		unset( $request );
		if (
			current_user_can( Capabilities::MANAGE )
			&& get_current_user_id() > 0
			&& function_exists( 'cb_work_runtime_ready' )
			&& \cb_work_runtime_ready()
		) {
			return true;
		}
		return new \WP_Error(
			'rest_forbidden',
			__( 'You do not have permission to manage Work.', 'core-blueprint-work' ),
			[ 'status' => rest_authorization_required_code() ]
		);
	}

	public static function read( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$allowed = self::can_read( $request );
		if ( true !== $allowed ) {
			return $allowed;
		}
		$snapshot = WorkspaceSnapshot::read( get_current_user_id() );
		return is_wp_error( $snapshot ) ? $snapshot : rest_ensure_response( $snapshot );
	}

	private function __construct() {}
}

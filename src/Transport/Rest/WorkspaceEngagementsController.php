<?php
declare(strict_types=1);

namespace CB\Work\Transport\Rest;

use CB\Work\Capabilities;
use CB\Work\Content\ProjectMeta;
use CB\Work\PublicApi\Projects;
use CB\Work\PublicApi\Services;
use CB\Work\PublicApi\ServiceOperations;
use CB\Work\PublicApi\ServiceOperationsActions;

defined( 'ABSPATH' ) || exit;

/**
 * M3-B1: in-place Service Engagement list and basic create transport.
 * Calls canonical read/write APIs. The original Service Operations screen
 * retains recurring-rule linking, billing and advanced operational workflows.
 */
final class WorkspaceEngagementsController {
	public const NAMESPACE = 'cb-work/v1';
	public const ROUTE = '/workspace/engagements';
	private const MAX_PAGE_SIZE = 25;

	public static function init(): void {
		add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
	}

	public static function register_routes(): void {
		register_rest_route( self::NAMESPACE, self::ROUTE, [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ self::class, 'list_engagements' ],
				'permission_callback' => [ self::class, 'can_manage' ],
				'args'                => [
					'page' => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 100000, 'default' => 1 ],
					'search' => [ 'type' => 'string', 'maxLength' => 160, 'default' => '' ],
					'work_context' => [ 'type' => 'string', 'enum' => [ '', 'internal', 'customer' ], 'default' => '' ],
				],
			],
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ self::class, 'create_engagement' ],
				'permission_callback' => [ self::class, 'can_manage' ],
				'args'                => [
					'project_id' => [ 'type' => 'integer', 'minimum' => 1, 'required' => true ],
					'service_id' => [ 'type' => 'integer', 'minimum' => 1, 'required' => true ],
					'starts_on' => [ 'type' => 'string', 'required' => true ],
					'label' => [ 'type' => 'string', 'maxLength' => 190, 'default' => '' ],
				],
			],
		] );
	}

	public static function can_manage( \WP_REST_Request $request ): bool|\WP_Error {
		unset( $request );
		if (
			current_user_can( Capabilities::MANAGE )
			&& get_current_user_id() > 0
			&& function_exists( 'cb_work_runtime_ready' )
			&& \cb_work_runtime_ready()
		) {
			return true;
		}
		return new \WP_Error( 'rest_forbidden',
			__( 'You do not have permission to manage Work.', 'core-blueprint-work' ),
			[ 'status' => rest_authorization_required_code() ] );
	}

	public static function list_engagements( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$allowed = self::can_manage( $request );
		if ( true !== $allowed ) {
			return $allowed;
		}

		$page_raw = $request->get_param( 'page' ) ?? 1;
		$search_raw = $request->get_param( 'search' ) ?? '';
		$context_raw = $request->get_param( 'work_context' ) ?? '';
		if ( ! ( is_int( $page_raw ) || is_string( $page_raw ) )
			|| ! ctype_digit( (string) $page_raw ) || (int) $page_raw < 1
			|| ! is_string( $search_raw ) || ! is_string( $context_raw ) ) {
			return new \WP_Error( 'work_service_input_invalid', '', [ 'status' => 400 ] );
		}
		$page = min( 100000, (int) $page_raw );
		$search = substr( sanitize_text_field( $search_raw ), 0, 160 );
		$context = sanitize_key( $context_raw );
		if ( ! in_array( $context, [ '', 'internal', 'customer' ], true ) ) {
			return new \WP_Error( 'work_service_context_invalid', '', [ 'status' => 400 ] );
		}
		$result = ServiceOperations::search( [
			'page' => $page, 'per_page' => self::MAX_PAGE_SIZE,
			'search' => $search, 'work_context' => $context,
		] );
		if ( is_wp_error( $result ) ) {
			return new \WP_Error( 'work_service_query_failed',
				__( 'Work storage unavailable', 'core-blueprint-work' ), [ 'status' => 503 ] );
		}
		$items = [];
		foreach ( $result['items'] as $engagement ) {
			$project = Projects::get( (int) $engagement['project_id'] );
			$service = Services::get( (int) $engagement['service_id'] );
			$items[] = [
				'id'         => (int) $engagement['id'],
				'project'    => is_array( $project ) ? (string) $project['title'] : '',
				'service'    => is_array( $service ) ? (string) $service['title'] : '',
				'label'      => (string) $engagement['label'],
				'context'    => (string) $engagement['work_context'],
				'starts_on'  => (string) $engagement['starts_on'],
				'is_active'  => (bool) $engagement['is_active'],
			];
		}
		return rest_ensure_response( [
			'items' => $items,
			'total' => (int) $result['total'],
			'page'  => (int) $result['page'],
			'pages' => (int) $result['pages'],
		] );
	}

	public static function create_engagement( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$allowed = self::can_manage( $request );
		if ( true !== $allowed ) {
			return $allowed;
		}
		$project_raw = $request->get_param( 'project_id' );
		$service_raw = $request->get_param( 'service_id' );
		$date_raw = $request->get_param( 'starts_on' );
		$label_raw = $request->get_param( 'label' ) ?? '';
		if ( ! ( is_int( $project_raw ) || is_string( $project_raw ) )
			|| ! ( is_int( $service_raw ) || is_string( $service_raw ) )
			|| ! ctype_digit( (string) $project_raw )
			|| ! ctype_digit( (string) $service_raw )
			|| ! is_string( $date_raw ) || ! is_string( $label_raw ) ) {
			return new \WP_Error( 'work_service_input_invalid',
				__( 'The change could not be saved.', 'core-blueprint-work' ), [ 'status' => 400 ] );
		}
		$project_id = (int) $project_raw;
		$service_id = (int) $service_raw;
		$starts_on = sanitize_text_field( $date_raw );
		$label = sanitize_text_field( $label_raw );
		if ( $project_id <= 0 || $service_id <= 0
			|| '' === $starts_on || ProjectMeta::sanitize_date( $starts_on ) !== $starts_on
			|| strlen( $label ) > 190 ) {
			return new \WP_Error( 'work_service_input_invalid',
				__( 'The change could not be saved.', 'core-blueprint-work' ), [ 'status' => 400 ] );
		}
		// Project context and customer provenance are always read from the
		// server's canonical Project by Engagements::create(). No client
		// context, customer, billing or recurrence state is accepted here.
		$id = ServiceOperationsActions::create( [
			'project_id' => $project_id,
			'service_id' => $service_id,
			'starts_on'  => $starts_on,
			'label'      => $label,
		] );
		if ( is_wp_error( $id ) ) {
			return new \WP_Error( $id->get_error_code(),
				__( 'The change could not be saved.', 'core-blueprint-work' ), [ 'status' => 422 ] );
		}
		return new \WP_REST_Response( [ 'id' => $id ], 201 );
	}

	private function __construct() {}
}

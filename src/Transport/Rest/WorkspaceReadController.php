<?php
declare(strict_types=1);

namespace CB\Work\Transport\Rest;

use CB\Work\Capabilities;
use CB\Work\Content\PostTypes;
use CB\Work\Domain\WorkItemPriority;
use CB\Work\Domain\WorkItemStatus;
use CB\Work\PublicApi\Projects;
use CB\Work\PublicApi\Services;
use CB\Work\Query\WorkItemQuery;
use CB\Work\Repository\WorkItems;

defined( 'ABSPATH' ) || exit;

/**
 * UWE-03B.1: Work-owned, authenticated, read-only admin workspace transport.
 *
 * A manager-only API, never a D3 public/frontend resource. This transport
 * projects an explicit set of safe list fields from canonical Work readers.
 * It does not create a second Work Item query engine or register mutations.
 */
final class WorkspaceReadController {
	public const NAMESPACE = 'cb-work/v1';
	public const ROUTE = '/workspace/(?P<section>work-items|projects|services)';
	private const MAX_PAGE_SIZE = 100;
	private const POST_STATUSES = [ 'publish', 'draft', 'pending', 'private', 'future' ];

	public static function init(): void {
		add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
	}

	public static function register_routes(): void {
		register_rest_route( self::NAMESPACE, self::ROUTE, [
			'methods' => \WP_REST_Server::READABLE,
			'callback' => [ self::class, 'read' ],
			'permission_callback' => [ self::class, 'can_read' ],
			'args' => [
				'page' => [ 'type' => 'integer', 'minimum' => 1, 'default' => 1 ],
				'per_page' => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => self::MAX_PAGE_SIZE, 'default' => 25 ],
				'search' => [ 'type' => 'string', 'maxLength' => 160, 'default' => '' ],
				'sort' => [ 'type' => 'string', 'enum' => WorkItemQuery::sorts() ],
				'status' => [ 'type' => 'string', 'enum' => WorkItemStatus::all() ],
				'priority' => [ 'type' => 'string', 'enum' => WorkItemPriority::all() ],
				'project_id' => [ 'type' => 'integer', 'minimum' => 0 ],
			],
		] );
	}

	public static function can_read( \WP_REST_Request $request ): bool|\WP_Error {
		unset( $request );
		if ( current_user_can( Capabilities::MANAGE ) && function_exists( 'cb_work_runtime_ready' ) && \cb_work_runtime_ready() ) {
			return true;
		}
		return new \WP_Error(
			'rest_forbidden',
			__( 'You do not have permission to manage Work.', 'core-blueprint-work' ),
			[ 'status' => rest_authorization_required_code() ]
		);
	}

	public static function read( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		// Fail closed even if the callback is invoked without REST permission checks.
		$permission = self::can_read( $request );
		if ( true !== $permission ) {
			return $permission;
		}

		$section = (string) $request->get_param( 'section' );
		if ( ! in_array( $section, [ 'work-items', 'projects', 'services' ], true ) ) {
			return new \WP_Error( 'rest_no_route', '', [ 'status' => 404 ] );
		}
		$page = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = max( 1, min( self::MAX_PAGE_SIZE, (int) $request->get_param( 'per_page' ) ) );
		$search = sanitize_text_field( (string) ( $request->get_param( 'search' ) ?? '' ) );
		$sort_input = $request->get_param( 'sort' );
		$sort = is_string( $sort_input ) ? $sort_input : ( 'work-items' === $section ? WorkItemQuery::SORT_WORKLOAD : WorkItemQuery::SORT_TITLE );

		if ( 'work-items' === $section ) {
			$criteria = [
				'page' => $page,
				'per_page' => $per_page,
				'search' => $search,
				'sort' => $sort,
			];
			$status = $request->get_param( 'status' );
			$priority = $request->get_param( 'priority' );
			if ( is_string( $status ) && '' !== $status ) {
				$criteria['statuses'] = [ $status ];
			}
			if ( is_string( $priority ) && '' !== $priority ) {
				$criteria['priorities'] = [ $priority ];
			}
			$project_id = (int) ( $request->get_param( 'project_id' ) ?? 0 );
			if ( $project_id > 0 ) {
				$criteria['project_id'] = $project_id;
			}
			$result = WorkItems::search( $criteria );
			$items = array_map( [ self::class, 'work_item_projection' ], $result['items'] );
		} else {
			$result = self::catalog_page( 'projects' === $section ? PostTypes::PROJECT : PostTypes::SERVICE, $page, $per_page, $search, $sort );
			$items = array_map(
				static function ( int $id ) use ( $section ): ?array {
					$record = 'projects' === $section ? Projects::get( $id ) : Services::get( $id );
					if ( ! is_array( $record ) ) {
						return null;
					}
					return [
						'id' => (int) $record['id'],
						'title' => (string) $record['title'],
						'status' => (string) ( $record['post_status'] ?? $record['status'] ?? '' ),
					];
				},
				$result['ids']
			);
			$items = array_values( array_filter( $items, 'is_array' ) );
		}

		return rest_ensure_response( [
			'section' => $section,
			'items' => $items,
			'total' => (int) $result['total'],
			'page' => $page,
			'per_page' => $per_page,
			'pages' => (int) $result['pages'],
			'search' => $search,
			'sort' => $sort,
		] );
	}

	/** @param array<string,mixed> $item @return array<string,int|string> */
	private static function work_item_projection( array $item ): array {
		return [
			'id' => (int) $item['id'],
			'title' => (string) $item['title'],
			'post_status' => (string) $item['post_status'],
			'status' => (string) $item['status'],
			'priority' => (string) $item['priority'],
			'project_id' => (int) $item['project_id'],
			'service_id' => (int) $item['service_id'],
			'work_type_id' => (int) $item['work_type_id'],
			'scheduled_on' => (string) $item['scheduled_on'],
			'due_on' => (string) $item['due_on'],
			'updated_at' => (string) $item['updated_at'],
		];
	}

	/**
	 * Bounded CPT index query; canonical Projects/Services readers still own
	 * record projections. Only requested page IDs are materialized.
	 *
	 * @return array{ids:int[],total:int,pages:int}
	 */
	private static function catalog_page( string $post_type, int $page, int $per_page, string $search, string $sort ): array {
		$args = [
			'post_type' => $post_type,
			'post_status' => self::POST_STATUSES,
			'posts_per_page' => $per_page,
			'paged' => $page,
			'fields' => 'ids',
			'no_found_rows' => false,
			'ignore_sticky_posts' => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'orderby' => WorkItemQuery::SORT_UPDATED === $sort
				? [ 'modified' => 'DESC', 'ID' => 'DESC' ]
				: [ 'title' => 'ASC', 'ID' => 'ASC' ],
		];
		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		$query = new \WP_Query( $args );
		$total = max( 0, (int) $query->found_posts );
		if ( $page > 1 && [] === (array) $query->posts && 0 === $total ) {
			// Match WorkItemReadQuery's out-of-range pagination contract.
			$count_args = $args;
			$count_args['paged'] = 1;
			$count_args['posts_per_page'] = 1;
			$count = new \WP_Query( $count_args );
			$total = max( 0, (int) $count->found_posts );
		}
		return [
			'ids' => array_values( array_map( 'absint', (array) $query->posts ) ),
			'total' => $total,
			'pages' => (int) ceil( $total / $per_page ),
		];
	}
}

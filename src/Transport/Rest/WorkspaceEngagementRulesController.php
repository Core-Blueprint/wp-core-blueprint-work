<?php
declare(strict_types=1);

namespace CB\Work\Transport\Rest;

use CB\Work\Admin\ServiceOperations as ServiceOperationsAdmin;
use CB\Work\Content\ProjectMeta;
use CB\Work\PublicApi\ServiceOperations;
use CB\Work\PublicApi\ServiceOperationsActions;
use CB\Work\Repository\RecurrenceRules;
use CB\Work\ServiceOperations\Engagements;
use CB\Work\ServiceOperations\Periods;

defined( 'ABSPATH' ) || exit;

/**
 * M3-B2: scoped, read-on-demand recurrence binding and execution status.
 * Canonical domain remains the only owner of rule eligibility and writes.
 */
final class WorkspaceEngagementRulesController {
	public const NAMESPACE = 'cb-work/v1';
	public const ROUTE = '/workspace/engagements/(?P<engagement_id>[1-9][0-9]*)/rules';

	public static function init(): void {
		add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
	}

	public static function register_routes(): void {
		register_rest_route( self::NAMESPACE, self::ROUTE, [
			[
				'methods' => \WP_REST_Server::READABLE,
				'callback' => [ self::class, 'read' ],
				'permission_callback' => [ WorkspaceEngagementsController::class, 'can_manage' ],
				'args' => [
					'month' => [ 'type' => 'string', 'required' => false ],
					'page' => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 100000, 'default' => 1 ],
					'search' => [ 'type' => 'string', 'maxLength' => 160, 'default' => '' ],
				],
			],
			[
				'methods' => \WP_REST_Server::CREATABLE,
				'callback' => [ self::class, 'bind' ],
				'permission_callback' => [ WorkspaceEngagementsController::class, 'can_manage' ],
				'args' => [
					'rule_id' => [ 'type' => 'integer', 'minimum' => 1, 'required' => true ],
					'effective_from' => [ 'type' => 'string', 'required' => true ],
				],
			],
		] );
	}

	private static function authorized( \WP_REST_Request $request ): true|\WP_Error {
		$permission = WorkspaceEngagementsController::can_manage( $request );
		return true === $permission ? true : $permission;
	}

	/** @return array{id:int,project_id:int,service_id:int,starts_on:string,work_context:string,customer_provider:string,customer_type:string,customer_id:string}|\WP_Error */
	private static function engagement( \WP_REST_Request $request ): array|\WP_Error {
		$raw = $request->get_param( 'engagement_id' );
		if ( ! ( is_string( $raw ) || is_int( $raw ) ) || ! ctype_digit( (string) $raw )
			|| (int) $raw <= 0 ) {
			return new \WP_Error( 'work_service_input_invalid', '', [ 'status' => 400 ] );
		}
		$engagement = ServiceOperations::engagement( (int) $raw );
		if ( is_wp_error( $engagement ) ) {
			return new \WP_Error( $engagement->get_error_code(), '', [ 'status' => 404 ] );
		}
		return $engagement;
	}

	/** Only return eligible canonical rules, never customer reference fields. */
	private static function matches( array $rule, array $engagement ): bool {
		return ! empty( $rule['is_active'] )
			&& empty( $rule['is_trashed'] )
			&& (int) ( $rule['project_id'] ?? 0 ) === (int) $engagement['project_id']
			&& (int) ( $rule['service_id'] ?? 0 ) === (int) $engagement['service_id']
			&& (string) ( $rule['work_context'] ?? '' ) === (string) $engagement['work_context']
			&& (string) ( $rule['customer_provider'] ?? '' ) === (string) $engagement['customer_provider']
			&& (string) ( $rule['customer_type'] ?? '' ) === (string) $engagement['customer_type']
			&& (string) ( $rule['customer_id'] ?? '' ) === (string) $engagement['customer_id'];
	}

	public static function read( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$permission = self::authorized( $request );
		if ( true !== $permission ) { return $permission; }
		$engagement = self::engagement( $request );
		if ( is_wp_error( $engagement ) ) { return $engagement; }

		$month_raw = $request->get_param( 'month' ) ?? wp_date( 'Y-m' );
		$page_raw = $request->get_param( 'page' ) ?? 1;
		$search_raw = $request->get_param( 'search' ) ?? '';
		if ( ! is_string( $month_raw ) || null === Periods::bounds( $month_raw )
			|| ! ( is_string( $page_raw ) || is_int( $page_raw ) )
			|| ! ctype_digit( (string) $page_raw ) || (int) $page_raw < 1
			|| ! is_string( $search_raw ) ) {
			return new \WP_Error( 'work_service_input_invalid', '', [ 'status' => 400 ] );
		}
		$page = min( 100000, (int) $page_raw );
		$search = substr( sanitize_text_field( $search_raw ), 0, 160 );
		$period = ServiceOperations::period( (int) $engagement['id'], $month_raw );
		if ( is_wp_error( $period ) ) {
			return new \WP_Error( 'work_service_period_unavailable', '', [ 'status' => 503 ] );
		}
		$linked = Engagements::rules( (int) $engagement['id'] );
		if ( is_wp_error( $linked ) ) {
			return new \WP_Error( 'work_service_rules_unavailable', '', [ 'status' => 503 ] );
		}
		$linked_ids = array_map( static fn( array $row ): int => (int) $row['rule_id'], $linked );
		$eligible = RecurrenceRules::search_page( [
			'page' => $page,
			'search' => $search,
			'status' => 'active',
			'project_id' => (int) $engagement['project_id'],
			'context' => (string) $engagement['work_context'],
		] );
		$rows = [];
		foreach ( $eligible['rows'] as $rule ) {
			if ( self::matches( $rule, $engagement ) && ! in_array( (int) $rule['id'], $linked_ids, true ) ) {
				$rows[] = [ 'id' => (int) $rule['id'], 'title' => (string) $rule['title'],
					'frequency' => (string) $rule['frequency'], 'next_on' => $rule['next_occurrence_on'] ];
			}
		}
		// Source pagination intentionally remains source pagination: filtered
		// pages may be empty while more candidate pages still exist. The UI
		// must not label an intermediate empty page as "no eligible rules".
		return rest_ensure_response( [
			'month' => $month_raw,
			'period' => [
				'status' => (string) $period['status'],
				'scheduled' => max( 0, (int) $period['scheduled'] ),
				'completed' => max( 0, (int) $period['completed'] ),
				'overdue' => max( 0, (int) $period['overdue'] ),
				'missing' => max( 0, (int) $period['missing'] ),
				'next_execution' => $period['next_execution'],
				'last_execution' => $period['last_execution'],
			],
			'engagement' => [ 'id' => (int) $engagement['id'], 'starts_on' => (string) $engagement['starts_on'] ],
			'linked_total' => count( $linked ),
			'rules' => $rows,
			'page' => (int) $eligible['page'],
			'pages' => (int) $eligible['pages'],
		] );
	}

	public static function bind( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$permission = self::authorized( $request );
		if ( true !== $permission ) { return $permission; }
		$engagement = self::engagement( $request );
		if ( is_wp_error( $engagement ) ) { return $engagement; }
		$raw_rule = $request->get_param( 'rule_id' );
		$raw_date = $request->get_param( 'effective_from' );
		if ( ! ( is_int( $raw_rule ) || is_string( $raw_rule ) )
			|| ! ctype_digit( (string) $raw_rule ) || (int) $raw_rule <= 0
			|| ! is_string( $raw_date )
			|| ProjectMeta::sanitize_date( $raw_date ) !== $raw_date ) {
			return new \WP_Error( 'work_service_input_invalid', '', [ 'status' => 400 ] );
		}
		// This Workspace chooser only offers active, untrashed matches.
		// Recheck at write time so a forged or stale chooser cannot bind
		// an ineligible rule. The canonical writer still enforces all
		// relationship, effective-date and transactional invariants.
		$rule = RecurrenceRules::get( (int) $raw_rule );
		if ( ! is_array( $rule ) || ! self::matches( $rule, $engagement ) ) {
			return new \WP_Error( 'work_service_rule_context_mismatch',
				__( 'The change could not be saved.', 'core-blueprint-work' ),
				[ 'status' => 409 ] );
		}
		$result = ServiceOperationsActions::bind_rule( (int) $engagement['id'], (int) $raw_rule, $raw_date );
		if ( is_wp_error( $result ) ) {
			return new \WP_Error( $result->get_error_code(),
				__( 'The change could not be saved.', 'core-blueprint-work' ),
				[ 'status' => 409 ] );
		}
		return rest_ensure_response( [ 'linked' => true ] );
	}

	private function __construct() {}
}

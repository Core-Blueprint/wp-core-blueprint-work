<?php
declare(strict_types=1);

namespace CB\Work\Transport\Rest;

use CB\Work\Content\ProjectMeta;
use CB\Work\PublicApi\ServiceBilling;
use CB\Work\PublicApi\ServiceBillingActions;
use CB\Work\PublicApi\ServiceOperations;
use CB\Work\ServiceOperations\Periods;

defined( 'ABSPATH' ) || exit;

/**
 * M3-B3: governed, operator-attested external invoice coverage only.
 *
 * No invoice issuing/provider status: the existing ServiceBilling domain is
 * the sole source of coverage and its InnoDB CAS/audit-journal mutations.
 */
final class WorkspaceEngagementBillingController {
	public const NAMESPACE = 'cb-work/v1';
	public const ROUTE = '/workspace/engagements/(?P<engagement_id>[1-9][0-9]*)/billing';
	public const TERMS_ROUTE = self::ROUTE . '/terms';

	public static function init(): void {
		add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
	}

	public static function register_routes(): void {
		$permission = [ WorkspaceEngagementsController::class, 'can_manage' ];
		register_rest_route( self::NAMESPACE, self::ROUTE, [
			[
				'methods' => \WP_REST_Server::READABLE,
				'callback' => [ self::class, 'read' ],
				'permission_callback' => $permission,
				'args' => [ 'month' => [ 'type' => 'string', 'required' => false ] ],
			],
			[
				'methods' => \WP_REST_Server::CREATABLE,
				'callback' => [ self::class, 'record' ],
				'permission_callback' => $permission,
				'args' => [
					'month' => [ 'type' => 'string', 'required' => true ],
					'state' => [ 'type' => 'string', 'enum' => [ 'invoiced', 'not_recorded' ], 'required' => true ],
					'invoiced_on' => [ 'type' => 'string', 'default' => '' ],
					'reference' => [ 'type' => 'string', 'maxLength' => 190, 'default' => '' ],
					'expected_revision' => [ 'type' => 'integer', 'minimum' => 0, 'required' => true ],
				],
			],
		] );
		register_rest_route( self::NAMESPACE, self::TERMS_ROUTE, [
			'methods' => \WP_REST_Server::CREATABLE,
			'callback' => [ self::class, 'create_terms' ],
			'permission_callback' => $permission,
			'args' => [
				'frequency' => [ 'type' => 'string', 'enum' => [ 'weekly', 'monthly', 'quarterly', 'yearly' ], 'required' => true ],
				'anchor_on' => [ 'type' => 'string', 'required' => true ],
				'timing' => [ 'type' => 'string', 'enum' => [ 'advance', 'arrears' ], 'required' => true ],
			],
		] );
	}

	/** @return array<string,mixed>|\WP_Error */
	private static function engagement( \WP_REST_Request $request ): array|\WP_Error {
		$raw = $request->get_param( 'engagement_id' );
		if ( ! ( is_int( $raw ) || is_string( $raw ) ) || ! ctype_digit( (string) $raw )
			|| (int) $raw <= 0 ) {
			return new \WP_Error( 'work_service_input_invalid', '', [ 'status' => 400 ] );
		}
		$engagement = ServiceOperations::engagement( (int) $raw );
		return is_wp_error( $engagement )
			? new \WP_Error( $engagement->get_error_code(), '', [ 'status' => 404 ] )
			: $engagement;
	}

	private static function month( mixed $raw ): string|\WP_Error {
		if ( ! is_string( $raw ) || null === Periods::bounds( $raw ) ) {
			return new \WP_Error( 'work_service_period_invalid', '', [ 'status' => 400 ] );
		}
		return $raw;
	}

	private static function authorized( \WP_REST_Request $request ): true|\WP_Error {
		return WorkspaceEngagementsController::can_manage( $request );
	}

	public static function read( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$allowed = self::authorized( $request );
		if ( true !== $allowed ) { return $allowed; }
		$engagement = self::engagement( $request );
		if ( is_wp_error( $engagement ) ) { return $engagement; }
		$month = self::month( $request->get_param( 'month' ) ?? wp_date( 'Y-m' ) );
		if ( is_wp_error( $month ) ) { return $month; }
		$coverage = ServiceBilling::coverage( (int) $engagement['id'], $month );
		if ( is_wp_error( $coverage ) ) {
			return new \WP_Error( 'work_service_billing_unavailable', '', [ 'status' => 503 ] );
		}
		$context = (string) $engagement['work_context'];
		if ( ! in_array( $context, [ 'internal', 'customer' ], true )
			|| ! in_array( $coverage['state'] ?? null, [ 'not_applicable', 'not_recorded', 'invoiced' ], true )
			|| ( 'internal' === $context && 'not_applicable' !== $coverage['state'] ) ) {
			return new \WP_Error( 'work_service_billing_invalid', '', [ 'status' => 503 ] );
		}
		$registration = $coverage['registration'] ?? null;
		return rest_ensure_response( [
			'engagement_id' => (int) $engagement['id'],
			'month' => $month,
			'work_context' => $context,
			'starts_on' => (string) $engagement['starts_on'],
			'state' => (string) $coverage['state'],
			'period_start' => isset( $coverage['period_start'] ) ? (string) $coverage['period_start'] : null,
			'period_end_exclusive' => isset( $coverage['period_end_exclusive'] ) ? (string) $coverage['period_end_exclusive'] : null,
			'registration' => is_array( $registration ) ? [
				'revision' => max( 0, (int) $registration['revision'] ),
				'invoiced_on' => $registration['invoiced_on'] === null ? null : (string) $registration['invoiced_on'],
				'external_reference' => (string) $registration['external_reference'],
			] : null,
		] );
	}

	public static function record( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$allowed = self::authorized( $request );
		if ( true !== $allowed ) { return $allowed; }
		$engagement = self::engagement( $request );
		if ( is_wp_error( $engagement ) ) { return $engagement; }
		if ( 'customer' !== (string) $engagement['work_context'] ) {
			return new \WP_Error( 'work_service_registration_not_applicable', '', [ 'status' => 422 ] );
		}
		$month = self::month( $request->get_param( 'month' ) );
		if ( is_wp_error( $month ) ) { return $month; }
		$state = $request->get_param( 'state' );
		$invoiced_on = $request->get_param( 'invoiced_on' ) ?? '';
		$reference = $request->get_param( 'reference' ) ?? '';
		$revision_raw = $request->get_param( 'expected_revision' );
		if ( ! is_string( $state ) || ! in_array( $state, [ 'invoiced', 'not_recorded' ], true )
			|| ! is_string( $invoiced_on ) || ! is_string( $reference )
			|| strlen( $reference ) > 190
			|| ! ( is_int( $revision_raw ) || is_string( $revision_raw ) )
			|| ! ctype_digit( (string) $revision_raw )
			|| (int) $revision_raw < 0
			|| ( '' !== $invoiced_on && ProjectMeta::sanitize_date( $invoiced_on ) !== $invoiced_on ) ) {
			return new \WP_Error( 'work_service_registration_invalid', '', [ 'status' => 400 ] );
		}
		// Server canonical billing owns CAS, atomic journal and audit.
		$result = ServiceBillingActions::record(
			(int) $engagement['id'], $month, $state, $invoiced_on, $reference, (int) $revision_raw
		);
		if ( is_wp_error( $result ) ) {
			return new \WP_Error( $result->get_error_code(),
				__( 'The change could not be saved.', 'core-blueprint-work' ), [ 'status' => 409 ] );
		}
		$registration = $result['registration'] ?? null;
		if ( ! in_array( $result['outcome'] ?? null, [ 'saved', 'reused' ], true )
			|| ! is_array( $registration ) || (int) ( $registration['revision'] ?? 0 ) < 1 ) {
			return new \WP_Error( 'work_service_registration_readback_failed', '', [ 'status' => 503 ] );
		}
		return rest_ensure_response( [
			'outcome' => (string) $result['outcome'],
			'revision' => (int) $registration['revision'],
		] );
	}

	public static function create_terms( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$allowed = self::authorized( $request );
		if ( true !== $allowed ) { return $allowed; }
		$engagement = self::engagement( $request );
		if ( is_wp_error( $engagement ) ) { return $engagement; }
		if ( 'customer' !== (string) $engagement['work_context'] ) {
			return new \WP_Error( 'work_service_billing_not_applicable', '', [ 'status' => 422 ] );
		}
		$frequency = $request->get_param( 'frequency' );
		$timing = $request->get_param( 'timing' );
		$anchor = $request->get_param( 'anchor_on' );
		if ( ! is_string( $frequency ) || ! in_array( $frequency, [ 'weekly', 'monthly', 'quarterly', 'yearly' ], true )
			|| ! is_string( $timing ) || ! in_array( $timing, [ 'advance', 'arrears' ], true )
			|| ! is_string( $anchor ) || ProjectMeta::sanitize_date( $anchor ) !== $anchor
			|| $anchor < (string) $engagement['starts_on'] ) {
			return new \WP_Error( 'work_service_terms_invalid', '', [ 'status' => 400 ] );
		}
		$id = ServiceBillingActions::create_terms( (int) $engagement['id'], $frequency, $anchor, $timing );
		if ( is_wp_error( $id ) ) {
			return new \WP_Error( $id->get_error_code(),
				__( 'The change could not be saved.', 'core-blueprint-work' ), [ 'status' => 409 ] );
		}
		return new \WP_REST_Response( [ 'terms_id' => $id ], 201 );
	}

	private function __construct() {}
}

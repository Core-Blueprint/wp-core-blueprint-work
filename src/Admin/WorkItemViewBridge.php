<?php
declare(strict_types=1);

namespace CB\Work\Admin;

use CB\Work\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * UWE-03C.1: authenticated, read-only HTML bridge to the exact canonical
 * Work Items renderer. No renderer duplication, no new Work domain logic.
 *
 * This transport is prepared for UWE-03C.2 view mounting and interaction
 * integration. The existing native Work Items page remains operational.
 */
final class WorkItemViewBridge {
	public const ACTION = 'cb_work_uwe_render_view';
	public const NONCE_ACTION = 'cb_work_uwe_render_view';

	/** @var list<string> */
	private const FIELDS = [
		'view', 's', 'status', 'priority', 'project_id', 'service_id',
		'work_type_id', 'assignee_id', 'billing', 'work_context', 'customer',
		'calendar_month', 'scheduled_from', 'scheduled_to', 'due_from',
		'due_to', 'sort', 'paged',
	];
	private const NUMERIC = [ 'project_id', 'service_id', 'work_type_id', 'assignee_id', 'paged' ];

	public static function init(): void {
		add_action( 'wp_ajax_' . self::ACTION, [ self::class, 'handle_ajax' ] );
	}

	public static function handle_ajax(): void {
		if ( 'GET' !== (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			wp_send_json_error( [ 'code' => 'work_view_read_only' ], 405 );
		}
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_send_json_error( [ 'code' => 'work_view_forbidden' ], 403 );
		}
		if ( false === check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( [ 'code' => 'work_view_invalid_nonce' ], 403 );
		}
		if ( ! function_exists( 'cb_work_runtime_ready' ) || ! \cb_work_runtime_ready() ) {
			wp_send_json_error( [ 'code' => 'work_view_unavailable' ], 503 );
		}

		$rendered = self::render_html( $_GET );
		if ( is_wp_error( $rendered ) ) {
			$status = (int) ( $rendered->get_error_data()['status'] ?? 400 );
			wp_send_json_error( [ 'code' => $rendered->get_error_code() ], $status );
		}

		wp_send_json_success( $rendered, 200 );
	}

	/**
	 * Pure HTML transport around the existing exact-source Operations renderer.
	 * Isolates request state while preserving native presentation and governance.
	 *
	 * @param array<string,mixed> $request
	 * @return array{view:string,html:string}|\WP_Error
	 */
	public static function render_html( array $request ): array|\WP_Error {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			return new \WP_Error( 'work_view_forbidden', '', [ 'status' => 403 ] );
		}
		if ( ! function_exists( 'cb_work_runtime_ready' ) || ! \cb_work_runtime_ready() ) {
			return new \WP_Error( 'work_view_unavailable', '', [ 'status' => 503 ] );
		}

		$normalized = self::normalize_request( $request );
		if ( is_wp_error( $normalized ) ) {
			return $normalized;
		}

		$with_defaults = WorkItemViewPreferences::apply_default_to_request( $normalized, get_current_user_id() );
		$view = (string) WorkItemViewState::from_request( $with_defaults )['view'];
		$original = $_GET;
		$buffer_level = ob_get_level();
		$_GET = $normalized;
		try {
			ob_start();
			Operations::render_work_items();
			$html = (string) ob_get_clean();
			return [ 'view' => $view, 'html' => $html ];
		} finally {
			while ( ob_get_level() > $buffer_level ) {
				ob_end_clean();
			}
			$_GET = $original;
		}
	}

	/**
	 * Whitelist only canonical WorkItemViewState request keys. Never allow
	 * admin AJAX action/nonce or WordPress's reserved admin route parameter.
	 *
	 * @param array<string,mixed> $request
	 * @return array<string,string>|\WP_Error
	 */
	private static function normalize_request( array $request ): array|\WP_Error {
		$accepted = [];
		foreach ( self::FIELDS as $field ) {
			if ( ! array_key_exists( $field, $request ) ) {
				continue;
			}
			$value = $request[ $field ];
			if ( ! is_scalar( $value ) ) {
				return new \WP_Error( 'work_view_invalid_parameter', '', [ 'status' => 400 ] );
			}
			$value = sanitize_text_field( (string) wp_unslash( $value ) );
			$max_length = 's' === $field ? 160 : ( 'customer' === $field ? 191 : 64 );
			if ( strlen( $value ) > $max_length ) {
				return new \WP_Error( 'work_view_invalid_parameter', '', [ 'status' => 400 ] );
			}
			if ( in_array( $field, self::NUMERIC, true ) ) {
				if ( ! ctype_digit( $value ) || strlen( $value ) > 12 || ( 'paged' === $field && (int) $value < 1 ) ) {
					return new \WP_Error( 'work_view_invalid_parameter', '', [ 'status' => 400 ] );
				}
				if ( 'paged' === $field && (int) $value > 100000 ) {
					return new \WP_Error( 'work_view_invalid_parameter', '', [ 'status' => 400 ] );
				}
			}
			if ( 'view' === $field && ! in_array( $value, WorkItemViewState::views(), true ) ) {
				return new \WP_Error( 'work_view_invalid_view', '', [ 'status' => 400 ] );
			}
			$accepted[ $field ] = $value;
		}

		return $accepted;
	}

	private function __construct() {}
}

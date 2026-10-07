<?php
declare(strict_types=1);

namespace CB\Work\Admin;

use CB\Work\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Personal presentation preferences for the Work Items view switcher.
 *
 * URL state remains canonical and explicit ?view= always wins. This policy
 * supplies only the personal default when view is omitted and the presentation
 * order of the four canonical views.
 */
final class WorkItemViewPreferences {
	public const ACTION       = 'cb_work_save_view_preferences';
	public const NONCE_ACTION = 'cb_work_view_preferences';

	private const USER_META = '_cb_work_items_view_preferences_v1';

	/** @return list<string> */
	public static function canonical_order(): array {
		return [
			WorkItemViewState::VIEW_TABLE,
			WorkItemViewState::VIEW_LIST,
			WorkItemViewState::VIEW_KANBAN,
			WorkItemViewState::VIEW_CALENDAR,
		];
	}

	/** @return array{default_view:string,order:list<string>} */
	public static function defaults(): array {
		return [
			'default_view' => WorkItemViewState::VIEW_TABLE,
			'order'        => self::canonical_order(),
		];
	}

	public static function init(): void {
		add_action( 'wp_ajax_' . self::ACTION, [ self::class, 'handle_ajax' ] );
	}

	/** @return array{default_view:string,order:list<string>} */
	public static function get( int $user_id ): array {
		if ( $user_id <= 0 ) {
			return self::defaults();
		}

		$stored = get_user_meta( $user_id, self::USER_META, true );
		return is_array( $stored ) ? self::normalize( $stored ) : self::defaults();
	}

	/** @return array{default_view:string,order:list<string>} */
	public static function normalize( array $policy ): array {
		$canonical = self::canonical_order();
		$allowed   = array_fill_keys( $canonical, true );

		$default_view = sanitize_key( is_scalar( $policy['default_view'] ?? null ) ? (string) $policy['default_view'] : '' );
		if ( ! isset( $allowed[ $default_view ] ) ) {
			$default_view = WorkItemViewState::VIEW_TABLE;
		}

		$order = [];
		foreach ( (array) ( $policy['order'] ?? [] ) as $view ) {
			$view = sanitize_key( is_scalar( $view ) ? (string) $view : '' );
			if ( '' !== $view && isset( $allowed[ $view ] ) && ! in_array( $view, $order, true ) ) {
				$order[] = $view;
			}
		}
		foreach ( $canonical as $view ) {
			if ( ! in_array( $view, $order, true ) ) {
				$order[] = $view;
			}
		}

		return [
			'default_view' => $default_view,
			'order'        => $order,
		];
	}

	/**
	 * Apply the personal default only when the request does not explicitly
	 * contain a usable view value. Invalid explicit values are left intact so
	 * WorkItemViewState can fail closed to its canonical Table fallback.
	 *
	 * @param array<string,mixed> $request
	 * @return array<string,mixed>
	 */
	public static function apply_default_to_request( array $request, int $user_id ): array {
		$has_explicit = array_key_exists( 'view', $request )
			&& is_scalar( $request['view'] )
			&& '' !== trim( (string) $request['view'] );

		if ( ! $has_explicit ) {
			$request['view'] = self::get( $user_id )['default_view'];
		}

		return $request;
	}

	public static function resolve_request_view( array $request, int $user_id ): string {
		$request = self::apply_default_to_request( $request, $user_id );
		$view    = sanitize_key( is_scalar( $request['view'] ?? null ) ? (string) $request['view'] : '' );
		return in_array( $view, WorkItemViewState::views(), true ) ? $view : WorkItemViewState::VIEW_TABLE;
	}

	public static function handle_ajax(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission to manage Work.', 'core-blueprint-work' ) ], 403 );
		}

		$raw = isset( $_POST['policy'] ) ? (string) wp_unslash( $_POST['policy'] ) : '';
		if ( '' === $raw || strlen( $raw ) > 2048 ) {
			wp_send_json_error( [ 'message' => __( 'The Work Item view preference payload is invalid.', 'core-blueprint-work' ) ], 400 );
		}

		try {
			$decoded = json_decode( $raw, true, 4, JSON_THROW_ON_ERROR );
		} catch ( \JsonException ) {
			wp_send_json_error( [ 'message' => __( 'The Work Item view preference payload is invalid.', 'core-blueprint-work' ) ], 400 );
		}
		if ( ! is_array( $decoded ) ) {
			wp_send_json_error( [ 'message' => __( 'The Work Item view preference payload is invalid.', 'core-blueprint-work' ) ], 400 );
		}

		$policy = self::normalize( $decoded );
		update_user_meta( get_current_user_id(), self::USER_META, $policy );
		wp_send_json_success( [ 'policy' => $policy ] );
	}

	private function __construct() {}
}

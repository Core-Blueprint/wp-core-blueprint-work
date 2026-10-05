<?php
declare(strict_types=1);

namespace CB\Work\Admin;

use CB\Work\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Personal presentation preferences for the custom Work Items Table view.
 *
 * This is intentionally separate from Base Admin Columns Governance, which
 * owns site-wide policy for native WordPress list tables. Work reuses Base
 * Reorder for interaction while keeping this custom workspace preference
 * scoped to the current user.
 */
final class WorkItemTablePreferences {
	public const ACTION = 'cb_work_save_table_preferences';
	public const NONCE_ACTION = 'cb_work_table_preferences';
	private const USER_META = '_cb_work_items_table_columns_v1';
	private const PROTECTED = [ 'work_item' ];

	/** @return list<string> */
	public static function column_ids(): array {
		return [
			'work_item',
			'status',
			'priority',
			'project',
			'due',
			'assigned',
			'actions',
			'customer',
			'type',
			'billing',
		];
	}

	/** @return array{order:list<string>,hidden:list<string>} */
	public static function defaults(): array {
		return [
			'order'  => self::column_ids(),
			'hidden' => [ 'customer', 'type', 'billing' ],
		];
	}

	public static function init(): void {
		add_action( 'wp_ajax_' . self::ACTION, [ self::class, 'handle_ajax' ] );
	}

	/** @return array{order:list<string>,hidden:list<string>} */
	public static function get( int $user_id ): array {
		if ( $user_id <= 0 ) {
			return self::defaults();
		}
		$stored = get_user_meta( $user_id, self::USER_META, true );
		return is_array( $stored ) ? self::normalize( $stored ) : self::defaults();
	}

	/** @return array{order:list<string>,hidden:list<string>} */
	public static function normalize( array $policy ): array {
		$allowed = array_fill_keys( self::column_ids(), true );
		$order   = [];

		foreach ( (array) ( $policy['order'] ?? [] ) as $column_id ) {
			$column_id = sanitize_key( is_scalar( $column_id ) ? (string) $column_id : '' );
			if ( '' !== $column_id && isset( $allowed[ $column_id ] ) && ! in_array( $column_id, $order, true ) ) {
				$order[] = $column_id;
			}
		}
		foreach ( self::column_ids() as $column_id ) {
			if ( ! in_array( $column_id, $order, true ) ) {
				$order[] = $column_id;
			}
		}

		$hidden = [];
		foreach ( (array) ( $policy['hidden'] ?? [] ) as $column_id ) {
			$column_id = sanitize_key( is_scalar( $column_id ) ? (string) $column_id : '' );
			if (
				isset( $allowed[ $column_id ] )
				&& ! in_array( $column_id, self::PROTECTED, true )
				&& ! in_array( $column_id, $hidden, true )
			) {
				$hidden[] = $column_id;
			}
		}

		return [
			'order'  => $order,
			'hidden' => $hidden,
		];
	}

	public static function handle_ajax(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_send_json_error( [ 'message' => __( 'You are not allowed to change Work Item table preferences.', 'core-blueprint-work' ) ], 403 );
		}

		$user_id   = get_current_user_id();
		$operation = isset( $_POST['operation'] ) ? sanitize_key( (string) wp_unslash( $_POST['operation'] ) ) : 'save';

		if ( 'reset' === $operation ) {
			delete_user_meta( $user_id, self::USER_META );
			wp_send_json_success( [ 'policy' => self::defaults() ] );
		}
		if ( 'save' !== $operation ) {
			wp_send_json_error( [ 'message' => __( 'The table preference operation is invalid.', 'core-blueprint-work' ) ], 400 );
		}

		$raw = isset( $_POST['policy'] ) ? (string) wp_unslash( $_POST['policy'] ) : '';
		if ( '' === $raw || strlen( $raw ) > 8192 ) {
			wp_send_json_error( [ 'message' => __( 'The table preference payload is invalid.', 'core-blueprint-work' ) ], 400 );
		}

		try {
			$decoded = json_decode( $raw, true, 8, JSON_THROW_ON_ERROR );
		} catch ( \JsonException ) {
			wp_send_json_error( [ 'message' => __( 'The table preference payload is invalid.', 'core-blueprint-work' ) ], 400 );
		}
		if ( ! is_array( $decoded ) ) {
			wp_send_json_error( [ 'message' => __( 'The table preference payload is invalid.', 'core-blueprint-work' ) ], 400 );
		}

		$policy = self::normalize( $decoded );
		update_user_meta( $user_id, self::USER_META, $policy );
		wp_send_json_success( [ 'policy' => $policy ] );
	}

	private function __construct() {}
}

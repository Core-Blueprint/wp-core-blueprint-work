<?php
declare(strict_types=1);

namespace CB\Work\Admin;

use CB\Work\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Personal presentation preferences for the custom Work Items List view.
 *
 * Grouping is presentation-only: WorkItemViewState remains the canonical
 * filter/sort contract and item ordering is preserved inside each group.
 */
final class WorkItemListPreferences {
	public const ACTION = 'cb_work_save_list_preferences';
	public const NONCE_ACTION = 'cb_work_list_preferences';
	public const GROUP_NONE = 'none';
	public const GROUP_PROJECT = 'project';
	public const ORDER_ASC = 'asc';
	public const ORDER_DESC = 'desc';

	private const USER_META = '_cb_work_items_list_preferences_v1';
	private const GROUPS = [ self::GROUP_NONE, self::GROUP_PROJECT ];
	private const ORDERS = [ self::ORDER_ASC, self::ORDER_DESC ];

	/** @return array{group_by:string,project_order:string} */
	public static function defaults(): array {
		return [
			'group_by'       => self::GROUP_PROJECT,
			'project_order'  => self::ORDER_ASC,
		];
	}

	public static function init(): void {
		add_action( 'wp_ajax_' . self::ACTION, [ self::class, 'handle_ajax' ] );
	}

	/** @return array{group_by:string,project_order:string} */
	public static function get( int $user_id ): array {
		if ( $user_id <= 0 ) {
			return self::defaults();
		}
		$stored = get_user_meta( $user_id, self::USER_META, true );
		return is_array( $stored ) ? self::normalize( $stored ) : self::defaults();
	}

	/** @return array{group_by:string,project_order:string} */
	public static function normalize( array $policy ): array {
		$group_by = sanitize_key( is_scalar( $policy['group_by'] ?? null ) ? (string) $policy['group_by'] : '' );
		if ( ! in_array( $group_by, self::GROUPS, true ) ) {
			$group_by = self::GROUP_PROJECT;
		}

		$project_order = sanitize_key( is_scalar( $policy['project_order'] ?? null ) ? (string) $policy['project_order'] : '' );
		if ( ! in_array( $project_order, self::ORDERS, true ) ) {
			$project_order = self::ORDER_ASC;
		}

		return [
			'group_by'      => $group_by,
			'project_order' => $project_order,
		];
	}

	public static function handle_ajax(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission to manage Work.', 'core-blueprint-work' ) ], 403 );
		}

		$raw = isset( $_POST['policy'] ) ? (string) wp_unslash( $_POST['policy'] ) : '';
		if ( '' === $raw || strlen( $raw ) > 2048 ) {
			wp_send_json_error( [ 'message' => __( 'The list preference payload is invalid.', 'core-blueprint-work' ) ], 400 );
		}

		try {
			$decoded = json_decode( $raw, true, 4, JSON_THROW_ON_ERROR );
		} catch ( \JsonException ) {
			wp_send_json_error( [ 'message' => __( 'The list preference payload is invalid.', 'core-blueprint-work' ) ], 400 );
		}
		if ( ! is_array( $decoded ) ) {
			wp_send_json_error( [ 'message' => __( 'The list preference payload is invalid.', 'core-blueprint-work' ) ], 400 );
		}

		$policy = self::normalize( $decoded );
		update_user_meta( get_current_user_id(), self::USER_META, $policy );
		wp_send_json_success( [ 'policy' => $policy ] );
	}

	private function __construct() {}
}

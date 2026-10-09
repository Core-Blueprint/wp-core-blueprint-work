<?php
declare(strict_types=1);

namespace CB\Work\Admin;

use CB\Work\Capabilities;
use CB\Work\Domain\WorkItemStatus;
use CB\Work\Repository\WorkItems;

defined( 'ABSPATH' ) || exit;

final class WorkItemBoardActions {
	public const ACTION = 'cb_work_board_transition';
	public const NONCE_ACTION = 'cb_work_board_transition';

	public static function init(): void {
		add_action( 'wp_ajax_' . self::ACTION, [ self::class, 'handle' ] );
	}

	public static function handle(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_send_json_error( [ 'message' => __( 'You are not allowed to move Work Items.', 'core-blueprint-work' ) ], 403 );
		}

		$work_item_id = isset( $_POST['work_item_id'] ) ? absint( $_POST['work_item_id'] ) : 0;
		$target       = isset( $_POST['status'] ) ? sanitize_key( (string) wp_unslash( $_POST['status'] ) ) : '';
		$expected     = isset( $_POST['expected_status'] ) && is_scalar( $_POST['expected_status'] )
			? sanitize_key( (string) wp_unslash( $_POST['expected_status'] ) )
			: null;
		$item         = WorkItems::get( $work_item_id );

		if ( null !== $expected && ! WorkItemStatus::is_valid( $expected ) ) {
			wp_send_json_error( [ 'message' => __( 'The Work Item move is invalid.', 'core-blueprint-work' ) ], 400 );
		}

		if ( null === $item || ! WorkItemStatus::is_valid( $target ) ) {
			wp_send_json_error( [ 'message' => __( 'The Work Item move is invalid.', 'core-blueprint-work' ) ], 400 );
		}

		$from = (string) $item['status'];
		if ( null !== $expected && $expected !== $from ) {
			wp_send_json_error( [ 'message' => __( 'That Work Item status transition is not allowed.', 'core-blueprint-work' ) ], 409 );
		}
		if ( ! WorkItemStatus::can_transition( $from, $target ) ) {
			wp_send_json_error( [ 'message' => __( 'That Work Item status transition is not allowed.', 'core-blueprint-work' ) ], 409 );
		}

		if ( ! WorkItems::transition_status( $work_item_id, $target, get_current_user_id(), $expected ?? $from ) ) {
			wp_send_json_error( [ 'message' => __( 'The Work Item status could not be updated.', 'core-blueprint-work' ) ], 409 );
		}

		wp_send_json_success( [
			'work_item_id'    => $work_item_id,
			'status'          => $target,
			'allowed_statuses'=> WorkItemStatus::transitions_from( $target ),
		] );
	}

	private function __construct() {}
}

<?php
declare(strict_types=1);

namespace CB\Work\Admin;

use CoreBlueprint\Core\Governance\Audit;
use CB\Work\Capabilities;
use CB\Work\Domain\WorkItemStatus;
use CB\Work\Governance\Events;
use CB\Work\Repository\WorkItems;
use CB\Work\Repository\WorkTypes;

defined( 'ABSPATH' ) || exit;

final class OperationalActions {
	public static function init(): void {
		add_action( 'admin_post_cb_work_transition_work_item', [ self::class, 'transition_work_item' ] );
		add_action( 'admin_post_cb_work_bulk_transition_work_items', [ self::class, 'bulk_transition_work_items' ] );
		add_action( 'admin_post_cb_work_quick_edit_work_item', [ self::class, 'quick_edit_work_item' ] );
		add_action( 'admin_post_cb_work_bulk_edit_work_items', [ self::class, 'bulk_edit_work_items' ] );
		add_action( 'admin_post_cb_work_create_work_type', [ self::class, 'create_work_type' ] );
		add_action( 'admin_post_cb_work_toggle_work_type', [ self::class, 'toggle_work_type' ] );
	}

	public static function transition_work_item(): never {
		$id = isset( $_POST['work_item_id'] ) ? absint( $_POST['work_item_id'] ) : 0;
		self::guard( 'cb_work_transition_work_item_' . $id );
		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( (string) $_POST['status'] ) ) : '';
		$item = WorkItems::get( $id );
		$from = is_array( $item ) ? (string) ( $item['status'] ?? '' ) : '';
		if ( $id > 0 && WorkItems::transition_status( $id, $status, get_current_user_id() ) ) {
			Audit::record( Events::WORK_ITEM_STATUS_CHANGED, 'notice', [ 'work_item_id' => $id, 'from' => $from, 'to' => $status ] );
			self::redirect_work_items( 'work-item-transitioned' );
		}
		self::redirect_work_items( 'work-item-transition-invalid' );
	}


	public static function bulk_transition_work_items(): never {
		self::guard( 'cb_work_bulk_transition_work_items' );

		$ids    = self::posted_work_item_ids();
		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( (string) $_POST['status'] ) ) : '';
		$actor  = get_current_user_id();
		$updated = 0;

		foreach ( $ids as $id ) {
			$item = WorkItems::get( $id );
			$from = is_array( $item ) ? (string) ( $item['status'] ?? '' ) : '';
			if ( WorkItems::transition_status( $id, $status, $actor ) ) {
				Audit::record(
					Events::WORK_ITEM_STATUS_CHANGED,
					'notice',
					[ 'work_item_id' => $id, 'from' => $from, 'to' => $status, 'bulk' => true ]
				);
				$updated++;
			}
		}

		self::redirect_work_items( $updated > 0 ? 'work-item-transitioned' : 'work-item-transition-invalid' );
	}


	public static function quick_edit_work_item(): never {
		$id = isset( $_POST['work_item_id'] ) ? absint( $_POST['work_item_id'] ) : 0;
		self::guard( 'cb_work_quick_edit_work_item_' . $id );

		$current = WorkItems::get( $id );
		$input   = isset( $_POST['work_item'] ) && is_array( $_POST['work_item'] ) ? wp_unslash( $_POST['work_item'] ) : [];
		$status  = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( (string) $_POST['status'] ) ) : '';
		$from    = is_array( $current ) ? (string) ( $current['status'] ?? '' ) : '';

		if (
			$id <= 0
			|| ! is_array( $current )
			|| ( '' !== $status && $status !== $from && ! WorkItemStatus::can_transition( $from, $status ) )
			|| ! WorkItems::update( $id, $input )
		) {
			self::redirect_work_items( 'work-item-update-invalid' );
		}

		Audit::record( Events::WORK_ITEM_UPDATED, 'notice', [ 'work_item_id' => $id, 'quick_edit' => true ] );

		if ( '' !== $status && $status !== $from ) {
			if ( ! WorkItems::transition_status( $id, $status, get_current_user_id() ) ) {
				self::redirect_work_items( 'work-item-update-invalid' );
			}
			Audit::record( Events::WORK_ITEM_STATUS_CHANGED, 'notice', [ 'work_item_id' => $id, 'from' => $from, 'to' => $status, 'quick_edit' => true ] );
		}

		self::redirect_work_items( 'work-item-updated' );
	}

	public static function bulk_edit_work_items(): never {
		self::guard( 'cb_work_bulk_edit_work_items' );

		$ids   = self::posted_work_item_ids();
		$input = [];

		$priority = isset( $_POST['bulk_priority'] ) ? sanitize_key( wp_unslash( (string) $_POST['bulk_priority'] ) ) : '__keep';
		if ( '__keep' !== $priority ) {
			$input['priority'] = $priority;
		}

		if ( isset( $_POST['apply_due'] ) && '1' === sanitize_text_field( wp_unslash( (string) $_POST['apply_due'] ) ) ) {
			$input['due_on'] = isset( $_POST['bulk_due_on'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['bulk_due_on'] ) ) : '';
		}

		$work_type_id = isset( $_POST['bulk_work_type_id'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['bulk_work_type_id'] ) ) : '__keep';
		if ( '__keep' !== $work_type_id ) {
			$input['work_type_id'] = absint( $work_type_id );
		}

		$billing = isset( $_POST['bulk_billing_disposition'] ) ? sanitize_key( wp_unslash( (string) $_POST['bulk_billing_disposition'] ) ) : '__keep';
		if ( '__keep' !== $billing ) {
			$input['billing_disposition'] = '__clear' === $billing ? '' : $billing;
		}

		if ( isset( $_POST['apply_assignees'] ) && '1' === sanitize_text_field( wp_unslash( (string) $_POST['apply_assignees'] ) ) ) {
			$input['assigned_user_ids'] = isset( $_POST['bulk_assigned_user_ids'] )
				? wp_unslash( $_POST['bulk_assigned_user_ids'] )
				: '';
		}

		if ( [] === $ids || [] === $input ) {
			self::redirect_work_items( 'work-item-update-invalid' );
		}

		$updated = 0;
		foreach ( $ids as $id ) {
			if ( WorkItems::update( $id, $input ) ) {
				Audit::record( Events::WORK_ITEM_UPDATED, 'notice', [ 'work_item_id' => $id, 'bulk_edit' => true ] );
				$updated++;
			}
		}

		self::redirect_work_items( $updated > 0 ? 'work-item-updated' : 'work-item-update-invalid' );
	}

	/** @return int[] */
	private static function posted_work_item_ids(): array {
		$raw_ids = isset( $_POST['work_item_ids'] ) && is_array( $_POST['work_item_ids'] )
			? wp_unslash( $_POST['work_item_ids'] )
			: [];
		$ids = [];
		foreach ( $raw_ids as $raw_id ) {
			if ( ! is_scalar( $raw_id ) ) {
				continue;
			}
			$id = absint( $raw_id );
			if ( $id > 0 && ! in_array( $id, $ids, true ) ) {
				$ids[] = $id;
			}
		}
		return array_slice( $ids, 0, 100 );
	}

	public static function create_work_type(): never {
		self::guard( 'cb_work_create_work_type' );
		$input = isset( $_POST['work_type'] ) && is_array( $_POST['work_type'] ) ? wp_unslash( $_POST['work_type'] ) : [];
		$id = WorkTypes::create( $input );
		if ( $id > 0 ) {
			Audit::record( Events::WORK_TYPE_CREATED, 'notice', [ 'work_type_id' => $id ] );
			self::redirect_work_types( 'work-type-created' );
		}
		self::redirect_work_types( 'work-type-invalid' );
	}

	public static function toggle_work_type(): never {
		$id = isset( $_POST['work_type_id'] ) ? absint( $_POST['work_type_id'] ) : 0;
		self::guard( 'cb_work_toggle_work_type_' . $id );
		$active = isset( $_POST['active'] ) && '1' === sanitize_text_field( wp_unslash( (string) $_POST['active'] ) );
		if ( $id > 0 && WorkTypes::set_active( $id, $active ) ) {
			Audit::record( Events::WORK_TYPE_STATUS_CHANGED, 'notice', [ 'work_type_id' => $id, 'active' => $active ] );
			self::redirect_work_types( 'work-type-updated' );
		}
		self::redirect_work_types( 'work-type-invalid' );
	}

	private static function guard( string $nonce_action ): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Work.', 'core-blueprint-work' ) );
		}
		check_admin_referer( $nonce_action );
	}

	private static function redirect_work_items( string $notice ): never {
		$return_state = isset( $_POST['return_state'] ) && is_array( $_POST['return_state'] )
			? wp_unslash( $_POST['return_state'] )
			: [];
		$state = WorkItemViewState::from_request( $return_state );
		$args  = WorkItemViewState::query_args( $state );
		$args['cb-work-notice'] = sanitize_key( $notice );
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	private static function redirect_work_types( string $notice ): never {
		wp_safe_redirect( add_query_arg(
			[ 'page' => Menu::WORK_TYPES_SLUG, 'cb-work-notice' => sanitize_key( $notice ) ],
			admin_url( 'admin.php' )
		) );
		exit;
	}
}

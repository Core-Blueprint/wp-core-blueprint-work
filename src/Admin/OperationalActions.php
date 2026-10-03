<?php
declare(strict_types=1);

namespace CB\Work\Admin;

use CoreBlueprint\Core\Governance\Audit;
use CB\Work\Capabilities;
use CB\Work\Governance\Events;
use CB\Work\Repository\WorkItems;
use CB\Work\Repository\WorkTypes;

defined( 'ABSPATH' ) || exit;

final class OperationalActions {
	public static function init(): void {
		add_action( 'admin_post_cb_work_transition_work_item', [ self::class, 'transition_work_item' ] );
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

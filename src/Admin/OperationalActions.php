<?php
declare(strict_types=1);

namespace CB\Work\Admin;

use CB\Core\Governance\Audit;
use CB\Work\Capabilities;
use CB\Work\Governance\Events;
use CB\Work\Repository\Projects;
use CB\Work\Repository\WorkItems;

defined( 'ABSPATH' ) || exit;

final class OperationalActions {
	public static function init(): void {
		add_action( 'admin_post_cb_work_create_project', [ self::class, 'create_project' ] );
		add_action( 'admin_post_cb_work_create_work_item', [ self::class, 'create_work_item' ] );
		add_action( 'admin_post_cb_work_transition_work_item', [ self::class, 'transition_work_item' ] );
	}

	public static function create_project(): never {
		self::guard( 'cb_work_create_project' );
		$input = isset( $_POST['project'] ) && is_array( $_POST['project'] ) ? wp_unslash( $_POST['project'] ) : [];
		$input['created_by'] = get_current_user_id();
		$id = Projects::create( $input );
		if ( $id > 0 ) {
			Audit::record( Events::PROJECT_CREATED, 'notice', [ 'project_id' => $id ] );
			self::redirect( Menu::PROJECTS_SLUG, 'project-created' );
		}
		self::redirect( Menu::PROJECTS_SLUG, 'project-invalid' );
	}

	public static function create_work_item(): never {
		self::guard( 'cb_work_create_work_item' );
		$input = isset( $_POST['work_item'] ) && is_array( $_POST['work_item'] ) ? wp_unslash( $_POST['work_item'] ) : [];
		$input['created_by'] = get_current_user_id();
		$id = WorkItems::create( $input );
		if ( $id > 0 ) {
			Audit::record( Events::WORK_ITEM_CREATED, 'notice', [ 'work_item_id' => $id ] );
			self::redirect( Menu::WORK_ITEMS_SLUG, 'work-item-created' );
		}
		self::redirect( Menu::WORK_ITEMS_SLUG, 'work-item-invalid' );
	}

	public static function transition_work_item(): never {
		$id = isset( $_POST['work_item_id'] ) ? absint( $_POST['work_item_id'] ) : 0;
		self::guard( 'cb_work_transition_work_item_' . $id );
		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( (string) $_POST['status'] ) ) : '';
		$item = WorkItems::get( $id );
		$from = is_array( $item ) ? (string) ( $item['status'] ?? '' ) : '';
		if ( $id > 0 && WorkItems::transition_status( $id, $status, get_current_user_id() ) ) {
			Audit::record( Events::WORK_ITEM_STATUS_CHANGED, 'notice', [ 'work_item_id' => $id, 'from' => $from, 'to' => $status ] );
			self::redirect( Menu::WORK_ITEMS_SLUG, 'work-item-transitioned' );
		}
		self::redirect( Menu::WORK_ITEMS_SLUG, 'work-item-transition-invalid' );
	}

	private static function guard( string $nonce_action ): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Work.', 'core-blueprint-work' ) );
		}
		check_admin_referer( $nonce_action );
	}

	private static function redirect( string $page, string $notice ): never {
		wp_safe_redirect( add_query_arg( [ 'page' => $page, 'cb-work-notice' => sanitize_key( $notice ) ], admin_url( 'admin.php' ) ) );
		exit;
	}
}

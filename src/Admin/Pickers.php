<?php
declare(strict_types=1);

namespace CB\Work\Admin;

use CB\Core\UI\Assets;
use CB\Core\UI\ObjectPicker;
use CB\Work\Capabilities;
use CB\Work\Content\PostTypes;
use CB\Work\Integration\CRMCustomers;

defined( 'ABSPATH' ) || exit;

final class Pickers {
	private const NONCE_ACTION = 'cb_work_object_picker';

	public static function init(): void {
		add_action( 'wp_ajax_cb_work_search_customers', [ self::class, 'search_customers' ] );
		add_action( 'wp_ajax_cb_work_search_users', [ self::class, 'search_users' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue' ] );
	}

	public static function enqueue(): void {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}
		$page = isset( $_GET['page'] ) ? sanitize_key( (string) wp_unslash( $_GET['page'] ) ) : '';
		if (
			! in_array( (string) $screen->post_type, [ PostTypes::PROJECT, PostTypes::WORK_ITEM ], true )
			&& ! in_array( $page, [ Menu::WORK_ITEMS_SLUG, Menu::RECURRENCE_SLUG ], true )
		) {
			return;
		}
		Assets::enqueue_object_picker();
	}

	/** @param array{id:int|string,label:string,meta:string}|null $selected */
	public static function customer( string $name, string $id, ?array $selected = null ): void {
		if ( ! CRMCustomers::available() ) {
			echo '<p class="description">' . esc_html__( 'Activate Core Blueprint CRM to link a customer. Work Items and Projects remain usable without CRM.', 'core-blueprint-work' ) . '</p>';
			return;
		}
		echo ObjectPicker::render( [
			'name'          => $name,
			'id'            => $id,
			'multiple'      => false,
			'action'        => 'cb_work_search_customers',
			'nonce'         => wp_create_nonce( self::NONCE_ACTION ),
			'selected'      => null === $selected ? [] : [ $selected ],
			'placeholder'   => __( 'Search contacts and organizations…', 'core-blueprint-work' ),
			'empty_message' => __( 'No matching CRM customers found.', 'core-blueprint-work' ),
		] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Base ObjectPicker returns escaped markup.
	}

	public static function assignee( string $name, string $id, int $user_id = 0 ): void {
		self::render_user_picker( $name, $id, $user_id > 0 ? [ $user_id ] : [], false );
	}

	/** @param int[] $user_ids */
	public static function assignees( string $name, string $id, array $user_ids = [] ): void {
		self::render_user_picker( $name, $id, $user_ids, true );
	}

	public static function search_customers(): never {
		self::guard_ajax();
		$term = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['search'] ) ) : '';
		$items = CRMCustomers::search( $term, 20 );
		if ( is_wp_error( $items ) ) {
			wp_send_json_error( [ 'message' => $items->get_error_message() ], 403 );
		}
		wp_send_json_success( [ 'items' => $items ] );
	}

	public static function search_users(): never {
		self::guard_ajax();
		$term = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['search'] ) ) : '';
		if ( strlen( $term ) < 2 ) {
			wp_send_json_success( [ 'items' => [] ] );
		}
		$users = get_users( [
			'number'         => 20,
			'orderby'        => 'display_name',
			'order'          => 'ASC',
			'search'         => '*' . $term . '*',
			'search_columns' => [ 'user_login', 'user_nicename', 'user_email', 'display_name' ],
			'fields'         => [ 'ID', 'display_name', 'user_login' ],
		] );
		$items = [];
		foreach ( $users as $user ) {
			$items[] = [ 'id' => (int) $user->ID, 'label' => (string) $user->display_name, 'meta' => (string) $user->user_login ];
		}
		wp_send_json_success( [ 'items' => $items ] );
	}

	/** @param int[] $user_ids */
	private static function render_user_picker( string $name, string $id, array $user_ids, bool $multiple ): void {
		$selected = [];
		foreach ( $user_ids as $user_id ) {
			$user = get_userdata( (int) $user_id );
			if ( ! $user ) {
				continue;
			}
			$selected[] = [
				'id'    => (int) $user->ID,
				'label' => (string) $user->display_name,
				'meta'  => (string) $user->user_login,
			];
		}
		echo ObjectPicker::render( [
			'name'          => $name,
			'id'            => $id,
			'multiple'      => $multiple,
			'action'        => 'cb_work_search_users',
			'nonce'         => wp_create_nonce( self::NONCE_ACTION ),
			'selected'      => $selected,
			'placeholder'   => __( 'Search WordPress users…', 'core-blueprint-work' ),
			'empty_message' => __( 'No matching users found.', 'core-blueprint-work' ),
		] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Base ObjectPicker returns escaped markup.
	}

	private static function guard_ajax(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission to manage Work.', 'core-blueprint-work' ) ], 403 );
		}
		check_ajax_referer( self::NONCE_ACTION );
	}
}

<?php
declare(strict_types=1);

namespace CB\Work\Admin;

use CB\Work\Capabilities;
use CB\Work\Content\PostTypes;

defined( 'ABSPATH' ) || exit;

final class Menu {
	public const TOP_LEVEL_SLUG      = 'core-blueprint-work';
	public const WORK_ITEMS_SLUG     = 'core-blueprint-work-items';
	public const RECURRENCE_SLUG     = 'core-blueprint-work-recurrence';
	public const WORK_TYPES_SLUG     = 'core-blueprint-work-types';
	public const CONTEXT_OVERVIEW    = 'overview';
	public const CONTEXT_WORK_ITEMS  = 'work_items';
	public const CONTEXT_RECURRENCE  = 'recurrence';
	public const CONTEXT_PROJECTS    = 'projects';
	public const CONTEXT_SERVICES    = 'services';
	public const CONTEXT_WORK_TYPES  = 'work_types';

	public static function init(): void {
		add_action( 'admin_menu', [ self::class, 'register' ], 5 );
		add_filter( 'parent_file', [ self::class, 'parent_file' ] );
		add_filter( 'submenu_file', [ self::class, 'submenu_file' ], 10, 2 );
	}

	public static function register(): void {
		add_menu_page( __( 'Work', 'core-blueprint-work' ), __( 'Work', 'core-blueprint-work' ), Capabilities::MANAGE, self::TOP_LEVEL_SLUG, [ Operations::class, 'render_overview' ], 'dashicons-clipboard', 26.5 );
		add_submenu_page( self::TOP_LEVEL_SLUG, __( 'Overview', 'core-blueprint-work' ), __( 'Overview', 'core-blueprint-work' ), Capabilities::MANAGE, self::TOP_LEVEL_SLUG, [ Operations::class, 'render_overview' ], 5 );
		add_submenu_page( self::TOP_LEVEL_SLUG, __( 'Work Items', 'core-blueprint-work' ), __( 'Work Items', 'core-blueprint-work' ), Capabilities::MANAGE, self::WORK_ITEMS_SLUG, [ Operations::class, 'render_work_items' ], 10 );
		add_submenu_page( self::TOP_LEVEL_SLUG, __( 'Recurring Work', 'core-blueprint-work' ), __( 'Recurring Work', 'core-blueprint-work' ), Capabilities::MANAGE, self::RECURRENCE_SLUG, [ Recurrence::class, 'render' ], 15 );
		add_submenu_page( self::TOP_LEVEL_SLUG, __( 'Projects', 'core-blueprint-work' ), __( 'Projects', 'core-blueprint-work' ), Capabilities::MANAGE, self::projects_path(), '', 20 );
		add_submenu_page( self::TOP_LEVEL_SLUG, __( 'Services', 'core-blueprint-work' ), __( 'Services', 'core-blueprint-work' ), Capabilities::MANAGE, self::services_path(), '', 30 );
		add_submenu_page( self::TOP_LEVEL_SLUG, __( 'Work Types', 'core-blueprint-work' ), __( 'Work Types', 'core-blueprint-work' ), Capabilities::MANAGE, self::WORK_TYPES_SLUG, [ Operations::class, 'render_work_types' ], 40 );
	}

	public static function projects_path(): string {
		return 'edit.php?post_type=' . PostTypes::PROJECT;
	}

	public static function services_path(): string {
		return 'edit.php?post_type=' . PostTypes::SERVICE;
	}

	public static function projects_url(): string {
		return admin_url( self::projects_path() );
	}

	public static function new_work_item_url( int $project_id = 0 ): string {
		$args = [ 'post_type' => PostTypes::WORK_ITEM ];
		if ( $project_id > 0 ) {
			$args['project_id'] = $project_id;
		}
		return add_query_arg( $args, admin_url( 'post-new.php' ) );
	}

	public static function edit_work_item_url( int $work_item_id ): string {
		return add_query_arg(
			[ 'post' => max( 0, $work_item_id ), 'action' => 'edit' ],
			admin_url( 'post.php' )
		);
	}

	public static function screen_context( ?\WP_Screen $screen = null ): string {
		$screen = $screen ?? get_current_screen();
		if ( ! $screen ) {
			return '';
		}
		$page = isset( $_GET['page'] ) ? sanitize_key( (string) wp_unslash( $_GET['page'] ) ) : '';
		if ( self::TOP_LEVEL_SLUG === $page ) {
			return self::CONTEXT_OVERVIEW;
		}
		if ( self::WORK_ITEMS_SLUG === $page || PostTypes::WORK_ITEM === (string) $screen->post_type ) {
			return self::CONTEXT_WORK_ITEMS;
		}
		if ( self::RECURRENCE_SLUG === $page ) {
			return self::CONTEXT_RECURRENCE;
		}
		if ( self::WORK_TYPES_SLUG === $page ) {
			return self::CONTEXT_WORK_TYPES;
		}
		if ( PostTypes::PROJECT === (string) $screen->post_type ) {
			return self::CONTEXT_PROJECTS;
		}
		return PostTypes::SERVICE === (string) $screen->post_type ? self::CONTEXT_SERVICES : '';
	}

	public static function parent_file( string $parent_file ): string {
		return '' !== self::screen_context() ? self::TOP_LEVEL_SLUG : $parent_file;
	}

	public static function submenu_file( mixed $submenu_file, mixed $parent_file = '' ): mixed {
		unset( $parent_file );
		$slug = self::submenu_slug( self::screen_context() );
		return '' !== $slug ? $slug : $submenu_file;
	}

	private static function submenu_slug( string $context ): string {
		return match ( $context ) {
			self::CONTEXT_OVERVIEW   => self::TOP_LEVEL_SLUG,
			self::CONTEXT_WORK_ITEMS => self::WORK_ITEMS_SLUG,
			self::CONTEXT_RECURRENCE => self::RECURRENCE_SLUG,
			self::CONTEXT_PROJECTS   => self::projects_path(),
			self::CONTEXT_SERVICES   => self::services_path(),
			self::CONTEXT_WORK_TYPES => self::WORK_TYPES_SLUG,
			default                  => '',
		};
	}
}

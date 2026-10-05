<?php
declare(strict_types=1);

namespace CB\Work\Admin;

use CB\Work\Capabilities;
use CB\Work\Content\PostTypes;

defined( 'ABSPATH' ) || exit;

final class Menu {
	public const TOP_LEVEL_SLUG        = 'core-blueprint-work';
	public const WORK_ITEMS_SLUG       = 'core-blueprint-work-items';
	public const PROJECT_WORKSPACE_SLUG = 'core-blueprint-work-project';
	public const PROJECT_IMPORT_SLUG    = 'core-blueprint-work-project-import';
	public const RECURRENCE_SLUG       = 'core-blueprint-work-recurrence';
	public const TIME_SLUG             = 'core-blueprint-work-time';
	public const WORK_TYPES_SLUG       = 'core-blueprint-work-types';
	public const CONTEXT_OVERVIEW      = 'overview';
	public const CONTEXT_WORK_ITEMS    = 'work_items';
	public const CONTEXT_RECURRENCE    = 'recurrence';
	public const CONTEXT_TIME          = 'time';
	public const CONTEXT_PROJECTS      = 'projects';
	public const CONTEXT_SERVICES      = 'services';
	public const CONTEXT_WORK_TYPES    = 'work_types';

	public static function init(): void {
		add_action( 'admin_menu', [ self::class, 'register' ], 5 );
		add_action( 'current_screen', [ self::class, 'register_admin_theme_screen' ] );
		add_filter( 'parent_file', [ self::class, 'parent_file' ] );
		add_filter( 'submenu_file', [ self::class, 'submenu_file' ], 10, 2 );
	}

	public static function register(): void {
		$can_manage = current_user_can( Capabilities::MANAGE );
		$can_track  = $can_manage || current_user_can( Capabilities::TRACK_TIME );
		if ( ! $can_track ) {
			return;
		}

		if ( $can_manage ) {
			add_menu_page( __( 'Work', 'core-blueprint-work' ), __( 'Work', 'core-blueprint-work' ), Capabilities::MANAGE, self::TOP_LEVEL_SLUG, [ Overview::class, 'render' ], 'dashicons-clipboard', 26.5 );
			add_submenu_page( self::TOP_LEVEL_SLUG, __( 'Overview', 'core-blueprint-work' ), __( 'Overview', 'core-blueprint-work' ), Capabilities::MANAGE, self::TOP_LEVEL_SLUG, [ Overview::class, 'render' ], 5 );
			add_submenu_page( self::TOP_LEVEL_SLUG, __( 'Work Items', 'core-blueprint-work' ), __( 'Work Items', 'core-blueprint-work' ), Capabilities::MANAGE, self::WORK_ITEMS_SLUG, [ Operations::class, 'render_work_items' ], 10 );
			add_submenu_page( self::TOP_LEVEL_SLUG, __( 'Recurring Work', 'core-blueprint-work' ), __( 'Recurring Work', 'core-blueprint-work' ), Capabilities::MANAGE, self::RECURRENCE_SLUG, [ Recurrence::class, 'render' ], 15 );
			add_submenu_page( self::TOP_LEVEL_SLUG, __( 'Time', 'core-blueprint-work' ), __( 'Time', 'core-blueprint-work' ), Capabilities::MANAGE, self::TIME_SLUG, [ Time::class, 'render' ], 17 );
			add_submenu_page( self::TOP_LEVEL_SLUG, __( 'Projects', 'core-blueprint-work' ), __( 'Projects', 'core-blueprint-work' ), Capabilities::MANAGE, self::projects_path(), '', 20 );
			add_submenu_page( self::TOP_LEVEL_SLUG, __( 'Services', 'core-blueprint-work' ), __( 'Services', 'core-blueprint-work' ), Capabilities::MANAGE, self::services_path(), '', 30 );
			add_submenu_page( self::TOP_LEVEL_SLUG, __( 'Work Types', 'core-blueprint-work' ), __( 'Work Types', 'core-blueprint-work' ), Capabilities::MANAGE, self::WORK_TYPES_SLUG, [ Operations::class, 'render_work_types' ], 40 );
			add_submenu_page( null, __( 'Project Workspace', 'core-blueprint-work' ), __( 'Project Workspace', 'core-blueprint-work' ), Capabilities::MANAGE, self::PROJECT_WORKSPACE_SLUG, [ ProjectWorkspace::class, 'render' ] );
			add_submenu_page( null, __( 'Import Work Project', 'core-blueprint-work' ), __( 'Import Work Project', 'core-blueprint-work' ), Capabilities::MANAGE, self::PROJECT_IMPORT_SLUG, [ ProjectDataExchange::class, 'render_import' ] );
			return;
		}

		add_menu_page( __( 'Work', 'core-blueprint-work' ), __( 'Work', 'core-blueprint-work' ), Capabilities::TRACK_TIME, self::TOP_LEVEL_SLUG, [ Time::class, 'render' ], 'dashicons-clipboard', 26.5 );
		add_submenu_page( self::TOP_LEVEL_SLUG, __( 'Time', 'core-blueprint-work' ), __( 'Time', 'core-blueprint-work' ), Capabilities::TRACK_TIME, self::TOP_LEVEL_SLUG, [ Time::class, 'render' ], 5 );
		add_submenu_page( null, __( 'Time', 'core-blueprint-work' ), __( 'Time', 'core-blueprint-work' ), Capabilities::TRACK_TIME, self::TIME_SLUG, [ Time::class, 'render' ] );
	}

	/**
	 * Declare Work-owned admin screens compatible with Base's global Admin Theme.
	 * Theme state and WordPress primitive presentation remain Base-owned; this is
	 * only the public compatibility declaration/hook point for the Work product.
	 */
	public static function register_admin_theme_screen( \WP_Screen $screen ): void {
		if ( '' === self::screen_context( $screen ) || ! class_exists( '\\CoreBlueprint\\Core\\UI\\AdminTheme' ) ) {
			return;
		}

		$hook_suffix = $GLOBALS['hook_suffix'] ?? '';
		if ( is_string( $hook_suffix ) && '' !== $hook_suffix ) {
			\CoreBlueprint\Core\UI\AdminTheme::register_screen( $hook_suffix );
		}
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

	public static function project_import_url(): string {
		return add_query_arg( [ 'page' => self::PROJECT_IMPORT_SLUG ], admin_url( 'admin.php' ) );
	}

	public static function project_workspace_url( int $project_id ): string {
		return add_query_arg(
			[ 'page' => self::PROJECT_WORKSPACE_SLUG, 'project_id' => max( 0, $project_id ) ],
			admin_url( 'admin.php' )
		);
	}

	/** @param array<string,int|string> $args */
	public static function time_url( array $args = [] ): string {
		$slug = current_user_can( Capabilities::MANAGE ) ? self::TIME_SLUG : self::TOP_LEVEL_SLUG;
		return add_query_arg( [ 'page' => $slug, ...$args ], admin_url( 'admin.php' ) );
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
			return current_user_can( Capabilities::MANAGE ) ? self::CONTEXT_OVERVIEW : self::CONTEXT_TIME;
		}
		if ( in_array( $page, [ self::PROJECT_WORKSPACE_SLUG, self::PROJECT_IMPORT_SLUG ], true ) ) {
			return self::CONTEXT_PROJECTS;
		}
		if ( self::WORK_ITEMS_SLUG === $page || PostTypes::WORK_ITEM === (string) $screen->post_type ) {
			return self::CONTEXT_WORK_ITEMS;
		}
		if ( self::RECURRENCE_SLUG === $page ) {
			return self::CONTEXT_RECURRENCE;
		}
		if ( self::TIME_SLUG === $page ) {
			return self::CONTEXT_TIME;
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
			self::CONTEXT_TIME       => current_user_can( Capabilities::MANAGE ) ? self::TIME_SLUG : self::TOP_LEVEL_SLUG,
			self::CONTEXT_PROJECTS   => self::projects_path(),
			self::CONTEXT_SERVICES   => self::services_path(),
			self::CONTEXT_WORK_TYPES => self::WORK_TYPES_SLUG,
			default                  => '',
		};
	}
}

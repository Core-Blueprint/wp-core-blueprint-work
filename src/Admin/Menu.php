<?php
declare(strict_types=1);

namespace CB\Work\Admin;

use CB\Work\Capabilities;
use CB\Work\Content\PostTypes;
defined( 'ABSPATH' ) || exit;

final class Menu {
	public const TOP_LEVEL_SLUG = 'core-blueprint-work';
	public const WORK_ITEMS_SLUG = 'core-blueprint-work-items';
	public const PROJECTS_SLUG = 'core-blueprint-work-projects';
	public const CONTEXT_OVERVIEW = 'overview';
	public const CONTEXT_WORK_ITEMS = 'work_items';
	public const CONTEXT_PROJECTS = 'projects';
	public const CONTEXT_SERVICES = 'services';

	public static function init(): void {
		add_action( 'admin_menu', [ self::class, 'register' ], 5 );
		add_filter( 'parent_file', [ self::class, 'parent_file' ] );
		add_filter( 'submenu_file', [ self::class, 'submenu_file' ], 10, 2 );
	}

	public static function register(): void {
		add_menu_page( __( 'Work', 'core-blueprint-work' ), __( 'Work', 'core-blueprint-work' ), Capabilities::MANAGE, self::TOP_LEVEL_SLUG, [ Operations::class, 'render_overview' ], 'dashicons-clipboard', 26.5 );
		add_submenu_page( self::TOP_LEVEL_SLUG, __( 'Overview', 'core-blueprint-work' ), __( 'Overview', 'core-blueprint-work' ), Capabilities::MANAGE, self::TOP_LEVEL_SLUG, [ Operations::class, 'render_overview' ], 5 );
		add_submenu_page( self::TOP_LEVEL_SLUG, __( 'Work Items', 'core-blueprint-work' ), __( 'Work Items', 'core-blueprint-work' ), Capabilities::MANAGE, self::WORK_ITEMS_SLUG, [ Operations::class, 'render_work_items' ], 10 );
		add_submenu_page( self::TOP_LEVEL_SLUG, __( 'Projects', 'core-blueprint-work' ), __( 'Projects', 'core-blueprint-work' ), Capabilities::MANAGE, self::PROJECTS_SLUG, [ Operations::class, 'render_projects' ], 20 );
		add_submenu_page( self::TOP_LEVEL_SLUG, __( 'Services', 'core-blueprint-work' ), __( 'Services', 'core-blueprint-work' ), Capabilities::MANAGE, 'edit.php?post_type=' . PostTypes::SERVICE, '', 30 );
	}

	public static function screen_context( ?\WP_Screen $screen = null ): string {
		$screen = $screen ?? get_current_screen();
		if ( ! $screen ) { return ''; }
		$page = isset( $_GET['page'] ) ? sanitize_key( (string) wp_unslash( $_GET['page'] ) ) : '';
		if ( self::TOP_LEVEL_SLUG === $page ) { return self::CONTEXT_OVERVIEW; }
		if ( self::WORK_ITEMS_SLUG === $page ) { return self::CONTEXT_WORK_ITEMS; }
		if ( self::PROJECTS_SLUG === $page ) { return self::CONTEXT_PROJECTS; }
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
			self::CONTEXT_OVERVIEW => self::TOP_LEVEL_SLUG,
			self::CONTEXT_WORK_ITEMS => self::WORK_ITEMS_SLUG,
			self::CONTEXT_PROJECTS => self::PROJECTS_SLUG,
			self::CONTEXT_SERVICES => 'edit.php?post_type=' . PostTypes::SERVICE,
			default => '',
		};
	}
}

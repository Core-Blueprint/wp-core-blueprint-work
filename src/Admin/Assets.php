<?php
declare(strict_types=1);

namespace CB\Work\Admin;
defined( 'ABSPATH' ) || exit;

/**
 * Work-owned wp-admin presentation assets.
 *
 * Base owns the global WordPress Admin Theme, theme state and shared primitive
 * presentation. Work only layers domain-specific workspace composition on top:
 * filters, Board layout, Calendar density and Work-specific empty states.
 */
final class Assets {
	private const STYLE_HANDLE  = 'cb-work-admin';
	private const SCRIPT_HANDLE = 'cb-work-admin';

	public static function init(): void {
		// Base fires this public hook after the canonical Admin Theme assets are
		// enqueued. Work therefore consumes the public integration contract rather
		// than coupling to Base's internal stylesheet handles or theme slugs.
		add_action( 'cb_admin_theme_enqueue', [ self::class, 'enqueue' ], 10, 4 );
	}

	public static function enqueue( string $hook_suffix = '', string $theme = '', string $mode = '', bool $registered = false ): void {
		unset( $hook_suffix, $theme, $mode, $registered );

		$page = isset( $_GET['page'] ) ? sanitize_key( (string) wp_unslash( $_GET['page'] ) ) : '';
		if ( Menu::WORK_ITEMS_SLUG !== $page ) {
			return;
		}

		self::enqueue_style();
		self::enqueue_script();
	}

	private static function enqueue_style(): void {
		$file = CB_WORK_DIR . 'assets/work-admin.css';
		if ( ! is_file( $file ) ) {
			return;
		}

		$modified = filemtime( $file );
		$version  = false === $modified ? CB_WORK_VERSION : (string) $modified;

		wp_enqueue_style(
			self::STYLE_HANDLE,
			CB_WORK_URL . 'assets/work-admin.css',
			[],
			$version
		);
	}

	private static function enqueue_script(): void {
		$file = CB_WORK_DIR . 'assets/work-admin.js';
		if ( ! is_file( $file ) ) {
			return;
		}

		$modified = filemtime( $file );
		$version  = false === $modified ? CB_WORK_VERSION : (string) $modified;

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			CB_WORK_URL . 'assets/work-admin.js',
			[],
			$version,
			true
		);

		wp_localize_script( self::SCRIPT_HANDLE, 'cbWorkAdminUx', [
			'filter'              => __( 'Filter', 'core-blueprint-work' ),
			'search'              => __( 'Search', 'core-blueprint-work' ),
			'moreFilters'         => __( 'More filters', 'core-blueprint-work' ),
			'lessFilters'         => __( 'Hide filters', 'core-blueprint-work' ),
			'activeFilters'       => __( 'Active filters', 'core-blueprint-work' ),
			'selected'            => __( 'Selected', 'core-blueprint-work' ),
			'today'               => __( 'Today', 'core-blueprint-work' ),
			'status'              => __( 'Status', 'core-blueprint-work' ),
			'project'             => __( 'Project', 'core-blueprint-work' ),
			'service'             => __( 'Service', 'core-blueprint-work' ),
			'customer'            => __( 'Customer', 'core-blueprint-work' ),
			'assignee'            => __( 'Assignee', 'core-blueprint-work' ),
			'priority'            => __( 'Priority', 'core-blueprint-work' ),
			'workType'            => __( 'Work Type', 'core-blueprint-work' ),
			'billing'             => __( 'Billing', 'core-blueprint-work' ),
			'sort'                => __( 'Sort', 'core-blueprint-work' ),
			'scheduled'           => __( 'Scheduled', 'core-blueprint-work' ),
			'due'                 => __( 'Due', 'core-blueprint-work' ),
			'to'                  => __( 'to', 'core-blueprint-work' ),
			'board'               => __( 'Board', 'core-blueprint-work' ),
			'noItemsYet'          => __( 'No Work Items yet.', 'core-blueprint-work' ),
			'noItemsYetDetail'    => __( 'Create your first Work Item to start planning and tracking customer work.', 'core-blueprint-work' ),
			'noMatchingItems'     => __( 'No Work Items match these filters.', 'core-blueprint-work' ),
			'noMatchingDetail'    => __( 'Adjust or clear the current filters to broaden this view.', 'core-blueprint-work' ),
			'planned'             => __( 'Planned', 'core-blueprint-work' ),
			'inProgress'          => __( 'In Progress', 'core-blueprint-work' ),
			'completed'           => __( 'Completed', 'core-blueprint-work' ),
			'skipped'             => __( 'Skipped', 'core-blueprint-work' ),
			'cancelled'           => __( 'Cancelled', 'core-blueprint-work' ),
			'emptyLane'           => __( 'No Work Items', 'core-blueprint-work' ),
		] );
	}
}
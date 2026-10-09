<?php
declare(strict_types=1);

namespace CB\Work\Admin;
defined( 'ABSPATH' ) || exit;

/**
 * Work-owned wp-admin presentation assets.
 *
 * Base owns the global WordPress Admin Theme, theme state and shared primitive
 * presentation. Work only layers domain-specific workspace composition on top:
 * shared Work navigation, filters, Board layout, Calendar density and
 * Work-specific empty states.
 */
final class Assets {
	private const STYLE_HANDLE             = 'cb-work-admin';
	private const WORKSPACE_STYLE_HANDLE   = 'cb-work-workspace';
	private const CONTEXT_SCRIPT_HANDLE      = 'cb-work-context';
	private const PROJECT_STYLE_HANDLE     = 'cb-work-project-workspace';
	private const PROJECT_LIST_SCRIPT_HANDLE = 'cb-work-projects-list';
	private const OVERVIEW_STYLE_HANDLE    = 'cb-work-overview';
	private const QUICK_ADD_STYLE_HANDLE   = 'cb-work-quick-add';
	private const FAST_PATH_STYLE_HANDLE   = 'cb-work-fast-paths';
	private const TOOLBAR_GOLDEN_STYLE_HANDLE = 'cb-work-toolbar-golden';
	private const REFINEMENT_STYLE_HANDLE  = 'cb-work-items-refinement';
	private const SCRIPT_HANDLE            = 'cb-work-admin';
	private const QUICK_ADD_SCRIPT_HANDLE  = 'cb-work-quick-add';
	private const FAST_PATH_SCRIPT_HANDLE  = 'cb-work-fast-paths';
	private const REFINEMENT_SCRIPT_HANDLE = 'cb-work-items-refinement';
	private const CALENDAR_SCRIPT_HANDLE   = '@cb-work/work-calendar';
	private const VIEW_PREFERENCES_SCRIPT_HANDLE = '@cb-work/work-view-preferences';

	public static function init(): void {
		add_action( 'core_blueprint_admin_theme_enqueue', [ self::class, 'enqueue' ], 10, 4 );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_project_list_action' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_toast_feedback' ] );
	}

	public static function enqueue( string $hook_suffix = '', string $theme = '', string $mode = '', bool $registered = false ): void {
		unset( $hook_suffix, $theme, $mode, $registered );

		$context = Menu::screen_context();
		if ( '' === $context ) {
			return;
		}

		self::enqueue_workspace_style();
		self::enqueue_context_script();
		self::enqueue_quick_add_assets();
		$page = isset( $_GET['page'] ) ? sanitize_key( (string) wp_unslash( $_GET['page'] ) ) : '';
		if ( Menu::PROJECT_WORKSPACE_SLUG === $page ) {
			self::enqueue_project_workspace_style();
		}
		if ( Menu::CONTEXT_OVERVIEW === $context ) {
			self::enqueue_overview_style();
		}

		if ( Menu::CONTEXT_WORK_ITEMS !== $context ) {
			return;
		}

		self::enqueue_style();
		self::enqueue_script();
		\CoreBlueprint\Core\UI\Assets::enqueue_segmented_control();
		self::enqueue_refinement_assets();
		self::enqueue_fast_path_assets();
		self::enqueue_toolbar_golden_style();
		self::enqueue_reorder_assets();
		self::enqueue_view_preferences_assets();
		if ( WorkItemViewState::VIEW_CALENDAR === WorkItemViewPreferences::resolve_request_view( $_GET, get_current_user_id() ) ) {
			self::enqueue_calendar_assets();
		}
	}

	/** Shared Base Toast Foundation on Work-owned admin screens only. */
	public static function enqueue_toast_feedback(): void {
		$page = isset( $_GET['page'] ) && is_string( $_GET['page'] )
			? sanitize_key( (string) wp_unslash( $_GET['page'] ) ) : '';
		$extension = isset( $_GET['extension'] ) && is_string( $_GET['extension'] )
			? sanitize_key( (string) wp_unslash( $_GET['extension'] ) ) : '';
		$work_settings = \CoreBlueprint\Core\Admin\Pages\Settings::SLUG === $page
			&& \CB\Work\Integration\Suite::EXTENSION_ID === $extension;
		if ( '' === Menu::screen_context() && ! $work_settings ) {
			return;
		}
		$file = CB_WORK_DIR . 'assets/work-toast.js';
		if ( ! is_file( $file ) ) {
			return; // The original server notice remains available.
		}
		// The style prints in wp-admin's head before PHP renders notices.
		// Its scripting-aware fallback reveals notices if the module never loads.
		$handoff_css = CB_WORK_DIR . 'assets/work-toast-handoff.css';
		if ( is_file( $handoff_css ) ) {
			$css_modified = filemtime( $handoff_css );
			wp_enqueue_style(
				'cb-work-toast-handoff',
				CB_WORK_URL . 'assets/work-toast-handoff.css',
				[],
				false === $css_modified ? CB_WORK_VERSION : (string) $css_modified
			);
		}
		// Run in the admin head: hide only Work's transient PHP notices
		// before first paint, and restore them if the Toast module fails.
		if ( is_file( $handoff_css ) ) {
			wp_register_script( 'cb-work-toast-handoff', false, [], CB_WORK_VERSION, false );
			wp_enqueue_script( 'cb-work-toast-handoff' );
			wp_add_inline_script(
				'cb-work-toast-handoff',
				'document.documentElement.classList.add("cb-work-toast-pending");'
				. 'window.setTimeout(function(){document.documentElement.classList.remove("cb-work-toast-pending");},3000);',
				'before'
			);
		}
		\CoreBlueprint\Core\UI\Assets::enqueue_toasts(
			\CoreBlueprint\Core\UI\Assets::TOAST_PRESENTATION_CORE
		);
		$modified = filemtime( $file );
		wp_enqueue_script_module(
			'@cb-work/toast',
			CB_WORK_URL . 'assets/work-toast.js',
			[ '@cb-core/toast' ],
			false === $modified ? CB_WORK_VERSION : (string) $modified
		);
	}

	public static function enqueue_project_list_action(): void {
		$screen = get_current_screen();
		if (
			! $screen
			|| 'edit' !== (string) $screen->base
			|| \CB\Work\Content\PostTypes::PROJECT !== (string) $screen->post_type
			|| ! current_user_can( \CB\Work\Capabilities::MANAGE )
			|| ! ProjectDataExchange::available()
		) {
			return;
		}

		$file = CB_WORK_DIR . 'assets/projects-list.js';
		if ( ! is_file( $file ) ) {
			return;
		}
		$modified = filemtime( $file );
		$version  = false === $modified ? CB_WORK_VERSION : (string) $modified;
		wp_enqueue_script( self::PROJECT_LIST_SCRIPT_HANDLE, CB_WORK_URL . 'assets/projects-list.js', [], $version, true );
		wp_localize_script( self::PROJECT_LIST_SCRIPT_HANDLE, 'cbWorkProjectsList', [
			'importUrl'   => ProjectDataExchange::import_url(),
			'importLabel' => __( 'Import project', 'core-blueprint-work' ),
		] );
	}

	private static function enqueue_context_script(): void {
		$file = CB_WORK_DIR . 'assets/work-context.js';
		if ( ! is_file( $file ) ) {
			return;
		}
		$modified = filemtime( $file );
		$version  = false === $modified ? CB_WORK_VERSION : (string) $modified;
		wp_enqueue_script( self::CONTEXT_SCRIPT_HANDLE, CB_WORK_URL . 'assets/work-context.js', [], $version, true );
	}

	private static function enqueue_workspace_style(): void {
		$file = CB_WORK_DIR . 'assets/work-workspace.css';
		if ( ! is_file( $file ) ) {
			return;
		}
		$modified = filemtime( $file );
		$version  = false === $modified ? CB_WORK_VERSION : (string) $modified;
		wp_enqueue_style( self::WORKSPACE_STYLE_HANDLE, CB_WORK_URL . 'assets/work-workspace.css', [], $version );
	}

	private static function enqueue_project_workspace_style(): void {
		$file = CB_WORK_DIR . 'assets/project-workspace.css';
		if ( ! is_file( $file ) ) {
			return;
		}
		$modified = filemtime( $file );
		$version  = false === $modified ? CB_WORK_VERSION : (string) $modified;
		wp_enqueue_style( self::PROJECT_STYLE_HANDLE, CB_WORK_URL . 'assets/project-workspace.css', [ self::WORKSPACE_STYLE_HANDLE ], $version );
	}

	private static function enqueue_overview_style(): void {
		$file = CB_WORK_DIR . 'assets/work-overview.css';
		if ( ! is_file( $file ) ) {
			return;
		}
		$modified = filemtime( $file );
		$version  = false === $modified ? CB_WORK_VERSION : (string) $modified;
		wp_enqueue_style( self::OVERVIEW_STYLE_HANDLE, CB_WORK_URL . 'assets/work-overview.css', [ self::WORKSPACE_STYLE_HANDLE ], $version );
	}

	private static function enqueue_quick_add_assets(): void {
		$style = CB_WORK_DIR . 'assets/work-quick-add.css';
		if ( is_file( $style ) ) {
			$modified = filemtime( $style );
			$version  = false === $modified ? CB_WORK_VERSION : (string) $modified;
			wp_enqueue_style( self::QUICK_ADD_STYLE_HANDLE, CB_WORK_URL . 'assets/work-quick-add.css', [ self::WORKSPACE_STYLE_HANDLE ], $version );
		}

		$script = CB_WORK_DIR . 'assets/work-quick-add.js';
		if ( ! is_file( $script ) ) {
			return;
		}
		$modified = filemtime( $script );
		$version  = false === $modified ? CB_WORK_VERSION : (string) $modified;
		wp_enqueue_script( self::QUICK_ADD_SCRIPT_HANDLE, CB_WORK_URL . 'assets/work-quick-add.js', [], $version, true );
	}

	private static function enqueue_style(): void {
		$file = CB_WORK_DIR . 'assets/work-admin.css';
		if ( ! is_file( $file ) ) {
			return;
		}
		$modified = filemtime( $file );
		$version  = false === $modified ? CB_WORK_VERSION : (string) $modified;
		wp_enqueue_style( self::STYLE_HANDLE, CB_WORK_URL . 'assets/work-admin.css', [ self::WORKSPACE_STYLE_HANDLE ], $version );
	}

	private static function enqueue_script(): void {
		$file = CB_WORK_DIR . 'assets/work-admin.js';
		if ( ! is_file( $file ) ) {
			return;
		}
		$modified = filemtime( $file );
		$version  = false === $modified ? CB_WORK_VERSION : (string) $modified;
		wp_enqueue_script( self::SCRIPT_HANDLE, CB_WORK_URL . 'assets/work-admin.js', [], $version, true );

		wp_localize_script( self::SCRIPT_HANDLE, 'cbWorkAdminUx', [
			'filter'               => __( 'Filter', 'core-blueprint-work' ),
			'filters'              => __( 'Filters', 'core-blueprint-work' ),
			'search'               => __( 'Search', 'core-blueprint-work' ),
			'moreFilters'          => __( 'More filters', 'core-blueprint-work' ),
			'lessFilters'          => __( 'Hide filters', 'core-blueprint-work' ),
			'activeFilters'        => __( 'Active filters', 'core-blueprint-work' ),
			'selected'             => __( 'Selected', 'core-blueprint-work' ),
			'today'                => __( 'Today', 'core-blueprint-work' ),
			'status'               => __( 'Status', 'core-blueprint-work' ),
			'project'              => __( 'Project', 'core-blueprint-work' ),
			'service'              => __( 'Service', 'core-blueprint-work' ),
			'customer'             => __( 'Customer', 'core-blueprint-work' ),
			'assignee'             => __( 'Assignee', 'core-blueprint-work' ),
			'priority'             => __( 'Priority', 'core-blueprint-work' ),
			'workType'             => __( 'Work Type', 'core-blueprint-work' ),
			'workContext'          => __( 'Work context', 'core-blueprint-work' ),
			'billing'              => __( 'Billing', 'core-blueprint-work' ),
			'sort'                 => __( 'Sort', 'core-blueprint-work' ),
			'scheduled'            => __( 'Scheduled', 'core-blueprint-work' ),
			'due'                  => __( 'Due', 'core-blueprint-work' ),
			'to'                   => __( 'to', 'core-blueprint-work' ),
			'board'                => __( 'Board', 'core-blueprint-work' ),
			'noItemsYet'           => __( 'No Work Items yet.', 'core-blueprint-work' ),
			'noItemsYetDetail'     => __( 'Create your first Work Item to start planning and tracking customer work.', 'core-blueprint-work' ),
			'noMatchingItems'      => __( 'No Work Items match these filters.', 'core-blueprint-work' ),
			'noMatchingDetail'     => __( 'Adjust or clear the current filters to broaden this view.', 'core-blueprint-work' ),
			'noScheduledThisMonth' => __( 'No scheduled work or deadlines this month.', 'core-blueprint-work' ),
			'planned'              => __( 'Planned', 'core-blueprint-work' ),
			'inProgress'           => __( 'In Progress', 'core-blueprint-work' ),
			'blocked'              => __( 'Blocked', 'core-blueprint-work' ),
			'completed'            => __( 'Completed', 'core-blueprint-work' ),
			'skipped'              => __( 'Skipped', 'core-blueprint-work' ),
			'cancelled'            => __( 'Cancelled', 'core-blueprint-work' ),
			'showClosed'           => __( 'Show closed', 'core-blueprint-work' ),
			'hideClosed'           => __( 'Hide closed', 'core-blueprint-work' ),
			'emptyLane'            => __( 'No Work Items', 'core-blueprint-work' ),
		] );
	}

	private static function enqueue_refinement_assets(): void {
		$style = CB_WORK_DIR . 'assets/work-items-refinement.css';
		if ( is_file( $style ) ) {
			$modified = filemtime( $style );
			$version  = false === $modified ? CB_WORK_VERSION : (string) $modified;
			wp_enqueue_style( self::REFINEMENT_STYLE_HANDLE, CB_WORK_URL . 'assets/work-items-refinement.css', [ self::STYLE_HANDLE ], $version );
		}

		$script = CB_WORK_DIR . 'assets/work-items-refinement.js';
		if ( ! is_file( $script ) ) {
			return;
		}
		$modified = filemtime( $script );
		$version  = false === $modified ? CB_WORK_VERSION : (string) $modified;
		wp_enqueue_script( self::REFINEMENT_SCRIPT_HANDLE, CB_WORK_URL . 'assets/work-items-refinement.js', [ self::SCRIPT_HANDLE ], $version, true );
	}

	private static function enqueue_reorder_assets(): void {
		\CoreBlueprint\Core\UI\Assets::enqueue_reorder();

		$file = CB_WORK_DIR . 'assets/work-items-reorder.js';
		if ( ! is_file( $file ) ) {
			return;
		}
		$modified = filemtime( $file );
		$version  = false === $modified ? CB_WORK_VERSION : (string) $modified;
		wp_enqueue_script_module(
			'@cb-work/work-items-reorder',
			CB_WORK_URL . 'assets/work-items-reorder.js',
			[ '@cb-core/reorder' ],
			$version
		);
	}


	private static function enqueue_view_preferences_assets(): void {
		\CoreBlueprint\Core\UI\Assets::enqueue_modals( \CoreBlueprint\Core\UI\Assets::MODAL_PRESENTATION_CORE );

		$file = CB_WORK_DIR . 'assets/work-view-preferences.js';
		if ( ! is_file( $file ) ) {
			return;
		}
		$modified = filemtime( $file );
		$version  = false === $modified ? CB_WORK_VERSION : (string) $modified;
		wp_enqueue_script_module(
			self::VIEW_PREFERENCES_SCRIPT_HANDLE,
			CB_WORK_URL . 'assets/work-view-preferences.js',
			[ '@cb-core/modal', '@cb-core/reorder' ],
			$version
		);
	}

	private static function enqueue_calendar_assets(): void {
		\CoreBlueprint\Core\UI\Assets::enqueue_modals( \CoreBlueprint\Core\UI\Assets::MODAL_PRESENTATION_CORE );

		// Calendar-only presentation. Base Modal remains responsible for
		// workspace sizing, expand/restore and focus management.
		$style = CB_WORK_DIR . 'assets/work-calendar-golden.css';
		if ( is_file( $style ) ) {
			$modified = filemtime( $style );
			$version = false === $modified ? CB_WORK_VERSION : (string) $modified;
			wp_enqueue_style( 'cb-work-calendar-golden', CB_WORK_URL . 'assets/work-calendar-golden.css', [ self::REFINEMENT_STYLE_HANDLE ], $version );
		}

		$file = CB_WORK_DIR . 'assets/work-calendar.js';
		if ( ! is_file( $file ) ) {
			return;
		}
		$modified = filemtime( $file );
		$version  = false === $modified ? CB_WORK_VERSION : (string) $modified;
		wp_enqueue_script_module(
			self::CALENDAR_SCRIPT_HANDLE,
			CB_WORK_URL . 'assets/work-calendar.js',
			[ '@cb-core/modal', '@cb-work/work-items-reorder' ],
			$version
		);
	}

	/** CV-G-003 Work-only toolbar composition, after accepted Refinement CSS. */
	private static function enqueue_toolbar_golden_style(): void {
		$file = CB_WORK_DIR . 'assets/work-toolbar-golden.css';
		if ( ! is_file( $file ) ) {
			return;
		}
		$modified = filemtime( $file );
		$version  = false === $modified ? CB_WORK_VERSION : (string) $modified;
		wp_enqueue_style( self::TOOLBAR_GOLDEN_STYLE_HANDLE, CB_WORK_URL . 'assets/work-toolbar-golden.css', [ self::REFINEMENT_STYLE_HANDLE ], $version );
	}

	private static function enqueue_fast_path_assets(): void {
		$style = CB_WORK_DIR . 'assets/work-fast-paths.css';
		if ( is_file( $style ) ) {
			$modified = filemtime( $style );
			$version  = false === $modified ? CB_WORK_VERSION : (string) $modified;
			wp_enqueue_style( self::FAST_PATH_STYLE_HANDLE, CB_WORK_URL . 'assets/work-fast-paths.css', [ self::REFINEMENT_STYLE_HANDLE ], $version );
		}

		$script = CB_WORK_DIR . 'assets/work-fast-paths.js';
		if ( ! is_file( $script ) ) {
			return;
		}
		$modified = filemtime( $script );
		$version  = false === $modified ? CB_WORK_VERSION : (string) $modified;
		wp_enqueue_script( self::FAST_PATH_SCRIPT_HANDLE, CB_WORK_URL . 'assets/work-fast-paths.js', [ self::REFINEMENT_SCRIPT_HANDLE ], $version, true );
	}
}

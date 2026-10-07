<?php
declare(strict_types=1);

$root      = dirname( __DIR__ );
$assets    = file_get_contents( $root . '/src/Admin/Assets.php' );
$workspace = file_get_contents( $root . '/src/Admin/Workspace.php' );
$state     = file_get_contents( $root . '/src/Admin/WorkItemViewState.php' );
$script      = file_get_contents( $root . '/assets/work-items-refinement.js' );
$css         = file_get_contents( $root . '/assets/work-items-refinement.css' );
$operations  = file_get_contents( $root . '/src/Admin/Operations.php' );
$adminScript = file_get_contents( $root . '/assets/work-admin.js' );
$adminCss    = file_get_contents( $root . '/assets/work-admin.css' );

function refinement_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "Work Items UX refinement smoke failed: {$message}\n" );
		exit( 1 );
	}
}

refinement_assert(
	false !== $assets && false !== $workspace && false !== $state && false !== $script && false !== $css && false !== $operations && false !== $adminScript && false !== $adminCss,
	'Refinement source files are readable.'
);

refinement_assert(
	str_contains( $assets, "assets/work-items-refinement.css" )
	&& str_contains( $assets, "assets/work-items-refinement.js" )
	&& str_contains( $assets, '\\CoreBlueprint\\Core\\UI\\Assets::enqueue_segmented_control();' )
	&& str_contains( $assets, "'blocked'" )
	&& str_contains( $assets, "'showClosed'" ),
	'Refinement assets, Base Segmented Control and Board vocabulary are registered only through Work admin assets.'
);

refinement_assert(
	str_contains( $workspace, "add_filter( 'admin_body_class', [ self::class, 'body_class' ] )" )
	&& str_contains( $workspace, 'Menu::screen_context()' )
	&& str_contains( $workspace, 'cb-work-workspace-screen' )
	&& ! str_contains( $workspace, 'remove_submenu_page' )
	&& ! str_contains( $workspace, 'hide_duplicate_submenus' ),
	'WordPress native navigation remains canonical while Work adds only scoped workspace context.'
);

refinement_assert(
	str_contains( $state, '$calendar_from' )
	&& str_contains( $state, '$calendar_to' )
	&& str_contains( $state, 'default_sort_for_view' )
	&& str_contains( $state, "self::VIEW_LIST === \$view ? WorkItemQuery::SORT_TITLE : WorkItemQuery::SORT_WORKLOAD" )
	&& str_contains( $state, "'sort_explicit'  => \$sort_explicit" )
	&& str_contains( $state, '$query_scheduled_from' )
	&& str_contains( $state, '$query_scheduled_to' )
	&& str_contains( $state, "'calendar_from'        => \$calendar_from" )
	&& str_contains( $state, 'Explicit user filters only. Calendar month bounds are query viewport state.' )
	&& str_contains( $state, "'calendar_month' === \$state_key && self::VIEW_CALENDAR !== \$view" ),
	'Calendar viewport state is structurally separate from explicit scheduled filters.'
);

refinement_assert(
	str_contains( $operations, 'cb-work-calendar-navigation__controls' )
	&& str_contains( $operations, "'Today', 'core-blueprint-work'" )
	&& str_contains( $operations, "'Scheduled', 'core-blueprint-work'" )
	&& str_contains( $operations, "'Due', 'core-blueprint-work'" )
	&& str_contains( $operations, 'cb-work-calendar-entry--' )
	&& ! str_contains( $adminScript, 'enhanceCalendarNavigation' )
	&& str_contains( $adminCss, '.cb-work-calendar-navigation__controls' )
	&& str_contains( $adminCss, '.cb-work-calendar-entry--due' ),
	'Calendar renders aligned server-side navigation and distinguishes scheduled work from deadlines.'
);

refinement_assert(
	str_contains( $operations, 'cb-work-toolbar__advanced' )
	&& str_contains( $operations, 'cb-work-filter-field--service' )
	&& str_contains( $operations, 'cb-work-more-filters-toggle' )
	&& str_contains( $adminScript, 'advanced.hidden = ! opening' )
	&& str_contains( $adminScript, "setAttribute( 'aria-expanded'" ),
	'Advanced filter power is server-rendered and progressively disclosed by interaction-only JavaScript.'
);

refinement_assert(
	str_contains( $operations, 'cb-work-toolbar__row' )
	&& str_contains( $operations, 'cb-work-toolbar__head' )
	&& str_contains( $adminCss, 'grid-template-columns: auto minmax(0, 1fr) auto;' )
	&& str_contains( $css, 'body.cb-admin-theme .cb-work-items-page--refined .cb-core-segmented-control' )
	&& str_contains( $css, 'var(--cb-interactive-focus)' ),
	'Work Items command bar stays unified and reconciles the public Segmented Control with Admin Theme tokens.'
);

refinement_assert(
	str_contains( $operations, 'data-cb-work-auto-submit' )
	&& str_contains( $operations, 'dashicons-filter' )
	&& str_contains( $operations, 'cb-work-search-field' )
	&& str_contains( $operations, 'cb-work-view-switcher__option' )
	&& str_contains( $adminScript, 'form.requestSubmit()' )
	&& str_contains( $css, '.cb-work-search-field__icon' )
	&& str_contains( $css, '.cb-work-view-switcher__option' ),
	'Primary filters auto-apply and compact icon controls preserve a functional command bar.'
);

$list_start = strpos( $operations, 'private static function render_work_item_list(' );
$list_end   = false === $list_start ? false : strpos( $operations, 'private static function render_work_item_kanban(', $list_start );
$list_source = false !== $list_start && false !== $list_end ? substr( $operations, $list_start, $list_end - $list_start ) : '';

refinement_assert(
	'' !== $list_source
	&& str_contains( $list_source, 'cb-work-items-list--golden' )
	&& str_contains( $list_source, 'data-cb-work-list-item' )
	&& str_contains( $list_source, 'StateBadge::render' )
	&& str_contains( $list_source, 'render_work_item_priority' )
	&& str_contains( $list_source, 'render_work_item_due' )
	&& str_contains( $list_source, 'render_work_item_assignee' )
	&& str_contains( $list_source, 'render_work_item_list_actions' )
	&& str_contains( $list_source, 'render_work_item_list_details' )
	&& str_contains( $list_source, 'data-cb-work-list-item-toggle' )
	&& str_contains( $list_source, 'data-cb-work-list-item-details' )
	&& str_contains( $list_source, 'data-cb-work-list-group-toggle' )
	&& str_contains( $list_source, 'data-cb-work-list-group-items' )
	&& str_contains( $list_source, "esc_html_e( 'Description', 'core-blueprint-work' )" )
	&& str_contains( $list_source, "esc_html_e( 'Edit Work Item', 'core-blueprint-work' )" )
	&& ! str_contains( $list_source, 'class="postbox"' )
	&& ! str_contains( $list_source, 'transition_buttons( $item, $state )' )
	&& str_contains( $css, '.cb-work-items-page--refined .cb-work-items-list' )
	&& str_contains( $css, 'max-width: none' )
	&& str_contains( $css, '.cb-work-list-item__meta' )
	&& str_contains( $css, 'grid-template-columns: minmax(320px, 1fr) 574px 70px' )
	&& str_contains( $css, 'grid-template-columns: 120px 90px 120px 190px' )
	&& str_contains( $css, '.cb-work-list-item__actions' )
	&& str_contains( $css, 'width: 70px' )
	&& str_contains( $css, '.cb-work-items-list.is-grouped' )
	&& str_contains( $css, 'gap: var(--cb-space-5)' )
	&& str_contains( $css, '.cb-work-list-group__header' )
	&& str_contains( $css, 'box-shadow: inset 2px 0 0 color-mix' )
	&& str_contains( $css, '.cb-work-list-item__details' )
	&& str_contains( $css, 'grid-template-columns: repeat(4, minmax(130px, 1fr))' )
	&& str_contains( $css, '.cb-work-list-group__toggle[aria-expanded="true"]' )
	&& str_contains( $css, '.cb-work-list-actions__menu' )
	&& str_contains( $script, 'initListProgressiveDisclosure' )
	&& str_contains( $script, "itemToggles.forEach" )
	&& str_contains( $script, "event.key !== 'Escape'" ),
	'List Golden completion keeps the collapsed stream calm while adding accessible progressive detail and project disclosure.'
);

refinement_assert(
	str_contains( $operations, 'data-cb-work-tooltip' )
	&& str_contains( $operations, "WorkItemStatus::BLOCKED     => 'dashicons-no'" )
	&& str_contains( $css, '.cb-work-row-action[data-cb-work-tooltip]::after' )
	&& str_contains( $css, '.cb-work-status-badge.cb-core-state-badge--info' ),
	'Table Golden action controls and semantic badges retain the refined product UI contract.'
);

refinement_assert(
	str_contains( $operations, 'cb-work-item-cell__context' )
	&& str_contains( $operations, 'StateBadge::render' )
	&& str_contains( $operations, 'human_time_diff' )
	&& str_contains( $operations, 'transition_icon_buttons' )
	&& str_contains( $css, '.cb-work-status-badge' )
	&& str_contains( $css, '.cb-work-priority' )
	&& str_contains( $css, '.cb-work-due' )
	&& str_contains( $css, '.cb-work-assignee' )
	&& str_contains( $css, '.cb-work-row-actions' ),
	'Table Golden renders semantic Work Item context, status, priority, due, assignee and compact transitions.'
);

refinement_assert(
	str_contains( $css, '.cb-work-select-column input[type="checkbox"]' )
	&& str_contains( $css, 'margin-inline: auto !important' ),
	'Table selection checkboxes share one horizontal centerline across header and item rows.'
);

refinement_assert(
	str_contains( $operations, 'data-cb-work-select-all' )
	&& str_contains( $operations, 'data-cb-work-select-item' )
	&& str_contains( $operations, 'data-cb-work-bulk-form' )
	&& str_contains( $operations, 'render_work_item_table_header' )
	&& str_contains( $operations, "WorkItemQuery::SORT_TITLE" )
	&& str_contains( $operations, "WorkItemQuery::SORT_DUE" )
	&& str_contains( $operations, 'WorkItemStatus::PLANNED     => StateBadge::NEUTRAL' )
	&& str_contains( $adminScript, 'initTableBulkActions' )
	&& str_contains( $adminScript, 'selectAll.indeterminate' )
	&& str_contains( $css, '.cb-work-table-bulk' )
	&& str_contains( $css, '.cb-work-table-sort' )
	&& str_contains( $css, 'tr.is-selected td' ),
	'Table Golden completion keeps sorting, real row selection, bulk interaction and neutral Planned hierarchy functional.'
);

refinement_assert(
	str_contains( $operations, 'data-cb-work-quick-edit-toggle' )
	&& str_contains( $operations, 'data-cb-work-quick-edit-row' )
	&& str_contains( $operations, 'data-cb-work-bulk-edit-form' )
	&& str_contains( $operations, "Pickers::assignees( 'work_item[assigned_user_ids]'" )
	&& str_contains( $operations, "Pickers::assignees( 'bulk_assigned_user_ids'" )
	&& str_contains( $adminScript, 'initQuickEdit' )
	&& str_contains( $adminScript, 'data-cb-work-bulk-edit-toggle' )
	&& str_contains( $css, '.cb-work-inline-editor' )
	&& str_contains( $css, '.cb-work-quick-edit-row' ),
	'Quick Edit and Bulk Edit stay server-rendered, multi-assignee aware and progressively enhanced.'
);

$board_start = strpos( $operations, 'private static function render_work_item_kanban(' );
$board_end   = false === $board_start ? false : strpos( $operations, 'private static function render_work_item_calendar(', $board_start );
$board_source = false !== $board_start && false !== $board_end ? substr( $operations, $board_start, $board_end - $board_start ) : '';

refinement_assert(
	'' !== $board_source
	&& str_contains( $board_source, 'cb-work-board__title' )
	&& str_contains( $board_source, 'cb-work-board__context' )
	&& str_contains( $board_source, 'cb-work-board__type' )
	&& str_contains( $board_source, 'render_work_item_priority' )
	&& str_contains( $board_source, 'render_work_item_due' )
	&& str_contains( $board_source, 'render_work_item_assignee' )
	&& ! str_contains( $board_source, "esc_html_e( 'Priority:', 'core-blueprint-work' )" )
	&& ! str_contains( $board_source, "esc_html_e( 'Customer:', 'core-blueprint-work' )" )
	&& str_contains( $adminCss, '.cb-work-board__title a' )
	&& str_contains( $adminCss, 'text-decoration: none' )
	&& str_contains( $adminCss, '.cb-work-board__signals' )
	&& str_contains( $adminCss, '.cb-work-board__lane[data-cb-work-status-lane="blocked"]' ),
	'Board Golden B1 uses compact semantic cards, calm title links and status-aware lane composition.'
);

refinement_assert(
	str_contains( $board_source, 'cb-work-board__card-controls' )
	&& str_contains( $board_source, 'render_work_item_board_actions' )
	&& ! str_contains( $board_source, 'cb-work-board__status-actions' )
	&& ! str_contains( $board_source, 'transition_buttons( $item, $state )' )
	&& str_contains( $board_source, 'cb-work-board__empty' )
	&& str_contains( $board_source, "esc_html_e( 'Drop Work Items here', 'core-blueprint-work' )" )
	&& str_contains( $operations, 'cb-work-board-actions__menu' )
	&& str_contains( $operations, 'transition_menu_form( $item, $state, $from, $to )' )
	&& str_contains( $adminCss, '.cb-work-board__more-toggle' )
	&& str_contains( $adminCss, '.cb-work-board.is-reordering .cb-work-board__lane:has(.cb-core-reorder__drop-marker)' ),
	'Board Golden B2 keeps drag/drop primary while preserving canonical non-pointer transitions, restrained controls and valid-target feedback.'
);

refinement_assert(
	str_contains( $script, "[ 'planned', 'in_progress', 'blocked', 'completed', 'skipped', 'cancelled' ]" )
	&& str_contains( $script, "const closed = [ byStatus.skipped, byStatus.cancelled ].filter( Boolean )" )
	&& str_contains( $script, 'cb-work-board-closed-toggle' ),
	'Board prioritizes active workflow while preserving access to closed statuses.'
);

refinement_assert(
	str_contains( $operations, 'cb-work-page-header' )
	&& str_contains( $operations, 'cb-work-fast-paths' )
	&& str_contains( $operations, 'cb-core-segmented-control' )
	&& str_contains( $operations, 'cb-work-filter-summary' )
	&& str_contains( $script, 'cb-work-empty-state--primary' )
	&& str_contains( $script, 'cb-work-calendar-empty-note' )
	&& ! str_contains( $script, 'refineHeader(' )
	&& ! str_contains( $script, 'refineFilters(' )
	&& ! str_contains( $adminScript, 'createElement(' ),
	'PHP owns the Work Items workspace structure while JavaScript only enhances interaction and projection-specific states.'
);

refinement_assert(
	str_contains( $css, '@media screen and (max-width: 782px)' )
	&& str_contains( $css, '.cb-work-board__lane[hidden]' )
	&& str_contains( $css, '.cb-work-page-header__actions' )
	&& str_contains( $css, '.cb-work-page-header__actions .button' ),
	'Refinement stays responsive and preserves native button semantics.'
);

echo "Work Items UX refinement smoke passed.\n";

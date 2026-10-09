<?php
declare(strict_types=1);

// CV-G-004: keep Board efficiency isolated from accepted Base Reorder,
// Calendar day-board rendering and the authorized status transition model.
$root = dirname( __DIR__ );
$php  = (string) file_get_contents( $root . '/src/Admin/Operations.php' );
$css  = (string) file_get_contents( $root . '/assets/work-board-efficiency-golden.css' );
$assets = (string) file_get_contents( $root . '/src/Admin/Assets.php' );
$reorder = (string) file_get_contents( $root . '/assets/work-items-reorder.js' );
$quickAdd = (string) file_get_contents( $root . '/assets/work-quick-add.js' );
$calendar = (string) file_get_contents( $root . '/src/Admin/WorkItemCalendarView.php' );

$start = strpos( $php, 'private static function render_work_item_kanban(' );
$end = strpos( $php, 'private static function', $start + 40 );
$board = false !== $start ? substr( $php, $start, false === $end ? null : $end - $start ) : '';

$checks = [
	'Header shortcut only for long active lanes' =>
		str_contains( $board, '$show_header_add = count( $lane_items ) >= 8' )
		&& str_contains( $board, "in_array( (string) \$status, WorkItemStatus::active(), true )" )
		&& str_contains( $board, 'if ( $show_header_add )' )
		&& str_contains( $board, 'cb-work-board__header-add' ),
	'Header shortcut preserves canonical project, status and quick-add semantics' =>
		str_contains( $board, 'Menu::new_work_item_url( $lane_project_id, (string) $status )' )
		&& substr_count( $board, 'Menu::new_work_item_url( $lane_project_id, (string) $status )' ) === 2
		&& substr_count( $board, 'data-cb-work-quick-status-label=' ) === 2
		&& str_contains( $quickAdd, "const requestedStatus = url.searchParams.get( 'cb_work_status' ) || '';" ),
	'Header shortcut accessible with existing translation and lane context' =>
		str_contains( $board, 'aria-describedby="cb-work-board-lane-label-' )
		&& str_contains( $board, 'id="cb-work-board-lane-label-' )
		&& str_contains( $board, "esc_attr_e( 'Add Work Item', 'core-blueprint-work' )" )
		&& str_contains( $css, '.cb-work-board__header-add:focus-visible' ),
	'Original footer shortcut remains outside Base Reorder list' =>
		str_contains( $board, 'class="cb-work-board__lane-footer"' )
		&& str_contains( $board, 'class="cb-work-board__add"' )
		&& str_contains( $board, 'data-cb-core-reorder-list=' )
		&& str_contains( $board, 'data-cb-core-reorder-item=' )
		&& str_contains( $board, 'data-cb-core-reorder-handle' ),
	'All Board lane headings reserve equal shortcut space at desktop and mobile sizes' =>
		str_contains( $css, '> .cb-work-board__lane > .hndle {' )
		&& str_contains( $css, 'box-sizing: border-box;' )
		&& str_contains( $css, 'align-items: center;' )
		&& 1 === substr_count( $css, 'min-height: calc(32px + var(--cb-space-3) + var(--cb-space-3) + 1px);' )
		&& str_contains( $css, '@media screen and (max-width: 782px)' )
		&& 1 === substr_count( $css, 'min-height: calc(40px + var(--cb-space-3) + var(--cb-space-3) + 1px);' )
		&& str_contains( $css, 'min-width: 40px;' )
		&& str_contains( $css, 'min-height: 40px;' ),
	'Empty lane density follows live hidden state without removing drop destinations' =>
		str_contains( $css, ':has(> .cb-work-board__list > [data-cb-work-board-empty]:not([hidden]))' )
		&& str_contains( $css, 'min-height: 104px;' )
		&& str_contains( $css, 'min-height: 144px;' )
		&& str_contains( $php, 'data-cb-work-board-empty' )
		&& str_contains( $reorder, 'if (empty) empty.hidden = count > 0;' )
		&& str_contains( $reorder, 'crossList: true' ),
	'Calendar uses its own day board and is not selected by the new stylesheet' =>
		str_contains( $calendar, 'cb-work-day-board' )
		&& str_contains( $css, '.cb-work-items-kanban.cb-work-board' )
		&& ! str_contains( $css, '.cb-work-day-board' ),
	'Scoped and conditional asset loading only for Work Items Board' =>
		str_contains( $assets, 'self::enqueue_board_efficiency_style();' )
		&& str_contains( $assets, 'WorkItemViewState::VIEW_KANBAN === WorkItemViewPreferences::resolve_request_view' )
		&& str_contains( $assets, "CB_WORK_URL . 'assets/work-board-efficiency-golden.css', [ self::REFINEMENT_STYLE_HANDLE ]" )
		&& strpos( $assets, 'self::enqueue_board_efficiency_style();' ) > strpos( $assets, 'if ( Menu::CONTEXT_WORK_ITEMS !== $context )' ),
	'Base-owned status transitions and reorder behavior left unchanged' =>
		str_contains( $reorder, 'allowedStatuses(card).has(move.to.listId)' )
		&& str_contains( $reorder, 'persistBoardTransition(root, card, move.to.listId)' )
		&& str_contains( $reorder, 'refreshBoardCounts(root);' )
		&& str_contains( $css, 'var(--cb-interactive-focus)' )
		&& ! preg_match( '/#[0-9a-f]{3,8}\b/i', $css ),
];

foreach ( $checks as $label => $pass ) {
	if ( ! $pass ) {
		fwrite( STDERR, "Work Board Efficiency Golden smoke FAILED: {$label}\n" );
		exit( 1 );
	}
}

// Product policy boundary: status quick creation must remain inactive for
// Completed/Skipped/Cancelled, but enabled for long active lanes.
define( 'ABSPATH', '/tmp/cb-work-test/' );
require_once $root . '/src/Domain/WorkItemStatus.php';
foreach ( \CB\Work\Domain\WorkItemStatus::all() as $status ) {
	foreach ( [ 0, 1, 7, 8, 19 ] as $count ) {
		$renderShortcut = $count >= 8 && in_array( $status, \CB\Work\Domain\WorkItemStatus::active(), true );
		$expected = $count >= 8 && ! \CB\Work\Domain\WorkItemStatus::is_terminal( $status );
		if ( $renderShortcut !== $expected ) {
			fwrite( STDERR, "Board header shortcut policy FAILED for {$status}/{$count}.\n" );
			exit( 1 );
		}
	}
}

echo "Work Board Efficiency Golden smoke passed (long/empty lanes, quick-add, live dropzone and active statuses).\n";

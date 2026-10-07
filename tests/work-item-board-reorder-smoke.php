<?php
declare(strict_types=1);

$root       = dirname( __DIR__ );
$operations = file_get_contents( $root . '/src/Admin/Operations.php' );
$actions    = file_get_contents( $root . '/src/Admin/WorkItemBoardActions.php' );
$module     = file_get_contents( $root . '/assets/work-items-reorder.js' );
$css        = file_get_contents( $root . '/assets/work-admin.css' );
$menu       = file_get_contents( $root . '/src/Admin/Menu.php' );
$editor     = file_get_contents( $root . '/src/Admin/WorkItems.php' );

$board_start  = strpos( $operations, 'private static function render_work_item_kanban(' );
$board_end    = false === $board_start ? false : strpos( $operations, 'private static function render_work_item_calendar(', $board_start );
$board_source = false !== $board_start && false !== $board_end ? substr( $operations, $board_start, $board_end - $board_start ) : '';

$checks = [
	'Board exposes Base Reorder root, lanes, items and handles' =>
		str_contains( $operations, 'data-cb-work-board-reorder' )
		&& str_contains( $operations, 'data-cb-core-reorder' )
		&& str_contains( $operations, 'data-cb-core-reorder-list=' )
		&& str_contains( $operations, 'data-cb-core-reorder-item=' )
		&& str_contains( $operations, 'data-cb-core-reorder-handle' ),

	'Board cards publish domain-approved target statuses' =>
		str_contains( $operations, 'WorkItemStatus::transitions_from' )
		&& str_contains( $operations, 'data-cb-work-allowed-statuses' ),

	'Board keeps canonical non-pointer status controls in a compact overflow menu' =>
		'' !== $board_source
		&& str_contains( $board_source, 'self::render_work_item_board_actions( $item, $state )' )
		&& ! str_contains( $board_source, 'self::transition_buttons( $item, $state )' )
		&& ! str_contains( $board_source, 'cb-work-board__status-actions' )
		&& str_contains( $operations, 'cb-work-board__more' )
		&& str_contains( $operations, 'self::transition_menu_form( $item, $state, $from, $to )' ),

	'Board B1 cards reuse Golden semantic renderers instead of legacy label stacks' =>
		str_contains( $operations, 'cb-work-board__context' )
		&& str_contains( $operations, 'self::render_work_item_priority' )
		&& str_contains( $operations, 'self::render_work_item_due' )
		&& str_contains( $operations, 'self::render_work_item_assignee' )
		&& ! str_contains( $operations, "<strong><?php esc_html_e( 'Priority:', 'core-blueprint-work' ); ?></strong>" ),

	'Board AJAX validates domain transition and uses canonical repository lifecycle' =>
		str_contains( $actions, 'WorkItemStatus::can_transition' )
		&& str_contains( $actions, 'WorkItems::transition_status' )
		&& ! str_contains( $actions, 'update_post_meta' )
		&& ! str_contains( $actions, 'WorkItemMeta::set_status' ),

	'Board client uses Base cross-list Reorder and rejects same-lane ordering' =>
		str_contains( $module, 'crossList: true' )
		&& str_contains( $module, 'move.from.listId === move.to.listId' )
		&& str_contains( $module, 'allowedStatuses(card).has(move.to.listId)' ),

	'Board client persists status, refreshes server-rendered actions and participates in Base rollback events' =>
		str_contains( $module, 'persistBoardTransition' )
		&& str_contains( $module, 'window.location.reload()' )
		&& str_contains( $module, "cb:reorder:error" )
		&& str_contains( $module, "cb:reorder:change" ),

	'Board drag presentation stays Work-owned while Base owns interaction states' =>
		str_contains( $css, '.cb-work-board__card-header' )
		&& str_contains( $css, '.cb-work-board__drag-handle' )
		&& str_contains( $css, 'data-cb-core-reorder-pending' )
		&& str_contains( $css, '.cb-work-board.is-reordering .cb-work-board__lane:has(.cb-core-reorder__drop-marker)' )
		&& str_contains( $css, '.cb-work-board__empty-hint' ),

	'Board lane add stays outside the reorder list and uses canonical create context' =>
		str_contains( $board_source, 'cb-work-board__lane-footer' )
		&& str_contains( $board_source, 'Menu::new_work_item_url( $lane_project_id, (string) $status )' )
		&& str_contains( $css, '.cb-work-board__add' )
		&& str_contains( $menu, "'cb_work_status'" )
		&& str_contains( $editor, 'WorkItemStatus::can_transition( WorkItemStatus::PLANNED, $requested_status )' ),
];

foreach ( $checks as $message => $passed ) {
	if ( ! $passed ) {
		fwrite( STDERR, "Work Item Board reorder smoke failed: {$message}\n" );
		exit( 1 );
	}
}

echo "Work Item Board reorder smoke passed.\n";

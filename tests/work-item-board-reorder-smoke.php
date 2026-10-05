<?php
declare(strict_types=1);

$root       = dirname( __DIR__ );
$operations = file_get_contents( $root . '/src/Admin/Operations.php' );
$actions    = file_get_contents( $root . '/src/Admin/WorkItemBoardActions.php' );
$module     = file_get_contents( $root . '/assets/work-items-reorder.js' );
$css        = file_get_contents( $root . '/assets/work-admin.css' );

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

	'Board keeps canonical non-pointer status controls' =>
		str_contains( $operations, 'self::transition_buttons( $item, $state )' ),

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
		&& str_contains( $css, 'data-cb-core-reorder-pending' ),
];

foreach ( $checks as $message => $passed ) {
	if ( ! $passed ) {
		fwrite( STDERR, "Work Item Board reorder smoke failed: {$message}\n" );
		exit( 1 );
	}
}

echo "Work Item Board reorder smoke passed.\n";

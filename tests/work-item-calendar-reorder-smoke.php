<?php
declare(strict_types=1);

$root       = dirname( __DIR__ );
$operations = file_get_contents( $root . '/src/Admin/Operations.php' );
$actions    = file_get_contents( $root . '/src/Admin/WorkItemCalendarActions.php' );
$module     = file_get_contents( $root . '/assets/work-items-reorder.js' );
$css        = file_get_contents( $root . '/assets/work-admin.css' );

$checks = [
	'Calendar exposes Base Reorder root, date lists, items and handles' =>
		str_contains( $operations, 'data-cb-work-calendar-reorder' )
		&& str_contains( $operations, 'data-cb-core-reorder' )
		&& str_contains( $operations, 'data-cb-core-reorder-list=' )
		&& str_contains( $operations, 'data-cb-core-reorder-item="calendar:' )
		&& str_contains( $operations, 'data-cb-core-reorder-handle' ),

	'Calendar entries retain canonical editor access as a non-drag date editing path' =>
		str_contains( $operations, 'Menu::edit_work_item_url( $item_id )' ),

	'Calendar move action accepts only scheduled or due dates and uses canonical repository update' =>
		str_contains( $actions, "[ 'scheduled', 'due' ]" )
		&& str_contains( $actions, "'scheduled_on' : 'due_on'" )
		&& str_contains( $actions, 'WorkItems::update( $work_item_id, [ $field => $date ] )' )
		&& ! str_contains( $actions, 'update_post_meta' )
		&& ! str_contains( $actions, 'WorkItemMeta::' ),

	'Calendar client uses Base cross-list Reorder and rejects same-day ordering' =>
		str_contains( $module, 'const initCalendarReorder' )
		&& str_contains( $module, 'crossList: true' )
		&& str_contains( $module, 'move.from.listId === move.to.listId' )
		&& str_contains( $module, 'persistCalendarMove' ),

	'Calendar refreshes server-authoritative projection after a persisted date move' =>
		str_contains( $module, 'await persistCalendarMove( root, entry, move.to.listId )' )
		&& str_contains( $module, 'window.location.reload()' ),

	'Calendar empty dates remain physical drop targets' =>
		str_contains( $css, '.cb-work-calendar-day__list' )
		&& str_contains( $css, 'min-height: 84px' ),
];

foreach ( $checks as $message => $passed ) {
	if ( ! $passed ) {
		fwrite( STDERR, "Work Item Calendar reorder smoke failed: {$message}\n" );
		exit( 1 );
	}
}

echo "Work Item Calendar reorder smoke passed.\n";

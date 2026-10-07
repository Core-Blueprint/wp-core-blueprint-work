<?php
declare(strict_types=1);

$root     = dirname( __DIR__ );
$view     = file_get_contents( $root . '/src/Admin/WorkItemCalendarView.php' );
$assets   = file_get_contents( $root . '/src/Admin/Assets.php' );
$calendar = file_get_contents( $root . '/assets/work-calendar.js' );
$reorder  = file_get_contents( $root . '/assets/work-items-reorder.js' );
$css      = file_get_contents( $root . '/assets/work-admin.css' );

$checks = [
	'Month Calendar is a compact day projection rather than a date reorder surface' =>
		str_contains( $view, 'data-cb-work-calendar-day-open' )
		&& str_contains( $view, 'data-template-id=' )
		&& str_contains( $view, 'cb-work-calendar-day__count' )
		&& ! str_contains( $view, 'data-cb-work-calendar-reorder' )
		&& ! str_contains( $view, 'data-cb-work-calendar-kind' ),

	'Day detail uses the canonical active Work statuses as modal board lanes' =>
		str_contains( $view, 'array_fill_keys( WorkItemStatus::active(), [] )' )
		&& str_contains( $view, 'data-cb-work-board-reorder' )
		&& str_contains( $view, 'WorkItemBoardActions::ACTION' )
		&& str_contains( $view, 'WorkItemStatus::transitions_from( $status )' ),

	'Calendar relationship remains distinct from workflow status' =>
		str_contains( $view, "'kind'      => 'scheduled'" )
		&& str_contains( $view, "'kind'      => 'due'" )
		&& str_contains( $view, "__( 'Scheduled', 'core-blueprint-work' ) . ' · ' . __( 'Due', 'core-blueprint-work' )" )
		&& str_contains( $view, "__( 'Due', 'core-blueprint-work' )" ),

	'Terminal Work Items stay outside active drag lanes' =>
		str_contains( $view, '$closed_entries = []' )
		&& str_contains( $view, 'cb-work-day-modal__closed' )
		&& str_contains( $view, "esc_html_e( 'Show closed', 'core-blueprint-work' )" ),

	'Calendar consumes Base Modal and the shared status reorder implementation' =>
		str_contains( $calendar, "import '@cb-core/modal';" )
		&& str_contains( $calendar, "import { enhanceStatusBoard } from '@cb-work/work-items-reorder';" )
		&& str_contains( $calendar, 'modal.show({' )
		&& str_contains( $calendar, "size: 'workspace'" )
		&& str_contains( $calendar, 'expandable: true' )
		&& str_contains( $calendar, 'reloadAfterMove: false' ),

	'Calendar persists modal status moves into the backing day template before reopen' =>
		str_contains( $calendar, 'syncTemplateMove' )
		&& str_contains( $calendar, 'onPersistedMove: (move) => syncTemplateMove(template, move)' )
		&& str_contains( $calendar, 'target.append(card)' )
		&& str_contains( $calendar, 'refreshTemplateCounts(template)' ),

	'Work status reorder is reusable without forcing a Calendar modal reload' =>
		str_contains( $reorder, 'const enhanceStatusBoard = (root, options = {}) =>' )
		&& str_contains( $reorder, 'const reloadAfterMove = options.reloadAfterMove !== false;' )
		&& str_contains( $reorder, "typeof options.onPersistedMove === 'function'" )
		&& str_contains( $reorder, 'if (reloadAfterMove) window.location.reload();' )
		&& str_contains( $reorder, 'export { enhanceStatusBoard };' )
		&& ! str_contains( $reorder, 'persistCalendarMove' ),

	'Calendar assets load the Base Modal foundation only for Calendar view' =>
		str_contains( $assets, 'WorkItemViewState::VIEW_CALENDAR' )
		&& str_contains( $assets, 'enqueue_calendar_assets()' )
		&& str_contains( $assets, 'Assets::enqueue_modals' )
		&& str_contains( $assets, "'@cb-core/modal', '@cb-work/work-items-reorder'" ),

	'Month cells and modal status lanes have bounded compact presentation' =>
		str_contains( $view, 'cb-work-day-board__viewport' )
		&& str_contains( $css, '.cb-work-calendar-day__trigger' )
		&& str_contains( $css, '.cb-work-day-board__viewport' )
		&& str_contains( $css, 'overflow-x: auto;' )
		&& str_contains( $css, 'grid-template-columns: repeat(3, minmax(320px, 1fr));' )
		&& str_contains( $css, 'min-width: 320px;' )
		&& ! str_contains( $css, '.cb-work-day-board {\n\t\tgrid-template-columns: 1fr;' ),
];

foreach ( $checks as $message => $passed ) {
	if ( ! $passed ) {
		fwrite( STDERR, "Work Item Calendar day modal smoke failed: {$message}\n" );
		exit( 1 );
	}
}

echo "Work Item Calendar day modal smoke passed.\n";

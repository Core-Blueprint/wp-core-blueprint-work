<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$operations = file_get_contents( $root . '/src/Admin/Operations.php' );
$calendar   = file_get_contents( $root . '/src/Admin/WorkItemCalendarView.php' );
$actions    = file_get_contents( $root . '/src/Admin/OperationalActions.php' );

$calendarDispatchPos = strpos( $operations, 'self::render_work_item_calendar( $items, $project_map, $type_map, $state )' );
$emptyStatePos       = strpos( $operations, "<?php elseif ( [] === \$items ) : ?>" );

$checks = [
	'Work Items workspace resolves canonical view state' => is_string( $operations )
		&& str_contains( $operations, 'WorkItemViewState::from_request( $_GET )' ),
	'Work Items workspace queries only through the canonical operational engine' => 1 === substr_count( $operations, 'WorkItems::search( $query )' )
		&& str_contains( $operations, "\$query = (array) \$state['query'];" )
		&& ! str_contains( $operations, 'WorkItems::for_project( $project_filter' )
		&& ! str_contains( $operations, 'WorkItems::all( 200 )' ),
	'Table, List, Kanban and Calendar are the enabled D2 renderers' => str_contains( $operations, '[ WorkItemViewState::VIEW_TABLE, WorkItemViewState::VIEW_LIST, WorkItemViewState::VIEW_KANBAN, WorkItemViewState::VIEW_CALENDAR ]' )
		&& str_contains( $operations, 'render_work_item_table(' )
		&& str_contains( $operations, 'render_work_item_list(' )
		&& str_contains( $operations, 'render_work_item_kanban(' )
		&& str_contains( $operations, 'render_work_item_calendar(' ),
	'Kanban is a renderer over canonical status and the existing result set' => str_contains( $operations, 'array_fill_keys( WorkItemStatus::all(), [] )' )
		&& str_contains( $operations, '$lanes[ $status ][] = $item;' )
		&& str_contains( $operations, 'self::render_work_item_kanban( $items, $project_map, $type_map, $state )' )
		&& str_contains( $operations, 'self::render_work_item_board_actions( $item, $state )' )
		&& str_contains( $operations, 'self::transition_menu_form( $item, $state, $from, $to )' ),
	'Calendar is a renderer over scheduled and due dates from the existing result set' => is_string( $calendar )
		&& str_contains( $calendar, "\$scheduled_on       = (string) ( \$item['scheduled_on'] ?? '' );" )
		&& str_contains( $calendar, "\$due_on             = (string) ( \$item['due_on'] ?? '' );" )
		&& str_contains( $calendar, '$entries[ $scheduled_on ][]' )
		&& str_contains( $calendar, '$entries[ $due_on ][]' )
		&& str_contains( $calendar, "'kind'      => 'scheduled'" )
		&& str_contains( $calendar, "'kind'      => 'due'" )
		&& str_contains( $operations, 'self::render_work_item_calendar( $items, $project_map, $type_map, $state )' )
		&& str_contains( $operations, 'WorkItemCalendarView::render( $items, $project_map, $type_map, $state )' ),
	'Calendar month navigation preserves canonical state and resets pagination' => str_contains( $calendar, "[ 'calendar_month' => \$previous_month, 'page' => 1 ]" )
		&& str_contains( $calendar, "[ 'calendar_month' => \$next_month, 'page' => 1 ]" )
		&& str_contains( $calendar, 'Previous month' )
		&& str_contains( $calendar, 'Next month' ),
	'Calendar remains rendered and navigable when a month has no Work Items' => false !== $calendarDispatchPos
		&& false !== $emptyStatePos
		&& $calendarDispatchPos < $emptyStatePos,
	'view switcher and filter form preserve canonical view state' => str_contains( $operations, 'render_work_item_views( $state )' )
		&& str_contains( $operations, "name=\"view\" value=\"<?php echo esc_attr( (string) \$state['view'] ); ?>\"" )
		&& str_contains( $operations, "WorkItemViewState::VIEW_CALENDAR => [ 'label' => __( 'Calendar'" ),
	'Calendar filters preserve month state instead of exposing derived scheduled bounds' => str_contains( $operations, "name=\"calendar_month\" value=\"<?php echo esc_attr( (string) \$state['calendar_month'] ); ?>\"" )
		&& str_contains( $operations, 'if ( ! $is_calendar )' )
		&& str_contains( $operations, "\$clear_state['calendar_month'] = (string) \$state['calendar_month'];" ),
	'customer and assignee filtering stay on shared Base ObjectPicker paths' => str_contains( $operations, "Pickers::customer( 'customer', 'cb-work-filter-customer'" )
		&& str_contains( $operations, "Pickers::assignee( 'assignee_id', 'cb-work-filter-assignee', (int) \$state['assignee_id'] )" ),
	'invalid customer state fails closed instead of broadening the dataset' => str_contains( $operations, "false === \$state['customer_valid']" )
		&& str_contains( $operations, "? WorkItems::search( \$query )" )
		&& str_contains( $operations, "'items' => [], 'total' => 0" ),
	'workspace exposes canonical filter and pagination renderers' => str_contains( $operations, 'render_work_item_filters(' )
		&& str_contains( $operations, 'render_work_item_pagination(' )
		&& str_contains( $operations, 'WorkItemViewState::query_args( $state, $overrides )' ),
	'transition forms post only canonical return state' => str_contains( $operations, '$return_args = WorkItemViewState::query_args( $state );' )
		&& str_contains( $operations, "unset( \$return_args['page'] );" )
		&& str_contains( $operations, 'name="return_state[<?php echo esc_attr( (string) $key ); ?>]"' )
		&& ! str_contains( $operations, 'return_project_id' ),
	'transition redirect re-normalizes posted state through the canonical state contract' => is_string( $actions )
		&& str_contains( $actions, "\$_POST['return_state']" )
		&& str_contains( $actions, 'WorkItemViewState::from_request( $return_state )' )
		&& str_contains( $actions, 'WorkItemViewState::query_args( $state )' )
		&& str_contains( $actions, "admin_url( 'admin.php' )" )
		&& ! str_contains( $actions, 'return_project_id' ),
	'Project context remains native Work Item create context while transitions receive full canonical state' => str_contains( $operations, 'Menu::new_work_item_url( $project_filter )' )
		&& str_contains( $operations, 'self::render_work_item_board_actions( $item, $state )' )
		&& str_contains( $operations, '$return_args = WorkItemViewState::query_args( $state );' )
		&& str_contains( $operations, 'self::transition_menu_form( $item, $state, $from, $to )' ),
];

foreach ( $checks as $label => $passed ) {
	if ( ! $passed ) {
		fwrite( STDERR, "D2 workspace routing smoke failed: {$label}\n" );
		exit( 1 );
	}
}

echo "D2 workspace routing smoke passed.\n";

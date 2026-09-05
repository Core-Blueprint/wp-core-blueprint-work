<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$operations = file_get_contents( $root . '/src/Admin/Operations.php' );

$checks = [
	'Work Items workspace resolves canonical view state' => is_string( $operations )
		&& str_contains( $operations, 'WorkItemViewState::from_request( $_GET )' ),
	'Work Items workspace queries only through the canonical operational engine' => str_contains( $operations, 'WorkItems::search( (array) $state[\'query\'] )' )
		&& ! str_contains( $operations, 'WorkItems::for_project( $project_filter' )
		&& ! str_contains( $operations, 'WorkItems::all( 200 )' ),
	'Table and List are the only enabled D2 renderers at this gate' => str_contains( $operations, '[ WorkItemViewState::VIEW_TABLE, WorkItemViewState::VIEW_LIST ]' )
		&& str_contains( $operations, 'render_work_item_table(' )
		&& str_contains( $operations, 'render_work_item_list(' )
		&& ! str_contains( $operations, 'render_work_item_kanban(' )
		&& ! str_contains( $operations, 'render_work_item_calendar(' ),
	'view switcher and filter form preserve canonical view state' => str_contains( $operations, 'render_work_item_views( $state )' )
		&& str_contains( $operations, "name=\"view\" value=\"<?php echo esc_attr( (string) \$state['view'] ); ?>\"" )
		&& str_contains( $operations, "[ 'view' => (string) \$state['view'] ]" ),
	'customer filtering stays on the shared Base ObjectPicker path' => str_contains( $operations, "Pickers::customer( 'customer', 'cb-work-filter-customer'" ),
	'invalid customer state fails closed instead of broadening the dataset' => str_contains( $operations, "false === \$state['customer_valid']" )
		&& str_contains( $operations, "? WorkItems::search( (array) \$state['query'] )" )
		&& str_contains( $operations, "'items' => [], 'total' => 0" ),
	'workspace exposes canonical filter and pagination renderers' => str_contains( $operations, 'render_work_item_filters(' )
		&& str_contains( $operations, 'render_work_item_pagination(' )
		&& str_contains( $operations, 'WorkItemViewState::query_args( $state, $overrides )' ),
	'Project context remains the native Work Item create/transition context' => str_contains( $operations, 'Menu::new_work_item_url( $project_filter )' )
		&& str_contains( $operations, 'self::transition_buttons( $item, $project_filter )' ),
];

foreach ( $checks as $label => $passed ) {
	if ( ! $passed ) {
		fwrite( STDERR, "D2 workspace routing smoke failed: {$label}\n" );
		exit( 1 );
	}
}

echo "D2 workspace routing smoke passed.\n";

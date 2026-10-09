<?php
declare(strict_types=1);

$root       = dirname( __DIR__ );
$operations = file_get_contents( $root . '/src/Admin/Operations.php' );
$actions    = file_get_contents( $root . '/src/Admin/OperationalActions.php' );
$script     = file_get_contents( $root . '/assets/work-admin.js' );

$checks = [
	'bulk transition endpoint is explicitly registered' => is_string( $actions )
		&& str_contains( $actions, "admin_post_cb_work_bulk_transition_work_items" )
		&& str_contains( $actions, "self::guard( 'cb_work_bulk_transition_work_items' )" ),
	'bulk transition input is bounded and delegates to the canonical transition contract' => str_contains( $actions, 'array_slice( $ids, 0, 100 )' )
		&& str_contains( $actions, 'WorkItems::transition_status( $id, $status, $actor, $from )' )
		&& str_contains( $actions, "Events::WORK_ITEM_STATUS_CHANGED" )
		&& str_contains( $actions, "'bulk' => true" ),
	'bulk transition returns through canonical Work Item view state' => str_contains( $actions, 'self::redirect_work_items( $updated > 0 ? \'work-item-transitioned\' : \'work-item-transition-invalid\' )' )
		&& str_contains( $actions, 'WorkItemViewState::from_request( $return_state )' )
		&& str_contains( $actions, 'WorkItemViewState::query_args( $state )' ),
	'table selection is associated with the external bulk form without nesting action forms' => is_string( $operations )
		&& str_contains( $operations, 'id="cb-work-bulk-form"' )
		&& str_contains( $operations, 'form="cb-work-bulk-form"' )
		&& str_contains( $operations, 'name="work_item_ids[]"' )
		&& str_contains( $operations, 'data-cb-work-select-all' ),
	'bulk controls are progressive and selection state is synchronized client-side' => is_string( $script )
		&& str_contains( $script, 'initTableBulkActions' )
		&& str_contains( $script, 'selectAll.indeterminate' )
		&& str_contains( $script, "classList.toggle( 'is-selected', item.checked )" )
		&& str_contains( $script, "submit.disabled = selected.length === 0 || status.value === ''" ),
];

foreach ( $checks as $label => $passed ) {
	if ( ! $passed ) {
		fwrite( STDERR, "Work Item table bulk smoke failed: {$label}\n" );
		exit( 1 );
	}
}

echo "Work Item table bulk smoke passed.\n";

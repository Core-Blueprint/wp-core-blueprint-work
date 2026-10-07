<?php
declare(strict_types=1);

$root       = dirname( __DIR__ );
$operations = file_get_contents( $root . '/src/Admin/Operations.php' );
$actions    = file_get_contents( $root . '/src/Admin/OperationalActions.php' );
$script     = file_get_contents( $root . '/assets/work-admin.js' );
$css        = file_get_contents( $root . '/assets/work-items-refinement.css' );

$checks = [
	'Quick Edit and Bulk Edit endpoints are explicit admin-post actions' => is_string( $actions )
		&& str_contains( $actions, "admin_post_cb_work_quick_edit_work_item" )
		&& str_contains( $actions, "admin_post_cb_work_bulk_edit_work_items" )
		&& str_contains( $actions, 'self::guard( \'cb_work_quick_edit_work_item_\' . $id )' )
		&& str_contains( $actions, "self::guard( 'cb_work_bulk_edit_work_items' )" ),
	'Quick Edit delegates field writes to canonical WorkItems update and status to canonical transitions' => str_contains( $actions, 'WorkItems::update( $id, $input )' )
		&& str_contains( $actions, 'WorkItemStatus::can_transition( $from, $status )' )
		&& str_contains( $actions, 'WorkItems::transition_status( $id, $status, get_current_user_id() )' )
		&& str_contains( $actions, "Events::WORK_ITEM_UPDATED" )
		&& str_contains( $actions, "'quick_edit' => true" ),
	'Bulk Edit is bounded, partial-field only and audit-observable' => str_contains( $actions, 'return array_slice( $ids, 0, 100 )' )
		&& str_contains( $actions, "'__keep' !== \$priority" )
		&& str_contains( $actions, "isset( \$_POST['apply_due'] )" )
		&& str_contains( $actions, "'__keep' !== \$work_type_id" )
		&& str_contains( $actions, "'__keep' !== \$billing" )
		&& str_contains( $actions, "isset( \$_POST['apply_assignees'] )" )
		&& str_contains( $actions, "'bulk_edit' => true" ),
	'inline editors preserve canonical return state and multi-assignee semantics' => is_string( $operations )
		&& str_contains( $operations, 'WorkItemViewState::query_args( $state )' )
		&& str_contains( $operations, "Pickers::assignees( 'work_item[assigned_user_ids]'" )
		&& str_contains( $operations, "Pickers::assignees( 'bulk_assigned_user_ids'" )
		&& str_contains( $operations, "[], false" )
		&& str_contains( $operations, 'WorkTypes::get( $current_type_id )' ),
	'Quick Edit is available through overflow independent of status transition availability' => str_contains( $operations, 'data-cb-work-quick-edit-toggle' )
		&& str_contains( $operations, '<details class="cb-work-row-actions__more">' )
		&& ! str_contains( $operations, "if ( [] === \$transitions ) {\n\t\t\techo esc_html( '—' );" ),
	'More Actions behaves like a dismissible operator popover' => str_contains( $script, 'initRowActionMenus' )
		&& str_contains( $script, "document.addEventListener( 'click'" )
		&& str_contains( $script, "! menu.contains( event.target )" )
		&& str_contains( $script, "event.key !== 'Escape'" )
		&& str_contains( $script, "closeMenu( openMenu, true )" )
		&& is_string( $css )
		&& str_contains( $css, 'min-width: 210px' )
		&& str_contains( $css, 'min-height: 38px' )
		&& str_contains( $css, '.cb-work-transition-form--menu')
		&& str_contains( $css, 'var(--cb-tint-danger)' ),
	'client interaction only reveals server-rendered editors and never creates persistence UI dynamically' => is_string( $script )
		&& str_contains( $script, 'initQuickEdit' )
		&& str_contains( $script, 'row.hidden = ! opening' )
		&& str_contains( $script, 'data-cb-work-bulk-edit-id' )
		&& str_contains( $script, "dueInput.disabled = ! dueToggle.checked" )
		&& str_contains( $script, "toggleAttribute( 'inert', ! enabled )" )
		&& ! str_contains( $script, 'createElement(' )
		&& is_string( $css )
		&& str_contains( $css, '.cb-work-inline-editor' )
		&& str_contains( $css, '.cb-work-bulk-assignees.is-disabled' )
		&& str_contains( $css, '[data-cb-work-bulk-edit-submit]:disabled' ),
];

foreach ( $checks as $label => $passed ) {
	if ( ! $passed ) {
		fwrite( STDERR, "Work Item inline edit smoke failed: {$label}\n" );
		exit( 1 );
	}
}

echo "Work Item inline edit smoke passed.\n";

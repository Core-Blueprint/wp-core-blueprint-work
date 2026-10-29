<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$actions = (string) file_get_contents( $root . '/src/Admin/OperationalActions.php' );
$ui = (string) file_get_contents( $root . '/assets/work-admin.js' );
$adapter = (string) file_get_contents( $root . '/assets/js/workspace/canonical-mutations.js' );
$mount = (string) file_get_contents( $root . '/assets/js/workspace/canonical-mount.js' );
$css = (string) file_get_contents( $root . '/assets/css/unified-workspace.css' );
$shell = (string) file_get_contents( $root . '/src/Admin/UnifiedWorkspace.php' );
$assets = (string) file_get_contents( $root . '/src/Admin/Assets.php' );
$g0 = (string) file_get_contents( $root . '/src/Admin/Operations.php' );
$check = (string) file_get_contents( $root . '/tools/check' );

$actions_names = [
	'cb_work_transition_work_item' => 'transition_work_item',
	'cb_work_quick_edit_work_item' => 'quick_edit_work_item',
	'cb_work_bulk_transition_work_items' => 'bulk_transition_work_items',
	'cb_work_bulk_edit_work_items' => 'bulk_edit_work_items',
];

$checks = [
	'G0/B1 canonical Work Items operations renderer byte-identical' =>
		'92a4814f24b43f96d16e3d96ce61a49ee9f21500' === sha1( 'blob ' . strlen( $g0 ) . "\0" . $g0 ),
	'exact native and AJAX action handlers share one canonical implementation' =>
		! array_filter(
			$actions_names,
			static fn ( string $handler, string $action ): bool =>
				! str_contains( $actions, "admin_post_{$action}" )
				|| ! str_contains( $actions, "wp_ajax_{$action}" )
				|| ! str_contains( $actions, "[ self::class, '{$handler}' ]" ),
			ARRAY_FILTER_USE_BOTH
		),
	'nonce capability and source status guards preserved' =>
		str_contains( $actions, 'current_user_can( Capabilities::MANAGE )' )
		&& str_contains( $actions, 'check_admin_referer( $nonce_action )' )
		&& str_contains( $actions, "self::guard( 'cb_work_quick_edit_work_item_' . \$id )" )
		&& str_contains( $actions, "self::guard( 'cb_work_bulk_edit_work_items' )" )
		&& str_contains( $actions, 'WorkItemStatus::can_transition( $from, $status )' )
		&& str_contains( $actions, 'WorkItems::transition_status( $id, $status, $actor, $from )' )
		&& str_contains( $actions, 'WorkItems::update(' )
		&& str_contains( $actions, 'Audit::record(' )
		&& str_contains( $actions, 'return array_slice( $ids, 0, 100 )' ),
	'JSON only for AJAX and admin-post redirect fallback retained' =>
		str_contains( $actions, 'if ( wp_doing_ajax() ) {' )
		&& str_contains( $actions, 'wp_send_json_success( $data, 200 )' )
		&& str_contains( $actions, 'wp_send_json_error( $data, 409 )' )
		&& str_contains( $actions, 'wp_safe_redirect( add_query_arg( $args, admin_url(' )
		&& str_contains( $actions, "WorkItemViewState::from_request( \$return_state )" ),
	'only known admin-ajax actions with form nonce may submit; server controls success' =>
		str_contains( $adapter, 'MUTATION_ACTIONS.includes(action) && nonce' )
		&& str_contains( $adapter, "new FormData(form)" )
		&& str_contains( $adapter, "method: 'POST'" )
		&& str_contains( $adapter, "credentials: 'same-origin'" )
		&& str_contains( $adapter, "response.json()" )
		&& str_contains( $adapter, 'if (!response.ok || !result.success)' )
		&& str_contains( $adapter, 'await refresh()' )
		&& str_contains( $adapter, 'inFlight.has(form)' )
		&& str_contains( $adapter, 'if (mutationPending)' )
		&& str_contains( $adapter, "destination.setAttribute('aria-busy', 'true')" )
		&& str_contains( $adapter, "destination.removeAttribute('aria-busy')" )
		&& str_contains( $adapter, 'if (!panel.hidden) await refresh();' )
		&& str_contains( $adapter, 'if (event.defaultPrevented) return;' ),
	'dynamic UI hooks activate Table bulk Quick Edit and menus, no duplicate native filter handlers' =>
		str_contains( $ui, 'initTableBulkActions( page );' )
		&& str_contains( $ui, 'initQuickEdit( page );' )
		&& str_contains( $ui, 'initRowActionMenus( page, controller.signal );' )
		&& str_contains( $ui, "cb-work:canonical-view-unmount" )
		&& str_contains( $ui, 'dynamicActionControllers.get( page )?.abort()' )
		&& str_contains( $ui, 'ready( initWorkItems );' )
		&& str_contains( $ui, "page.closest( '[data-cb-work-uwe-canonical]' )" )
		&& ! str_contains( $ui, 'fetch(' ),
	'mutation forms display exclusively after verified secure JS lifecycle' =>
		str_contains( $css, '[data-cb-work-mutations-ready="1"] form.cb-work-transition-form' )
		&& str_contains( $css, '[data-cb-work-mutations-ready="1"] form.cb-work-quick-editor' )
		&& str_contains( $css, '[data-cb-work-mutations-ready="1"] form.cb-work-bulk-editor' )
		&& str_contains( $mount, 'mutationAdapter?.ready(inserted)' )
		&& str_contains( $mount, 'mutationAdapter?.disable()' ),
	'packaged JS with localized status data and focused behavioral regression' =>
		str_contains( $assets, "CB_WORK_DIR . 'assets/js/workspace/canonical-mutations.js'" )
		&& str_contains( $assets, 'is_file( $canonical_mutations )' )
		&& str_contains( $shell, 'data-cb-work-uwe-mutation-saving=' )
		&& str_contains( $shell, 'data-cb-work-uwe-mutation-saved=' )
		&& str_contains( $shell, 'data-cb-work-uwe-mutation-failed=' )
		&& str_contains( $mount, "from './canonical-mutations.js'" )
		&& str_contains( $check, 'tests/work-m1-mutations-smoke.php' )
		&& str_contains( $check, 'tests/work-m1-mutations-runtime.mjs' ),
];
foreach ( $checks as $name => $valid ) {
	if ( ! $valid ) {
		fwrite( STDERR, "Work M1 mutation lifecycle source FAIL: {$name}\n" );
		exit( 1 );
	}
}
echo "Work M1 mutation lifecycle source contract: PASS.\n";

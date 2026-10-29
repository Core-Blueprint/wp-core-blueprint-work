<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$reorder = (string) file_get_contents( $root . '/assets/work-items-reorder.js' );
$views = (string) file_get_contents( $root . '/assets/work-view-preferences.js' );
$mount = (string) file_get_contents( $root . '/assets/js/workspace/canonical-mount.js' );
$assets = (string) file_get_contents( $root . '/src/Admin/Assets.php' );
$css = (string) file_get_contents( $root . '/assets/css/unified-workspace.css' );
$operations = (string) file_get_contents( $root . '/src/Admin/Operations.php' );
$check = (string) file_get_contents( $root . '/tools/check' );
$shell = (string) file_get_contents( $root . '/src/Admin/UnifiedWorkspace.php' );

$checks = [
	'Exact protected G0/B1 Work Items HTML renderer remains unchanged' =>
		'92a4814f24b43f96d16e3d96ce61a49ee9f21500'
			=== sha1( 'blob ' . strlen( $operations ) . "\0" . $operations ),
	'Only existing secured preference endpoints and Base interaction dependencies remain' =>
		str_contains( $reorder, "persist(root, 'save', policy)" )
		&& str_contains( $reorder, 'persistListDisplay(root, listDisplayPolicy(root))' )
		&& str_contains( $views, "import '@cb-core/modal';" )
		&& str_contains( $views, "import '@cb-core/reorder';" )
		&& str_contains( $views, 'await persist(body, policyFromBody(body))' )
		&& str_contains( $assets, 'self::enqueue_view_preferences_assets();' )
		&& str_contains( $assets, 'self::VIEW_PREFERENCES_SCRIPT_HANDLE ],' ),
	'Native page retains original reload fallback for preference mutations' =>
		str_contains( $reorder, 'window.location.reload();' )
		&& str_contains( $views, 'window.location.reload();' ),
	'Workspace saves only refresh active canonical Work Items HTML' =>
		str_contains( $reorder, "new CustomEvent('cb-work:canonical-preferences-saved', { bubbles: true })" )
		&& str_contains( $views, "new CustomEvent('cb-work:canonical-preferences-saved', { bubbles: true })" )
		&& str_contains( $mount, "destination.addEventListener('cb-work:canonical-preferences-saved'" )
		&& str_contains( $mount, 'void refresh();' ),
	'Table List and View Preferences support repeatable mount without duplicate listeners' =>
		str_contains( $reorder, "cb-work:canonical-view-mounted" )
		&& str_contains( $reorder, "cb-work:canonical-view-unmount" )
		&& str_contains( $reorder, 'root.dataset.cbWorkTablePrefMounted' )
		&& str_contains( $reorder, 'root.dataset.cbWorkListDisplayMounted' )
		&& str_contains( $reorder, '{ signal: lifecycle.signal }' )
		&& str_contains( $reorder, 'owned?.controller?.destroy?.()' )
		&& str_contains( $reorder, 'owned?.lifecycle?.abort()' )
		&& str_contains( $views, 'trigger.dataset.cbWorkViewPrefsMounted' )
		&& str_contains( $views, "cb-work:canonical-view-mounted" ),
	'Unapproved edit/bulk mutation forms still hidden but preferences trigger visible' =>
		str_contains( $css, '.cb-work-unified .cb-work-uwe__canonical-view form,' )
		&& str_contains( $css, 'form.cb-work-items-filters {' )
		&& str_contains( $css, '.cb-work-uwe__canonical-view .cb-work-view-preferences__trigger' ),
	'User-selected view order controls the visible workspace switcher, not a second preference store' =>
		str_contains( $shell, "WorkItemViewPreferences::get( get_current_user_id() )['order']" )
		&& str_contains( $shell, 'foreach ( $preferred_order as $view_id )' )
		&& str_contains( $mount, 'syncPreferredOrder(canonical);' )
		&& str_contains( $mount, "canonical.querySelector('.cb-work-view-switcher')" )
		&& str_contains( $css, '.cb-work-uwe__canonical-view .cb-work-view-switcher-shell' )
		&& str_contains( $css, '.cb-work-uwe__canonical-view .cb-work-view-switcher {'),
	'Behavioral tests connected to Work Level 1' =>
		str_contains( $check, 'tests/work-m1-preferences-smoke.php' )
		&& str_contains( $check, 'tests/work-m1-preference-lifecycle-runtime.mjs' ),
];
foreach ( $checks as $name => $pass ) {
	if ( ! $pass ) {
		fwrite( STDERR, "Work M1 preference lifecycle source FAIL: {$name}\n" );
		exit( 1 );
	}
}
echo "Work M1 preference lifecycle source: PASS.\n";

<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$calendar = (string) file_get_contents( $root . '/assets/work-calendar.js' );
$adapter = (string) file_get_contents( $root . '/assets/js/workspace/canonical-mutations.js' );
$shortcuts = (string) file_get_contents( $root . '/assets/work-fast-paths.js' );
$assets = (string) file_get_contents( $root . '/src/Admin/Assets.php' );
$operations = (string) file_get_contents( $root . '/src/Admin/Operations.php' );
$bridge = (string) file_get_contents( $root . '/src/Admin/WorkItemViewBridge.php' );
$check = (string) file_get_contents( $root . '/tools/check' );

$checks = [
	'Canonical Golden Operations renderer remains byte-identical' =>
		'92a4814f24b43f96d16e3d96ce61a49ee9f21500'
			=== sha1( 'blob ' . strlen( $operations ) . "\0" . $operations ),
	'Calendar day Base modal marker is scoped to authenticated Unified Work mount' =>
		str_contains( $calendar, "calendar?.closest('[data-cb-work-uwe-canonical]')" )
		&& str_contains( $calendar, "body.setAttribute('data-cb-work-uwe-modal', '')" )
		&& str_contains( $calendar, "body.addEventListener('cb-work:calendar-modal-mutation-saved'" )
		&& str_contains( $calendar, 'statusCommitted = true;' )
		&& str_contains( $calendar, 'Promise.resolve(pending).then(() => {' ),
	'Detached Calendar status forms use the same Work guarded POST action allowlist' =>
		str_contains( $adapter, "form?.closest?.('[data-cb-work-uwe-modal]')" )
		&& str_contains( $adapter, '(!destination.contains(form) && !modalBody)' )
		&& str_contains( $adapter, 'MUTATION_ACTIONS.includes(action) && nonce' )
		&& str_contains( $adapter, "method: 'POST'" )
		&& str_contains( $adapter, "credentials: 'same-origin'" )
		&& str_contains( $adapter, "document.addEventListener?.('submit'" )
		&& str_contains( $adapter, "if (event.target?.closest?.('[data-cb-work-uwe-modal]'))" )
		&& str_contains( $adapter, '}, true);' ),
	'Successful modal mutation never refreshes during Base dialog lifetime' =>
		str_contains( $adapter, "new CustomEvent('cb-work:calendar-modal-mutation-saved'" )
		&& str_contains( $adapter, "form.dispatchEvent(" )
		&& str_contains( $adapter, '} else if (!panel.hidden) {' )
		&& str_contains( $calendar, "cb-work:canonical-status-committed" ),
	'Permission loss clears detached private modal records and rechecks guarded read' =>
		str_contains( $adapter, 'if (response.status === 401 || response.status === 403)' )
		&& str_contains( $adapter, 'if (modalBody) modalBody.replaceChildren();' )
		&& str_contains( $adapter, 'if (!panel.hidden) await refresh();' ),
	'Native fast path is preserved and initialized against dynamic active view' =>
		str_contains( $shortcuts, "event.key === '/'" )
		&& str_contains( $shortcuts, "document.querySelector( '.cb-work-items-page' )" )
		&& str_contains( $shortcuts, "page.closest( '[data-cb-work-uwe-panel]' )?.hidden" )
		&& str_contains( $shortcuts, "page.querySelector( '#cb-work-filter-search' )" )
		&& str_contains( $assets, 'self::enqueue_fast_path_assets();' ),
	'Shared Work status and authorization source remain unchanged' =>
		str_contains( $bridge, 'Operations::render_work_items();' )
		&& ! str_contains( $adapter, 'wp_insert_post' )
		&& ! str_contains( $adapter, 'update_post_meta' ),
	'Focused dynamic Calendar and shortcut runtime is wired into Level 1' =>
		str_contains( $check, 'tests/work-m1-modal-action-parity-smoke.php' )
		&& str_contains( $check, 'tests/work-m1-modal-action-parity-runtime.mjs' ),
];
foreach ( $checks as $label => $passed ) {
	if ( ! $passed ) {
		fwrite( STDERR, "Work M1 dynamic Calendar parity FAIL: {$label}\n" );
		exit( 1 );
	}
}
echo "Work M1 dynamic Calendar modal and shortcut source: PASS.\n";

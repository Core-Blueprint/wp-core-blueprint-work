<?php
declare(strict_types=1);

$root       = dirname( __DIR__ );
$board      = (string) file_get_contents( $root . '/assets/work-items-reorder.js' );
$calendar   = (string) file_get_contents( $root . '/assets/work-calendar.js' );
$mount      = (string) file_get_contents( $root . '/assets/js/workspace/canonical-mount.js' );
$golden     = (string) file_get_contents( $root . '/src/Admin/Operations.php' );
$actions    = (string) file_get_contents( $root . '/src/Admin/OperationalActions.php' );
$check      = (string) file_get_contents( $root . '/tools/check' );

$checks = [
	'G0/B1 Work Items renderer is exactly source protected' =>
		'92a4814f24b43f96d16e3d96ce61a49ee9f21500'
		=== sha1( 'blob ' . strlen( $golden ) . "\0" . $golden ),
	'Board publishes one event only following server confirmation' =>
		str_contains( $board, 'await persistBoardTransition(root, card, move.to.listId)' )
		&& str_contains( $board, "root.dispatchEvent(new CustomEvent('cb-work:canonical-status-committed'" )
		&& strpos( $board, 'await persistBoardTransition(root, card, move.to.listId)' )
			< strpos( $board, "root.dispatchEvent(new CustomEvent('cb-work:canonical-status-committed'" )
		&& str_contains( $board, "root.closest('[data-cb-work-uwe-canonical]')" )
		&& str_contains( $board, 'if (reloadAfterMove) {' )
		&& str_contains( $board, 'window.location.reload();' ),
	'Calendar keeps original Base modal and waits for its close before refresh' =>
		str_contains( $calendar, "import '@cb-core/modal';" )
		&& str_contains( $calendar, 'modal.show({' )
		&& str_contains( $calendar, 'onPersistedMove: (move) => {' )
		&& str_contains( $calendar, 'syncTemplateMove(template, move);' )
		&& str_contains( $calendar, 'statusCommitted = true;' )
		&& str_contains( $calendar, 'Promise.resolve(pending).then(() => {' )
		&& str_contains( $calendar, "calendar.dispatchEvent(new CustomEvent('cb-work:canonical-status-committed'" )
		&& str_contains( $calendar, "calendar?.closest('[data-cb-work-uwe-canonical]')" ),
	'Canonical handler is scoped to Work Items and coalesces status changes' =>
		str_contains( $mount, "destination.addEventListener('cb-work:canonical-status-committed'" )
		&& str_contains( $mount, 'destination.contains(event.target)' )
		&& str_contains( $mount, 'createCanonicalStatusSync(browser' )
		&& str_contains( $mount, 'if (!panel.hidden) void refresh();' )
		&& str_contains( $mount, 'statusSync.cancel();' )
		&& str_contains( $mount, 'browser.clearTimeout(timer)' ),
	'Board keyboard focus is restored after replacing canonical markup' =>
		str_contains( $mount, "activeInput?.closest?.('[data-cb-core-reorder-item]')" )
		&& str_contains( $mount, "inserted.querySelectorAll('[data-cb-core-reorder-item]')" )
		&& str_contains( $mount, 'focusTarget.focus({ preventScroll: true });' )
		&& str_contains( $mount, "switcher.querySelector('.is-active')?.focus?.({ preventScroll: true });" ),
	'Status changes never create new write endpoints or repositories' =>
		! str_contains( $mount, "method: 'POST'" )
		&& ! str_contains( $calendar, 'wp_insert_post' )
		&& str_contains( $actions, 'WorkItems::transition_status(' )
		&& str_contains( $board, 'persistBoardTransition(' ),
	'Focused regression is in canonical check' =>
		str_contains( $check, 'tests/work-m1-status-sync-smoke.php' )
		&& str_contains( $check, 'tests/work-m1-status-sync-runtime.mjs' ),
];
foreach ( $checks as $label => $pass ) {
	if ( ! $pass ) {
		fwrite( STDERR, "Work M1 status sync source FAIL: {$label}\n" );
		exit( 1 );
	}
}
echo "Work M1 status sync source contract: PASS.\n";

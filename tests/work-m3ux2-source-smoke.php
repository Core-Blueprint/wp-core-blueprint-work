<?php
declare(strict_types=1);
$root = dirname( __DIR__ );
$host = (string) file_get_contents( $root . '/assets/js/workspace/operation-dialog.js' );
$eng = (string) file_get_contents( $root . '/assets/js/workspace/engagements.js' );
$time = (string) file_get_contents( $root . '/assets/js/workspace/time.js' );
$stage = (string) file_get_contents( $root . '/tools/prepare-workspace-modules.py' );
$assets = (string) file_get_contents( $root . '/src/Admin/Assets.php' );
$styles = (string) file_get_contents( $root . '/assets/css/unified-workspace.css' );
$checks = [
	'Base-only modal host uses documented workspace, expandable and confirmation' =>
		str_contains( $host, 'modal.show({' ) &&
		str_contains( $host, "size: 'workspace', expandable: true" ) &&
		str_contains( $host, 'onConfirm: async () => true' ) &&
		str_contains( $host, 'form.requestSubmit()' ) &&
		! str_contains( $host, 'dialog.close(' ) &&
		! str_contains( $host, 'innerHTML' ),
	'child action is deferred until parent settles, original panel reattached' =>
		str_contains( $host, 'await modal.show(' ) &&
		str_contains( $host, 'parent.insertBefore(panel, marker.nextSibling)' ) &&
		str_contains( $host, 'await waitForBaseCleanup()' ) &&
		str_contains( $host, 'await next();' ),
	'old REST, Time mutation/CAS and billing/rule editor paths retained' =>
		str_contains( $eng, "from './operation-dialog.js'" ) &&
		str_contains( $time, "from './operation-dialog.js'" ) &&
		str_contains( $eng, 'createEngagementBillingDialog(' ) &&
		str_contains( $eng, 'createEngagementRuleDialog(' ) &&
		str_contains( $time, 'createTimeEntryDialog(' ) &&
		str_contains( $time, 'Timers' ) === false &&
		str_contains( $eng, 'refresh, collapse,' ),
	'workspace-only CSS and immutable dependency fail-closed' =>
		str_contains( $styles, '.cb-work-ux2-dialog' ) &&
		str_contains( $stage, '"operation-dialog.js"' ) &&
		str_contains( $assets, 'is_file( $operation_dialog )' ),
];
foreach ( $checks as $label => $ok ) {
	if ( ! $ok ) { fwrite( STDERR, "Work M3-UX2 SOURCE FAIL: {$label}\n" ); exit( 1 ); }
}
echo "Work M3-UX2 SOURCE: PASS (Base host, sequential child, protected native fallbacks).\n";

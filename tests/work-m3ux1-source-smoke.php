<?php
declare(strict_types=1);
$root = dirname( __DIR__ );
$shell = (string) file_get_contents( $root . '/src/Admin/UnifiedWorkspace.php' );
$style = (string) file_get_contents( $root . '/assets/css/unified-workspace.css' );
$app = (string) file_get_contents( $root . '/assets/js/workspace/app.js' );
$operations_module = (string) file_get_contents( $root . '/assets/js/workspace/operations.js' );
$engagements = (string) file_get_contents( $root . '/assets/js/workspace/engagements.js' );
$time = (string) file_get_contents( $root . '/assets/js/workspace/time.js' );
$operations = (string) file_get_contents( $root . '/src/Admin/Operations.php' );
$checks = [
	'one workspace with real tabs and native Recurring fallback' =>
		str_contains( $shell, 'cb-work-uwe__primary-navigation' ) &&
		str_contains( $shell, 'role="tablist"' ) &&
		str_contains( $shell, "'recurring' === \$key" ) &&
		str_contains( $shell, 'Menu::RECURRENCE_SLUG' ) &&
		str_contains( $shell, 'WorkspaceOperationsPanel::render();' ),
	'only authenticated canonical Work Items mount hides duplicate shell create' =>
		str_contains( $style, '[data-cb-work-uwe-panel="work-items"]:has(> [data-cb-work-uwe-canonical]:not([hidden]) > .cb-work-items-page) > .cb-work-uwe__heading-row' ) &&
		str_contains( $shell, 'data-cb-work-m2-add=' ),
	'compact operations and responsive shell without changing Base tokens' =>
		str_contains( $style, '.cb-work-unified .cb-work-m3-operations__item' ) &&
		str_contains( $style, '.cb-work-unified .cb-work-uwe__primary-navigation' ) &&
		str_contains( $style, '@media screen and (max-width: 782px)' ),
	'exclusive Operations are UI-only; canonical M1/M2 mounting remains' =>
		str_contains( $app, "mountWorkspaceOperations(root, browser);" ) &&
		str_contains( $app, "import { mountWorkspaceOperations, isolateOperations } from './operations.js';" ) &&
		str_contains( $operations_module, 'export function isolateOperations(kind, views)' ) &&
		str_contains( $app, 'isolateOperations(kind, operationalViews);' ) &&
		str_contains( $app, 'mountUnifiedEditors(root, browser, refresh);' ) &&
		str_contains( $engagements, 'refresh, collapse,' ) &&
		str_contains( $time, 'refresh, lookup, mutate, collapse,' ),
	'protected Golden renderer unchanged' =>
		sha1( 'blob ' . strlen( $operations ) . "\0" . $operations ) ===
			'92a4814f24b43f96d16e3d96ce61a49ee9f21500',
];
foreach ( $checks as $name => $pass ) {
	if ( ! $pass ) {
		fwrite( STDERR, "Work M3-UX1 source FAIL: {$name}\n" );
		exit( 1 );
	}
}
echo "Work M3-UX1 source: PASS (IA, single CTA, Operations, protected renderer).\n";

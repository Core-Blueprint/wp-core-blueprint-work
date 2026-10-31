<?php
declare(strict_types=1);
$root = dirname( __DIR__ );
$mount = (string) file_get_contents( $root . '/assets/js/workspace/canonical-mount.js' );
$renderer = (string) file_get_contents( $root . '/src/Admin/Operations.php' );
$shell = (string) file_get_contents( $root . '/src/Admin/UnifiedWorkspace.php' );
$checks = [
	'Detached canonical fragment loses only direct native header' =>
		str_contains( $mount, 'export function stripWorkspaceCanonicalHeader(canonical)' )
		&& str_contains( $mount, "canonical?.querySelector?.(':scope > header.cb-work-page-header')" )
		&& str_contains( $mount, 'header.remove();' )
		&& ! str_contains( $mount, "canonical.remove();" ),
	'Canonical data and toolbar are composed before detached fragment import' =>
		strpos( $mount, 'composeCanonicalToolbar(canonical);' ) <
		strpos( $mount, 'stripWorkspaceCanonicalHeader(canonical);' )
		&& strpos( $mount, 'stripWorkspaceCanonicalHeader(canonical);' ) <
		strpos( $mount, 'document.importNode(canonical, true)' )
		&& str_contains( $mount, "inserted.querySelector?.('.cb-work-results-header__count')" ),
	'One real context Add action remains in Workspace' =>
		substr_count( $shell, 'data-cb-work-m2-add="work-item" href=' ) === 1
		&& str_contains( $shell, 'Menu::new_work_item_url()' )
		&& str_contains( $shell, 'data-cb-work-uwe-context' ),
	'Native header and Add remain on standalone route, protected renderer unchanged' =>
		str_contains( $renderer, '<header class="cb-work-page-header">' )
		&& str_contains( $renderer, 'cb-work-page-header__primary' )
		&& sha1( 'blob ' . strlen( $renderer ) . "\0" . $renderer )
			=== '92a4814f24b43f96d16e3d96ce61a49ee9f21500',
];
foreach ( $checks as $label => $ok ) {
	if ( ! $ok ) {
		fwrite( STDERR, "Work M3-UX3-F2A SOURCE FAIL: {$label}\n" );
		exit( 1 );
	}
}
echo "Work M3-UX3-F2A SOURCE: PASS (single context header, native route and Golden unchanged).\n";

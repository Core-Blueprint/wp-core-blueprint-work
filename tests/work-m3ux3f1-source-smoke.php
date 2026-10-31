<?php
declare(strict_types=1);
$root = dirname( __DIR__ );
$shell = (string) file_get_contents( $root . '/src/Admin/UnifiedWorkspace.php' );
$ops = (string) file_get_contents( $root . '/src/Admin/WorkspaceOperationsPanel.php' );
$mount = (string) file_get_contents( $root . '/assets/js/workspace/canonical-mount.js' );
$app = (string) file_get_contents( $root . '/assets/js/workspace/app.js' );
$css = (string) file_get_contents( $root . '/assets/css/unified-workspace.css' );
$renderer = (string) file_get_contents( $root . '/src/Admin/Operations.php' );
$checks = [
	'global row retains real SPA tabs, Base actions and no-JS panels' =>
		str_contains( $shell, '<header class="cb-work-uwe__header">' )
		&& str_contains( $shell, 'WorkspaceOperationsPanel::render_navigation();' )
		&& str_contains( $shell, 'WorkspaceOperationsPanel::render_panels();' )
		&& str_contains( $ops, 'public static function render(): void' )
		&& str_contains( $ops, 'public static function render_navigation(): void' )
		&& str_contains( $ops, 'public static function render_panels(): void' ),
	'context row keeps actual view switcher and native add fallback' =>
		str_contains( $shell, 'data-cb-work-uwe-context' )
		&& str_contains( $shell, 'data-cb-work-uwe-context-count' )
		&& str_contains( $shell, 'data-cb-work-uwe-view-switcher' )
		&& str_contains( $shell, 'data-cb-work-m2-add="work-item" href=' )
		&& str_contains( $shell, 'Menu::new_work_item_url()' )
		&& str_contains( $shell, 'Menu::RECURRENCE_SLUG' ),
	'one canonical set of real filter nodes moved inside its canonical root' =>
		str_contains( $mount, 'export function composeCanonicalToolbar(canonical)' )
		&& str_contains( $mount, 'row.insertBefore(shortcuts, primary)' )
		&& str_contains( $mount, 'composeCanonicalToolbar(canonical);' )
		&& str_contains( $mount, "destination.addEventListener('submit'" )
		&& ! str_contains( $mount, 'innerHTML' ),
	'permission loss purges contextual counts and private list rows' =>
		str_contains( $mount, "contextCount.textContent = ''; contextCount.hidden = true;" )
		&& str_contains( $app, "failure?.status === 401 || failure?.status === 403" )
		&& str_contains( $app, 'list?.replaceChildren()' ),
	'counts come only from authorized canonical/REST results' =>
		str_contains( $mount, 'resultCount.textContent.trim()' )
		&& str_contains( $app, 'contextCount.textContent = String(result.total)' ),
	'Work-only responsive styling and Golden renderer unchanged' =>
		str_contains( $css, 'M3-UX3-F1: compact two-row Workspace' )
		&& str_contains( $css, '.cb-work-uwe__canonical-view .cb-work-page-header' )
		&& str_contains( $css, '@media screen and (max-width: 782px)' )
		&& sha1( 'blob ' . strlen( $renderer ) . "\0" . $renderer )
			=== '92a4814f24b43f96d16e3d96ce61a49ee9f21500',
];
foreach ( $checks as $label => $passed ) {
	if ( ! $passed ) { fwrite( STDERR, "Work M3-UX3-F1 SOURCE FAIL: {$label}\n" ); exit( 1 ); }
}
echo "Work M3-UX3-F1 SOURCE: PASS (two-row shell, controls, counts, Golden).\n";

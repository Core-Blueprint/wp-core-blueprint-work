<?php
declare(strict_types=1);
$root = dirname( __DIR__ );
$shell = (string) file_get_contents( $root . '/src/Admin/UnifiedWorkspace.php' );
$ops = (string) file_get_contents( $root . '/src/Admin/WorkspaceOperationsPanel.php' );
$mount = (string) file_get_contents( $root . '/assets/js/workspace/canonical-mount.js' );
$css = (string) file_get_contents( $root . '/assets/css/unified-workspace.css' );
$renderer = (string) file_get_contents( $root . '/src/Admin/Operations.php' );
$checks = [
	'Only Workspace label changes to Tasks; native and route unchanged' =>
		str_contains($shell, "'label' => __( 'Tasks', 'core-blueprint-work' )") &&
		str_contains($shell, 'data-cb-work-m2-add="work-item"') &&
		str_contains($shell, 'Menu::new_work_item_url()') &&
		str_contains($shell, "'work-items' => 'yes-alt'") &&
		str_contains($shell, "esc_html_e( 'Add Task', 'core-blueprint-work' )"),
	'Top-level tabs and Operations use existing WordPress Dashicons with accessible text' =>
		str_contains($shell, 'aria-hidden="true"></span><span>') &&
		str_contains($ops, 'dashicons-chart-line') &&
		str_contains($ops, 'dashicons-clock') &&
		str_contains($shell, "esc_attr_e( 'Task views', 'core-blueprint-work' )"),
	'Task context visually avoids repeated heading, preserving screen-reader heading' =>
		str_contains($shell, 'class="screen-reader-text"') &&
		str_contains($shell, 'data-cb-work-uwe-task-singular') &&
		str_contains($shell, 'data-cb-work-uwe-task-plural'),
	'Canonical detached text and count localized only inside Workspace' =>
		str_contains($mount, 'export function taskCountLabel(') &&
		str_contains($mount, 'panel.dataset.cbWorkUweSearchPlaceholder') &&
		str_contains($mount, "canonical.querySelector('#cb-work-filter-search')") &&
		str_contains($mount, 'resultCount.textContent.trim()') &&
		str_contains($mount, 'localizeWorkspaceBoard(canonical, {') &&
		str_contains($shell, 'data-cb-work-uwe-no-tasks'),
	'View modes segmented; filters and search border corrected in Workspace scope' =>
		str_contains($css, 'M3-UX3-F2B: coherent dashboard controls') &&
		str_contains($css, '.cb-work-uwe__view-link.is-active') &&
		str_contains($css, '.cb-work-toolbar__search') &&
		str_contains($css, 'border-top: 0') &&
		str_contains($css, ':focus-visible'),
	'Golden native renderer source remains exact' =>
		sha1('blob ' . strlen($renderer) . "\0" . $renderer) ===
			'92a4814f24b43f96d16e3d96ce61a49ee9f21500',
];
foreach ($checks as $label => $passed) {
	if (!$passed) { fwrite(STDERR, "Work M3-UX3-F2B SOURCE FAIL: {$label}\n"); exit(1); }
}
echo "Work M3-UX3-F2B SOURCE: PASS (Tasks, navigation, segmented modes, filters, Golden).\n";

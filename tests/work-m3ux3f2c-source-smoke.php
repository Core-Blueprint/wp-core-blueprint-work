<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$shell = (string)file_get_contents($root . '/src/Admin/UnifiedWorkspace.php');
$mount = (string)file_get_contents($root . '/assets/js/workspace/canonical-mount.js');
$css = (string)file_get_contents($root . '/assets/css/unified-workspace.css');
$renderer = (string)file_get_contents($root . '/src/Admin/Operations.php');
$board = (string)file_get_contents($root . '/src/Admin/WorkItemBoardRenderer.php');
$checks = [
	'Tasks icon refers to work list, not completed state' =>
		str_contains($shell, "'work-items' => 'clipboard'"),
	'No inference of missing project from display text' =>
		!str_contains($mount, 'minimizeUnassignedBoardContext') &&
		!str_contains($shell, 'data-cb-work-uwe-no-project'),
	'Single compact toolbar, existing filters intact' =>
		str_contains($css, 'M3-UX3-F2C: visual clarity') &&
		str_contains($css, '.cb-work-toolbar__search') &&
		str_contains($css, '.cb-work-fast-paths') &&
		str_contains($css, '.cb-work-toolbar__primary') &&
		str_contains($css, '.cb-work-toolbar__head') &&
		!str_contains($mount, 'removeChild(primary)'),
	'Board badges, contextual cards, safe drop and theme parity' =>
		str_contains($css, '.cb-work-board__lane > .hndle .count') &&
		str_contains($css, '.cb-work-board__card:is(:hover, :focus-within)') &&
		str_contains($css, '.cb-work-board.is-reordering .cb-work-board__lane:has(.cb-core-reorder__drop-marker)') &&
		str_contains($css, 'body.cb-admin-theme .cb-work-unified .cb-work-uwe__types-action.button:not(.button-primary)') &&
		str_contains($css, '@media (forced-colors: active)'),
	'Canonical Golden renderers not altered' =>
		sha1('blob ' . strlen($renderer) . "\0" . $renderer) === '92a4814f24b43f96d16e3d96ce61a49ee9f21500' &&
		str_contains($board, 'data-cb-work-board-reorder') &&
		str_contains($board, 'data-cb-core-reorder-handle'),
];
foreach($checks as $label=>$ok){if(!$ok){fwrite(STDERR,"Work M3-UX3-F2C SOURCE FAIL: {$label}\n"); exit(1);}}
echo "Work M3-UX3-F2C SOURCE: PASS (hierarchy, scope, theme, board and Golden).\n";

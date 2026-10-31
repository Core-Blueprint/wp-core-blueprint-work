<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$css = (string) file_get_contents($root . '/assets/css/unified-workspace.css');
$js = (string) file_get_contents($root . '/assets/js/workspace/board-viewport.js');
$mount = (string) file_get_contents($root . '/assets/js/workspace/canonical-mount.js');
$prepare = (string) file_get_contents($root . '/tools/prepare-workspace-modules.py');
$native = (string) file_get_contents($root . '/src/Admin/Operations.php');
$reorder = (string) file_get_contents($root . '/assets/work-items-reorder.js');
$checks = [
	'Board viewport behavior only in authenticated Work Workspace mount' =>
		str_contains($mount, "import { mountBoardViewport, rememberBoardScroll, restoreBoardScroll } from './board-viewport.js'") &&
		str_contains($mount, "if (view === 'kanban') {") &&
		str_contains($mount, 'stopBoardViewport = mountBoardViewport(board, browser') &&
		str_contains($mount, 'restoreBoardScroll(board, savedBoard);') &&
		str_contains($mount, 'function clearUnauthorized() {') &&
		str_contains($mount, 'stopBoard();') &&
		str_contains($mount, 'return { refresh, abort() {'),
	'Native horizontal Board, scrollable canonical list and capped lane use same variable' =>
		str_contains($css, '.cb-work-items-kanban.cb-work-board[data-cb-work-board-viewport] {') &&
		str_contains($css, 'max-block-size: var(--cb-work-board-available-block);') &&
		str_contains($css, 'align-items: start;') &&
		str_contains($css, 'max-block-size: calc(var(--cb-work-board-available-block) - var(--cb-space-1, 4px) - var(--cb-space-4, 16px));') &&
		str_contains($css, 'overflow-x: auto;') &&
		str_contains($css, 'overflow-y: hidden;') &&
		str_contains($css, '.cb-work-board__lane > .cb-work-board__list {') &&
		str_contains($css, 'overflow-y: auto;') &&
		str_contains($css, 'overscroll-behavior-block: contain;'),
	'No hardcoded WordPress chrome measurement, no fake synchronized scrollbar' =>
		str_contains($js, 'board.getBoundingClientRect().top') &&
		str_contains($js, 'browser.visualViewport?.height || browser.innerHeight') &&
		str_contains($js, 'observer.observe(element)') &&
		! str_contains($js, "querySelector('#wpadminbar')") &&
		! str_contains($js, 'document.createElement'),
	'Base remains sole DnD mutation owner, scroll assist only during active Base reorder' =>
		str_contains($js, "board.classList?.contains('is-reordering')") &&
		str_contains($js, "list.scrollTop += step;") &&
		str_contains($js, 'board.scrollLeft += sideways;') &&
		str_contains($reorder, 'persistBoardTransition(root, card, move.to.listId)') &&
		str_contains($reorder, 'crossList: true'),
	'Immutable ESM build registers new module and native Golden renderer is untouched' =>
		str_contains($prepare, '"board-viewport.js"') &&
		sha1('blob '.strlen($native)."\0".$native) === '92a4814f24b43f96d16e3d96ce61a49ee9f21500',
];
foreach ($checks as $label=>$ok) { if (!$ok) { fwrite(STDERR,"Work M3-UX3-F2E SOURCE FAIL: {$label}\n"); exit(1); } }
echo "Work M3-UX3-F2E SOURCE: PASS (viewport, independent lanes, Base DnD, native Golden).\n";

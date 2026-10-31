<?php
declare(strict_types=1);
$root = dirname( __DIR__ );
$js = (string) file_get_contents( $root . '/assets/js/workspace/recurring.js' );
$css = (string) file_get_contents( $root . '/assets/css/unified-workspace.css' );
$php = (string) file_get_contents( $root . '/src/Admin/UnifiedWorkspace.php' );
$catalog = (string) file_get_contents( $root . '/languages/core-blueprint-work.pot' );
$checker = (string) file_get_contents( $root . '/tools/check' );
$backend = (string) file_get_contents( $root . '/src/Transport/Rest/WorkspaceRecurrenceEditorController.php' );
$checks = [
	'Bounded two-column editor content; responsive single column without scroll masking' =>
		str_contains( $css, '.cb-work-recurring__layout { display:grid; grid-template-columns:minmax(0, 1.8fr) minmax(260px, 1fr);' )
		&& str_contains( $css, '.cb-work-recurring { width:min(100%, 1080px);' )
		&& str_contains( $css, '.cb-work-recurring__field :is(input,select,textarea) { box-sizing:border-box;' )
		&& str_contains( $css, '@media (max-width: 1000px)' )
		&& str_contains( $css, '.cb-work-recurring__layout { grid-template-columns:minmax(0,1fr); }' )
		&& ! str_contains( $css, '.cb-work-recurring { overflow-x:hidden;' ),
	'Schedule before context, preview in dedicated aside, advanced offsets' =>
		str_contains( $js, "main.append(section(doc, labels.cbWorkRecurringSchedule" )
		&& str_contains( $js, "main.append(section(doc, labels.cbWorkRecurringContext" )
		&& str_contains( $js, "aside.append(previewCard)" )
		&& str_contains( $js, "'create_ahead_days'" )
		&& str_contains( $js, "'due_offset_days'" )
		&& str_contains( $js, "advanced.append(advancedGrid)" ),
	'Optional end-mode selector is not persisted, locked dates keep current contracts' =>
		str_contains( $js, "endSelect.removeAttribute('name');" )
		&& str_contains( $js, 'endInput.required = enabled' )
		&& str_contains( $js, "for (const key of ['frequency','interval_count','start_on','end_on'])" )
		&& str_contains( $js, "schedulePreviewFields(form)" )
		&& str_contains( $js, "readChanges(form, id > 0 ? original : null, quick)" ),
	'Live preview remains non-mutating, rate-limited and resistant to stale results' =>
		str_contains( $js, "request('/preview', 'POST', {rule_id: id, ...fields})" )
		&& str_contains( $js, 'previewClosed || ticket !== previewVersion' )
		&& str_contains( $js, 'stopPreviewTimer()' )
		&& str_contains( $js, 'previewButton.disabled = false;' )
		&& str_contains( $backend, 'Recurrence::preview_dates(' )
		&& ! str_contains( $js, 'innerHTML' )
		&& ! str_contains( $js, '.showModal(' ),
	'All new PHP presentation labels reuse existing canonical gettext IDs' =>
		str_contains( $php, 'data-cb-work-recurring-unit-daily' )
		&& str_contains( $php, 'data-cb-work-recurring-preview-loading' )
		&& str_contains( $php, 'data-cb-work-recurring-no-limit' )
		&& str_contains( $catalog, 'msgid "No date limit"' )
		&& str_contains( $catalog, 'msgid "Building server preview…"' )
		&& str_contains( $catalog, 'msgid "day"' ),
	'Existing Base modal lifecycle + canonical REST save preserved' =>
		str_contains( $js, 'browser.cbCore.modal.show({' )
		&& str_contains( $js, "size: quick ? 'default' : 'workspace'" )
		&& str_contains( $js, "request('/rules/' + id, 'PATCH'" )
		&& str_contains( $js, "'X-WP-Nonce': nonce" ),
	'Canonical Level 1 runs both new UX tests' =>
		str_contains( $checker, 'work-m3b4d-editor-source-smoke.php' )
		&& str_contains( $checker, 'work-m3b4d-editor-runtime.mjs' ),
];
foreach ( $checks as $name => $passed ) {
	if (!$passed) { fwrite(STDERR, "Work M3-B4-B4 source FAIL: {$name}\n"); exit(1); }
}
echo "Work M3-B4-B4 editor SOURCE: PASS (responsive, canonical scheduling, preview, translations, Base).\n";

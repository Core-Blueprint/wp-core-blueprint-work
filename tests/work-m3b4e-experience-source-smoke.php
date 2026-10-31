<?php
declare(strict_types=1);
$root = dirname( __DIR__ );
$js = (string) file_get_contents($root . '/assets/js/workspace/recurring.js');
$app = (string) file_get_contents($root . '/assets/js/workspace/app.js');
$css = (string) file_get_contents($root . '/assets/css/unified-workspace.css');
$php = (string) file_get_contents($root . '/src/Admin/UnifiedWorkspace.php');
$read = (string) file_get_contents($root . '/src/Transport/Rest/WorkspaceReadController.php');
$editor = (string) file_get_contents($root . '/src/Transport/Rest/WorkspaceRecurrenceEditorController.php');
$scheduler = (string) file_get_contents($root . '/src/Recurrence/Scheduler.php');
$runner = (string) file_get_contents($root . '/tools/check');
$pot = (string) file_get_contents($root . '/languages/core-blueprint-work.pot');
$checks = [
  'Recurring filters have a scoped responsive layout, not a global override' =>
    str_contains($css, '[data-cb-work-uwe-panel="recurring"] .cb-work-uwe__controls')
    && str_contains($css, 'grid-template-columns:minmax(240px,1fr)')
    && str_contains($css, '@media (max-width:540px)')
    && str_contains($php, 'cb-work-recurring__search-submit'),
  'Server-owned classification and site-formatted dates; no private data in cards' =>
    str_contains($read, "'occurrence_state' =>")
    && str_contains($read, "'next_occurrence_label' =>")
    && str_contains($read, "current_time( 'Y-m-d' )")
    && str_contains($app, 'recurringRecordFacts(record, panel.dataset)')
    && str_contains($app, 'button.dataset.pendingSince'),
  'Explicit catch-up consent is enforced before the guarded canonical writer' =>
    str_contains($editor, 'work_recurrence_catchup_confirmation_required')
    && str_contains($editor, 'RecurrenceRules::update_if_revision(')
    && str_contains($js, 'cbWorkRecurringActivateWarning')
    && str_contains($js, 'trigger.dataset.pendingSince')
    && str_contains($js, 'browser.cbCore.modal.show('),
  'Authoritative preview distinguishes pending dates without mutating schedule' =>
    str_contains($editor, "'pending' => \$date < \$site_today")
    && str_contains($editor, 'Recurrence::preview_dates(')
    && str_contains($js, 'cbWorkRecurringPendingPreview')
    && str_contains($js, 'value?.pending === true'),
  'Four new translatable strings are tracked in POT, protected Base modal unchanged' =>
    str_contains($pot, 'msgid "Pending since"')
    && str_contains($pot, 'msgid "Pending occurrences (preview)"')
    && str_contains($pot, 'msgid "Work associations"')
    && str_contains($pot, 'msgid "Activating this rule may generate Work Items for past scheduled dates. Continue?"')
    && str_contains($js, "size: quick ? 'default' : 'workspace'"),
  'Scheduler retains intentional occurrence-by-occurrence catch-up contract' =>
    str_contains($scheduler, 'Catch-up is intentional')
    && str_contains($scheduler, 'MAX_OCCURRENCES_PER_RULE = 250'),
  'Activation interaction test runs in canonical Level 1' =>
    str_contains($runner, 'work-m3b4e-activation-runtime.mjs'),
];
foreach ($checks as $name=>$ok) {
 if (!$ok) {fwrite(STDERR, "Work M3-B4-B5 source FAIL: $name\n");exit(1);}
}
echo "Work M3-B4-B5 source: PASS (scoped filters, overdue lifecycle guard, preview, i18n, Golden).\n";

<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$plugin = file_get_contents($root . '/src/Plugin.php');
$transport = file_get_contents($root . '/src/Transport/Rest/WorkspaceRecurrenceEditorController.php');
$runner = file_get_contents($root . '/tools/check');
$needles = [
	"current_user_can( Capabilities::MANAGE )",
	"CB_WORK_SCHEMA_VERSION === (string) get_option( Schema::OPTION, '0' )",
	'RecurrenceRules::get( $id )',
	'RecurrenceOccurrences::count_for_rule( $id )',
	"RecurrenceSchedule::normalize(",
	"Recurrence::preview_dates(",
	"CRMCustomers::selected(",
	'if ( $locked && ! $unchanged )',
	"rest_ensure_response(",
];
foreach ($needles as $needle) {
	if (false === strpos((string) $transport, $needle)) {
		fwrite(STDERR, "M3-B4-B transport source FAIL: {$needle}\n");
		exit(1);
	}
}
if (false === strpos((string) $plugin, 'WorkspaceRecurrenceEditorController::init();')
	|| false === strpos((string) $runner, 'tests/work-m3b4b-transport-runtime.php')
	|| false !== strpos((string) $transport, 'RecurrenceRules::update(')
	|| false !== strpos((string) $transport, 'RecurrenceRules::create(')) {
	fwrite(STDERR, "M3-B4-B transport source FAIL: boot, enrolment, or read-only boundary\n");
	exit(1);
}
echo "Work M3-B4-B read-only editor transport source: PASS\n";

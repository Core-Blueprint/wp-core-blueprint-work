<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$bootstrap = (string) file_get_contents($root . '/core-blueprint-work.php');
$schema = (string) file_get_contents($root . '/src/Database/Schema.php');
$repo = (string) file_get_contents($root . '/src/Repository/RecurrenceRules.php');
$controller = (string) file_get_contents($root . '/src/Transport/Rest/WorkspaceRecurrenceEditorController.php');
$runner = (string) file_get_contents($root . '/tools/check');
$checks = [
	'additive 2.5 schema' => str_contains($bootstrap, "CB_WORK_SCHEMA_VERSION', '2.5'") && str_contains($schema, 'revision int unsigned NOT NULL DEFAULT 1,'),
	'canonical revision row and hydration' => str_contains($repo, "'revision'             => " . '$normalized[\'revision\']') && str_contains($repo, "'revision'             => " . '(int) $row[\'revision\']'),
	'locked optimistic update' => str_contains($repo, 'public static function update_if_revision(') && str_contains($repo, 'work_recurrence_revision_conflict') && str_contains($repo, 'FOR UPDATE'),
	'project sync atomic increment' => str_contains($repo, 'revision = revision + 1 WHERE project_id = %d'),
	'scheduler and lifecycle increments' => substr_count($repo, "'revision' => (int) " . '$rule[\'revision\'] + 1') >= 2 && str_contains($repo, "'revision'           => " . '(int) $rule[\'revision\'] + 1'),
	'detail exposes revision' => str_contains($controller, "'revision' => (int) " . '$rule[\'revision\']'),
	'regression enrolled' => str_contains($runner, 'tests/work-m3b4b-revision-source-smoke.php'),
];
foreach($checks as $label=>$ok) {
	if(!$ok) { fwrite(STDERR, "M3-B4-B revision FAIL: {$label}\n"); exit(1); }
}
echo "Work M3-B4-B canonical revision foundation: PASS\n";

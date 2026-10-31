<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$bootstrap = (string) file_get_contents($root . '/core-blueprint-work.php');
$schema = (string) file_get_contents($root . '/src/Database/Schema.php');
$repo = (string) file_get_contents($root . '/src/Repository/RecurrenceRules.php');
$controller = (string) file_get_contents($root . '/src/Transport/Rest/WorkspaceRecurrenceEditorController.php');
$runner = (string) file_get_contents($root . '/tools/check');
$foundation = (string) file_get_contents($root . '/tests/recurrence-foundation-smoke.php');
$runtime = (string) file_get_contents($root . '/tests/runtime-smoke.php');
$checks = [
	'foundation and runtime use current 2.5 schema contract' => str_contains($foundation, "CB_WORK_SCHEMA_VERSION', '2.5'")
		&& str_contains($runtime, "CB_WORK_SCHEMA_VERSION, 'Work recurrence revision schema version 2.5.'")
		&& str_contains($runtime, "cb_work_db_version'] = '2.5'"),
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
// The current release schema is a product contract, not a historical
// fixture version. Detect stale hard-coded expectations across the complete
// PHP test tree before unrelated Level 1 smokes become serial failures.
$schema_version = [];
if ( ! preg_match( "/define\(\s*'CB_WORK_SCHEMA_VERSION'\s*,\s*'([0-9]+\.[0-9]+)'\s*\)/", $bootstrap, $schema_version ) ) {
	fwrite( STDERR, "M3-B4-B schema contract audit FAIL: missing current schema version\n" );
	exit( 1 );
}
$active = $schema_version[1];
$checked = 0;
$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $root . '/tests', FilesystemIterator::SKIP_DOTS )
);
foreach ( $iterator as $entry ) {
	if ( ! $entry->isFile() || 'php' !== $entry->getExtension() ) {
		continue;
	}
	++$checked;
	$source = (string) file_get_contents( $entry->getPathname() );
	$patterns = [
		'/CB_WORK_SCHEMA_VERSION\x27,\s*\x27([0-9]+\.[0-9]+)\x27/',
		'/\x27([0-9]+\.[0-9]+)\x27\s*===\s*CB_WORK_SCHEMA_VERSION/',
		'/cb_work_db_version\x27\]\s*=\s*\x27([0-9]+\.[0-9]+)\x27/',
	];
	foreach ( $patterns as $pattern ) {
		if ( ! preg_match_all( $pattern, $source, $matched ) ) {
			continue;
		}
		foreach ( $matched[1] as $expected ) {
			if ( $expected !== $active ) {
				fwrite( STDERR, "M3-B4-B schema contract audit FAIL: stale current-version assertion in "
					. $entry->getPathname() . " (expected {$expected}, current {$active})\n" );
				exit( 1 );
			}
		}
	}
}
echo "Work M3-B4-B schema contract audit: PASS ({$checked} PHP test files)\n";

echo "Work M3-B4-B canonical revision foundation: PASS\n";

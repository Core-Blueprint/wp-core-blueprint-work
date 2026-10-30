<?php
declare(strict_types=1);
$root = dirname( __DIR__ );
$shell = file_get_contents( $root . '/src/Admin/UnifiedWorkspace.php' );
$router = file_get_contents( $root . '/assets/js/workspace/router.js' );
$state = file_get_contents( $root . '/assets/js/workspace/state.js' );
$rest = file_get_contents( $root . '/src/Transport/Rest/WorkspaceReadController.php' );
$app = file_get_contents( $root . '/assets/js/workspace/app.js' );
$rule = file_get_contents( $root . '/src/Repository/RecurrenceRules.php' );
$checks = [
	'real fourth tab, no native-link substitute' => str_contains( $router, "'services', 'recurring'" ) && str_contains( $shell, "'services', 'recurring'" ) && !str_contains( $shell, 'data-cb-work-ux1-recurring-fallback' ),
	'SSR and managed query on canonical repository' => str_contains( $shell, 'RecurrenceRules::search_page(' ) && str_contains( $rest, 'RecurrenceRules::search_page(' ) && str_contains( $rule, 'public static function search_page(' ),
	'GET-only and private read' => str_contains( $rest, 'self::can_read( $request )' ) && !str_contains( $rest, 'RecurrenceRules::create(' ) && !str_contains( $rest, 'RecurrenceRules::update(' ),
	'recurring explicit filters and URL state' => str_contains( $state, "['active', 'inactive']" ) && str_contains( $state, "'next_occurrence'" ) && str_contains( $app, "section === 'recurring'" ),
	'native advanced editor retained as accessible fallback' => str_contains( $shell, 'Menu::RECURRENCE_SLUG' ) && str_contains( $shell, 'data-cb-work-b4-native' ) && str_contains( $app, "url.searchParams.set('rule_id'" ),
];
foreach ($checks as $label => $ok) {
	if (!$ok) { fwrite(STDERR, "Work M3-B4-A SOURCE FAIL: {$label}\n"); exit(1); }
}
echo "Work M3-B4-A SOURCE: PASS (router, bounded canonical read, filters, native editor fallback).\n";

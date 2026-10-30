<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$api = (string) file_get_contents( $root . '/src/Transport/Rest/WorkspaceTimeController.php' );
$panel = (string) file_get_contents( $root . '/src/Admin/WorkspaceTimePanel.php' );
$nav = (string) file_get_contents( $root . '/src/Admin/WorkspaceOperationsPanel.php' );
$js = (string) file_get_contents( $root . '/assets/js/workspace/time.js' );
$app = (string) file_get_contents( $root . '/assets/js/workspace/app.js' );
$hud = (string) file_get_contents( $root . '/assets/global-time-hud.js' );
$assets = (string) file_get_contents( $root . '/src/Admin/Assets.php' );
$compiler = (string) file_get_contents( $root . '/tools/prepare-workspace-modules.py' );
$plugin = (string) file_get_contents( $root . '/src/Plugin.php' );
$styles = (string) file_get_contents( $root . '/assets/css/unified-workspace.css' );
$tests = (string) file_get_contents( $root . '/tools/check' );
$ops = (string) file_get_contents( $root . '/src/Admin/Operations.php' );

$checks = [
	'GET state + GET picker + POST timer with explicit permission and rechecks' =>
		str_contains( $api, "'/workspace/time'" )
		&& str_contains( $api, "'/workspace/time/work-items'" )
		&& str_contains( $api, "'/workspace/time/timer'" )
		&& str_contains( $api, 'WP_REST_Server::READABLE' )
		&& str_contains( $api, 'WP_REST_Server::CREATABLE' )
		&& str_contains( $api, "'permission_callback' => $permission" )
		&& str_contains( $api, 'self::actor( $request )' ),
	'Manager-only current user and canonical schema gate, time-only roles preserved on native screen' =>
		str_contains( $api, 'current_user_can( Capabilities::MANAGE )' )
		&& str_contains( $api, 'CB_WORK_SCHEMA_VERSION === (string) get_option( Schema::OPTION' )
		&& str_contains( $api, 'get_current_user_id()' )
		&& str_contains( $panel, 'current_user_can( Capabilities::MANAGE )' )
		&& str_contains( $panel, 'Menu::time_url()' ),
	'Actor-scoped bounded canonical reader and Work Item picker, no leaked entry note' =>
		str_contains( $api, 'TimeEntries::query_completed(' )
		&& str_contains( $api, "'user_id' => \$actor" )
		&& str_contains( $api, "'per_page' => 10" )
		&& str_contains( $api, 'WorkItemPickerSearch::results(' )
		&& str_contains( $api, 'Access::can_view_entry(' )
		&& ! str_contains( $api, "'note' => (string) \$entry['note']" ),
	'Canonical timer start/stop retains Work Item auth, audit events and actor isolation' =>
		str_contains( $api, 'Access::can_track_work_item( $id, $actor )' )
		&& str_contains( $api, 'Access::can_stop_user_timer( $actor )' )
		&& str_contains( $api, 'Timers::start( $id, $actor, $note, $actor )' )
		&& str_contains( $api, 'Timers::stop( $actor, $actor )' )
		&& str_contains( $api, 'Events::TIMER_STARTED' )
		&& str_contains( $api, 'Events::TIMER_STOPPED' )
		&& ! str_contains( $api, 'WorkSqlTransaction::' ),
	'Progressive Time entry in Work preserves full native tracker and advanced Time entry routes' =>
		str_contains( $nav, 'data-cb-work-m3-time-link' )
		&& str_contains( $nav, 'WorkspaceTimePanel::render();' )
		&& str_contains( $panel, 'data-cb-work-m3-time-search' )
		&& str_contains( $panel, 'data-cb-work-m3-time-timer' )
		&& str_contains( $panel, 'data-cb-work-m3-time-start-form' )
		&& str_contains( $panel, 'data-cb-work-m3-time-entries' )
		&& str_contains( $panel, 'Open Time workspace' ),
	'Safe JS hydrated source, native fallback, and existing global Timer HUD re-sync' =>
		str_contains( $app, "from './time.js'" )
		&& str_contains( $app, 'mountWorkspaceTime(root, browser' )
		&& str_contains( $js, "credentials: 'same-origin'" )
		&& str_contains( $js, "'X-WP-Nonce': nonce" )
		&& str_contains( $js, 'textContent' )
		&& str_contains( $js, 'replaceChildren' )
		&& ! str_contains( $js, 'innerHTML' )
		&& str_contains( $js, "'cb-work-time:changed'" )
		&& str_contains( $hud, "window.addEventListener('cb-work-time:changed', sync);" ),
	'Asset graph is content-addressed, fail-closed and Work styles are scoped' =>
		str_contains( $assets, 'is_file( $time_module )' )
		&& str_contains( $compiler, '"time.js"' )
		&& str_contains( $plugin, 'WorkspaceTimeController::init();' )
		&& str_contains( $styles, '.cb-work-unified .cb-work-m3-time' ),
	'Regression tests enrolled; G0/B1 renderer SHA protected' =>
		str_contains( $tests, 'work-m3c1-time-source-smoke.php' )
		&& str_contains( $tests, 'work-m3c1-time-runtime.php' )
		&& str_contains( $tests, 'work-m3c1-time-runtime.mjs' )
		&& sha1( 'blob ' . strlen( $ops ) . "\0" . $ops )
			=== '92a4814f24b43f96d16e3d96ce61a49ee9f21500',
];
foreach ( $checks as $reason => $ok ) {
	if ( ! $ok ) { fwrite( STDERR, "M3-C1 Time source FAIL: {$reason}\n" ); exit( 1 ); }
}
echo "Work M3-C1 Time source: PASS (manager, timer, actor reads, HUD, immutable ESM).\n";

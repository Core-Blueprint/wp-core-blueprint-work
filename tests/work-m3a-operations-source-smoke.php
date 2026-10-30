<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$plugin = (string) file_get_contents( $root . '/src/Plugin.php' );
$api = (string) file_get_contents( $root . '/src/Transport/Rest/WorkspaceOperationsController.php' );
$snapshot = (string) file_get_contents( $root . '/src/Operations/WorkspaceSnapshot.php' );
$panel = (string) file_get_contents( $root . '/src/Admin/WorkspaceOperationsPanel.php' );
$shell = (string) file_get_contents( $root . '/src/Admin/UnifiedWorkspace.php' );
$app = (string) file_get_contents( $root . '/assets/js/workspace/app.js' );
$bridge = (string) file_get_contents( $root . '/assets/js/workspace/operations.js' );
$stage = (string) file_get_contents( $root . '/tools/prepare-workspace-modules.py' );
$style = (string) file_get_contents( $root . '/assets/css/unified-workspace.css' );
$check = (string) file_get_contents( $root . '/tools/check' );
$menu = (string) file_get_contents( $root . '/src/Admin/Menu.php' );
$ops = (string) file_get_contents( $root . '/src/Admin/Operations.php' );
$golden = sha1( 'blob ' . strlen( $ops ) . "\0" . $ops );

$checks = [
	'Only M3-A uses new GET-only REST route, initialized through product boot' =>
		str_contains( $plugin, 'WorkspaceOperationsController::init();' )
		&& str_contains( $api, "public const ROUTE = '/workspace/operations';" )
		&& str_contains( $api, 'WP_REST_Server::READABLE' )
		&& str_contains( $api, "'permission_callback' => [ self::class, 'can_read' ]" )
		&& str_contains( $api, 'current_user_can( Capabilities::MANAGE )' )
		&& str_contains( $api, '\\cb_work_runtime_ready()' )
		&& str_contains( $api, 'self::can_read( $request )' ),
	'Domain read model reuses canonical repositories without independent state or writers' =>
		str_contains( $snapshot, 'ServiceOperations::search(' )
		&& str_contains( $snapshot, 'RecurrenceRules::search_page(' )
		&& str_contains( $snapshot, 'Timers::active_for_user( $actor_id )' )
		&& str_contains( $snapshot, '$actor_id !== get_current_user_id()' )
		&& ! str_contains( $snapshot, 'Scheduler::' )
		&& ! str_contains( $snapshot, '::start(' )
		&& ! str_contains( $snapshot, '::stop(' )
		&& ! str_contains( $snapshot, '::create(' ),
	'M3-A operational shell is persistent and accessible from all Work views' =>
		str_contains( $shell, 'WorkspaceOperationsPanel::render();' )
		&& str_contains( $panel, 'data-cb-work-m3-operations' )
		&& str_contains( $panel, 'data-cb-work-m3-endpoint' )
		&& str_contains( $panel, 'data-cb-work-m3-nonce' )
		&& str_contains( $panel, 'Menu::SERVICE_OPERATIONS_SLUG' )
		&& str_contains( $panel, 'Menu::RECURRENCE_SLUG' )
		&& str_contains( $panel, 'Menu::time_url()' )
		&& ! str_contains( $shell, 'cb-work-uwe__related' ),
	'SPA module is part of the same cache-safe module graph and uses no HTML injection' =>
		str_contains( $app, "from './operations.js'" )
		&& str_contains( $app, 'mountWorkspaceOperations(root, browser);' )
		&& str_contains( $stage, '"operations.js"' )
		&& str_contains( $bridge, "method: 'GET'" )
		&& str_contains( $bridge, "'same-origin'" )
		&& str_contains( $bridge, "cache: 'no-store'" )
		&& str_contains( $bridge, "'X-WP-Nonce'" )
		&& str_contains( $bridge, 'textContent' )
		&& ! str_contains( $bridge, 'innerHTML' ),
	'Work CSS uses Base/Work scoped theme tokens and narrow viewport behavior' =>
		str_contains( $style, '.cb-work-unified .cb-work-m3-operations' )
		&& str_contains( $style, 'var(--cb-work-border' )
		&& str_contains( $style, ':focus-visible' )
		&& str_contains( $style, '@media screen and (max-width: 782px)' ),
	'Legacy authorized routes and G0/B1 HTML renderer untouched' =>
		str_contains( $menu, "self::UNIFIED_WORKSPACE_SLUG, [ UnifiedWorkspace::class, 'render' ]" )
		&& str_contains( $menu, 'remove_submenu_page( self::TOP_LEVEL_SLUG, self::TIME_SLUG )' )
		&& '92a4814f24b43f96d16e3d96ce61a49ee9f21500' === $golden,
	'All M3-A tests are registered with canonical Level 1' =>
		str_contains( $check, 'tests/work-m3a-operations-source-smoke.php' )
		&& str_contains( $check, 'tests/work-m3a-operations-snapshot-runtime.php' )
		&& str_contains( $check, 'tests/work-m3a-operations-runtime.mjs' ),
];
foreach ( $checks as $label => $ok ) {
	if ( ! $ok ) {
		fwrite( STDERR, "Work M3-A source contract FAIL: {$label}\n" );
		exit( 1 );
	}
}
echo "Work M3-A source: PASS (GET-only, manager, canonical reads, persistent operations, immutable modules).\n";

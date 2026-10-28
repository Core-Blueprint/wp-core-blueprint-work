<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$bridge = (string) file_get_contents( $root . '/src/Admin/WorkItemViewBridge.php' );
$plugin = (string) file_get_contents( $root . '/src/Plugin.php' );
$shell = (string) file_get_contents( $root . '/src/Admin/UnifiedWorkspace.php' );
$operations = (string) file_get_contents( $root . '/src/Admin/Operations.php' );
$check = (string) file_get_contents( $root . '/tools/check' );

$checks = [
	'authenticated admin-only AJAX hook' =>
		str_contains( $bridge, "add_action( 'wp_ajax_' . self::ACTION" )
		&& ! str_contains( $bridge, "wp_ajax_nopriv_" )
		&& str_contains( $plugin, 'WorkItemViewBridge::init();' )
		&& strpos( $plugin, 'WorkItemViewBridge::init();' ) > strpos( $plugin, 'if ( is_admin() ) {' ),
	'GET-only, manager-only, CSRF protected transport' =>
		str_contains( $bridge, "'GET' !== (string) ( \$_SERVER['REQUEST_METHOD']" )
		&& str_contains( $bridge, 'current_user_can( Capabilities::MANAGE )' )
		&& str_contains( $bridge, 'check_ajax_referer( self::NONCE_ACTION' )
		&& str_contains( $bridge, 'cb_work_runtime_ready()' )
		&& str_contains( $bridge, "wp_send_json_success( \$rendered, 200 )" ),
	'view parameter accepted only via canonical WorkItemViewState' =>
		str_contains( $bridge, 'WorkItemViewState::views()' )
		&& str_contains( $bridge, 'WorkItemViewPreferences::apply_default_to_request' )
		&& str_contains( $bridge, 'WorkItemViewState::from_request' )
		&& str_contains( $bridge, "'paged'" )
		&& ! str_contains( $bridge, "'page', 's'" ),
	'exact existing renderer called once, without copied renderer code' =>
		str_contains( $bridge, 'Operations::render_work_items();' )
		&& 1 === substr_count( $bridge, 'Operations::render_work_items();' )
		&& ! str_contains( $bridge, 'WorkItems::search(' )
		&& ! str_contains( $bridge, 'wp_insert_post(' )
		&& ! str_contains( $bridge, 'wp_update_post(' ),
	'superglobals and buffers isolated with exception-safe finally' =>
		str_contains( $bridge, '$original = $_GET;' )
		&& str_contains( $bridge, '$_GET = $normalized;' )
		&& str_contains( $bridge, 'finally {' )
		&& str_contains( $bridge, '$_GET = $original;' )
		&& str_contains( $bridge, 'ob_end_clean();' ),
	'only Work Items receives an opt-in bridge contract' =>
		str_contains( $shell, "if ( 'work-items' === \$key )" )
		&& str_contains( $shell, 'data-cb-work-uwe-classic-endpoint=' )
		&& str_contains( $shell, 'data-cb-work-uwe-classic-action=' )
		&& str_contains( $shell, 'data-cb-work-uwe-classic-nonce=' )
		&& str_contains( $shell, 'WorkItemViewBridge::NONCE_ACTION' ),
	'unchanged G0 source and no rollout of the new UI yet' =>
		'92a4814f24b43f96d16e3d96ce61a49ee9f21500' === sha1( 'blob ' . strlen( $operations ) . "\0" . $operations )
		&& str_contains( $shell, 'WorkItems::all( 25 )' )
		&& ! str_contains( $shell, 'WorkItemViewBridge::render_html(' ),
	'Level 1 and Level 2 contract fixtures' =>
		str_contains( $check, 'tests/work-uwe-03c1-bridge-smoke.php' )
		&& is_file( $root . '/tests/integration/WorkItemViewBridgeIntegrationTest.php' ),
];

foreach ( $checks as $label => $pass ) {
	if ( ! $pass ) {
		fwrite( STDERR, "UWE-03C.1 canonical bridge source FAIL: {$label}\n" );
		exit( 1 );
	}
}

echo "UWE-03C.1 canonical Work Items renderer bridge: PASS.\n";

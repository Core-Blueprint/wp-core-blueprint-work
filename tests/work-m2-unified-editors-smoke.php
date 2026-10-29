<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$action = (string) file_get_contents( $root . '/src/Admin/UnifiedEditorActions.php' );
$plugin = (string) file_get_contents( $root . '/src/Plugin.php' );
$asset = (string) file_get_contents( $root . '/src/Admin/Assets.php' );
$shell = (string) file_get_contents( $root . '/src/Admin/UnifiedWorkspace.php' );
$check = (string) file_get_contents( $root . '/tools/check' );
$valid = [
    'manager capability and nonce before reads or writes' =>
        str_contains( $action, 'current_user_can( Capabilities::MANAGE )' )
        && str_contains( $action, 'check_ajax_referer( self::NONCE_ACTION' )
        && strpos( $action, 'current_user_can( Capabilities::MANAGE )' ) < strpos( $action, 'wp_send_json_success' ),
    'existing Work domain actions own persistence and audits' =>
        str_contains( $action, 'WorkItemActions::create( $input )' )
        && str_contains( $action, 'WorkItemActions::update( $id, $input, $revision )' )
        && str_contains( $action, 'ProjectActions::create( $input )' )
        && str_contains( $action, 'ProjectActions::update( $id, $input, $revision )' )
        && ! str_contains( $action, 'wp_insert_post' )
        && ! str_contains( $action, 'wp_update_post' )
        && ! str_contains( $action, 'update_post_meta' ),
    'whitelist scalar fields, stale rejection and no content editing' =>
        str_contains( $action, 'in_array( $name, $allowed, true )' )
        && str_contains( $action, 'hash_equals(' )
        && str_contains( $action, "'work_editor_stale'" )
        && ! str_contains( $action, "'description', 'project_id'" ),
    'existing editors remain; no new Base internals' =>
        str_contains( $shell, 'data-cb-work-m2-endpoint' )
        && str_contains( $shell, 'Open advanced editor' )
        && str_contains( $asset, "self::enqueue_unified_canonical_view_styles();" )
        && str_contains( $plugin, 'UnifiedEditorActions::init();' ),
    'Work Types preserve canonical nonce/handler and native redirect fallback' =>
        str_contains( $shell, 'data-cb-work-m2-types' )
        && str_contains( $action, "'work-type' === \$entity" )
        && str_contains( $action, "wp_create_nonce( 'cb_work_create_work_type' )" )
        && str_contains( $action, "wp_create_nonce( 'cb_work_toggle_work_type_' ." )
        && str_contains( (string) file_get_contents( $root . '/src/Admin/OperationalActions.php' ), "add_action( 'wp_ajax_cb_work_toggle_work_type'" )
        && str_contains( (string) file_get_contents( $root . '/src/Admin/OperationalActions.php' ), "add_action( 'wp_ajax_cb_work_create_work_type'" )
        && str_contains( (string) file_get_contents( $root . '/src/Admin/OperationalActions.php' ), 'wp_safe_redirect(' ),
    'Level 1 registers new focused runtime test' =>
        str_contains( $check, 'work-m2-unified-editors-runtime.mjs' ),
];
foreach ( $valid as $name => $ok ) {
    if ( ! $ok ) {
        fwrite( STDERR, "M2 Unified Editors source contract FAIL: {$name}\n" );
        exit( 1 );
    }
}
echo "M2 Unified Editors source contract: PASS.\n";

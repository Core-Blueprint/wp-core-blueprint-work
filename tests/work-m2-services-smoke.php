<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$editor = (string) file_get_contents( $root . '/src/Admin/UnifiedEditorActions.php' );
$service = (string) file_get_contents( $root . '/src/PublicApi/ServiceActions.php' );
$work_item = (string) file_get_contents( $root . '/src/Repository/WorkItems.php' );
$project = (string) file_get_contents( $root . '/src/Repository/Projects.php' );
$app = (string) file_get_contents( $root . '/assets/js/workspace/app.js' );
$ui = (string) file_get_contents( $root . '/assets/js/workspace/editors.js' );
$layout = (string) file_get_contents( $root . '/src/Admin/UnifiedWorkspace.php' );
$cases = [
    'Services editor requires the same authenticated capability and nonce as the Workspace' =>
        str_contains( $editor, "current_user_can( Capabilities::MANAGE )" )
        && str_contains( $editor, "check_ajax_referer( self::NONCE_ACTION" )
        && str_contains( $editor, 'ServiceActions::get( $id )' )
        && str_contains( $editor, 'ServiceActions::save( $id, $input, $revision )' ),
    'Service storage reuses WordPress CPT and canonical pricing, never a second table' =>
        str_contains( $service, 'PostTypes::SERVICE' )
        && str_contains( $service, 'ServicePricing::save( $service_id, $pricing_input )' )
        && str_contains( $service, 'WorkSqlTransaction::run(' )
        && str_contains( $service, 'FOR UPDATE' )
        && str_contains( $service, 'hash_equals(' )
        && str_contains( $service, 'actual' ) && str_contains( $service, 'pricing' )
        && ! str_contains( $editor, 'wp_update_post(' ),
    'Services validate financial input before persistence and preserve blocks' =>
        str_contains( $service, 'ServicePricing::parse_amount_minor(' )
        && str_contains( $service, 'TaxRates::is_available(' )
        && str_contains( $service, "'work_service_pricing_invalid'" )
        && ! str_contains( $service, "'post_content' => wp_kses_post" )
        && str_contains( $service, "'post_content' => \$description_requested ? \$description : ''" )
        && str_contains( $service, "work_service_description_advanced_required" ),
    'Partial Work Item and Project updates never sanitize existing Gutenberg content' =>
        str_contains( $work_item, 'array_key_exists(' )
        && str_contains( $project, 'array_key_exists(' )
        && str_contains( $work_item, 'wp_update_post( $post_update, true )' )
        && str_contains( $project, 'wp_update_post( $post_update, true )' ),
    'Services create/edit are connected in the Unified Workspace only' =>
        str_contains( $layout, 'data-cb-work-m2-add=' )
        || ( str_contains( $layout, "[ 'work-items', 'projects', 'services' ]" )
            && str_contains( $layout, "data-cb-work-m2-add=" )
            && str_contains( $layout, "data-cb-work-m2-edit=" )
            && str_contains( $app, "'services'" )
            && str_contains( $ui, "'service' ? 'services'" ) ),
    'Level 2 suite includes guarded Service integration checks' =>
        is_file( $root . '/tests/integration/UnifiedServiceEditorIntegrationTest.php' )
        && str_contains( (string) file_get_contents( $root . '/tests/phpunit-time-cas.xml.dist' ), '<directory suffix="Test.php">integration</directory>' ),
];
foreach ( $cases as $name => $passed ) {
    if ( ! $passed ) {
        fwrite( STDERR, "M2 Service source gate failed: {$name}\n" );
        exit( 1 );
    }
}
echo "M2 Service source and content-preservation contracts: PASS.\n";

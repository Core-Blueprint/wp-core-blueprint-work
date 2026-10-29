<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$model = (string) file_get_contents( $root . '/src/Admin/UnifiedEditorDescription.php' );
$controller = (string) file_get_contents( $root . '/src/Admin/UnifiedEditorActions.php' );
$service = (string) file_get_contents( $root . '/src/PublicApi/ServiceActions.php' );
$js = (string) file_get_contents( $root . '/assets/js/workspace/editors.js' );
$shell = (string) file_get_contents( $root . '/src/Admin/UnifiedWorkspace.php' );
$gate = (string) file_get_contents( $root . '/tools/check' );
$checks = [
    'Gutenberg and HTML never traverse the plain-text editor' =>
        str_contains( $model, "str_contains( \$content, '<' )" )
        && str_contains( $model, "str_contains( \$submitted, '<' )" ) === false
        && str_contains( $model, 'self::editable( $submitted )' )
        && str_contains( $model, 'self::editable( $current )' )
        && str_contains( $model, 'work_editor_description_advanced_required' ),
    'Bounded text is normalized separately without destroying line breaks' =>
        str_contains( $model, 'MAX_LENGTH = 16000' )
        && str_contains( $model, 'sanitize_textarea_field( $submitted )' )
        && str_contains( $controller, "'description' === \$name" )
        && str_contains( $controller, 'UnifiedEditorDescription::normalize(' ),
    'Service description is updated under existing transaction with readback' =>
        str_contains( $service, 'WorkSqlTransaction::run(' )
        && str_contains( $service, "'post_content' => \$description_requested ? \$description : ''" )
        && str_contains( $service, "\$post_update['post_content'] = \$description;" )
        && str_contains( $service, "\$actual['description'] !== \$expected_content" )
        && str_contains( $service, 'work_service_description_advanced_required' ),
    'Modal preserves rich content and only submits changed plain text' =>
        str_contains( $js, "if (entity === 'work-item' || entity === 'project')" )
        && str_contains( $js, 'form.appendChild(grid)' )
        && str_contains( $js, 'descriptionInfo.mode === \'plain\'' )
        && str_contains( $js, 'control.name === \'description\' && control.value === control.dataset.cbWorkM2Initial' )
        && str_contains( $js, 'cbWorkM2RichDescription' )
        && ! str_contains( $js, 'innerHTML =' ),
    'Localized modal help and focused tests are included' =>
        str_contains( $shell, "data-cb-work-m2-description-label=" )
        && str_contains( $shell, "data-cb-work-m2-rich-description=" )
        && str_contains( $gate, 'work-m2-description-runtime.mjs' )
        && is_file( $root . '/tests/integration/UnifiedEditorDescriptionIntegrationTest.php' ),
];
foreach ( $checks as $name => $passed ) {
    if ( ! $passed ) {
        fwrite( STDERR, "M2 description source contract FAIL: {$name}\n" );
        exit( 1 );
    }
}
echo "M2 description, Gutenberg preservation and grid source: PASS.\n";

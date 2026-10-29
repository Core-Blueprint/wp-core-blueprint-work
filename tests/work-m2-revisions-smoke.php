<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$revisions = (string) file_get_contents( $root . '/src/Repository/EditorRevisions.php' );
$work = (string) file_get_contents( $root . '/src/Repository/WorkItems.php' );
$editor_mutations = (string) file_get_contents( $root . '/src/Repository/EditorMutations.php' );
$project = (string) file_get_contents( $root . '/src/Repository/Projects.php' );
$work_api = (string) file_get_contents( $root . '/src/PublicApi/WorkItemActions.php' );
$project_api = (string) file_get_contents( $root . '/src/PublicApi/ProjectActions.php' );
$ajax = (string) file_get_contents( $root . '/src/Admin/UnifiedEditorActions.php' );
$ui = (string) file_get_contents( $root . '/assets/js/workspace/editors.js' );
$checks = [
    'One stable, typed semantic editor fingerprint for both domains' =>
        str_contains( $revisions, 'function for_work_item(' )
        && str_contains( $revisions, 'function for_project(' )
        && str_contains( $revisions, "'description'" )
        && str_contains( $revisions, "'assigned_user_ids'" )
        && str_contains( $revisions, "'customer_provider'" ),
    'Work Item revision checked inside existing FOR UPDATE mutation lock' =>
        str_contains( $work, 'WorkItemMutationTransaction::run(' )
        && str_contains( $editor_mutations, 'WorkItemMutationTransaction::run(' )
        && str_contains( $editor_mutations, 'EditorRevisions::for_work_item( $current )' )
        && str_contains( $editor_mutations, 'WorkItems::update( $id, $input )' )
        && str_contains( $work_api, 'EditorMutations::update_work_item( $work_item_id, $input, $expected_revision )' ),
    'Project revision checked under a transactional row lock' =>
        str_contains( $project, 'WorkSqlTransaction::run(' )
        && str_contains( $project, 'FOR UPDATE' )
        && str_contains( $project, 'EditorRevisions::for_project( $fresh )' )
        && str_contains( $project_api, 'ProjectRepository::update( $project_id, $input, $expected_revision )' ),
    'AJAX fast-check is repeated by the underlying domains' =>
        str_contains( $ajax, 'EditorRevisions::for_work_item( $existing )' )
        && str_contains( $ajax, 'EditorRevisions::for_project( $existing )' )
        && str_contains( $ajax, "self::fail( 'work_editor_stale', 409 )" )
        && str_contains( $ajax, 'WorkItemActions::update_from_editor( $id, $input, $revision )' )
        && str_contains( $ajax, 'ProjectActions::update( $id, $input, $revision )' )
        && str_contains( $ui, "payload.set('expected_version', String(body.expected_version || ''))" ),
    'Billing disposition is delegated, never written as raw postmeta' =>
        str_contains( $ajax, "'billing_disposition'" )
        && str_contains( $ui, "'billing_disposition'" )
        && ! str_contains( $ajax, 'update_post_meta(' ),
];
foreach ( $checks as $label => $passed ) {
    if ( ! $passed ) {
        fwrite( STDERR, "M2 revision source FAIL: {$label}\n" );
        exit( 1 );
    }
}
echo "M2 semantic revision and billing source: PASS.\n";

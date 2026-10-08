<?php
declare(strict_types=1);

// Read-only proof of Work Time's bounded Base ObjectPicker integration.
// Real >500 SQL behavior and tracker visibility live in PHPUnit integration.
$root = dirname( __DIR__ );
$time = (string) file_get_contents( $root . '/src/Admin/Time.php' );
$bulk = (string) file_get_contents( $root . '/src/Admin/TimeEntryList.php' );
$handler = (string) file_get_contents( $root . '/src/Admin/TimeEntryBulkEdit.php' );
$pickers = (string) file_get_contents( $root . '/src/Admin/Pickers.php' );
$search = (string) file_get_contents( $root . '/src/Time/WorkItemPickerSearch.php' );
// Inspect the executable method, not its documentation: the docblock
// names WorkItems::search() solely to explain why it is not called.
$search_body = strstr( $search, 'public static function results(' ) ?: '';
$js = (string) file_get_contents( $root . '/assets/time-entry-bulk-edit.js' );
$integration = (string) file_get_contents( $root . '/tests/integration/WorkTimePickerScalabilityTest.php' );

$checks = [
    'Time Timer and manual correction both use Base async Work Item selection' =>
        substr_count( $time, 'Pickers::time_work_item(' ) === 2
        && ! str_contains( $time, 'WorkItems::all( 500 )' )
        && ! str_contains( $time, 'available_work_items(' )
        && str_contains( $pickers, 'ObjectPicker::render( [' ),
    'Time Bulk Edit uses the same picker and blank/no-change boundary' =>
        str_contains( $bulk, "Pickers::time_work_item( 'bulk_work_item_id'" )
        && ! str_contains( $bulk, 'WorkItems::all( 500 )' )
        && str_contains( $handler, "'' === \$target_value ? '0' : \$target_value;" ),
    'tracker-only Work landing loads Base picker module' =>
        str_contains( $pickers, 'Menu::TOP_LEVEL_SLUG' )
        && str_contains( $pickers, 'Assets::enqueue_object_picker();' ),
    'AJAX lookup is nonce-guarded and scoped to the actor' =>
        str_contains( $pickers, "wp_ajax_cb_work_search_time_work_items" )
        && str_contains( $pickers, "check_ajax_referer( self::NONCE_ACTION );" )
        && str_contains( $pickers, "if ( ! Access::can_track() )" )
        && str_contains( $pickers, "WorkItemPickerSearch::results( \$term, get_current_user_id(), Access::can_manage() )" ),
    'search is bounded at SQL before hydration and assignment-scoped for trackers' =>
        str_contains( $search, 'private const LIMIT = 20;' )
        && str_contains( $search, 'LIMIT %d' )
        && str_contains( $search, 'INNER JOIN' )
        && str_contains( $search, 'wa.user_id = %d' )
        && str_contains( $search, 'get_current_user_id() !== $actor_id' )
        && str_contains( $search, '$wpdb->esc_like( $term )' )
        && str_contains( $search, '$wpdb->prepare( $sql, ...$args )' )
        && '' !== $search_body && ! str_contains( $search_body, 'WorkItems::search(' ),
    'bulk async redraw reinitializes selected item picker' =>
        str_contains( $js, 'window.cbCore?.objectPicker?.init(list());' )
        && str_contains( $js, 'input[name="bulk_work_item_id"]' )
        && str_contains( $js, "target.value !== ''" ),
    'real >500 WordPress/MariaDB regression fixture exists' =>
        str_contains( $integration, '$i <= 520' )
        && str_contains( $integration, 'test_bounded_search_can_reach_520th_item_without_exposing_unassigned_titles' )
        && str_contains( $integration, 'Unassigned Work Item titles must never be returned' ),
];
foreach ( $checks as $name => $passed ) {
    if ( ! $passed ) {
        fwrite( STDERR, "Time Work Item picker smoke FAILED: {$name}\n" );
        exit( 1 );
    }
}
echo "Time Work Item picker smoke passed (Base picker, bounded search and actor authorization).\n";

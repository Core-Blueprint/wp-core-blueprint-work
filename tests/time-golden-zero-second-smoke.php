<?php
declare(strict_types=1);

// Read-only correction-policy contract. Real SQL acceptance lives in
// tests/integration/WorkTimeCasIntegrationTest.php (opt-in PHPUnit).
$root = dirname( __DIR__ );
$repository = (string) file_get_contents( $root . '/src/Repository/TimeEntries.php' );
$actions = (string) file_get_contents( $root . '/src/Admin/TimeActions.php' );
$quick = (string) file_get_contents( $root . '/assets/time-entry-quick-edit.js' );
$integration = (string) file_get_contents( $root . '/tests/integration/WorkTimeCasIntegrationTest.php' );

$create_start = strpos( $repository, 'public static function create_manual(' );
$update_start = strpos( $repository, 'public static function update_completed(' );
$totals_start = strpos( $repository, 'public static function total_seconds_for_work_item(' );

$create = false !== $create_start && false !== $update_start
    ? substr( $repository, $create_start, $update_start - $create_start ) : '';
$update = false !== $update_start && false !== $totals_start
    ? substr( $repository, $update_start, $totals_start - $update_start ) : '';

$checks = [
    'manual creation continues to require positive duration' =>
        str_contains( $create, '$duration <= 0' ),
    'zero-second correction guarded by original timer and exact instants' =>
        str_contains( $actions, '$preserving_zero_timer' )
        && str_contains( $actions, "TimeEntries::SOURCE_TIMER === (string) ( \$original_entry['entry_source'] ?? '' )" )
        && str_contains( $actions, "\$started_at === (string) ( \$original_entry['started_at'] ?? '' )" )
        && str_contains( $actions, "\$ended_at === (string) ( \$original_entry['ended_at'] ?? '' )" ),
    'repository accepts zero only with source, duration and original timestamps inside CAS WHERE' =>
        str_contains( $update, "0 === \$duration" )
        && str_contains( $update, 'AND entry_source = %s AND duration_seconds = 0 AND started_at = %s AND ended_at = %s' )
        && str_contains( $update, 'WHERE id = %d AND revision = %d AND ended_at IS NOT NULL' )
        && str_contains( $update, 'return 1 === $updated;' ),
    'Quick Edit preview displays zero rather than an invalid placeholder' =>
        str_contains( $quick, 'Number.isFinite(seconds) && seconds >= 0' ),
    'real SQL fixture tests zero timer and negative transitions' =>
        str_contains( $integration, 'test_zero_second_timer_can_be_corrected_without_creating_zero_manual_entries' )
        && str_contains( $integration, "'moved zero'" )
        && str_contains( $integration, "'converted to zero'" )
        && str_contains( $integration, 'A stale revision must still fail' ),
];
foreach ( $checks as $name => $ok ) {
    if ( ! $ok ) {
        fwrite( STDERR, "Time zero-second policy contract FAILED: {$name}\n" );
        exit( 1 );
    }
}
echo "Time zero-second policy contract passed.\n";

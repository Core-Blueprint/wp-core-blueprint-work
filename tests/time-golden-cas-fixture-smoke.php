<?php
declare(strict_types=1);

// Read-only gate for the optional live MariaDB CAS fixture. The live fixture
// is never invoked from tools/check or from the release build.
$root = dirname( __DIR__ );
$fixture = (string) file_get_contents( $root . '/tests/time-golden-cas-live.php' );
$entries = (string) file_get_contents( $root . '/src/Repository/TimeEntries.php' );
$bulk = (string) file_get_contents( $root . '/src/Admin/TimeEntryBulkEdit.php' );
$check = (string) file_get_contents( $root . '/tools/check' );
$build = (string) file_get_contents( $root . '/tools/build-release' );

$checks = [
    'explicit opt-in and test-only DB name' =>
        str_contains( $fixture, "'CB_WORK_TIME_CAS_TEST'" )
        && str_contains( $fixture, "defined( 'WP_CLI' )" )
        && str_contains( $fixture, "defined( 'DB_NAME' )" )
        && str_contains( $fixture, "defined( 'DB_HOST' )" )
        && str_contains( $fixture, 'local database host' )
        && str_contains( $fixture, 'testdb' ),
    'transactional storage is checked before fixture mutations' =>
        str_contains( $fixture, 'information_schema.TABLES' )
        && str_contains( $fixture, "'InnoDB'" )
        && strpos( $fixture, 'START TRANSACTION' ) > strpos( $fixture, 'information_schema.TABLES' ),
    'all fixture changes are rolled back in finally' =>
        str_contains( $fixture, '} finally {' )
        && str_contains( $fixture, "'ROLLBACK'" ),
    'repository CAS remains one conditional SQL write' =>
        str_contains( $entries, 'revision = revision + 1' )
        && str_contains( $entries, 'WHERE id = %d AND revision = %d AND ended_at IS NOT NULL' )
        && str_contains( $entries, 'return 1 === $updated;' ),
    'bulk preflight precedes guarded per-row writes and partial reporting' =>
        strpos( $bulk, 'foreach ( $ids as $id )' ) < strpos( $bulk, 'foreach ( $updates as $change )' )
        && str_contains( $bulk, 'TimeEntries::update_completed(' )
        && str_contains( $bulk, "'time-bulk-partial'" ),
    'fixture not automatically executed or released' =>
        ! str_contains( $check, 'php "$ROOT/tests/time-golden-cas-live.php"' )
        && ! str_contains( $build, 'time-golden-cas-live.php' ),
];
foreach ( $checks as $name => $passed ) {
    if ( ! $passed ) {
        fwrite( STDERR, "Time CAS fixture contract FAILED: {$name}\n" );
        exit( 1 );
    }
}
echo "Time CAS fixture contract passed (opt-in, DB safety, rollback and CAS).\n";

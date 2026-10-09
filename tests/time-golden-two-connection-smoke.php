<?php
declare(strict_types=1);

// WT-G-006 read-only source contract. Actual isolation and SQL assertions are
// covered by the opt-in WordPress PHPUnit / MariaDB integration fixture.
$root = dirname( __DIR__ );
$integration = (string) file_get_contents(
    $root . '/tests/integration/WorkTimeTwoConnectionCasIntegrationTest.php'
);
$repository = (string) file_get_contents( $root . '/src/Repository/TimeEntries.php' );
$bulk = (string) file_get_contents( $root . '/src/Admin/TimeEntryBulkEdit.php' );
$config = (string) file_get_contents( $root . '/tests/phpunit-time-cas.xml.dist' );

$checks = [
    'opt-in local-only DB gate before fixture setup' =>
        str_contains( $integration, "'core_blueprint_work_test' !== (string) DB_NAME" )
        && str_contains( $integration, "'127.0.0.1:3307' !== (string) DB_HOST" )
        && str_contains( $integration, 'parent::set_up();' ),
    'two distinct WordPress DB connections and server-side connection identifiers' =>
        substr_count( $integration, 'new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST )' ) === 2
        && substr_count( $integration, 'SELECT CONNECTION_ID()' ) === 2
        && str_contains( $integration, 'assertNotSame( $connection_a_id, $connection_b_id' ),
    'both sessions share the same explicit test schema and committed DDL' =>
        str_contains( $integration, 'mysqli_select_db( $a->dbh, DB_NAME )' )
        && str_contains( $integration, 'mysqli_select_db( $b->dbh, DB_NAME )' )
        && str_contains( $integration, 'mysqli_autocommit( $a->dbh, true )' )
        && str_contains( $integration, 'mysqli_autocommit( $b->dbh, true )' )
        && str_contains( $integration, "self::assertSame( DB_NAME, (string) \$schema_a->fetch_row()[0] )" )
        && str_contains( $integration, "self::assertSame( DB_NAME, (string) \$schema_b->fetch_row()[0] )" )
        && str_contains( $integration, 'mysqli_query( $client->dbh, \'SELECT COUNT(*) FROM \' . $table )' ),
    'shared isolated table has unique name and explicit cleanup' =>
        str_contains( $integration, "'cb_wtg006_time_' . bin2hex( random_bytes( 8 ) )" )
        && str_contains( $integration, 'CREATE TABLE ' )
        && str_contains( $integration, 'DROP TABLE ' )
        && str_contains( $integration, '$this->owns_shared_table' )
        && str_contains( $integration, 'DROP TEMPORARY TABLE IF EXISTS ' ),
    'production repository and integration SQL both enforce revision CAS' =>
        str_contains( $repository, 'WHERE id = %d AND revision = %d AND ended_at IS NOT NULL' )
        && str_contains( $repository, 'revision = revision + 1' )
        && str_contains( $integration, 'WHERE id = %d AND revision = %d AND ended_at IS NOT NULL' ),
    'two-session tests cover stale full, quick and partial bulk conflicts' =>
        str_contains( $integration, 'test_two_independent_sessions_reject_stale_writes_and_report_partial_bulk' )
        && str_contains( $integration, "'stale-quick-from-b'" )
        && str_contains( $integration, "'stale-full-from-a'" )
        && str_contains( $integration, 'self::assertSame( 1, $updated' )
        && str_contains( $integration, 'self::assertSame( 1, $failed' )
        && str_contains( $bulk, 'TimeEntries::update_completed(' )
        && str_contains( $bulk, "'time-bulk-partial'" ),
    'zero-second timer guard remains source- and session-verified' =>
        str_contains( $repository, 'entry_source = %s AND duration_seconds = 0 AND started_at = %s AND ended_at = %s' )
        && str_contains( $integration, 'test_zero_second_timer_keeps_atomic_guards_between_sessions' )
        && str_contains( $integration, "'disallowed move'" ),
    'user identity and assignment checks use actual Work access contract' =>
        str_contains( $integration, 'test_two_distinct_user_identities_obey_time_entry_permissions' )
        && str_contains( $integration, 'Access::can_track_work_item(' )
        && str_contains( $integration, 'Access::can_edit_entry(' )
        && str_contains( $integration, 'Tracker B cannot impersonate tracker A' ),
    'existing canonical PHPUnit directory discovers the new test class' =>
        str_contains( $config, '<directory suffix="Test.php">integration</directory>' ),
];

foreach ( $checks as $description => $passed ) {
    if ( ! $passed ) {
        fwrite( STDERR, "Time two-connection CAS smoke FAILED: {$description}\n" );
        exit( 1 );
    }
}

echo "Time two-connection CAS smoke passed (two DB sessions, stale writes, bulk, ACL).\n";

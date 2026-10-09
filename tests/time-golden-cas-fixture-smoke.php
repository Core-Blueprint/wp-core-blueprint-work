<?php
declare(strict_types=1);

// Read-only contract for the optional real-MariaDB WordPress PHPUnit test.
// No DB access or test-site boot is performed by this smoke test.
$root = dirname( __DIR__ );
$integration = (string) file_get_contents( $root . '/tests/integration/WorkTimeCasIntegrationTest.php' );
$bootstrap = (string) file_get_contents( $root . '/tests/phpunit-time-bootstrap.php' );
$xml = (string) file_get_contents( $root . '/tests/phpunit-time-cas.xml.dist' );
$entries = (string) file_get_contents( $root . '/src/Repository/TimeEntries.php' );
$bulk = (string) file_get_contents( $root . '/src/Admin/TimeEntryBulkEdit.php' );
$check = (string) file_get_contents( $root . '/tools/check' );

$checks = [
    'use the canonical product-isolated Work PHPUnit bootstrap' =>
        str_contains( $bootstrap, "'/phpunit-work-bootstrap.php'" )
        && str_contains( $xml, 'bootstrap="phpunit-work-bootstrap.php"' )
        && str_contains( $integration, 'extends WP_UnitTestCase' ),
    'guard local PHPUnit test database' =>
        str_contains( $integration, "defined( 'DB_NAME' )" )
        && str_contains( $integration, "defined( 'DB_HOST' )" )
        && str_contains( $integration, 'local WordPress PHPUnit test DB' ),
    'use a temporary real InnoDB table and drop after test' =>
        str_contains( $integration, 'CREATE TEMPORARY TABLE' )
        && str_contains( $integration, 'ENGINE=InnoDB' )
        && str_contains( $integration, 'DROP TEMPORARY TABLE IF EXISTS' ),
    'repository CAS keeps conditional revision protection' =>
        str_contains( $entries, 'revision = revision + 1' )
        && str_contains( $entries, 'WHERE id = %d AND revision = %d AND ended_at IS NOT NULL' )
        && str_contains( $integration, "'operator-b'" )
        && str_contains( $integration, "'newer-second'" ),
    'bulk preserves preflight followed by revisioned per-row writes' =>
        strpos( $bulk, 'foreach ( $ids as $id )' ) < strpos( $bulk, 'foreach ( $updates as $change )' )
        && str_contains( $bulk, 'TimeEntries::update_completed(' )
        && str_contains( $bulk, "'time-bulk-partial'" ),
    'integration remains outside Level 1 and is mandatory in Level 2' =>
        ! str_contains( $check, 'phpunit-time-cas.xml.dist' )
        && ! str_contains( $check, 'WorkTimeCasIntegrationTest.php' )
        && str_contains( (string) file_get_contents( $root . '/tools/check-integration' ), 'phpunit-time-cas.xml.dist' ),
];
foreach ( $checks as $name => $passed ) {
    if ( ! $passed ) {
        fwrite( STDERR, "Time CAS fixture contract FAILED: {$name}\n" );
        exit( 1 );
    }
}
echo "Time CAS fixture contract passed (existing WordPress PHPUnit, temporary InnoDB and CAS).\n";

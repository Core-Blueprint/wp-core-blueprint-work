<?php
declare(strict_types=1);

/** Contract regression for the Work-owned Handbook Level 2 provisioner. */
$root = dirname( __DIR__ );
$runner = (string) file_get_contents( $root . '/tools/check-integration' );
$check = (string) file_get_contents( $root . '/tools/check' );
$builder = (string) file_get_contents( $root . '/tools/build-release' );
$bootstrap = (string) file_get_contents( $root . '/tests/phpunit-work-bootstrap.php' );
$config = (string) file_get_contents( $root . '/tests/wp-tests-config.php' );
$xml = (string) file_get_contents( $root . '/tests/phpunit-time-cas.xml.dist' );
$time = (string) file_get_contents( $root . '/tests/integration/WorkTimeTwoConnectionCasIntegrationTest.php' );
$work = (string) file_get_contents( $root . '/tests/integration/WorkItemTwoConnectionCasIntegrationTest.php' );

$checks = [
	'Level 2 is executable with isolated Work root and database' =>
		str_starts_with( $runner, '#!/usr/bin/env bash' )
		&& is_executable( $root . '/tools/check-integration' )
		&& str_contains( $runner, '/tmp/core-blueprint-tests/core-blueprint-work' )
		&& str_contains( $runner, 'DB_NAME="core_blueprint_work_test"' )
		&& str_contains( $runner, 'cb-base-test-db' )
		&& str_contains( $runner, 'mariadb:10.11.19' ),
	'DB reset is restricted to canonical Work-only test database' =>
		str_contains( $runner, 'DROP DATABASE IF EXISTS' )
		&& str_contains( $runner, 'DB_NAME="core_blueprint_work_test"' )
		&& ! str_contains( $runner, 'DB_NAME="wordpress_test"' ),
	'wp-phpunit bootstrap stages Base and Work independently' =>
		str_contains( $bootstrap, "tests_add_filter( 'muplugins_loaded'" )
		&& str_contains( $bootstrap, 'CB_TEST_BASE_PLUGIN_FILE' )
		&& str_contains( $bootstrap, 'CB_TEST_WORK_PLUGIN_FILE' )
		&& str_contains( $bootstrap, 'wp-tests-config.php' )
		&& str_contains( $xml, 'bootstrap="phpunit-work-bootstrap.php"' ),
	'WordPress test configuration refuses foreign databases' =>
		str_contains( $config, "'core_blueprint_work_test' !== \$db_name" )
		&& str_contains( $config, "'127.0.0.1:3307' !== \$db_host" ),
	'Both two-connection fixtures have the isolated Work DB guard' =>
		str_contains( $time, "'core_blueprint_work_test' !== (string) DB_NAME" )
		&& str_contains( $work, "'core_blueprint_work_test' !== (string) DB_NAME" ),
	'Release must run both levels in order before customer staging' =>
		str_contains( $builder, '"$ROOT/tools/check"' )
		&& str_contains( $builder, 'bash "$ROOT/tools/check-integration"' )
		&& strpos( $builder, '"$ROOT/tools/check"' ) < strpos( $builder, 'bash "$ROOT/tools/check-integration"' )
		&& strpos( $builder, 'bash "$ROOT/tools/check-integration"' ) < strpos( $builder, 'TMP="$(mktemp -d)"' ),
	'Level 1 never recursively invokes the customer release builder' =>
		! str_contains( $check, '"$ROOT/tools/build-release"' )
		&& str_contains( $builder, 'SECOND_ZIP=' ),
];
foreach ( $checks as $name => $passed ) {
	if ( ! $passed ) {
		fwrite( STDERR, "Work integration conformance failed: {$name}\n" );
		exit( 1 );
	}
}
echo "Work Handbook Level 2 integration contract smoke passed.\n";

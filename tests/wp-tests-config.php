<?php
declare(strict_types=1);

/** Isolated Core Blueprint Work wp-phpunit configuration. */
$core_dir = rtrim( (string) getenv( 'WP_CORE_DIR' ), '/\\' );
if ( '' === $core_dir || ! is_file( $core_dir . '/wp-settings.php' ) ) {
	throw new RuntimeException( 'Work integration requires its provisioned WordPress runtime.' );
}

$db_name = (string) getenv( 'WP_DB_NAME' );
$db_host = (string) getenv( 'WP_DB_HOST' );
if ( 'core_blueprint_work_test' !== $db_name || '127.0.0.1:3307' !== $db_host ) {
	throw new RuntimeException( 'Work integration refuses a noncanonical database or host.' );
}

define( 'ABSPATH', $core_dir . '/' );
define( 'DB_NAME', $db_name );
define( 'DB_USER', (string) getenv( 'WP_DB_USER' ) );
define( 'DB_PASSWORD', (string) getenv( 'WP_DB_PASSWORD' ) );
define( 'DB_HOST', $db_host );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

define( 'AUTH_KEY', 'cb-work-test-auth' );
define( 'SECURE_AUTH_KEY', 'cb-work-test-secure-auth' );
define( 'LOGGED_IN_KEY', 'cb-work-test-login' );
define( 'NONCE_KEY', 'cb-work-test-nonce' );
define( 'AUTH_SALT', 'cb-work-test-auth-salt' );
define( 'SECURE_AUTH_SALT', 'cb-work-test-secure-salt' );
define( 'LOGGED_IN_SALT', 'cb-work-test-login-salt' );
define( 'NONCE_SALT', 'cb-work-test-nonce-salt' );

define( 'WP_TESTS_DOMAIN', 'work.test' );
define( 'WP_TESTS_EMAIL', 'noreply@work.test' );
define( 'WP_TESTS_TITLE', 'Core Blueprint Work Integration Tests' );
define( 'WP_PHP_BINARY', PHP_BINARY );
define( 'WPLANG', '' );
define( 'WP_DEBUG', true );

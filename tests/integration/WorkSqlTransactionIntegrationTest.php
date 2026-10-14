<?php
declare(strict_types=1);

use CB\Work\Repository\WorkSqlTransaction;

/** Tests real MariaDB savepoint ownership without disturbing the PHPUnit transaction. */
final class WorkSqlTransactionIntegrationTest extends WP_UnitTestCase {
	private string $table = '';

	public function set_up(): void {
		if ( ! defined( 'DB_NAME' ) || 'core_blueprint_work_test' !== (string) DB_NAME
			|| ! defined( 'DB_HOST' ) || '127.0.0.1:3307' !== (string) DB_HOST ) {
			self::fail( 'B3.2 integration requires the disposable Work test database.' );
		}
		parent::set_up();
		global $wpdb;
		self::assertSame( 1, (int) $wpdb->get_var( 'SELECT @@in_transaction' ) );
		$this->table = $wpdb->prefix . 'b32_transaction_fixture';
		$ok = $wpdb->query( 'CREATE TEMPORARY TABLE ' . $this->table . ' (
			id bigint unsigned NOT NULL PRIMARY KEY,
			label varchar(32) NOT NULL,
			CONSTRAINT cb_work_b32_fixture_valid CHECK (label <> \'reject\')
		) ENGINE=InnoDB' );
		self::assertNotFalse( $ok );
	}

	public function tear_down(): void {
		global $wpdb;
		if ( '' !== $this->table ) {
			$wpdb->query( 'DROP TEMPORARY TABLE IF EXISTS ' . $this->table );
		}
		parent::tear_down();
	}

	public function test_nested_success_preserves_outer_ownership_and_rollback(): void {
		global $wpdb;
		self::assertNotFalse( $wpdb->query( 'SAVEPOINT cb_work_b32_test_outer' ) );
		self::assertSame( 1, $wpdb->insert( $this->table, [ 'id' => 1, 'label' => 'outer' ], [ '%d', '%s' ] ) );
		$ok = WorkSqlTransaction::run( function (): int {
			global $wpdb;
			return 1 === $wpdb->insert( $this->table, [ 'id' => 2, 'label' => 'nested' ], [ '%d', '%s' ] ) ? 2 : 0;
		}, 0 );
		self::assertSame( 2, $ok );
		self::assertSame( 1, (int) $wpdb->get_var( 'SELECT @@in_transaction' ), 'Nested mutation must not commit the WordPress transaction.' );
		self::assertSame( 2, (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $this->table ) );
		self::assertNotFalse( $wpdb->query( 'ROLLBACK TO SAVEPOINT cb_work_b32_test_outer' ) );
		self::assertSame( 0, (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $this->table ) );
		self::assertNotFalse( $wpdb->query( 'RELEASE SAVEPOINT cb_work_b32_test_outer' ) );
		self::assertSame( 1, (int) $wpdb->get_var( 'SELECT @@in_transaction' ) );
	}

	public function test_failed_mutation_rolls_back_only_its_savepoint(): void {
		global $wpdb;
		self::assertSame( 1, $wpdb->insert( $this->table, [ 'id' => 1, 'label' => 'outer' ], [ '%d', '%s' ] ) );
		$wpdb->suppress_errors( true );
		try {
			$result = WorkSqlTransaction::run( function (): \WP_Error {
				global $wpdb;
				$wpdb->insert( $this->table, [ 'id' => 2, 'label' => 'partial' ], [ '%d', '%s' ] );
				$failed = $wpdb->insert( $this->table, [ 'id' => 3, 'label' => 'reject' ], [ '%d', '%s' ] );
				return false === $failed ? new \WP_Error( 'b32_expected_failure' ) : new \WP_Error( 'b32_unexpected_success' );
			}, new \WP_Error( 'b32_begin_failed' ) );
		} finally {
			$wpdb->suppress_errors( false );
		}
		self::assertInstanceOf( \WP_Error::class, $result );
		self::assertSame( 'b32_expected_failure', $result->get_error_code() );
		self::assertSame( 'outer', $wpdb->get_var( 'SELECT label FROM ' . $this->table . ' WHERE id = 1' ) );
		self::assertSame( 1, (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $this->table ) );
		self::assertSame( 1, (int) $wpdb->get_var( 'SELECT @@in_transaction' ) );
	}

	public function test_exception_restores_savepoint_and_rethrows(): void {
		global $wpdb;
		try {
			WorkSqlTransaction::run( function (): bool {
				global $wpdb;
				$wpdb->insert( $this->table, [ 'id' => 1, 'label' => 'partial' ], [ '%d', '%s' ] );
				throw new \RuntimeException( 'expected B3.2 fixture failure' );
			}, false );
			self::fail( 'Exception must propagate.' );
		} catch ( \RuntimeException $exception ) {
			self::assertSame( 'expected B3.2 fixture failure', $exception->getMessage() );
		}
		self::assertSame( 0, (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $this->table ) );
		self::assertSame( 1, (int) $wpdb->get_var( 'SELECT @@in_transaction' ) );
	}
}

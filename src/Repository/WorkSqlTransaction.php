<?php
declare(strict_types=1);

namespace CB\Work\Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Work-owned SQL mutations must never commit an existing caller transaction.
 * The caller owns the final COMMIT whenever we use a SAVEPOINT.
 * All tables in the mutation must be transactional (InnoDB).
 */
final class WorkSqlTransaction {
	private static int $savepoint_sequence = 0;

	/**
	 * The mutation returns a positive ID, true, or successful array; zero, false,
	 * null and WP_Error are failure results and must roll back. Unexpected errors
	 * are rolled back and rethrown rather than hidden.
	 *
	 * @param callable():mixed $mutation
	 */
	public static function run( callable $mutation, mixed $failure ): mixed {
		global $wpdb;
		$active = $wpdb->get_var( 'SELECT @@in_transaction' );
		if ( null === $active ) {
			return $failure;
		}
		$nested = (int) $active > 0;
		$savepoint = $nested ? 'cb_work_b32_' . ++self::$savepoint_sequence : '';
		$begin = $nested ? 'SAVEPOINT ' . $savepoint : 'START TRANSACTION';
		if ( false === $wpdb->query( $begin ) ) {
			return $failure;
		}

		try {
			$result = $mutation();
		} catch ( \Throwable $exception ) {
			self::rollback( $nested, $savepoint );
			throw $exception;
		}
		if ( null === $result || false === $result || 0 === $result || $result instanceof \WP_Error ) {
			self::rollback( $nested, $savepoint );
			return $result;
		}

		$finish = $nested ? 'RELEASE SAVEPOINT ' . $savepoint : 'COMMIT';
		if ( false === $wpdb->query( $finish ) ) {
			self::rollback( $nested, $savepoint );
			return $failure;
		}
		return $result;
	}

	private static function rollback( bool $nested, string $savepoint ): void {
		global $wpdb;
		if ( $nested ) {
			if ( false !== $wpdb->query( 'ROLLBACK TO SAVEPOINT ' . $savepoint ) ) {
				$wpdb->query( 'RELEASE SAVEPOINT ' . $savepoint );
			}
			return;
		}
		$wpdb->query( 'ROLLBACK' );
	}

	private function __construct() {}
}

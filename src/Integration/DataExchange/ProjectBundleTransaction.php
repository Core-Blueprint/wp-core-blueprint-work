<?php
declare(strict_types=1);

namespace CB\Work\Integration\DataExchange;

use CB\Work\Database\Schema;
use CoreBlueprint\Core\DB;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Database-state atomicity for one Project bundle.
 *
 * This protects the local InnoDB writes, not external WordPress callbacks or
 * remote side effects. Audit/lifecycle delivery must pass the separate B3.1
 * side-effect acceptance gate before the feature may be released.
 */
final class ProjectBundleTransaction {
	private static int $savepoint_sequence = 0;

	/**
	 * @param callable(callable(int):void):(array<string,mixed>|WP_Error) $mutation
	 * @return array<string,mixed>|WP_Error
	 */
	public static function run( callable $mutation ): array|WP_Error {
		if ( ! self::storage_ready() ) {
			return new WP_Error( 'work_project_bundle_transaction_storage_unavailable' );
		}

		global $wpdb;
		$was_active = $wpdb->get_var( 'SELECT @@in_transaction' );
		if ( null === $was_active ) {
			return new WP_Error( 'work_project_bundle_transaction_unavailable' );
		}

		$nested = (int) $was_active > 0;
		$savepoint = $nested ? 'cb_work_bundle_' . ++self::$savepoint_sequence : '';
		$begin = $nested ? 'SAVEPOINT ' . $savepoint : 'START TRANSACTION';
		if ( false === $wpdb->query( $begin ) ) {
			return new WP_Error( 'work_project_bundle_transaction_unavailable' );
		}

		$touched = [];
		$touch = static function ( int $post_id ) use ( &$touched ): void {
			if ( $post_id > 0 ) {
				$touched[ $post_id ] = true;
			}
		};

		try {
			$result = $mutation( $touch );
		} catch ( \Throwable $exception ) {
			unset( $exception );
			self::rollback( $nested, $savepoint );
			self::invalidate( $touched );
			return new WP_Error( 'work_project_bundle_apply_failed' );
		}
		if ( is_wp_error( $result ) || ! is_array( $result ) ) {
			self::rollback( $nested, $savepoint );
			self::invalidate( $touched );
			return is_wp_error( $result ) ? $result : new WP_Error( 'work_project_bundle_apply_contract' );
		}

		$finish = $nested ? 'RELEASE SAVEPOINT ' . $savepoint : 'COMMIT';
		if ( false === $wpdb->query( $finish ) ) {
			self::rollback( $nested, $savepoint );
			self::invalidate( $touched );
			return new WP_Error( 'work_project_bundle_transaction_commit_failed' );
		}
		self::invalidate( $touched );
		return $result;
	}

	/** Reject nontransactional/missing tables instead of promising a false rollback. */
	private static function storage_ready(): bool {
		global $wpdb;
		if ( ! class_exists( DB::class ) ) {
			return false;
		}
		$tables = [
			$wpdb->posts,
			$wpdb->postmeta,
			Schema::portable_identities_table(),
			Schema::assignments_table(),
			Schema::relations_table(),
			Schema::recurrence_rules_table(),
			DB::audit_log_table(),
		];
		foreach ( $tables as $table ) {
			// SHOW CREATE TABLE also sees connection-local TEMPORARY InnoDB fixtures.
			$ddl = $wpdb->get_row( $wpdb->prepare( 'SHOW CREATE TABLE %i', $table ), ARRAY_N );
			if ( ! is_array( $ddl ) || ! isset( $ddl[1] )
				|| 1 !== preg_match( '/\bENGINE\s*=\s*InnoDB\b/i', (string) $ddl[1] ) ) {
				return false;
			}
		}
		return true;
	}

	private static function rollback( bool $nested, string $savepoint ): void {
		global $wpdb;
		if ( $nested ) {
			$wpdb->query( 'ROLLBACK TO SAVEPOINT ' . $savepoint );
			$wpdb->query( 'RELEASE SAVEPOINT ' . $savepoint );
			return;
		}
		$wpdb->query( 'ROLLBACK' );
	}

	/** WordPress post/meta object caches are not transactional. */
	private static function invalidate( array $touched ): void {
		foreach ( array_keys( $touched ) as $post_id ) {
			clean_post_cache( (int) $post_id );
			wp_cache_delete( (int) $post_id, 'post_meta' );
		}
	}

	private function __construct() {}
}

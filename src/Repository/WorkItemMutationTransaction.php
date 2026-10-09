<?php
declare(strict_types=1);

namespace CB\Work\Repository;

use CB\Work\Content\PostTypes;

defined( 'ABSPATH' ) || exit;

/**
 * Serializes a Work Item's WordPress post/meta and Work-owned child-row writes.
 *
 * Standard WordPress and Work tables must be transactional (InnoDB). When a
 * caller already owns a transaction, a SAVEPOINT preserves its ownership;
 * a nested START TRANSACTION would otherwise silently COMMIT the caller.
 * In the savepoint case the caller still owns the final transaction commit.
 */
final class WorkItemMutationTransaction {
	private static int $savepoint_sequence = 0;

	/** @param callable():bool $mutation Work-owned database writes only. */
	public static function run( int $work_item_id, callable $mutation ): bool {
		if ( $work_item_id <= 0 ) {
			return false;
		}

		global $wpdb;
		$in_transaction = $wpdb->get_var( 'SELECT @@in_transaction' );
		if ( null === $in_transaction ) {
			return false;
		}

		$nested = (int) $in_transaction > 0;
		$savepoint = $nested ? 'cb_work_a1_' . ++self::$savepoint_sequence : '';
		$begin = $nested ? 'SAVEPOINT ' . $savepoint : 'START TRANSACTION';
		if ( false === $wpdb->query( $begin ) ) {
			return false;
		}

		$locked = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE ID = %d AND post_type = %s AND post_status NOT IN ('trash', 'auto-draft') FOR UPDATE",
				$work_item_id,
				PostTypes::WORK_ITEM
			),
			ARRAY_A
		);
		if ( ! is_array( $locked ) || (int) $locked['ID'] !== $work_item_id ) {
			self::rollback( $nested, $savepoint );
			self::invalidate( $work_item_id );
			return false;
		}

		self::invalidate( $work_item_id );
		try {
			$successful = true === $mutation();
		} catch ( \Throwable $exception ) {
			self::rollback( $nested, $savepoint );
			self::invalidate( $work_item_id );
			throw $exception;
		}

		if ( ! $successful ) {
			self::rollback( $nested, $savepoint );
			self::invalidate( $work_item_id );
			return false;
		}

		$finish = $nested ? 'RELEASE SAVEPOINT ' . $savepoint : 'COMMIT';
		if ( false === $wpdb->query( $finish ) ) {
			self::rollback( $nested, $savepoint );
			self::invalidate( $work_item_id );
			return false;
		}

		self::invalidate( $work_item_id );
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

	private static function invalidate( int $work_item_id ): void {
		clean_post_cache( $work_item_id );
		wp_cache_delete( $work_item_id, 'post_meta' );
	}

	private function __construct() {}
}

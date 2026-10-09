<?php
declare(strict_types=1);

namespace CB\Work\Repository;

use CB\Work\Content\PostTypes;

defined( 'ABSPATH' ) || exit;

/**
 * Serializes one Work Item mutation through the canonical WordPress posts row.
 *
 * The Work-owned assignments table and standard WordPress posts/postmeta tables
 * must use a transactional storage engine. Do not nest START TRANSACTION calls:
 * MySQL implicitly commits an active transaction when a new one is started.
 * No side-effecting Work lifecycle hooks are fired until after run() commits.
 */
final class WorkItemMutationTransaction {
	/**
	 * @param callable():bool $mutation Work-owned DB writes; no external side effects.
	 */
	public static function run( int $work_item_id, callable $mutation ): bool {
		if ( $work_item_id <= 0 ) {
			return false;
		}

		global $wpdb;
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
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
			$wpdb->query( 'ROLLBACK' );
			self::invalidate( $work_item_id );
			return false;
		}

		// Refresh the post and meta snapshots *after* acquiring the row lock.
		self::invalidate( $work_item_id );

		try {
			$successful = true === $mutation();
		} catch ( \Throwable $exception ) {
			$wpdb->query( 'ROLLBACK' );
			self::invalidate( $work_item_id );
			throw $exception;
		}

		if ( ! $successful ) {
			$wpdb->query( 'ROLLBACK' );
			self::invalidate( $work_item_id );
			return false;
		}

		if ( false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' );
			self::invalidate( $work_item_id );
			return false;
		}

		self::invalidate( $work_item_id );
		return true;
	}

	private static function invalidate( int $work_item_id ): void {
		clean_post_cache( $work_item_id );
		wp_cache_delete( $work_item_id, 'post_meta' );
	}

	private function __construct() {}
}

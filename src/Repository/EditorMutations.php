<?php
declare(strict_types=1);

namespace CB\Work\Repository;

defined( 'ABSPATH' ) || exit;

/**
 * M2 editor-only compare-and-swap adapter.
 *
 * WorkItems::update() is the immutable B2 public facade: never modify its
 * signature. The editor first acquires the existing A1 row lock, checks its
 * semantic revision, then delegates the write to that canonical facade.
 * The nested A1 savepoint remains owned by the transaction foundation.
 */
final class EditorMutations {
	/** @param array<string,mixed> $input */
	public static function update_work_item( int $id, array $input, string $expected_revision ): bool {
		if ( $id <= 0 || '' === $expected_revision || 64 !== strlen( $expected_revision ) ) {
			return false;
		}

		return WorkItemMutationTransaction::run(
			$id,
			static function () use ( $id, $input, $expected_revision ): bool {
				$current = WorkItems::get( $id );
				if ( null === $current || ! hash_equals(
					EditorRevisions::for_work_item( $current ),
					$expected_revision
				) ) {
					return false;
				}

				// The outer transaction owns the post row lock. WorkItems
				// keeps its B2 signature and its existing A1 mutation path.
				return WorkItems::update( $id, $input );
			}
		);
	}

	/**
	 * Editor-only Project CAS. The canonical Projects repository remains
	 * WordPress-native and retains its original public update signature.
	 * SQL row locking and savepoints are isolated to this optional adapter.
	 *
	 * @param array<string,mixed> $input
	 */
	public static function update_project( int $id, array $input, string $expected_revision ): bool {
		if ( $id <= 0 || 64 !== strlen( $expected_revision ) ) {
			return false;
		}

		$updated = WorkSqlTransaction::run(
			static function () use ( $id, $input, $expected_revision ): bool {
				global $wpdb;
				$locked = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT ID FROM {$wpdb->posts} WHERE ID = %d AND post_type = %s AND post_status NOT IN ('trash','auto-draft') FOR UPDATE",
						$id,
						\CB\Work\Content\PostTypes::PROJECT
					),
					ARRAY_A
				);
				if ( ! is_array( $locked ) || (int) $locked['ID'] !== $id ) {
					return false;
				}

				clean_post_cache( $id );
				wp_cache_delete( $id, 'post_meta' );
				$current = Projects::get( $id );
				if ( null === $current || ! hash_equals(
					EditorRevisions::for_project( $current ),
					$expected_revision
				) ) {
					return false;
				}

				// Project updates continue to use their canonical WP CPT
				// persistence path under the lock; no second write model.
				return Projects::update( $id, $input );
			},
			false
		);

		clean_post_cache( $id );
		wp_cache_delete( $id, 'post_meta' );
		return true === $updated;
	}

	private function __construct() {}
}

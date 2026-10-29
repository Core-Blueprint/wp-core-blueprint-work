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

	private function __construct() {}
}

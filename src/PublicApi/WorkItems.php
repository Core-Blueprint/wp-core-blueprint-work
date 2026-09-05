<?php
declare(strict_types=1);

namespace CB\Work\PublicApi;

use CB\Work\Repository\WorkItems as WorkItemRepository;

defined( 'ABSPATH' ) || exit;

/** Supported read-only Work Item contract for sibling integrations. */
final class WorkItems {
	/** @return array<string,mixed>|null */
	public static function get( int $work_item_id ): ?array {
		return WorkItemRepository::get( $work_item_id );
	}

	/** @return array<int,array<string,mixed>> */
	public static function all( int $limit = 100, array $statuses = [] ): array {
		return WorkItemRepository::all( $limit, $statuses );
	}
}

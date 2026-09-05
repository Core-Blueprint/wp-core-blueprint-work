<?php
declare(strict_types=1);

namespace CB\Work\PublicApi;

use CB\Work\Repository\WorkTypes as WorkTypeRepository;

defined( 'ABSPATH' ) || exit;

/** Supported read-only Work Type contract for sibling integrations. */
final class WorkTypes {
	/** @return array<string,mixed>|null */
	public static function get( int $work_type_id ): ?array {
		return WorkTypeRepository::get( $work_type_id );
	}

	/** @return array<int,array<string,mixed>> */
	public static function all( bool $include_inactive = false ): array {
		return WorkTypeRepository::all( $include_inactive );
	}
}

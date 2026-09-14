<?php
declare(strict_types=1);

namespace CB\Work\PublicApi;

use CB\Work\Repository\WorkTypes as WorkTypeRepository;

defined( 'ABSPATH' ) || exit;

/** Supported read-only Work Type contract for sibling integrations. */
final class WorkTypes {
	/** @return array<string,mixed>|null */
	public static function get( int $work_type_id ): ?array {
		return self::runtime_ready() ? WorkTypeRepository::get( $work_type_id ) : null;
	}

	/** @return array<int,array<string,mixed>> */
	public static function all( bool $include_inactive = false ): array {
		return self::runtime_ready() ? WorkTypeRepository::all( $include_inactive ) : [];
	}

	private static function runtime_ready(): bool {
		return function_exists( 'cb_work_runtime_ready' ) && \cb_work_runtime_ready();
	}
}

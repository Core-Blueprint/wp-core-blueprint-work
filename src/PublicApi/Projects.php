<?php
declare(strict_types=1);

namespace CB\Work\PublicApi;

use CB\Work\Repository\Projects as ProjectRepository;

defined( 'ABSPATH' ) || exit;

/** Supported read-only Project contract for sibling integrations. */
final class Projects {
	/** @return array<string,mixed>|null */
	public static function get( int $project_id ): ?array {
		return ProjectRepository::get( $project_id );
	}

	/** @return array<int,array<string,mixed>> */
	public static function all( int $limit = 250 ): array {
		return ProjectRepository::all( $limit );
	}
}

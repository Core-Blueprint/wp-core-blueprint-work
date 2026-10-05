<?php
declare(strict_types=1);

namespace CB\Work\Domain;

defined( 'ABSPATH' ) || exit;

final class WorkItemStatus {
	public const PLANNED     = 'planned';
	public const IN_PROGRESS = 'in_progress';
	public const BLOCKED     = 'blocked';
	public const COMPLETED   = 'completed';
	public const SKIPPED     = 'skipped';
	public const CANCELLED   = 'cancelled';

	/** @return string[] */
	public static function all(): array {
		return [ self::PLANNED, self::IN_PROGRESS, self::BLOCKED, self::COMPLETED, self::SKIPPED, self::CANCELLED ];
	}

	/** @return string[] */
	public static function active(): array {
		return [ self::PLANNED, self::IN_PROGRESS, self::BLOCKED ];
	}

	public static function is_valid( string $status ): bool {
		return in_array( $status, self::all(), true );
	}

	public static function is_terminal( string $status ): bool {
		return in_array( $status, [ self::COMPLETED, self::SKIPPED, self::CANCELLED ], true );
	}

	/** @return string[] */
	public static function transitions_from( string $status ): array {
		return match ( $status ) {
			self::PLANNED => [ self::IN_PROGRESS, self::BLOCKED, self::COMPLETED, self::SKIPPED, self::CANCELLED ],
			self::IN_PROGRESS => [ self::PLANNED, self::BLOCKED, self::COMPLETED, self::SKIPPED, self::CANCELLED ],
			self::BLOCKED => [ self::PLANNED, self::IN_PROGRESS, self::COMPLETED, self::SKIPPED, self::CANCELLED ],
			self::COMPLETED => [ self::IN_PROGRESS ],
			default => [],
		};
	}

	public static function can_transition( string $from, string $to ): bool {
		return $from !== $to && in_array( $to, self::transitions_from( $from ), true );
	}
}

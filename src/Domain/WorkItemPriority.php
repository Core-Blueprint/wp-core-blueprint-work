<?php
declare(strict_types=1);

namespace CB\Work\Domain;

defined( 'ABSPATH' ) || exit;

final class WorkItemPriority {
	public const LOW    = 'low';
	public const NORMAL = 'normal';
	public const HIGH   = 'high';
	public const URGENT = 'urgent';

	/** @return string[] */
	public static function all(): array {
		return [ self::LOW, self::NORMAL, self::HIGH, self::URGENT ];
	}

	public static function is_valid( string $priority ): bool {
		return in_array( $priority, self::all(), true );
	}
}

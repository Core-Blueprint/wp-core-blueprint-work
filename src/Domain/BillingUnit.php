<?php
declare(strict_types=1);

namespace CB\Work\Domain;

defined( 'ABSPATH' ) || exit;

final class BillingUnit {
	public const WORK_ITEM = 'work_item';
	public const TIME_ENTRY = 'time_entry';

	public const READY = 'ready';
	public const EXTERNALLY_LINKED = 'externally_linked';

	/** @return string[] */
	public static function types(): array {
		return [ self::WORK_ITEM, self::TIME_ENTRY ];
	}

	public static function is_type( string $type ): bool {
		return in_array( sanitize_key( $type ), self::types(), true );
	}

	/** @return string[] */
	public static function statuses(): array {
		return [ self::READY, self::EXTERNALLY_LINKED ];
	}
}

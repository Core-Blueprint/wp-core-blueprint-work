<?php
declare(strict_types=1);

namespace CB\Work\Domain;

defined( 'ABSPATH' ) || exit;

final class BillingDisposition {
	public const HOURLY       = 'hourly';
	public const FIXED        = 'fixed';
	public const INCLUDED     = 'included';
	public const NON_BILLABLE = 'non_billable';

	/** @return string[] */
	public static function all(): array {
		return [ self::HOURLY, self::FIXED, self::INCLUDED, self::NON_BILLABLE ];
	}

	public static function is_valid( string $disposition ): bool {
		return in_array( $disposition, self::all(), true );
	}
}

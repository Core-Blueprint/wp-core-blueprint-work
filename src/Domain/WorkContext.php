<?php
declare(strict_types=1);

namespace CB\Work\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * Canonical ownership context for operational Work.
 *
 * Context answers for whom the work is performed. It is intentionally
 * independent from Work Type, Service and billing classification.
 */
final class WorkContext {
	public const INTERNAL = 'internal';
	public const CUSTOMER = 'customer';

	/** @return string[] */
	public static function all(): array {
		return [ self::INTERNAL, self::CUSTOMER ];
	}

	public static function sanitize( mixed $value ): string {
		$value = sanitize_key( is_scalar( $value ) ? (string) $value : '' );
		return self::is_valid( $value ) ? $value : '';
	}

	public static function is_valid( string $value ): bool {
		return in_array( $value, self::all(), true );
	}

	public static function requires_customer( string $value ): bool {
		return self::CUSTOMER === $value;
	}
}

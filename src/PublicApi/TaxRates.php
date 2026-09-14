<?php
declare(strict_types=1);

namespace CB\Work\PublicApi;

defined( 'ABSPATH' ) || exit;

/** Supported read-only tax catalog contract for sibling integrations. */
final class TaxRates {
	/** @return array<string,mixed>|null */
	public static function get( int $id ): ?array {
		return self::runtime_ready() ? \CB\Work\Repository\TaxRates::get( $id ) : null;
	}

	/** @return array<string,mixed>|null */
	public static function get_by_code( string $code ): ?array {
		return self::runtime_ready() ? \CB\Work\Repository\TaxRates::get_by_code( $code ) : null;
	}

	/** @return array<int,array<string,mixed>> */
	public static function all( bool $include_inactive = true ): array {
		return self::runtime_ready() ? \CB\Work\Repository\TaxRates::all( $include_inactive ) : [];
	}

	/** @return array<int,array<string,mixed>> */
	public static function available( ?string $on_date = null ): array {
		return self::runtime_ready() ? \CB\Work\Repository\TaxRates::available( $on_date ) : [];
	}

	public static function is_available( int $id, ?string $on_date = null ): bool {
		if ( ! self::runtime_ready() ) {
			return false;
		}
		$rate = self::get( $id );
		return null !== $rate && \CB\Work\Repository\TaxRates::is_available( $rate, $on_date );
	}

	private static function runtime_ready(): bool {
		return function_exists( 'cb_work_runtime_ready' ) && \cb_work_runtime_ready();
	}
}

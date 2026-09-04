<?php
declare(strict_types=1);

namespace CB\Work\PublicApi;

defined( 'ABSPATH' ) || exit;

/** Supported read-only tax catalog contract for sibling integrations. */
final class TaxRates {
	/** @return array<string,mixed>|null */
	public static function get( int $id ): ?array {
		return \CB\Work\Repository\TaxRates::get( $id );
	}

	/** @return array<int,array<string,mixed>> */
	public static function all( bool $include_inactive = true ): array {
		return \CB\Work\Repository\TaxRates::all( $include_inactive );
	}

	/** @return array<int,array<string,mixed>> */
	public static function available( ?string $on_date = null ): array {
		return \CB\Work\Repository\TaxRates::available( $on_date );
	}
}

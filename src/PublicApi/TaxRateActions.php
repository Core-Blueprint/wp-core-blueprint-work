<?php
declare(strict_types=1);

namespace CB\Work\PublicApi;

use CB\Core\Governance\Audit;
use CB\Work\Capabilities;
use CB\Work\Governance\Events;
use CB\Work\Repository\TaxRates as TaxRateRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Supported governed mutation contract for Work VAT rates.
 *
 * This is a server-side PHP contract, not an HTTP endpoint. Transport layers
 * remain responsible for nonce/CSRF protection. All mutations require the
 * current actor to hold the canonical Work management capability.
 */
final class TaxRateActions {
	/** @param array<string,mixed> $input @return array<string,mixed>|\WP_Error */
	public static function create( array $input ): array|\WP_Error {
		$forbidden = self::authorize_manage();
		if ( null !== $forbidden ) {
			return $forbidden;
		}

		$normalized = self::normalize_input( $input );
		if ( is_wp_error( $normalized ) ) {
			return $normalized;
		}

		$id = TaxRateRepository::create( [
			'code'         => $normalized['code'],
			'label'        => $normalized['label'],
			'country_code' => $normalized['country_code'],
			'rate'         => TaxRateRepository::format_rate_bp( $normalized['rate_bp'] ),
			'is_active'    => $normalized['is_active'],
			'valid_from'   => $normalized['valid_from'] ?? '',
			'valid_until'  => $normalized['valid_until'] ?? '',
		] );
		if ( $id <= 0 ) {
			return new \WP_Error( 'work_tax_rate_create_failed' );
		}

		$rate = TaxRates::get( $id );
		if ( null === $rate ) {
			return new \WP_Error( 'work_tax_rate_unavailable' );
		}

		Audit::record( Events::TAX_RATE_CREATED, 'notice', [
			'tax_rate_id'   => $id,
			'actor_user_id' => get_current_user_id(),
			'channel'       => 'public_api',
		] );

		return $rate;
	}

	/** @param array<string,mixed> $input @return array<string,mixed>|\WP_Error */
	public static function update( int $id, array $input ): array|\WP_Error {
		$forbidden = self::authorize_manage();
		if ( null !== $forbidden ) {
			return $forbidden;
		}

		$current = TaxRates::get( $id );
		if ( null === $current ) {
			return new \WP_Error( 'work_tax_rate_unavailable' );
		}

		if ( array_key_exists( 'code', $input ) && ( ! is_string( $input['code'] ) || $input['code'] !== (string) $current['code'] ) ) {
			return new \WP_Error( 'work_tax_rate_code_immutable' );
		}

		$normalized = self::normalize_input( [
			'code'         => (string) $current['code'],
			'label'        => $input['label'] ?? $current['label'],
			'country_code' => $input['country_code'] ?? $current['country_code'],
			'rate_bp'      => $input['rate_bp'] ?? (int) $current['rate_bp'],
			'is_active'    => $input['is_active'] ?? ( 1 === (int) $current['is_active'] ),
			'valid_from'   => array_key_exists( 'valid_from', $input ) ? $input['valid_from'] : ( (string) ( $current['valid_from'] ?? '' ) ),
			'valid_until'  => array_key_exists( 'valid_until', $input ) ? $input['valid_until'] : ( (string) ( $current['valid_until'] ?? '' ) ),
		] );
		if ( is_wp_error( $normalized ) ) {
			return $normalized;
		}

		$before_active = 1 === (int) $current['is_active'];
		$before_values = [
			'label'        => (string) $current['label'],
			'country_code' => (string) $current['country_code'],
			'rate_bp'      => (int) $current['rate_bp'],
			'valid_from'   => self::nullable_date( $current['valid_from'] ?? null ),
			'valid_until'  => self::nullable_date( $current['valid_until'] ?? null ),
		];
		$next_values = [
			'label'        => $normalized['label'],
			'country_code' => $normalized['country_code'],
			'rate_bp'      => $normalized['rate_bp'],
			'valid_from'   => $normalized['valid_from'],
			'valid_until'  => $normalized['valid_until'],
		];

		if (
			( $before_values !== $next_values || $before_active !== $normalized['is_active'] )
			&& ! TaxRateRepository::update( $id, [
				...$next_values,
				'is_active' => $normalized['is_active'],
			] )
		) {
			return new \WP_Error( 'work_tax_rate_update_failed' );
		}

		$rate = TaxRates::get( $id );
		if ( null === $rate ) {
			return new \WP_Error( 'work_tax_rate_unavailable' );
		}

		if ( $before_values !== $next_values ) {
			Audit::record( Events::TAX_RATE_UPDATED, 'notice', [
				'tax_rate_id'   => $id,
				'actor_user_id' => get_current_user_id(),
				'channel'       => 'public_api',
			] );
		}
		if ( $before_active !== $normalized['is_active'] ) {
			Audit::record(
				$normalized['is_active'] ? Events::TAX_RATE_ACTIVATED : Events::TAX_RATE_DEACTIVATED,
				'notice',
				[
					'tax_rate_id'   => $id,
					'actor_user_id' => get_current_user_id(),
					'channel'       => 'public_api',
				]
			);
		}

		return $rate;
	}

	/** @return array<string,mixed>|\WP_Error */
	public static function set_active( int $id, bool $active ): array|\WP_Error {
		$forbidden = self::authorize_manage();
		if ( null !== $forbidden ) {
			return $forbidden;
		}

		$current = TaxRates::get( $id );
		if ( null === $current ) {
			return new \WP_Error( 'work_tax_rate_unavailable' );
		}
		if ( ( 1 === (int) $current['is_active'] ) === $active ) {
			return $current;
		}
		if ( ! TaxRateRepository::set_active( $id, $active ) ) {
			return new \WP_Error( 'work_tax_rate_status_failed' );
		}

		$rate = TaxRates::get( $id );
		if ( null === $rate ) {
			return new \WP_Error( 'work_tax_rate_unavailable' );
		}
		Audit::record(
			$active ? Events::TAX_RATE_ACTIVATED : Events::TAX_RATE_DEACTIVATED,
			'notice',
			[
				'tax_rate_id'   => $id,
				'actor_user_id' => get_current_user_id(),
				'channel'       => 'public_api',
			]
		);
		return $rate;
	}

	/** @param array<string,mixed> $input @return array{code:string,label:string,country_code:string,rate_bp:int,is_active:bool,valid_from:?string,valid_until:?string}|\WP_Error */
	private static function normalize_input( array $input ): array|\WP_Error {
		$label = isset( $input['label'] ) ? sanitize_text_field( (string) $input['label'] ) : '';
		if ( '' === $label ) {
			return new \WP_Error( 'work_tax_rate_invalid_label' );
		}

		$raw_code = trim( (string) ( $input['code'] ?? '' ) );
		$code     = TaxRateRepository::normalize_code( '' !== $raw_code ? $raw_code : $label );
		if ( '' === $code ) {
			return new \WP_Error( 'work_tax_rate_invalid_code' );
		}

		$country = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) ( $input['country_code'] ?? '' ) ) ?? '' );
		$country = 2 === strlen( $country ) ? $country : '';

		if ( array_key_exists( 'rate_bp', $input ) ) {
			if ( ! is_int( $input['rate_bp'] ) || $input['rate_bp'] < 0 || $input['rate_bp'] > 10000 ) {
				return new \WP_Error( 'work_tax_rate_invalid_rate' );
			}
			$rate_bp = $input['rate_bp'];
		} else {
			$rate_bp = TaxRateRepository::parse_rate_bp( (string) ( $input['rate'] ?? '' ) );
			if ( null === $rate_bp ) {
				return new \WP_Error( 'work_tax_rate_invalid_rate' );
			}
		}

		if ( array_key_exists( 'is_active', $input ) && ! is_bool( $input['is_active'] ) ) {
			return new \WP_Error( 'work_tax_rate_invalid_status' );
		}
		$is_active = array_key_exists( 'is_active', $input ) ? $input['is_active'] : true;

		$valid_from  = self::validated_date( $input['valid_from'] ?? null );
		$valid_until = self::validated_date( $input['valid_until'] ?? null );
		if ( is_wp_error( $valid_from ) || is_wp_error( $valid_until ) ) {
			return new \WP_Error( 'work_tax_rate_invalid_date' );
		}
		if ( null !== $valid_from && null !== $valid_until && $valid_until < $valid_from ) {
			return new \WP_Error( 'work_tax_rate_invalid_date_range' );
		}

		return [
			'code'         => $code,
			'label'        => $label,
			'country_code' => $country,
			'rate_bp'      => $rate_bp,
			'is_active'    => $is_active,
			'valid_from'   => $valid_from,
			'valid_until'  => $valid_until,
		];
	}

	private static function authorize_manage(): ?\WP_Error {
		return current_user_can( Capabilities::MANAGE ) ? null : new \WP_Error( 'work_action_forbidden' );
	}

	private static function validated_date( mixed $value ): string|\WP_Error|null {
		if ( null === $value || '' === trim( (string) $value ) ) {
			return null;
		}
		if ( ! is_string( $value ) ) {
			return new \WP_Error( 'work_tax_rate_invalid_date' );
		}
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $value );
		return $date && $date->format( 'Y-m-d' ) === $value
			? $value
			: new \WP_Error( 'work_tax_rate_invalid_date' );
	}

	private static function nullable_date( mixed $value ): ?string {
		$value = (string) ( $value ?? '' );
		return '' === $value ? null : $value;
	}
}

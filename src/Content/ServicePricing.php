<?php
declare(strict_types=1);

namespace CB\Work\Content;

use CB\Work\Capabilities;
use CB\Work\Repository\TaxRates;

defined( 'ABSPATH' ) || exit;

final class ServicePricing {
	public const META_AMOUNT_MINOR     = '_cb_work_service_price_minor';
	public const META_CURRENCY         = '_cb_work_service_currency';
	public const META_PRICING_MODEL    = '_cb_work_service_pricing_model';
	public const META_RECURRING_PERIOD = '_cb_work_service_recurring_period';
	public const META_TAX_MODE         = '_cb_work_service_tax_mode';
	public const META_TAX_RATE_ID      = '_cb_work_service_tax_rate_id';

	public const MODEL_HOURLY    = 'hourly';
	public const MODEL_FIXED     = 'fixed';
	public const MODEL_RECURRING = 'recurring';

	public const PERIOD_WEEKLY    = 'weekly';
	public const PERIOD_MONTHLY   = 'monthly';
	public const PERIOD_QUARTERLY = 'quarterly';
	public const PERIOD_YEARLY    = 'yearly';

	public const TAX_EXCLUSIVE = 'exclusive';
	public const TAX_INCLUSIVE = 'inclusive';
	public const TAX_EXEMPT    = 'exempt';
	public const DEFAULT_CURRENCY = 'EUR';

	public static function init(): void {
		add_action( 'init', [ self::class, 'register_meta' ], 8 );
	}

	public static function register_meta(): void {
		$args = static fn( string $type, callable|string $sanitize ): array => [
			'type'              => $type,
			'single'            => true,
			'show_in_rest'      => false,
			'sanitize_callback' => $sanitize,
			'auth_callback'     => static fn(): bool => current_user_can( Capabilities::MANAGE ),
		];

		register_post_meta( PostTypes::SERVICE, self::META_AMOUNT_MINOR, $args( 'integer', 'absint' ) );
		register_post_meta( PostTypes::SERVICE, self::META_CURRENCY, $args( 'string', [ self::class, 'normalize_currency' ] ) );
		register_post_meta( PostTypes::SERVICE, self::META_PRICING_MODEL, $args( 'string', [ self::class, 'normalize_pricing_model' ] ) );
		register_post_meta( PostTypes::SERVICE, self::META_RECURRING_PERIOD, $args( 'string', [ self::class, 'normalize_recurring_period' ] ) );
		register_post_meta( PostTypes::SERVICE, self::META_TAX_MODE, $args( 'string', [ self::class, 'normalize_tax_mode' ] ) );
		register_post_meta( PostTypes::SERVICE, self::META_TAX_RATE_ID, $args( 'integer', 'absint' ) );
	}

	/** @return array{amount_minor:?int,currency:string,pricing_model:string,recurring_period:string,tax_mode:string,tax_rate_id:int} */
	public static function get( int $service_id ): array {
		$has_amount       = metadata_exists( 'post', $service_id, self::META_AMOUNT_MINOR );
		$amount           = $has_amount ? absint( get_post_meta( $service_id, self::META_AMOUNT_MINOR, true ) ) : null;
		$currency         = self::normalize_currency( (string) get_post_meta( $service_id, self::META_CURRENCY, true ) );
		$pricing_model    = self::normalize_pricing_model( (string) get_post_meta( $service_id, self::META_PRICING_MODEL, true ) );
		$recurring_period = self::normalize_recurring_period( (string) get_post_meta( $service_id, self::META_RECURRING_PERIOD, true ) );
		$tax_mode         = self::normalize_tax_mode( (string) get_post_meta( $service_id, self::META_TAX_MODE, true ) );
		$tax_rate_id      = absint( get_post_meta( $service_id, self::META_TAX_RATE_ID, true ) );

		if ( self::MODEL_RECURRING !== $pricing_model ) {
			$recurring_period = '';
		}
		if ( self::TAX_EXEMPT === $tax_mode || ! TaxRates::get( $tax_rate_id ) ) {
			$tax_rate_id = 0;
		}

		return [
			'amount_minor'     => $amount,
			'currency'         => $currency,
			'pricing_model'    => $pricing_model,
			'recurring_period' => $recurring_period,
			'tax_mode'         => $tax_mode,
			'tax_rate_id'      => $tax_rate_id,
		];
	}

	/** @param array<string,mixed> $input */
	public static function save( int $service_id, array $input ): bool {
		if ( PostTypes::SERVICE !== get_post_type( $service_id ) ) {
			return false;
		}

		$before     = self::get( $service_id );
		$amount_raw = trim( (string) ( $input['amount'] ?? '' ) );
		$amount     = '' === $amount_raw ? null : self::parse_amount_minor( $amount_raw );
		if ( '' !== $amount_raw && null === $amount ) {
			$amount = $before['amount_minor'];
		}

		$currency         = self::normalize_currency( (string) ( $input['currency'] ?? self::DEFAULT_CURRENCY ) );
		$pricing_model    = self::normalize_pricing_model( (string) ( $input['pricing_model'] ?? self::MODEL_FIXED ) );
		$recurring_period = self::normalize_recurring_period( (string) ( $input['recurring_period'] ?? self::PERIOD_MONTHLY ) );
		$tax_mode         = self::normalize_tax_mode( (string) ( $input['tax_mode'] ?? self::TAX_EXCLUSIVE ) );
		$tax_rate_id      = absint( $input['tax_rate_id'] ?? 0 );

		if ( self::MODEL_RECURRING !== $pricing_model ) {
			$recurring_period = '';
		}
		if ( self::TAX_EXEMPT === $tax_mode ) {
			$tax_rate_id = 0;
		} elseif ( $tax_rate_id > 0 ) {
			$rate = TaxRates::get( $tax_rate_id );
			if ( ! $rate ) {
				$tax_rate_id = 0;
			} elseif ( $tax_rate_id !== $before['tax_rate_id'] && ! TaxRates::is_available( $rate ) ) {
				$tax_rate_id = $before['tax_rate_id'];
			}
		}

		if ( null === $amount ) {
			delete_post_meta( $service_id, self::META_AMOUNT_MINOR );
		} else {
			update_post_meta( $service_id, self::META_AMOUNT_MINOR, $amount );
		}
		update_post_meta( $service_id, self::META_CURRENCY, $currency );
		update_post_meta( $service_id, self::META_PRICING_MODEL, $pricing_model );
		update_post_meta( $service_id, self::META_RECURRING_PERIOD, $recurring_period );
		update_post_meta( $service_id, self::META_TAX_MODE, $tax_mode );
		update_post_meta( $service_id, self::META_TAX_RATE_ID, $tax_rate_id );

		return $before !== self::get( $service_id );
	}

	public static function normalize_currency( mixed $currency ): string {
		$value = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $currency ) ?? '' );
		return 3 === strlen( $value ) ? $value : self::DEFAULT_CURRENCY;
	}

	public static function normalize_pricing_model( mixed $model ): string {
		$value = sanitize_key( (string) $model );
		return in_array( $value, [ self::MODEL_HOURLY, self::MODEL_FIXED, self::MODEL_RECURRING ], true ) ? $value : self::MODEL_FIXED;
	}

	public static function normalize_recurring_period( mixed $period ): string {
		$value = sanitize_key( (string) $period );
		return in_array( $value, [ self::PERIOD_WEEKLY, self::PERIOD_MONTHLY, self::PERIOD_QUARTERLY, self::PERIOD_YEARLY ], true ) ? $value : self::PERIOD_MONTHLY;
	}

	public static function normalize_tax_mode( mixed $mode ): string {
		$value = sanitize_key( (string) $mode );
		return in_array( $value, [ self::TAX_EXCLUSIVE, self::TAX_INCLUSIVE, self::TAX_EXEMPT ], true ) ? $value : self::TAX_EXCLUSIVE;
	}

	/** @return array<string,string> */
	public static function pricing_model_labels(): array {
		return [
			self::MODEL_HOURLY    => __( 'Hourly', 'core-blueprint-work' ),
			self::MODEL_FIXED     => __( 'Fixed price', 'core-blueprint-work' ),
			self::MODEL_RECURRING => __( 'Recurring', 'core-blueprint-work' ),
		];
	}

	/** @return array<string,string> */
	public static function recurring_period_labels(): array {
		return [
			self::PERIOD_WEEKLY    => __( 'Weekly', 'core-blueprint-work' ),
			self::PERIOD_MONTHLY   => __( 'Monthly', 'core-blueprint-work' ),
			self::PERIOD_QUARTERLY => __( 'Quarterly', 'core-blueprint-work' ),
			self::PERIOD_YEARLY    => __( 'Yearly', 'core-blueprint-work' ),
		];
	}

	/** @return array<string,string> */
	public static function tax_mode_labels(): array {
		return [
			self::TAX_EXCLUSIVE => __( 'Excluding VAT', 'core-blueprint-work' ),
			self::TAX_INCLUSIVE => __( 'Including VAT', 'core-blueprint-work' ),
			self::TAX_EXEMPT    => __( 'VAT exempt / no VAT', 'core-blueprint-work' ),
		];
	}

	public static function parse_amount_minor( string $value ): ?int {
		$value = str_replace( [ "\xc2\xa0", ' ' ], '', trim( $value ) );
		if ( '' === $value ) {
			return null;
		}

		$comma = strrpos( $value, ',' );
		$dot   = strrpos( $value, '.' );
		if ( false !== $comma && false !== $dot ) {
			if ( $comma > $dot ) {
				$value = str_replace( '.', '', $value );
				$value = str_replace( ',', '.', $value );
			} else {
				$value = str_replace( ',', '', $value );
			}
		} elseif ( false !== $comma ) {
			$value = str_replace( ',', '.', $value );
		}

		if ( 1 !== preg_match( '/^(\d+)(?:\.(\d{1,2}))?$/D', $value, $matches ) ) {
			return null;
		}

		$whole = (int) $matches[1];
		if ( $whole > intdiv( PHP_INT_MAX, 100 ) ) {
			return null;
		}
		$fraction = isset( $matches[2] ) ? str_pad( $matches[2], 2, '0' ) : '00';
		return ( $whole * 100 ) + (int) $fraction;
	}

	public static function amount_input( ?int $amount_minor ): string {
		if ( null === $amount_minor ) {
			return '';
		}
		return intdiv( $amount_minor, 100 ) . '.' . str_pad( (string) ( $amount_minor % 100 ), 2, '0', STR_PAD_LEFT );
	}

	/** @param array{amount_minor:?int,currency:string,pricing_model:string,recurring_period:string,tax_mode:string,tax_rate_id:int}|null $pricing */
	public static function summary( ?array $pricing ): string {
		if ( ! $pricing ) {
			return __( 'No pricing configured.', 'core-blueprint-work' );
		}

		$model_labels = self::pricing_model_labels();
		$tax_labels   = self::tax_mode_labels();
		$parts        = [ $model_labels[ $pricing['pricing_model'] ] ?? $pricing['pricing_model'] ];
		if ( self::MODEL_RECURRING === $pricing['pricing_model'] ) {
			$periods = self::recurring_period_labels();
			$parts[] = $periods[ $pricing['recurring_period'] ] ?? $pricing['recurring_period'];
		}
		if ( null !== $pricing['amount_minor'] ) {
			$parts[] = $pricing['currency'] . ' ' . self::amount_input( $pricing['amount_minor'] );
		} else {
			$parts[] = __( 'No standard price', 'core-blueprint-work' );
		}
		$parts[] = $tax_labels[ $pricing['tax_mode'] ] ?? $pricing['tax_mode'];
		if ( self::TAX_EXEMPT !== $pricing['tax_mode'] && $pricing['tax_rate_id'] > 0 ) {
			$rate = TaxRates::get( $pricing['tax_rate_id'] );
			if ( $rate ) {
				$parts[] = TaxRates::display_label( $rate );
			}
		}
		return implode( ' · ', $parts );
	}
}

<?php
declare(strict_types=1);

namespace CB\Work\Pricing;

use CB\Work\Content\ServicePricing;
use CB\Work\PublicApi\PricingProviders;
use CB\Work\PublicApi\Services;
use CB\Work\PublicApi\TaxRates;
defined( 'ABSPATH' ) || exit;

/** Canonical effective-pricing resolver for Work commercial operations. */
final class Resolver {
	/**
	 * Precedence is fixed and owned by Work:
	 * 1. explicit Work Item/document override;
	 * 2. customer-specific provider agreement;
	 * 3. Work Service default.
	 *
	 * @param array<string,mixed> $context
	 * @param array<string,mixed> $explicit_override
	 * @return array<string,mixed>|null
	 */
	public static function resolve( int $service_id, array $context = [], array $explicit_override = [] ): ?array {
		$service = Services::get( $service_id );
		if ( null === $service || ! isset( $service['pricing'] ) || ! is_array( $service['pricing'] ) ) { return null; }
		$effective_at = self::date( $context['effective_at'] ?? '' );
		$effective = self::service_default( $service['pricing'] );
		$source = 'service_default'; $provider_id = ''; $reference_type = ''; $reference_id = '';

		$provider = PricingProviders::resolve( [ 'service_id' => $service_id, 'customer_type' => sanitize_key( (string) ( $context['customer_type'] ?? '' ) ), 'customer_id' => absint( $context['customer_id'] ?? 0 ), 'effective_at' => $effective_at ] );
		if ( null !== $provider ) {
			$effective = array_replace( $effective, self::override_layer( $provider['pricing'], $effective_at ) );
			$source = 'customer_agreement'; $provider_id = $provider['provider']; $reference_type = $provider['reference_type']; $reference_id = $provider['reference_id'];
		}

		$explicit = self::override_layer( $explicit_override, $effective_at );
		if ( [] !== $explicit ) { $effective = array_replace( $effective, $explicit ); $source = 'explicit_override'; }
		if ( ServicePricing::TAX_EXEMPT === $effective['tax_mode'] ) { $effective['tax_rate_id'] = 0; }

		return [ 'service_id' => $service_id, 'amount_minor' => $effective['amount_minor'], 'currency' => $effective['currency'], 'pricing_model' => $effective['pricing_model'], 'recurring_period' => $effective['recurring_period'], 'tax_mode' => $effective['tax_mode'], 'tax_rate_id' => $effective['tax_rate_id'], 'effective_at' => $effective_at, 'source' => $source, 'provider' => $provider_id, 'reference_type' => $reference_type, 'reference_id' => $reference_id ];
	}

	/** @param array<string,mixed> $pricing @return array{amount_minor:?int,currency:string,pricing_model:string,recurring_period:string,tax_mode:string,tax_rate_id:int} */
	private static function service_default( array $pricing ): array {
		$amount = $pricing['amount_minor'] ?? null; $model = ServicePricing::normalize_pricing_model( (string) ( $pricing['pricing_model'] ?? ServicePricing::MODEL_FIXED ) );
		return [ 'amount_minor' => null === $amount ? null : max( 0, (int) $amount ), 'currency' => ServicePricing::normalize_currency( (string) ( $pricing['currency'] ?? ServicePricing::DEFAULT_CURRENCY ) ), 'pricing_model' => $model, 'recurring_period' => ServicePricing::MODEL_RECURRING === $model ? ServicePricing::normalize_recurring_period( (string) ( $pricing['recurring_period'] ?? ServicePricing::PERIOD_MONTHLY ) ) : '', 'tax_mode' => ServicePricing::normalize_tax_mode( (string) ( $pricing['tax_mode'] ?? ServicePricing::TAX_EXCLUSIVE ) ), 'tax_rate_id' => absint( $pricing['tax_rate_id'] ?? 0 ) ];
	}

	/** @param array<string,mixed> $pricing @return array<string,mixed> */
	private static function override_layer( array $pricing, string $effective_at ): array {
		$layer = [];
		if ( array_key_exists( 'amount_minor', $pricing ) && null !== $pricing['amount_minor'] && is_numeric( $pricing['amount_minor'] ) && (int) $pricing['amount_minor'] >= 0 ) { $layer['amount_minor'] = (int) $pricing['amount_minor']; }
		if ( array_key_exists( 'currency', $pricing ) && is_scalar( $pricing['currency'] ) ) { $currency = strtoupper( trim( (string) $pricing['currency'] ) ); if ( 1 === preg_match( '/^[A-Z]{3}$/D', $currency ) ) { $layer['currency'] = $currency; } }
		if ( array_key_exists( 'tax_mode', $pricing ) && is_scalar( $pricing['tax_mode'] ) ) { $tax_mode = sanitize_key( (string) $pricing['tax_mode'] ); if ( in_array( $tax_mode, [ ServicePricing::TAX_EXCLUSIVE, ServicePricing::TAX_INCLUSIVE, ServicePricing::TAX_EXEMPT ], true ) ) { $layer['tax_mode'] = $tax_mode; } }
		if ( array_key_exists( 'tax_rate_id', $pricing ) && is_numeric( $pricing['tax_rate_id'] ) ) { $tax_rate_id = absint( $pricing['tax_rate_id'] ); if ( 0 === $tax_rate_id || TaxRates::is_available( $tax_rate_id, $effective_at ) ) { $layer['tax_rate_id'] = $tax_rate_id; } }
		if ( isset( $layer['tax_mode'] ) && ServicePricing::TAX_EXEMPT === $layer['tax_mode'] ) { $layer['tax_rate_id'] = 0; }
		return $layer;
	}
	private static function date( mixed $value ): string { $value = is_scalar( $value ) ? trim( (string) $value ) : ''; $date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $value ); return $date && $date->format( 'Y-m-d' ) === $value ? $value : current_time( 'Y-m-d' ); }
}

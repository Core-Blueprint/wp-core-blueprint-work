<?php
declare(strict_types=1);

namespace {
	define( 'ABSPATH', '/tmp/wp/' );

	function sanitize_key( string $value ): string {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', $value ) ?? '' );
	}
	function sanitize_text_field( string $value ): string {
		return trim( strip_tags( $value ) );
	}
	function absint( mixed $value ): int {
		return abs( (int) $value );
	}
	function current_time( string $format ): string {
		return 'Y-m-d' === $format ? '2026-09-04' : '';
	}
	function do_action( string $hook ): void {
		unset( $hook );
	}
	function assert_true( bool $condition, string $message ): void {
		if ( ! $condition ) {
			fwrite( STDERR, "FAIL: {$message}\n" );
			exit( 1 );
		}
	}
}

namespace CB\Work\PublicApi {
	final class Services {
		/** @return array<string,mixed>|null */
		public static function get( int $service_id ): ?array {
			if ( 42 !== $service_id ) {
				return null;
			}
			return [
				'id' => 42,
				'pricing' => [
					'amount_minor' => 10000,
					'currency' => 'EUR',
					'pricing_model' => 'fixed',
					'recurring_period' => '',
					'tax_mode' => 'exclusive',
					'tax_rate_id' => 1,
				],
			];
		}
	}

	final class TaxRates {
		public static function is_available( int $id, ?string $on_date = null ): bool {
			unset( $on_date );
			return in_array( $id, [ 1, 2 ], true );
		}
	}
}

namespace {
	require dirname( __DIR__ ) . '/src/Content/ServicePricing.php';
	require dirname( __DIR__ ) . '/src/PublicApi/PricingProviders.php';
	require dirname( __DIR__ ) . '/src/Pricing/Resolver.php';

	use CB\Work\Pricing\Resolver;
	use CB\Work\PublicApi\PricingProviders;

	PricingProviders::register( 'crm', static function ( array $context ): ?array {
		if ( 'contact' !== $context['customer_type'] || 7 !== $context['customer_id'] || 42 !== $context['service_id'] ) {
			return null;
		}
		return [
			'pricing' => [
				'amount_minor' => 8500,
				'currency' => 'EUR',
				'tax_mode' => 'inclusive',
				'tax_rate_id' => 2,
			],
			'reference_type' => 'service_agreement',
			'reference_id' => '31',
		];
	} );

	$agreement = Resolver::resolve( 42, [ 'customer_type' => 'contact', 'customer_id' => 7, 'effective_at' => '2026-09-04' ] );
	assert_true( is_array( $agreement ), 'Resolver returns effective pricing for a valid Work service.' );
	assert_true( 8500 === $agreement['amount_minor'], 'Customer agreement overrides the Work Service amount.' );
	assert_true( 'customer_agreement' === $agreement['source'], 'Agreement source provenance is explicit.' );
	assert_true( 'crm' === $agreement['provider'], 'Provider provenance is retained.' );
	assert_true( 'service_agreement' === $agreement['reference_type'] && '31' === $agreement['reference_id'], 'Provider reference provenance is retained.' );

	$explicit = Resolver::resolve(
		42,
		[ 'customer_type' => 'contact', 'customer_id' => 7, 'effective_at' => '2026-09-04' ],
		[ 'amount_minor' => 7000, 'currency' => 'usd', 'tax_rate_id' => 99 ]
	);
	assert_true( is_array( $explicit ), 'Resolver accepts an explicit Work override.' );
	assert_true( 7000 === $explicit['amount_minor'] && 'USD' === $explicit['currency'], 'Explicit override wins over agreement pricing.' );
	assert_true( 2 === $explicit['tax_rate_id'], 'Invalid explicit VAT reference cannot replace the valid agreement VAT reference.' );
	assert_true( 'explicit_override' === $explicit['source'], 'Explicit override provenance wins at highest precedence.' );

	$default = Resolver::resolve( 42, [ 'customer_type' => 'contact', 'customer_id' => 999, 'effective_at' => '2026-09-04' ] );
	assert_true( is_array( $default ) && 10000 === $default['amount_minor'], 'Work Service default is used without a customer agreement.' );
	assert_true( 'service_default' === $default['source'], 'Service-default provenance is explicit.' );
	assert_true( null === Resolver::resolve( 999 ), 'Unknown Work service resolves to null without fallback catalog behavior.' );

	echo "Pricing smoke passed.\n";
}

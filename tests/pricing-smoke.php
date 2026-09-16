<?php
declare(strict_types=1);

namespace {
	define( 'ABSPATH', '/tmp/wp/' );
	function sanitize_key( string $value ): string { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', $value ) ?? '' ); }
	function sanitize_text_field( string $value ): string { return trim( strip_tags( $value ) ); }
	function absint( mixed $value ): int { return abs( (int) $value ); }
	function current_time( string $format ): string { return 'Y-m-d' === $format ? '2026-09-04' : ''; }
	function cb_work_runtime_ready(): bool { return true; }
	function do_action( string $hook ): void { unset( $hook ); }
	function assert_true( bool $condition, string $message ): void { if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); } }
}
namespace CB\Work\PublicApi {
	final class Services { public static function get( int $service_id ): ?array { return 42 !== $service_id ? null : [ 'id' => 42, 'pricing' => [ 'amount_minor' => 10000, 'currency' => 'EUR', 'pricing_model' => 'fixed', 'recurring_period' => '', 'tax_mode' => 'exclusive', 'tax_rate_id' => 1 ] ]; } }
	final class TaxRates { public static function is_available( int $id, ?string $on_date = null ): bool { unset( $on_date ); return in_array( $id, [ 1, 2 ], true ); } }
}
namespace {
	require dirname( __DIR__ ) . '/src/Content/ServicePricing.php'; require dirname( __DIR__ ) . '/src/PublicApi/PricingProviders.php'; require dirname( __DIR__ ) . '/src/Pricing/Resolver.php';
	use CB\Work\Pricing\Resolver; use CB\Work\PublicApi\PricingProviders;
	PricingProviders::register( 'crm', static function ( array $context ): ?array { if ( 'contact' !== $context['customer_type'] || 42 !== $context['service_id'] ) { return null; } if ( 7 === $context['customer_id'] ) { return [ 'pricing' => [ 'amount_minor' => 8500, 'currency' => 'EUR', 'tax_mode' => 'inclusive', 'tax_rate_id' => 2 ], 'reference_type' => 'service_agreement', 'reference_id' => '31' ]; } if ( 8 === $context['customer_id'] ) { return [ 'pricing' => [], 'reference_type' => 'service_agreement', 'reference_id' => '32' ]; } return null; } );
	$agreement = Resolver::resolve( 42, [ 'customer_type' => 'contact', 'customer_id' => 7, 'effective_at' => '2026-09-04' ] );
	assert_true( is_array( $agreement ) && 8500 === $agreement['amount_minor'], 'Customer agreement overrides the Work Service amount.' ); assert_true( 'customer_agreement' === $agreement['source'] && 'crm' === $agreement['provider'], 'Agreement provenance is explicit.' ); assert_true( 'service_agreement' === $agreement['reference_type'] && '31' === $agreement['reference_id'], 'Provider reference provenance is retained.' );
	$inherited = Resolver::resolve( 42, [ 'customer_type' => 'contact', 'customer_id' => 8, 'effective_at' => '2026-09-04' ] );
	assert_true( is_array( $inherited ) && 10000 === $inherited['amount_minor'], 'Inherited agreement keeps the Work Service values.' ); assert_true( 'customer_agreement' === $inherited['source'] && '32' === $inherited['reference_id'], 'Inherited agreement provenance is retained for future snapshots.' );
	$explicit = Resolver::resolve( 42, [ 'customer_type' => 'contact', 'customer_id' => 7, 'effective_at' => '2026-09-04' ], [ 'amount_minor' => 7000, 'currency' => 'usd', 'tax_rate_id' => 99 ] );
	assert_true( is_array( $explicit ) && 7000 === $explicit['amount_minor'] && 'USD' === $explicit['currency'], 'Explicit override wins over agreement pricing.' ); assert_true( 2 === $explicit['tax_rate_id'], 'Invalid explicit VAT reference cannot replace valid agreement VAT.' ); assert_true( 'explicit_override' === $explicit['source'], 'Explicit provenance wins at highest precedence.' );
	$invalid = Resolver::resolve( 42, [ 'customer_type' => 'contact', 'customer_id' => 7, 'effective_at' => '2026-09-04' ], [ 'currency' => 'invalid', 'tax_mode' => 'nonsense' ] );
	assert_true( is_array( $invalid ) && 'EUR' === $invalid['currency'] && 'inclusive' === $invalid['tax_mode'], 'Invalid override fields are ignored instead of normalized into new values.' );
	$default = Resolver::resolve( 42, [ 'customer_type' => 'contact', 'customer_id' => 999, 'effective_at' => '2026-09-04' ] ); assert_true( is_array( $default ) && 10000 === $default['amount_minor'] && 'service_default' === $default['source'], 'Work Service default is used without agreement.' ); assert_true( null === Resolver::resolve( 999 ), 'Unknown Work service resolves to null without fallback catalog behavior.' );
	echo "Pricing smoke passed.\n";
}

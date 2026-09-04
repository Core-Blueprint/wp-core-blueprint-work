<?php
declare(strict_types=1);

define( 'ABSPATH', '/tmp/wp/' );

function sanitize_key( string $value ): string {
	return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', $value ) ?? '' );
}
function sanitize_title( string $value ): string {
	$value = strtolower( trim( $value ) );
	$value = preg_replace( '/[^a-z0-9]+/', '-', $value ) ?? '';
	return trim( $value, '-' );
}
function __( string $text, string $domain = 'default' ): string {
	unset( $domain );
	return $text;
}
function assert_true( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

require dirname( __DIR__ ) . '/src/Content/ServicePricing.php';
require dirname( __DIR__ ) . '/src/Repository/TaxRates.php';

use CB\Work\Content\ServicePricing;
use CB\Work\Repository\TaxRates;

assert_true( 9500 === ServicePricing::parse_amount_minor( '95' ), 'Whole currency amount parses to minor units.' );
assert_true( 123456 === ServicePricing::parse_amount_minor( '1.234,56' ), 'European grouped amount parses deterministically.' );
assert_true( 123456 === ServicePricing::parse_amount_minor( '1,234.56' ), 'English grouped amount parses deterministically.' );
assert_true( null === ServicePricing::parse_amount_minor( '-5' ), 'Negative standard prices are rejected.' );
assert_true( ServicePricing::MODEL_FIXED === ServicePricing::normalize_pricing_model( 'unknown' ), 'Unknown pricing model falls back to fixed.' );
assert_true( ServicePricing::MODEL_HOURLY === ServicePricing::normalize_pricing_model( 'hourly' ), 'Hourly pricing model is accepted.' );
assert_true( ServicePricing::PERIOD_QUARTERLY === ServicePricing::normalize_recurring_period( 'quarterly' ), 'Quarterly recurring period is accepted.' );
assert_true( ServicePricing::TAX_EXEMPT === ServicePricing::normalize_tax_mode( 'exempt' ), 'VAT exemption mode is accepted.' );
assert_true( 'EUR' === ServicePricing::normalize_currency( 'eur' ), 'Currency is normalized to ISO-style uppercase.' );

assert_true( 2100 === TaxRates::parse_rate_bp( '21' ), '21 percent becomes 2100 basis points.' );
assert_true( 950 === TaxRates::parse_rate_bp( '9,5%' ), 'Decimal VAT rate parses to basis points.' );
assert_true( 10000 === TaxRates::parse_rate_bp( '100' ), '100 percent is accepted.' );
assert_true( null === TaxRates::parse_rate_bp( '100.01' ), 'VAT rates above 100 percent are rejected.' );
assert_true( 'nl-standard' === TaxRates::normalize_code( 'NL Standard' ), 'VAT code normalization is deterministic.' );
assert_true( '9.5' === TaxRates::format_rate_bp( 950 ), 'Basis point formatter avoids meaningless trailing zero.' );

echo "Domain smoke passed.\n";

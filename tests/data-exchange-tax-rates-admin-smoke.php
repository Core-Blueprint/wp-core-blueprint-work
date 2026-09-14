<?php
declare(strict_types=1);

$root   = dirname( __DIR__ );
$page   = file_get_contents( $root . '/src/Admin/Page.php' );
$admin  = file_get_contents( $root . '/src/Admin/TaxRateDataExchange.php' );
$js     = file_get_contents( $root . '/assets/data-exchange-tax-rates.js' );
$checks = file_get_contents( $root . '/tools/check' );

$fail = static function ( string $message ): never {
	fwrite( STDERR, "Data Exchange VAT admin smoke failed: {$message}\n" );
	exit( 1 );
};

if ( false === $page || false === $admin || false === $js || false === $checks ) {
	$fail( 'Expected source files are readable.' );
}

$assertions = [
	[
		str_contains( $page, "TaxRateDataExchange::init();" )
			&& str_contains( $page, "'vat-import' === \$view" )
			&& str_contains( $page, "TaxRateDataExchange::render_import();" ),
		'Work settings own the VAT import view without adding a second Settings page.',
	],
	[
		str_contains( $page, "TaxRateDataExchange::import_url()" )
			&& str_contains( $page, "TaxRateDataExchange::export_url()" )
			&& ! str_contains( $page, 'add_submenu_page' ),
		'VAT import/export stays contextual to the existing Extensions > Work settings surface.',
	],
	[
		str_contains( $admin, "admin_post_cb_work_export_tax_rates" )
			&& str_contains( $admin, "wp_ajax_cb_work_tax_rate_mapper_inspect" )
			&& str_contains( $admin, "wp_ajax_cb_work_tax_rate_mapper_preview" )
			&& str_contains( $admin, "wp_ajax_cb_work_tax_rate_mapper_apply" ),
		'Work owns explicit authorized transport endpoints.',
	],
	[
		str_contains( $admin, "current_user_can( Capabilities::MANAGE )" )
			&& str_contains( $admin, "check_ajax_referer( self::NONCE_ACTION, 'nonce', false )" )
			&& str_contains( $admin, "check_admin_referer( self::NONCE_ACTION )" ),
		'Import/export transport is capability and CSRF gated.',
	],
	[
		str_contains( $admin, 'Mapper::inspect_csv' )
			&& str_contains( $admin, 'Mapper::map_records' )
			&& str_contains( $admin, 'Mapper::exchange_json' )
			&& str_contains( $admin, 'Engine::preview_json' )
			&& str_contains( $admin, 'Engine::apply_json' ),
		'Consumer uses the public Base Data Mapper and Data Exchange contracts end to end.',
	],
	[
		str_contains( $admin, 'Foundation::MODE_CREATE_UPDATE' )
			&& str_contains( $admin, "preg_match( '/^[a-f0-9]{64}\$/D', \$fingerprint )" ),
		'Apply uses create/update mode and requires a validated preview fingerprint.',
	],
	[
		str_contains( $admin, 'is_uploaded_file( $tmp )' )
			&& str_contains( $admin, "Foundation::MAX_INPUT_BYTES" )
			&& str_contains( $admin, "PATHINFO_EXTENSION" ),
		'CSV intake is request-local, bounded and type checked.',
	],
	[
		str_contains( $js, "'cb:data-mapper:file-selected'" )
			&& str_contains( $js, "'cb:data-mapper:submit'" )
			&& str_contains( $js, "'cb:data-mapper:change'" ),
		'Vanilla consumer listens only to the public Data Mapper browser events.',
	],
	[
		str_contains( $js, 'controller.setSourceFields' )
			&& str_contains( $js, 'controller.setValidation' )
			&& str_contains( $js, 'controller.setBusy' )
			&& ! str_contains( $js, 'jQuery' )
			&& ! str_contains( $js, '$(' ),
		'Consumer uses the public controller seam and no jQuery.',
	],
	[
		str_contains( $checks, 'data-exchange-tax-rates-admin-smoke.php' ),
		'The admin consumer regression is wired into the canonical Work check runner.',
	],
];

foreach ( $assertions as [ $passed, $message ] ) {
	if ( ! $passed ) {
		$fail( $message );
	}
}

echo "Data Exchange VAT admin smoke passed.\n";

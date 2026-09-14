<?php
declare(strict_types=1);

$root   = dirname( __DIR__ );
$plugin = file_get_contents( $root . '/src/Plugin.php' );
$ux     = file_get_contents( $root . '/src/Admin/BillingClassificationUx.php' );
$js     = file_get_contents( $root . '/assets/work-item-billing.js' );

$fail = static function ( string $message ): never {
	fwrite( STDERR, "Billing classification UX smoke failed: {$message}\n" );
	exit( 1 );
};

if ( false === $plugin || false === $ux || false === $js ) {
	$fail( 'Expected source files are readable.' );
}

$checks = [
	[
		str_contains( $plugin, 'use CB\\Work\\Admin\\BillingClassificationUx;' )
			&& str_contains( $plugin, 'BillingClassificationUx::init();' ),
		'Plugin boot wires the Work-owned billing UX helper.',
	],
	[
		str_contains( $ux, "save_post_' . PostTypes::WORK_ITEM" )
			&& str_contains( $ux, '19, 3' ),
		'Editor payload defaulting runs immediately before the canonical Work Item save owner.',
	],
	[
		str_contains( $ux, "'' !== \$billing" ),
		'An explicit Work Item billing classification is never replaced.',
	],
	[
		str_contains( $ux, 'ServicePricing::MODEL_HOURLY' )
			&& str_contains( $ux, 'BillingDisposition::HOURLY' ),
		'Hourly Service pricing maps to Hourly Work Item billing.',
	],
	[
		str_contains( $ux, 'ServicePricing::MODEL_FIXED, ServicePricing::MODEL_RECURRING' )
			&& str_contains( $ux, 'BillingDisposition::FIXED' ),
		'Fixed and recurring Service pricing map to Fixed Work Item billing.',
	],
	[
		! preg_match( '/\b(?:update_post_meta|add_post_meta|delete_post_meta|wpdb->)\b/', $ux ),
		'The UX helper does not become a second persistence owner.',
	],
	[
		str_contains( $js, "billing.value === '' || ( autoValue && billing.value === autoValue )" ),
		'Browser defaults only replace an empty or previously auto-managed value.',
	],
	[
		str_contains( $js, "billing.addEventListener( 'change'" )
			&& str_contains( $js, "autoValue = '';" ),
		'A manual billing choice exits automatic mode.',
	],
	[
		str_contains( $js, 'cb-work-item-billing-hint' )
			&& str_contains( $js, 'pricingLabel' ),
		'The editor exposes the Service pricing/default relationship to the user.',
	],
	[
		! str_contains( $js, 'jQuery' )
			&& ! str_contains( $js, '$(' ),
		'The UX remains vanilla JavaScript.',
	],
];

foreach ( $checks as [ $passed, $message ] ) {
	if ( ! $passed ) {
		$fail( $message );
	}
}

echo "Billing classification UX smoke passed.\n";

<?php
declare(strict_types=1);

require __DIR__ . '/data-exchange-tax-rates-smoke.php';

use CoreBlueprint\Core\DataExchange\Foundation;

$GLOBALS['cb_work_test_authorized'] = true;

$partial_create = $entity->plan_import(
	[
		'code'      => 'es-reduced-10',
		'label'     => 'ES Reduced 10%',
		'rate_bp'   => '1000',
		'is_active' => '1',
	],
	Foundation::MODE_CREATE_UPDATE,
	1
);
assert_true( is_array( $partial_create ) && Foundation::OP_CREATE === $partial_create['operation'], 'Mapper import may omit optional VAT fields on create.' );
assert_true(
	'' === $partial_create['payload']['record']['country_code']
	&& null === $partial_create['payload']['record']['valid_from']
	&& null === $partial_create['payload']['record']['valid_until'],
	'Unmapped optional VAT fields receive deterministic Work defaults on create.'
);

$partial_update = $entity->plan_import(
	[
		'code'      => 'nl-standard-21',
		'label'     => 'NL Standard 21% mapped',
		'rate_bp'   => '2100',
		'is_active' => '1',
	],
	Foundation::MODE_CREATE_UPDATE,
	1
);
assert_true( is_array( $partial_update ) && Foundation::OP_UPDATE === $partial_update['operation'], 'Mapper import may omit optional VAT fields on update.' );
assert_true(
	'NL' === $partial_update['payload']['record']['country_code']
	&& null === $partial_update['payload']['record']['valid_from']
	&& null === $partial_update['payload']['record']['valid_until'],
	'Unmapped optional VAT fields preserve current Work-owned values on update.'
);

$missing_required = $entity->plan_import(
	[
		'code'      => 'es-reduced-10',
		'rate_bp'   => '1000',
		'is_active' => '1',
	],
	Foundation::MODE_CREATE_UPDATE,
	1
);
assert_true( is_wp_error( $missing_required ) && 'work_data_exchange_tax_rate_shape' === $missing_required->get_error_code(), 'Required Mapper targets remain mandatory.' );

echo "Data Exchange Tax Rates optional mapping smoke passed.\n";

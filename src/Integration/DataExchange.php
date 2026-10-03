<?php
declare(strict_types=1);

namespace CB\Work\Integration;

use CoreBlueprint\Core\DataExchange\CsvEntityInterface;
use CoreBlueprint\Core\DataExchange\Foundation;
use CoreBlueprint\Core\DataExchange\MappingEntityInterface;
use CoreBlueprint\Core\Interoperability\Registry;
use CB\Work\Integration\DataExchange\TaxRateEntity;

defined( 'ABSPATH' ) || exit;

final class DataExchange {
	public const TAX_RATE_ENTITY = 'tax-rate';

	public static function register(): void {
		if (
			! function_exists( 'cb_work_runtime_ready' )
			|| ! \cb_work_runtime_ready()
			|| ! class_exists( Registry::class )
			|| ! class_exists( Foundation::class )
			|| ! interface_exists( CsvEntityInterface::class )
			|| ! interface_exists( MappingEntityInterface::class )
		) {
			return;
		}
		$label = did_action( 'init' ) > 0 || doing_action( 'init' ) ? __( 'VAT', 'core-blueprint-work' ) : 'VAT';
		Registry::register_implementation( [
			'provider' => Suite::EXTENSION_ID,
			'id' => self::TAX_RATE_ENTITY,
			'label' => $label,
			'description' => $label,
			'contract_owner' => Foundation::CONTRACT_OWNER,
			'contract' => Foundation::CONTRACT_ID,
			'contract_version' => Foundation::CONTRACT_VERSION,
			'supports' => [ Foundation::SUPPORT_EXPORT, Foundation::SUPPORT_IMPORT, Foundation::SUPPORT_JSON, Foundation::SUPPORT_CSV, Foundation::SUPPORT_MAPPING ],
			'factory' => static fn() => new TaxRateEntity(),
		] );
	}

	private function __construct() {}
}

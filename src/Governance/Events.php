<?php
declare(strict_types=1);

namespace CB\Work\Governance;

use CB\Core\Governance\EventRegistry;
defined( 'ABSPATH' ) || exit;

final class Events {
	public const SERVICE_PRICING_UPDATED = 'work.service.pricing.updated';
	public const TAX_RATE_CREATED        = 'work.tax.rate.created';
	public const TAX_RATE_ACTIVATED      = 'work.tax.rate.activated';
	public const TAX_RATE_DEACTIVATED    = 'work.tax.rate.deactivated';

	public static function init(): void {
		add_action( 'init', [ self::class, 'register' ], 2 );
	}

	public static function register(): void {
		$events = [
			self::SERVICE_PRICING_UPDATED => __( 'Work service pricing updated', 'core-blueprint-work' ),
			self::TAX_RATE_CREATED        => __( 'Work VAT rate created', 'core-blueprint-work' ),
			self::TAX_RATE_ACTIVATED      => __( 'Work VAT rate activated', 'core-blueprint-work' ),
			self::TAX_RATE_DEACTIVATED    => __( 'Work VAT rate deactivated', 'core-blueprint-work' ),
		];
		foreach ( $events as $id => $label ) {
			EventRegistry::register( [
				'id'                 => $id,
				'label'              => $label,
				'retention_category' => 'settings',
			] );
		}
	}
}

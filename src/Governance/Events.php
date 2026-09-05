<?php
declare(strict_types=1);

namespace CB\Work\Governance;

use CB\Core\Governance\EventRegistry;

defined( 'ABSPATH' ) || exit;

final class Events {
	public const SERVICE_PRICING_UPDATED  = 'work.service.pricing.updated';
	public const TAX_RATE_CREATED         = 'work.tax.rate.created';
	public const TAX_RATE_ACTIVATED       = 'work.tax.rate.activated';
	public const TAX_RATE_DEACTIVATED     = 'work.tax.rate.deactivated';
	public const PROJECT_CREATED          = 'work.project.created';
	public const PROJECT_UPDATED          = 'work.project.updated';
	public const WORK_ITEM_CREATED        = 'work.item.created';
	public const WORK_ITEM_UPDATED        = 'work.item.updated';
	public const WORK_ITEM_STATUS_CHANGED = 'work.item.status.changed';
	public const WORK_TYPE_CREATED        = 'work.type.created';
	public const WORK_TYPE_STATUS_CHANGED = 'work.type.status.changed';

	public static function init(): void {
		add_action( 'init', [ self::class, 'register' ], 2 );
	}

	public static function register(): void {
		$events = [
			self::SERVICE_PRICING_UPDATED  => [ __( 'Work service pricing updated', 'core-blueprint-work' ), 'settings' ],
			self::TAX_RATE_CREATED         => [ __( 'Work VAT rate created', 'core-blueprint-work' ), 'settings' ],
			self::TAX_RATE_ACTIVATED       => [ __( 'Work VAT rate activated', 'core-blueprint-work' ), 'settings' ],
			self::TAX_RATE_DEACTIVATED     => [ __( 'Work VAT rate deactivated', 'core-blueprint-work' ), 'settings' ],
			self::PROJECT_CREATED          => [ __( 'Work Project created', 'core-blueprint-work' ), 'general' ],
			self::PROJECT_UPDATED          => [ __( 'Work Project updated', 'core-blueprint-work' ), 'general' ],
			self::WORK_ITEM_CREATED        => [ __( 'Work Item created', 'core-blueprint-work' ), 'general' ],
			self::WORK_ITEM_UPDATED        => [ __( 'Work Item updated', 'core-blueprint-work' ), 'general' ],
			self::WORK_ITEM_STATUS_CHANGED => [ __( 'Work Item status changed', 'core-blueprint-work' ), 'general' ],
			self::WORK_TYPE_CREATED        => [ __( 'Work Type created', 'core-blueprint-work' ), 'general' ],
			self::WORK_TYPE_STATUS_CHANGED => [ __( 'Work Type status changed', 'core-blueprint-work' ), 'general' ],
		];
		foreach ( $events as $id => [ $label, $category ] ) {
			EventRegistry::register( [
				'id'                 => $id,
				'label'              => $label,
				'retention_category' => $category,
			] );
		}
	}
}

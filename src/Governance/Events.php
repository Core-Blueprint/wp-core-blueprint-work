<?php
declare(strict_types=1);

namespace CB\Work\Governance;

use CoreBlueprint\Core\Governance\EventRegistry;

defined( 'ABSPATH' ) || exit;

final class Events {
	public const SERVICE_PRICING_UPDATED        = 'work.service.pricing.updated';
	public const TAX_RATE_CREATED               = 'work.tax.rate.created';
	public const TAX_RATE_UPDATED               = 'work.tax.rate.updated';
	public const TAX_RATE_ACTIVATED             = 'work.tax.rate.activated';
	public const TAX_RATE_DEACTIVATED           = 'work.tax.rate.deactivated';
	public const PROJECT_CREATED                = 'work.project.created';
	public const PROJECT_UPDATED                = 'work.project.updated';
	public const WORK_ITEM_CREATED              = 'work.item.created';
	public const WORK_ITEM_UPDATED              = 'work.item.updated';
	public const WORK_ITEM_STATUS_CHANGED       = 'work.item.status.changed';
	public const WORK_ITEM_SOURCE_ATTACHED      = 'work.item.source.attached';
	public const WORK_ITEM_RELATION_ADDED       = 'work.item.relation.added';
	public const BILLING_READY                   = 'work.billing.ready';
	public const BILLING_SNAPSHOT_REFRESHED      = 'work.billing.snapshot.refreshed';
	public const BILLING_EXTERNAL_LINKED         = 'work.billing.external.linked';
	public const BILLING_EXTERNAL_STATUS_UPDATED = 'work.billing.external.status.updated';
	public const WORK_TYPE_CREATED              = 'work.type.created';
	public const WORK_TYPE_STATUS_CHANGED       = 'work.type.status.changed';
	public const RECURRENCE_RULE_CREATED        = 'work.recurrence.rule.created';
	public const RECURRENCE_RULE_UPDATED        = 'work.recurrence.rule.updated';
	public const RECURRENCE_RULE_STATUS_CHANGED = 'work.recurrence.rule.status.changed';
	public const RECURRENCE_ITEM_GENERATED      = 'work.recurrence.item.generated';
	public const RECURRENCE_GENERATION_FAILED   = 'work.recurrence.generation.failed';
	public const RECURRENCE_GENERATOR_RUN       = 'work.recurrence.generator.run';
	public const TIME_ENTRY_CREATED             = 'work.time.entry.created';
	public const TIME_ENTRY_UPDATED              = 'work.time.entry.updated';
	public const TIMER_STARTED                   = 'work.time.timer.started';
	public const TIMER_STOPPED                   = 'work.time.timer.stopped';

	public static function init(): void {
		add_action( 'init', [ self::class, 'register' ], 2 );
	}

	public static function register(): void {
		$events = [
			self::SERVICE_PRICING_UPDATED        => [ __( 'Work service pricing updated', 'core-blueprint-work' ), 'settings' ],
			self::TAX_RATE_CREATED               => [ __( 'Work VAT rate created', 'core-blueprint-work' ), 'settings' ],
			self::TAX_RATE_UPDATED               => [ __( 'Work VAT rate updated', 'core-blueprint-work' ), 'settings' ],
			self::TAX_RATE_ACTIVATED             => [ __( 'Work VAT rate activated', 'core-blueprint-work' ), 'settings' ],
			self::TAX_RATE_DEACTIVATED           => [ __( 'Work VAT rate deactivated', 'core-blueprint-work' ), 'settings' ],
			self::PROJECT_CREATED                => [ __( 'Work Project created', 'core-blueprint-work' ), 'general' ],
			self::PROJECT_UPDATED                => [ __( 'Work Project updated', 'core-blueprint-work' ), 'general' ],
			self::WORK_ITEM_CREATED              => [ __( 'Work Item created', 'core-blueprint-work' ), 'general' ],
			self::WORK_ITEM_UPDATED              => [ __( 'Work Item updated', 'core-blueprint-work' ), 'general' ],
			self::WORK_ITEM_STATUS_CHANGED       => [ __( 'Work Item status changed', 'core-blueprint-work' ), 'general' ],
			self::WORK_ITEM_SOURCE_ATTACHED      => [ __( 'Work Item source attached', 'core-blueprint-work' ), 'general' ],
			self::WORK_ITEM_RELATION_ADDED       => [ __( 'Work Item relation added', 'core-blueprint-work' ), 'general' ],
			self::BILLING_READY                   => [ __( 'Work billing unit ready', 'core-blueprint-work' ), 'general' ],
			self::BILLING_SNAPSHOT_REFRESHED      => [ __( 'Work billing snapshot refreshed', 'core-blueprint-work' ), 'general' ],
			self::BILLING_EXTERNAL_LINKED         => [ __( 'Work billing unit linked externally', 'core-blueprint-work' ), 'general' ],
			self::BILLING_EXTERNAL_STATUS_UPDATED => [ __( 'Work external billing status updated', 'core-blueprint-work' ), 'general' ],
			self::WORK_TYPE_CREATED              => [ __( 'Work Type created', 'core-blueprint-work' ), 'general' ],
			self::WORK_TYPE_STATUS_CHANGED       => [ __( 'Work Type status changed', 'core-blueprint-work' ), 'general' ],
			self::RECURRENCE_RULE_CREATED        => [ __( 'Recurring Work rule created', 'core-blueprint-work' ), 'general' ],
			self::RECURRENCE_RULE_UPDATED        => [ __( 'Recurring Work rule updated', 'core-blueprint-work' ), 'general' ],
			self::RECURRENCE_RULE_STATUS_CHANGED => [ __( 'Recurring Work rule status changed', 'core-blueprint-work' ), 'general' ],
			self::RECURRENCE_ITEM_GENERATED      => [ __( 'Recurring Work Item generated', 'core-blueprint-work' ), 'maintenance' ],
			self::RECURRENCE_GENERATION_FAILED   => [ __( 'Recurring Work generation failed', 'core-blueprint-work' ), 'maintenance' ],
			self::RECURRENCE_GENERATOR_RUN       => [ __( 'Recurring Work generator run', 'core-blueprint-work' ), 'maintenance' ],
			self::TIME_ENTRY_CREATED             => [ __( 'Work Time entry created', 'core-blueprint-work' ), 'general' ],
			self::TIME_ENTRY_UPDATED             => [ __( 'Work Time entry updated', 'core-blueprint-work' ), 'general' ],
			self::TIMER_STARTED                  => [ __( 'Work timer started', 'core-blueprint-work' ), 'general' ],
			self::TIMER_STOPPED                   => [ __( 'Work timer stopped', 'core-blueprint-work' ), 'general' ],
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

<?php
declare(strict_types=1);

namespace CB\Work;

use CB\Work\Admin\Assets;
use CB\Work\Admin\Menu;
use CB\Work\Admin\OperationalActions;
use CB\Work\Admin\Page;
use CB\Work\Admin\Pickers;
use CB\Work\Admin\Projects;
use CB\Work\Admin\RecurrenceActions;
use CB\Work\Admin\ServicePricing;
use CB\Work\Admin\TaxRateActions;
use CB\Work\Admin\Time;
use CB\Work\Admin\TimeActions;
use CB\Work\Admin\WorkItems as WorkItemsAdmin;
use CB\Work\Content\PostTypes;
use CB\Work\Content\ProjectMeta;
use CB\Work\Content\ServicePricing as ServicePricingDomain;
use CB\Work\Content\WorkItemMeta;
use CB\Work\Governance\Events;
use CB\Work\Recurrence\Scheduler;
use CB\Work\Repository\WorkItems as WorkItemRepository;
use CB\Work\Support\Requirements;

defined( 'ABSPATH' ) || exit;

final class Plugin {
	private static bool $booted = false;

	public static function boot(): void {
		if ( self::$booted || ! Requirements::runtime_ready() ) {
			return;
		}
		self::$booted = true;

		Capabilities::init();
		Events::init();
		PostTypes::init();
		ProjectMeta::init();
		WorkItemMeta::init();
		WorkItemRepository::init();
		ServicePricingDomain::init();
		Scheduler::init();

		if ( is_admin() ) {
			Assets::init();
			Menu::init();
			Page::init();
			Pickers::init();
			Projects::init();
			WorkItemsAdmin::init();
			RecurrenceActions::init();
			Time::init();
			TimeActions::init();
			ServicePricing::init();
			TaxRateActions::init();
			OperationalActions::init();
		}
	}

	public static function is_booted(): bool {
		return self::$booted;
	}
}

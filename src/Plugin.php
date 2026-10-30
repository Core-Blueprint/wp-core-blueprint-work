<?php
declare(strict_types=1);

namespace CB\Work;

use CB\Work\Admin\Assets;
use CB\Work\Admin\BillingClassificationUx;
use CB\Work\Admin\Menu;
use CB\Work\Admin\OperationalActions;
use CB\Work\Admin\Page;
use CB\Work\Admin\Pickers;
use CB\Work\Admin\ProjectDataExchange;
use CB\Work\Admin\Projects;
use CB\Work\Admin\QuickAdd;
use CB\Work\Admin\RecurrenceActions;
use CB\Work\Admin\ServiceOperations as ServiceOperationsAdmin;
use CB\Work\Admin\ServiceOperationsActions;
use CB\Work\Admin\ServicePricing;
use CB\Work\Admin\TaxRateActions;
use CB\Work\Admin\Time;
use CB\Work\Admin\TimeActions;
use CB\Work\Admin\TimerHud;
use CB\Work\Admin\Workspace;
use CB\Work\Admin\WorkItems as WorkItemsAdmin;
use CB\Work\Admin\WorkItemViewBridge;
use CB\Work\Admin\UnifiedEditorActions;
use CB\Work\Admin\WorkItemListPreferences;
use CB\Work\Admin\WorkItemTablePreferences;
use CB\Work\Admin\WorkItemViewPreferences;
use CB\Work\Admin\WorkItemBoardActions;
use CB\Work\Admin\WorkItemCalendarActions;
use CB\Work\Content\PostTypes;
use CB\Work\Content\ProjectMeta;
use CB\Work\Content\ServicePricing as ServicePricingDomain;
use CB\Work\Content\WorkItemMeta;
use CB\Work\Governance\Events;
use CB\Work\Governance\WorkEventOutbox;
use CB\Work\Recurrence\Scheduler;
use CB\Work\Repository\PortableIdentities;
use CB\Work\Repository\WorkItemSources;
use CB\Work\Repository\WorkItems as WorkItemRepository;
use CB\Work\Transport\Rest\WorkspaceReadController;
use CB\Work\Transport\Rest\WorkspaceOperationsController;
use CB\Work\Transport\Rest\WorkspaceEngagementsController;
use CB\Work\Transport\Rest\WorkspaceEngagementRulesController;
use CB\Work\Transport\Rest\WorkspaceEngagementBillingController;

defined( 'ABSPATH' ) || exit;

final class Plugin {
	private static bool $booted = false;

	public static function boot(): void {
		if ( self::$booted || ! function_exists( 'cb_work_runtime_ready' ) || ! \cb_work_runtime_ready() ) {
			return;
		}
		self::$booted = true;

		Capabilities::init();
		Events::init();
		WorkEventOutbox::init();
		PostTypes::init();
		ProjectMeta::init();
		WorkItemMeta::init();
		WorkItemRepository::init();
		WorkItemSources::init();
		PortableIdentities::init();
		ServicePricingDomain::init();
		Scheduler::init();
		WorkspaceReadController::init();
		WorkspaceOperationsController::init();
		WorkspaceEngagementsController::init();
		WorkspaceEngagementRulesController::init();
		WorkspaceEngagementBillingController::init();

		if ( is_admin() ) {
			Assets::init();
			BillingClassificationUx::init();
			Menu::init();
			Workspace::init();
			QuickAdd::init();
			Page::init();
			Pickers::init();
			Projects::init();
			ProjectDataExchange::init();
			WorkItemsAdmin::init();
			WorkItemViewBridge::init();
			UnifiedEditorActions::init();
			WorkItemListPreferences::init();
			WorkItemTablePreferences::init();
			WorkItemViewPreferences::init();
			WorkItemBoardActions::init();
			WorkItemCalendarActions::init();
			RecurrenceActions::init();
		ServiceOperationsAdmin::init();
		ServiceOperationsActions::init();
			Time::init();
			TimeActions::init();
			TimerHud::init();
			ServicePricing::init();
			TaxRateActions::init();
			OperationalActions::init();
		}
	}

	public static function is_booted(): bool {
		return self::$booted;
	}
}

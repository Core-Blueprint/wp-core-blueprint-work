<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$required = [
	'core-blueprint-work.php',
	'src/Admin/Menu.php',
	'src/Admin/Operations.php',
	'src/Admin/OperationalActions.php',
	'src/Admin/Page.php',
	'src/Admin/Pickers.php',
	'src/Admin/Projects.php',
	'src/Admin/Recurrence.php',
	'src/Admin/RecurrenceActions.php',
	'src/Admin/Time.php',
	'src/Admin/TimeActions.php',
	'src/Admin/WorkItems.php',
	'src/Admin/ServicePricing.php',
	'src/Admin/TaxRateActions.php',
	'src/Capabilities.php',
	'src/Content/PostTypes.php',
	'src/Content/ProjectMeta.php',
	'src/Content/ProjectRestController.php',
	'src/Content/WorkItemMeta.php',
	'src/Content/WorkItemRestController.php',
	'src/Content/ServicePricing.php',
	'src/Database/Schema.php',
	'src/Domain/BillingDisposition.php',
	'src/Domain/RecurrenceSchedule.php',
	'src/Domain/TimeRange.php',
	'src/Domain/WorkItemPriority.php',
	'src/Domain/WorkItemStatus.php',
	'src/Frontend/Access.php',
	'src/Frontend/Actions/WorkItems.php',
	'src/Frontend/Conditions/Resources.php',
	'src/Frontend/Data/Project.php',
	'src/Frontend/Data/Service.php',
	'src/Frontend/Data/WorkItem.php',
	'src/Frontend/Queries/Projects.php',
	'src/Frontend/Queries/Services.php',
	'src/Frontend/Queries/WorkItems.php',
	'src/Governance/Events.php',
	'src/Integration/CRMCustomers.php',
	'src/Integration/Suite.php',
	'src/Lifecycle.php',
	'src/Plugin.php',
	'src/Pricing/Resolver.php',
	'src/PublicApi/Pricing.php',
	'src/PublicApi/PricingProviders.php',
	'src/PublicApi/Projects.php',
	'src/PublicApi/Services.php',
	'src/PublicApi/TaxRates.php',
	'src/PublicApi/WorkItems.php',
	'src/PublicApi/WorkTypes.php',
	'src/Recurrence/Scheduler.php',
	'src/Repository/Projects.php',
	'src/Repository/RecurrenceOccurrences.php',
	'src/Repository/RecurrenceRules.php',
	'src/Repository/TaxRates.php',
	'src/Repository/TimeEntries.php',
	'src/Repository/Timers.php',
	'src/Repository/WorkItems.php',
	'src/Repository/WorkTypes.php',
	'src/Support/Requirements.php',
	'src/Time/Access.php',
	'docs/ARCHITECTURE.md',
	'assets/service-pricing.js',
];

$failed = false;
foreach ( $required as $relative ) {
	if ( ! is_file( $root . '/' . $relative ) ) {
		fwrite( STDERR, "Missing required file: {$relative}\n" );
		$failed = true;
	}
}

$bootstrap  = file_get_contents( $root . '/core-blueprint-work.php' );
$suite      = file_get_contents( $root . '/src/Integration/Suite.php' );
$menu       = file_get_contents( $root . '/src/Admin/Menu.php' );
$operations = file_get_contents( $root . '/src/Admin/Operations.php' );
$operationalActions = file_get_contents( $root . '/src/Admin/OperationalActions.php' );
$recurrenceAdmin = file_get_contents( $root . '/src/Admin/Recurrence.php' );
$recurrenceActions = file_get_contents( $root . '/src/Admin/RecurrenceActions.php' );
$timeAdmin = file_get_contents( $root . '/src/Admin/Time.php' );
$timeActions = file_get_contents( $root . '/src/Admin/TimeActions.php' );
$projectsAdmin = file_get_contents( $root . '/src/Admin/Projects.php' );
$workItemsAdmin = file_get_contents( $root . '/src/Admin/WorkItems.php' );
$pickers    = file_get_contents( $root . '/src/Admin/Pickers.php' );
$page       = file_get_contents( $root . '/src/Admin/Page.php' );
$taxActions = file_get_contents( $root . '/src/Admin/TaxRateActions.php' );
$pricingUi  = file_get_contents( $root . '/src/Admin/ServicePricing.php' );
$arch       = file_get_contents( $root . '/docs/ARCHITECTURE.md' );
$schema     = file_get_contents( $root . '/src/Database/Schema.php' );
$postTypes  = file_get_contents( $root . '/src/Content/PostTypes.php' );
$projectMeta= file_get_contents( $root . '/src/Content/ProjectMeta.php' );
$workItemMeta = file_get_contents( $root . '/src/Content/WorkItemMeta.php' );
$projectRest = file_get_contents( $root . '/src/Content/ProjectRestController.php' );
$workItemRest = file_get_contents( $root . '/src/Content/WorkItemRestController.php' );
$workItems  = file_get_contents( $root . '/src/Repository/WorkItems.php' );
$projects   = file_get_contents( $root . '/src/Repository/Projects.php' );
$recurrence = file_get_contents( $root . '/src/Repository/RecurrenceRules.php' );
$recurrenceOccurrences = file_get_contents( $root . '/src/Repository/RecurrenceOccurrences.php' );
$recurrenceSchedule = file_get_contents( $root . '/src/Domain/RecurrenceSchedule.php' );
$scheduler = file_get_contents( $root . '/src/Recurrence/Scheduler.php' );
$timeRange = file_get_contents( $root . '/src/Domain/TimeRange.php' );
$timeEntries = file_get_contents( $root . '/src/Repository/TimeEntries.php' );
$timers = file_get_contents( $root . '/src/Repository/Timers.php' );
$timeAccess = file_get_contents( $root . '/src/Time/Access.php' );
$frontendAccess = file_get_contents( $root . '/src/Frontend/Access.php' );
$frontendService = file_get_contents( $root . '/src/Frontend/Data/Service.php' );
$frontendProject = file_get_contents( $root . '/src/Frontend/Data/Project.php' );
$frontendWorkItem = file_get_contents( $root . '/src/Frontend/Data/WorkItem.php' );
$frontendServiceQuery = file_get_contents( $root . '/src/Frontend/Queries/Services.php' );
$frontendProjectQuery = file_get_contents( $root . '/src/Frontend/Queries/Projects.php' );
$frontendWorkItemQuery = file_get_contents( $root . '/src/Frontend/Queries/WorkItems.php' );
$frontendConditions = file_get_contents( $root . '/src/Frontend/Conditions/Resources.php' );
$frontendActions = file_get_contents( $root . '/src/Frontend/Actions/WorkItems.php' );
$frontend = implode( "\n", [ $frontendAccess, $frontendService, $frontendProject, $frontendWorkItem, $frontendServiceQuery, $frontendProjectQuery, $frontendWorkItemQuery, $frontendConditions, $frontendActions ] );
$capabilities = file_get_contents( $root . '/src/Capabilities.php' );
$events = file_get_contents( $root . '/src/Governance/Events.php' );
$plugin = file_get_contents( $root . '/src/Plugin.php' );
$crm        = file_get_contents( $root . '/src/Integration/CRMCustomers.php' );
$public     = file_get_contents( $root . '/src/PublicApi/Services.php' )
	. file_get_contents( $root . '/src/PublicApi/TaxRates.php' )
	. file_get_contents( $root . '/src/PublicApi/Pricing.php' )
	. file_get_contents( $root . '/src/PublicApi/PricingProviders.php' )
	. file_get_contents( $root . '/src/PublicApi/Projects.php' )
	. file_get_contents( $root . '/src/PublicApi/WorkItems.php' )
	. file_get_contents( $root . '/src/PublicApi/WorkTypes.php' );
$resolver   = file_get_contents( $root . '/src/Pricing/Resolver.php' );

$allSource = '';
foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/src' ) ) as $file ) {
	if ( $file->isFile() && 'php' === strtolower( $file->getExtension() ) ) {
		$allSource .= file_get_contents( $file->getPathname() );
	}
}

$settingsPos = strpos( $page, 'private static function render_settings' );
$vatFormPos  = strpos( $page, 'name="action" value="cb_work_add_tax_rate"' );

$checks = [
	'launch candidate version is rc1' => 1 === preg_match( '/Version:\s+1\.0\.0-rc1/', $bootstrap ) && str_contains( $bootstrap, "CB_WORK_VERSION', '1.0.0-rc1'" ),
	'current Work schema version is 1.6' => str_contains( $bootstrap, "CB_WORK_SCHEMA_VERSION', '1.6'" ),
	'bootstrap registers Work schema before Base sweep' => str_contains( $bootstrap, "}, 4 );" ) && str_contains( $bootstrap, 'Database\\Schema::register();' ),
	'bootstrap waits for public Base boot signal' => str_contains( $bootstrap, "add_action( 'cb_core_booted'" ),
	'bootstrap does not pin an internal Base RC' => ! str_contains( $bootstrap, 'CB_WORK_REQUIRED_BASE' ),
	'suite registers through canonical extension hook' => str_contains( $suite, 'cb_core_register_extensions' ),
	'suite relies on Core API rather than requires_base' => ! str_contains( $suite, "'requires_base'" ),
	'suite exposes factual operational health' => str_contains( $suite, 'work items · %2$d projects · %3$d services · %4$d VAT rates' ),
	'suite Project shortcut uses native Project URL' => str_contains( $suite, 'Menu::projects_url()' ),
	'Work owns canonical Service Project and Work Item post types' => 1 === preg_match( "/const\s+SERVICE\s*=\s*'cb_work_service'/", $postTypes ) && 1 === preg_match( "/const\s+PROJECT\s*=\s*'cb_work_project'/", $postTypes ) && 1 === preg_match( "/const\s+WORK_ITEM\s*=\s*'cb_work_item'/", $postTypes ),
	'Project and Work Item CPTs are private by default' => preg_match_all( "/'publicly_queryable'\s*=>\s*false/", $postTypes ) >= 2,
	'Project metadata is registered Work post meta' => str_contains( $projectMeta, 'register_post_meta( PostTypes::PROJECT' ),
	'Work Item metadata is registered Work post meta' => str_contains( $workItemMeta, 'register_post_meta( PostTypes::WORK_ITEM' ) && str_contains( $workItemMeta, '_cb_work_item_status' ),
	'Work Item estimate remains planning metadata separate from Time actuals' => str_contains( $workItemMeta, '_cb_work_item_estimated_minutes' ) && str_contains( $workItemsAdmin, 'Planning estimate only. Registered time remains separate.' ),
	'Project Gutenberg REST route remains capability-gated' => str_contains( $projectRest, 'current_user_can( Capabilities::MANAGE )' ),
	'Work Item Gutenberg REST route remains capability-gated' => str_contains( $workItemRest, 'current_user_can( Capabilities::MANAGE )' ),
	'D1.2 owns CPT Work Items not relational Work Item table' => ! str_contains( $schema, 'work_items_table' ) && str_contains( $schema, "cb_work_items'" ) && str_contains( $schema, 'DROP TABLE IF EXISTS' ),
	'D1.2 destructively removes transitional Project and Work Item tables' => str_contains( $schema, 'cb_work_projects' ) && str_contains( $schema, 'cb_work_items' ) && substr_count( $schema, 'DROP TABLE IF EXISTS' ) >= 2,
	'D1.2 retains relational assignments and generic external relations' => str_contains( $schema, "'cb_work_item_assignments'" ) && str_contains( $schema, "'cb_work_item_relations'" ),
	'D1.2 has no cross-plugin SQL foreign keys' => ! str_contains( strtoupper( $schema ), 'FOREIGN KEY' ),
	'Project repository uses WordPress-native storage only' => str_contains( $projects, 'WP_Query' ) && str_contains( $projects, 'get_post(' ) && ! str_contains( $projects, 'Schema::' ) && ! str_contains( $projects, '$wpdb' ),
	'Work Item repository uses WordPress-native root storage' => str_contains( $workItems, 'WP_Query' ) && str_contains( $workItems, 'wp_insert_post(' ) && str_contains( $workItems, 'wp_update_post(' ) && ! str_contains( $workItems, 'Schema::work_items_table' ),
	'Work Item validates optional Project against Project CPT repository' => str_contains( $workItems, 'Projects::get( $project_id )' ),
	'Work Item keeps completion and billing separate' => str_contains( $workItemMeta, 'COMPLETED_AT' ) && str_contains( $workItemMeta, 'BILLING_DISPOSITION' ),
	'Work Item emits create update and lifecycle hooks' => str_contains( $workItems, 'cb_work_work_item_created' ) && str_contains( $workItems, 'cb_work_work_item_updated' ) && str_contains( $workItems, 'cb_work_work_item_status_changed' ),
	'recurrence rule assignments and executions are Work-owned relational facts' => str_contains( $schema, "'cb_work_recurrence_rules'" ) && str_contains( $schema, "'cb_work_recurrence_rule_assignments'" ) && str_contains( $schema, "'cb_work_recurrence_occurrences'" ),
	'recurrence occurrence identity is unique by rule and date' => str_contains( $schema, 'UNIQUE KEY rule_occurrence (rule_id,occurrence_on)' ),
	'finite recurrence rules use nullable next occurrence instead of sentinel dates' => str_contains( $schema, 'next_occurrence_on date NULL' ) && ! str_contains( $schema, 'next_occurrence_on date NOT NULL' ),
	'recurrence scheduler state supports stale claim recovery' => str_contains( $schema, 'claim_token varchar(64)' ) && str_contains( $schema, 'claimed_at datetime NULL' ) && str_contains( $schema, 'attempt_count int unsigned' ) && str_contains( $schema, 'last_error varchar(190)' ),
	'recurrence schedule is pure and calendar anchored' => str_contains( $recurrenceSchedule, "self::MONTHLY => self::next_monthly" ) && str_contains( $recurrenceSchedule, 'anchored_date' ) && ! str_contains( $recurrenceSchedule, 'wp_schedule_' ),
	'recurrence rules validate canonical Work references and assignments' => str_contains( $recurrence, 'Projects::get( $project_id )' ) && str_contains( $recurrence, 'Services::get( $service_id )' ) && str_contains( $recurrence, 'WorkTypes::get( $work_type_id )' ) && str_contains( $recurrence, 'get_userdata( $user_id )' ),
	'recurrence create-ahead is evaluated per rule' => str_contains( $recurrence, 'DATE_ADD(%s, INTERVAL create_ahead_days DAY)' ),
	'recurrence occurrence projects into canonical Work Item input' => str_contains( $recurrence, 'occurrence_work_item_input' ) && str_contains( $recurrence, "'source_type'         => 'recurrence_occurrence'" ) && str_contains( $recurrence, "'assigned_user_ids'   => \$rule['assigned_user_ids']" ),
	'recurrence only attaches a Work Item carrying its occurrence relation' => str_contains( $recurrence, "'recurrence_occurrence' ===" ) && str_contains( $recurrence, "(string) \$occurrence_id ===" ),
	'recurrence advances only after generated Work Item linkage' => str_contains( $recurrence, 'work_item_id > 0 LIMIT 1' ) && str_contains( $recurrence, 'next_occurrence_on' ),
	'recurrence foundation rule repository stays scheduler and UI free' => ! str_contains( $recurrence, 'wp_schedule_event' ) && ! str_contains( $recurrence, 'wp_schedule_single_event' ) && ! str_contains( $recurrence, 'add_action(' ),
	'occurrence execution repository claims atomically and can recover source-linked Work Items' => str_contains( $recurrenceOccurrences, 'attempt_count = attempt_count + 1' ) && str_contains( $recurrenceOccurrences, 'claimed_at < %s' ) && str_contains( $recurrenceOccurrences, 'find_work_item' ) && str_contains( $recurrenceOccurrences, "SOURCE_TYPE     = 'recurrence_occurrence'" ),
	'recurrence scheduler is hourly bounded and creates only through canonical WorkItems repository' => str_contains( $scheduler, "wp_schedule_event( time() + MINUTE_IN_SECONDS, 'hourly'" ) && str_contains( $scheduler, 'MAX_OCCURRENCES_PER_RULE' ) && str_contains( $scheduler, 'RecurrenceSchedule::add_days' ) && str_contains( $scheduler, 'WorkItems::create( $input )' ),
	'recurrence scheduler performs relation-first recovery before creating a Work Item' => strpos( $scheduler, 'RecurrenceOccurrences::find_work_item' ) < strpos( $scheduler, 'WorkItems::create( $input )' ),
	'recurrence admin actions are capability nonce and CRM-contract gated' => str_contains( $recurrenceActions, 'current_user_can( Capabilities::MANAGE )' ) && str_contains( $recurrenceActions, 'check_admin_referer' ) && str_contains( $recurrenceActions, 'CRMCustomers::reference' ),
	'recurrence admin exposes no destructive delete flow' => ! str_contains( $recurrenceActions, 'delete' ) && ! str_contains( $recurrenceAdmin, 'Delete' ) && ! str_contains( $recurrenceAdmin, 'delete' ),
	'E3 Time storage is Work-owned relational high-volume data' => str_contains( $schema, "'cb_work_time_entries'" ) && str_contains( $schema, "'cb_work_active_timers'" ) && str_contains( $schema, 'PRIMARY KEY  (user_id)' ),
	'E3 uses a distinct track-time capability and assignment-bounded tracker access' => str_contains( $capabilities, "TRACK_TIME = 'cb_track_work_time'" ) && str_contains( $timeAccess, 'get_current_user_id() !== $user_id' ) && str_contains( $timeAccess, "assigned_user_ids" ),
	'E3 local manual input canonicalizes to UTC in one pure domain seam' => str_contains( $timeRange, 'local_to_utc' ) && str_contains( $timeRange, "new \\DateTimeZone( 'UTC' )" ) && str_contains( $timeRange, 'duration_seconds' ),
	'E3 completed entry correction is revision-CAS protected' => str_contains( $timeEntries, 'revision = revision + 1' ) && str_contains( $timeEntries, 'WHERE id = %d AND revision = %d AND ended_at IS NOT NULL' ),
	'E3 timer start stop uses transactions row locks and server UTC' => str_contains( $timers, "'START TRANSACTION'" ) && substr_count( $timers, 'FOR UPDATE' ) >= 2 && str_contains( $timers, "current_time( 'mysql', true )" ),
	'E3 Time actions are nonce and authorization gated' => str_contains( $timeActions, 'check_admin_referer' ) && str_contains( $timeActions, 'Access::can_track_work_item' ) && str_contains( $timeActions, 'Access::can_edit_entry' ) && str_contains( $timeActions, 'Access::can_stop_user_timer' ),
	'E3 Time governance events do not audit notes' => str_contains( $events, 'work.time.entry.created' ) && str_contains( $events, 'work.time.timer.started' ) && ! preg_match( "/Audit::record\([^;]*['\"]note['\"]\s*=>/s", $timeActions ),
	'E3 Time admin reuses Base TimePicker and single-user Object Picker' => str_contains( $timeAdmin, 'Assets::enqueue_time_picker()' ) && str_contains( $timeAdmin, 'data-cb-time-picker' ) && str_contains( $timeAdmin, "Pickers::assignee( 'time[user_id]'" ),
	'E3 Time is wired only into authenticated WordPress Admin' => str_contains( $plugin, 'Time::init();' ) && str_contains( $plugin, 'TimeActions::init();' ),
	'E3 imports no old workspace Calendar or timesheet domain' => ! str_contains( $timeEntries . $timers, 'workspace_id' ) && ! str_contains( strtolower( $timeEntries . $timers ), 'calendar' ) && ! str_contains( strtolower( $timeEntries . $timers ), 'timesheet' ),
	'D3 frontend reads are default-deny outside manager preview' => str_contains( $frontendAccess, "'cb_work_frontend_can_read'" ) && str_contains( $frontendAccess, 'current_user_can( Capabilities::MANAGE )' ) && str_contains( $frontendAccess, "'publish' !== \$post->post_status" ),
	'D3 frontend mutation has a distinct default-deny authorization filter' => str_contains( $frontendAccess, "'cb_work_frontend_can_transition_work_item'" ) && str_contains( $frontendAccess, '$actor_user_id <= 0' ),
	'D3 frontend projections omit sensitive Work internals' => str_contains( $frontendWorkItem, "'estimated_minutes'" ) && ! str_contains( $frontendWorkItem, 'billing_disposition' ) && ! str_contains( $frontendWorkItem, 'customer_provider' ) && ! str_contains( $frontendWorkItem, 'assigned_user_ids' ) && ! str_contains( $frontendService, "'pricing'" ) && ! str_contains( $frontendProject, 'customer_' ),
	'D3 frontend queries are bounded and always reproject through authorization-aware Data contracts' => str_contains( $frontendServiceQuery, 'MAX_RESULTS    = 100' ) && str_contains( $frontendProjectQuery, 'MAX_RESULTS    = 100' ) && str_contains( $frontendWorkItemQuery, 'MAX_RESULTS    = 100' ) && str_contains( $frontendServiceQuery, 'Service::get( $id )' ) && str_contains( $frontendProjectQuery, 'Project::get( $id )' ) && str_contains( $frontendWorkItemQuery, 'WorkItem::get( $id )' ),
	'D3 Work Item query reuses canonical D2 search and omits sensitive filter dimensions' => str_contains( $frontendWorkItemQuery, 'WorkItemRepository::search( $criteria )' ) && ! str_contains( $frontendWorkItemQuery, "'customer'" ) && ! str_contains( $frontendWorkItemQuery, "'billing_dispositions'" ) && ! str_contains( $frontendWorkItemQuery, "'assignee_id'" ),
	'D3 conditions are pure and D3 action delegates lifecycle plus governance' => str_contains( $frontendConditions, 'work_item_can_transition_to' ) && ! str_contains( $frontendConditions, 'update_' ) && str_contains( $frontendActions, 'WorkItemRepository::transition_status' ) && str_contains( $frontendActions, 'Audit::record( Events::WORK_ITEM_STATUS_CHANGED' ),
	'D3 action is transport-neutral and D4 remains the adapter owner' => ! str_contains( $frontendActions, 'add_action(' ) && ! str_contains( $frontendActions, 'admin_post_' ) && ! str_contains( $frontendActions, 'wp_ajax_' ) && ! str_contains( $frontendActions, 'register_rest_route' ) && ! str_contains( strtolower( $frontend ), 'bricks' ),
	'D3 exposes no recurrence or Time resource surface' => ! str_contains( $frontend, 'RecurrenceRules' ) && ! str_contains( $frontend, 'TimeEntries' ) && ! str_contains( $frontend, 'Timers::' ),
	'public sibling contracts include Projects Work Items and Work Types' => str_contains( $public, 'Supported read-only Project contract' ) && str_contains( $public, 'Supported read-only Work Item contract' ) && str_contains( $public, 'Supported read-only Work Type contract' ),
	'pricing provider seam is Work-owned and lazy' => str_contains( $public, 'cb_work_register_pricing_providers' ) && str_contains( $public, 'PricingProviders' ),
	'pricing resolver owns explicit-agreement-default precedence' => str_contains( $resolver, "'explicit_override'" ) && str_contains( $resolver, "'customer_agreement'" ) && str_contains( $resolver, "'service_default'" ),
	'pricing resolver validates tax through Work public API' => str_contains( $resolver, 'TaxRates::is_available' ),
	'Work owns a standalone operational top-level menu' => str_contains( $menu, 'add_menu_page(' ) && str_contains( $menu, "TOP_LEVEL_SLUG      = 'core-blueprint-work'" ),
	'operational menu mounts Work Items Recurring Work Time native Projects native Services and Work Types' => str_contains( $menu, 'WORK_ITEMS_SLUG' ) && str_contains( $menu, 'RECURRENCE_SLUG' ) && str_contains( $menu, 'TIME_SLUG' ) && str_contains( $menu, 'PostTypes::PROJECT' ) && str_contains( $menu, 'PostTypes::SERVICE' ) && str_contains( $menu, 'WORK_TYPES_SLUG' ),
	'native Work Item editor stays visually under Work navigation' => str_contains( $menu, 'PostTypes::WORK_ITEM === (string) $screen->post_type' ) && str_contains( $menu, 'CONTEXT_WORK_ITEMS' ),
	'Project admin has contextual Work Item management' => str_contains( $projectsAdmin, 'WorkItems::for_project' ) && str_contains( $projectsAdmin, 'Menu::new_work_item_url( $project_id )' ) && str_contains( $projectsAdmin, 'Menu::edit_work_item_url' ),
	'Work Items use global workspace plus native Gutenberg editor and governed transitions' => str_contains( $operations, 'Add Work Item' ) && str_contains( $operations, 'Menu::edit_work_item_url' ) && str_contains( $workItemsAdmin, 'Work Item Details' ) && str_contains( $operationalActions, 'transition_status' ),
	'custom CRUD create update handlers are removed' => ! str_contains( $operationalActions, 'cb_work_create_work_item' ) && ! str_contains( $operationalActions, 'cb_work_update_work_item' ) && ! str_contains( $operations, 'render_work_item_form' ),
	'raw customer/source provider type id controls are absent from primary Work UI' => ! str_contains( $workItemsAdmin, 'source_provider' ) && ! str_contains( $workItemsAdmin, 'source_type' ) && ! str_contains( $workItemsAdmin, 'source_id' ),
	'CRM customer picker uses documented public Frontend Queries only' => str_contains( $crm, '\\CB\\CRM\\Frontend\\Queries\\Contacts' ) && str_contains( $crm, '\\CB\\CRM\\Frontend\\Queries\\Organizations' ) && ! str_contains( $crm, 'CB\\CRM\\Repository' ) && ! str_contains( $crm, '$wpdb' ),
	'CRM customer integration remains fail-soft' => str_contains( $crm, 'class_exists' ) && str_contains( $crm, 'public static function available' ),
	'Base Object Picker provides customer single-user and multi-user UX including recurrence and Time' => str_contains( $pickers, 'CB\\Core\\UI\\ObjectPicker' ) && str_contains( $pickers, 'Assets::enqueue_object_picker' ) && str_contains( $pickers, 'public static function assignee(' ) && str_contains( $pickers, 'public static function assignees(' ) && str_contains( $pickers, 'private static function render_user_picker(' ) && str_contains( $pickers, 'cb_work_search_users' ) && str_contains( $pickers, 'Menu::RECURRENCE_SLUG' ) && str_contains( $pickers, 'Menu::TIME_SLUG' ),
	'Core Blueprint Work page is settings-only' => str_contains( $page, "SLUG = 'core-blueprint-work-settings'" ) && ! str_contains( $page, 'render_services' ) && ! str_contains( $page, 'VIEW_SERVICES' ),
	'Core Blueprint settings page does not request operational nav tabs' => ! str_contains( $page, "'nav-tabs'" ) && ! str_contains( $page, 'nav-tab-wrapper' ),
	'VAT form is isolated in Settings render route' => false !== $settingsPos && false !== $vatFormPos && $vatFormPos > $settingsPos,
	'VAT configured heading uses panel-safe h2 rather than raw h3' => ! str_contains( $page, '<h3' ) && str_contains( $page, 'Configured VAT rates' ),
	'VAT actions return to Work Settings without legacy view routing' => str_contains( $taxActions, "'page'           => Page::SLUG" ) && ! str_contains( $taxActions, "'view'" ),
	'Service editor links to Work Settings for VAT' => str_contains( $pricingUi, 'Page::settings_url()' ),
	'admin settings page avoids unsupported Base tables requirement' => ! str_contains( $page, "'tables'" ),
	'architecture documents Project and Work Item CPT model' => str_contains( $arch, '`cb_work_project`' ) && str_contains( $arch, '`cb_work_item`' ) && str_contains( $arch, 'no legacy bridges' ),
	'architecture keeps high-volume child facts relational' => str_contains( $arch, 'high-volume child facts' ) && str_contains( $arch, 'time entries' ),
	'architecture separates commercial recurrence from work recurrence' => str_contains( $arch, 'recurring commercial pricing is not Work Item recurrence' ),
	'architecture documents recurrence rule and occurrence ownership' => str_contains( $arch, '`cb_work_recurrence_rules`' ) && str_contains( $arch, '`cb_work_recurrence_occurrences`' ) && str_contains( $arch, 'create-ahead' ),
	'architecture forbids direct sibling SQL' => str_contains( $arch, 'No cross-plugin SQL foreign keys' ),
	'architecture keeps D3 builder-neutral and D4 Bricks thin' => str_contains( $arch, '**D3 — Builder-neutral Frontend Resource Contracts:**' ) && str_contains( $arch, '**D4 — Bricks Adapter:**' ),
	'no CRM private repository dependency in Work source' => ! str_contains( $allSource, 'CB\\CRM\\Repository' ) && ! str_contains( $allSource, 'cb_crm_' ),
	'no Helpdesk private namespace dependency in Work source' => ! str_contains( $allSource, 'CB\\Helpdesk\\' ),
	'no jQuery dependency' => ! str_contains( strtolower( file_get_contents( $root . '/assets/service-pricing.js' ) ), 'jquery' ),
];

foreach ( $checks as $label => $passed ) {
	if ( ! $passed ) {
		fwrite( STDERR, "Conformance failed: {$label}\n" );
		$failed = true;
	}
}

exit( $failed ? 1 : 0 );
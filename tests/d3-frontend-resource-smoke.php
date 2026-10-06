<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$bootstrap = file_get_contents( $root . '/core-blueprint-work.php' );
$access = file_get_contents( $root . '/src/Frontend/Access.php' );
$service = file_get_contents( $root . '/src/Frontend/Data/Service.php' );
$project = file_get_contents( $root . '/src/Frontend/Data/Project.php' );
$workItem = file_get_contents( $root . '/src/Frontend/Data/WorkItem.php' );
$serviceQuery = file_get_contents( $root . '/src/Frontend/Queries/Services.php' );
$projectQuery = file_get_contents( $root . '/src/Frontend/Queries/Projects.php' );
$workItemQuery = file_get_contents( $root . '/src/Frontend/Queries/WorkItems.php' );
$conditions = file_get_contents( $root . '/src/Frontend/Conditions/Resources.php' );
$actions = file_get_contents( $root . '/src/Frontend/Actions/WorkItems.php' );
$all = implode( "\n", [ $access, $service, $project, $workItem, $serviceQuery, $projectQuery, $workItemQuery, $conditions, $actions ] );

$checks = [
	'D3 contracts preserve current release and schema identity' => str_contains( $bootstrap, "CB_WORK_VERSION', '1.0.0-rc1'" ) && str_contains( $bootstrap, "CB_WORK_SCHEMA_VERSION', '2.0'" ),
	'frontend reads are default deny for non-managers' => str_contains( $access, "'cb_work_frontend_can_read'" ) && str_contains( $access, "false," ) && str_contains( $access, "'publish' !== \$post->post_status" ) && str_contains( $access, 'post_password_required( $post )' ),
	'Work managers retain authenticated frontend preview access' => str_contains( $access, 'current_user_can( Capabilities::MANAGE )' ),
	'frontend mutation requires a separate explicit opt-in and logged-in actor' => str_contains( $access, "'cb_work_frontend_can_transition_work_item'" ) && str_contains( $access, '$actor_user_id <= 0' ),
	'Data projections exist for Services Projects and Work Items' => str_contains( $service, 'final class Service' ) && str_contains( $project, 'final class Project' ) && str_contains( $workItem, 'final class WorkItem' ),
	'Work Item frontend data includes planning estimate but not billing/customer/source internals' => str_contains( $workItem, "'estimated_minutes'" ) && ! str_contains( $workItem, "'billing_disposition'" ) && ! str_contains( $workItem, "'customer_provider'" ) && ! str_contains( $workItem, "'customer_type'" ) && ! str_contains( $workItem, "'customer_id'" ) && ! str_contains( $workItem, "'assigned_user_ids'" ) && ! str_contains( $workItem, "'source_provider'" ) && ! str_contains( $workItem, "'source_type'" ) && ! str_contains( $workItem, "'source_id'" ),
	'Service frontend data does not expose pricing' => ! str_contains( $service, "'pricing'" ),
	'Project frontend data does not expose CRM customer references' => ! str_contains( $project, 'customer_' ),
	'frontend queries are bounded' => str_contains( $serviceQuery, 'MAX_CANDIDATES = 250' ) && str_contains( $serviceQuery, 'MAX_RESULTS    = 100' ) && str_contains( $projectQuery, 'MAX_CANDIDATES = 250' ) && str_contains( $workItemQuery, 'MAX_CANDIDATES = 500' ) && str_contains( $workItemQuery, 'MAX_RESULTS    = 100' ),
	'every frontend query reprojects candidates through authorized Data contracts' => str_contains( $serviceQuery, 'Service::get( $id )' ) && str_contains( $projectQuery, 'Project::get( $id )' ) && str_contains( $workItemQuery, 'WorkItem::get( $id )' ),
	'Work Item frontend query reuses canonical D2 search instead of duplicating persistence queries' => str_contains( $workItemQuery, 'WorkItemRepository::search( $criteria )' ) && ! str_contains( $workItemQuery, 'new \\WP_Query' ) && ! str_contains( $workItemQuery, '$wpdb' ),
	'sensitive Work Item frontend filter dimensions are not accepted into canonical criteria' => ! str_contains( $workItemQuery, "'customer'" ) && ! str_contains( $workItemQuery, "'billing_dispositions'" ) && ! str_contains( $workItemQuery, "'assignee_id'" ),
	'conditions remain pure predicates over authorized resources' => str_contains( $conditions, 'work_item_status_is' ) && str_contains( $conditions, 'work_item_priority_is' ) && str_contains( $conditions, 'work_item_can_transition_to' ) && ! str_contains( $conditions, 'update_' ) && ! str_contains( $conditions, 'insert' ),
	'frontend action delegates to canonical Work Item lifecycle and governance' => str_contains( $actions, 'WorkItemRepository::transition_status' ) && str_contains( $actions, 'WorkItemStatus::can_transition' ) && str_contains( $actions, 'Audit::record( Events::WORK_ITEM_STATUS_CHANGED' ),
	'frontend action has no transport endpoint or CSRF bypass ownership' => ! str_contains( $actions, 'add_action(' ) && ! str_contains( $actions, 'admin_post_' ) && ! str_contains( $actions, 'wp_ajax_' ) && ! str_contains( $actions, 'register_rest_route' ),
	'D3 has no builder-specific dependency' => ! str_contains( strtolower( $all ), 'bricks' ) && ! str_contains( strtolower( $all ), 'elementor' ),
	'D3 does not expose recurrence or Time as frontend resources' => ! str_contains( $all, 'RecurrenceRules' ) && ! str_contains( $all, 'TimeEntries' ) && ! str_contains( $all, 'Timers::' ),
	'D3 frontend contracts do not read sibling private SQL or repositories' => ! str_contains( $all, 'CB\\CRM\\Repository' ) && ! str_contains( $all, 'CB\\Helpdesk\\' ) && ! str_contains( $all, 'cb_crm_' ),
];

foreach ( $checks as $label => $passed ) {
	if ( ! $passed ) {
		fwrite( STDERR, "D3 frontend resource smoke failed: {$label}\n" );
		exit( 1 );
	}
}

echo "D3 frontend resource smoke passed.\n";

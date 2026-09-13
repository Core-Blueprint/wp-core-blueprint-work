<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$bootstrap   = file_get_contents( $root . '/core-blueprint-work.php' );
$schema      = file_get_contents( $root . '/src/Database/Schema.php' );
$occurrences = file_get_contents( $root . '/src/Repository/RecurrenceOccurrences.php' );
$rules       = file_get_contents( $root . '/src/Repository/RecurrenceRules.php' );
$scheduler   = file_get_contents( $root . '/src/Recurrence/Scheduler.php' );
$admin       = file_get_contents( $root . '/src/Admin/Recurrence.php' );
$actions     = file_get_contents( $root . '/src/Admin/RecurrenceActions.php' );
$menu        = file_get_contents( $root . '/src/Admin/Menu.php' );
$pickers     = file_get_contents( $root . '/src/Admin/Pickers.php' );
$lifecycle   = file_get_contents( $root . '/src/Lifecycle.php' );
$events      = file_get_contents( $root . '/src/Governance/Events.php' );

$relationLookup = strpos( $scheduler, 'RecurrenceOccurrences::find_work_item' );
$createCall     = strpos( $scheduler, 'WorkItems::create( $input )' );

$checks = [
	'launch identity remains rc1 while later Work domains advance schema only' => str_contains( $bootstrap, "CB_WORK_VERSION', '1.0.0-rc1'" ) && str_contains( $bootstrap, "CB_WORK_SCHEMA_VERSION', '1.8'" ),
	'occurrence ledger retains unique rule date identity' => str_contains( $schema, 'UNIQUE KEY rule_occurrence (rule_id,occurrence_on)' ),
	'occurrence ledger stores bounded claim retry state' => str_contains( $schema, 'claim_token varchar(64)' ) && str_contains( $schema, 'claimed_at datetime NULL' ) && str_contains( $schema, 'attempt_count int unsigned NOT NULL DEFAULT 0' ) && str_contains( $schema, 'last_error varchar(190)' ),
	'claim is atomic and only targets ungenerated free or stale occurrences' => str_contains( $occurrences, 'attempt_count = attempt_count + 1' ) && str_contains( $occurrences, 'work_item_id = 0' ) && str_contains( $occurrences, "claim_token = '' OR claimed_at IS NULL OR claimed_at < %s" ),
	'claim takeover is time bounded' => str_contains( $occurrences, 'max( 60, min( DAY_IN_SECONDS' ) && str_contains( $occurrences, 'stale_before' ),
	'attach requires the active claim token and matching Work Item relation' => str_contains( $occurrences, 'AND claim_token = %s' ) && str_contains( $occurrences, 'SOURCE_PROVIDER' ) && str_contains( $occurrences, 'SOURCE_TYPE' ) && str_contains( $occurrences, 'relations' ),
	'relation lookup detects ambiguous duplicate Work Items fail closed' => str_contains( $occurrences, 'LIMIT 2' ) && str_contains( $occurrences, 'return -1' ),
	'scheduler recovers a source-linked Work Item before creating another' => false !== $relationLookup && false !== $createCall && $relationLookup < $createCall,
	'scheduler creates recurring children only through canonical WorkItems repository' => str_contains( $scheduler, 'WorkItems::create( $input )' ) && ! str_contains( $scheduler, 'wp_insert_post' ) && ! str_contains( $scheduler, 'update_post_meta' ),
	'scheduler catch-up honors each rule create-ahead horizon' => str_contains( $scheduler, 'RecurrenceSchedule::add_days( $today, (int) $rule[' . "'create_ahead_days'" . '] )' ) && str_contains( $scheduler, 'MAX_OCCURRENCES_PER_RULE = 250' ),
	'scheduler is hourly and self-heals one missing cron event' => str_contains( $scheduler, 'wp_next_scheduled( self::HOOK )' ) && str_contains( $scheduler, "wp_schedule_event( time() + MINUTE_IN_SECONDS, 'hourly', self::HOOK )" ),
	'deactivation removes only the recurrence cron hook' => str_contains( $lifecycle, 'Scheduler::deactivate()' ) && str_contains( $scheduler, 'wp_clear_scheduled_hook( self::HOOK )' ),
	'generated occurrence must advance through the E1 rule contract' => str_contains( $scheduler, 'RecurrenceRules::advance_after' ) && str_contains( $rules, 'work_item_id > 0 LIMIT 1' ),
	'concurrent already-advanced rule is observed as success before failure classification' => str_contains( $scheduler, 'private static function advance_or_observe' ) && str_contains( $scheduler, '$fresh_rule = RecurrenceRules::get( $rule_id );' ) && str_contains( $scheduler, "array_key_exists( 'next_occurrence_on', \$fresh_rule )" ) && str_contains( $scheduler, 'null === $fresh_next || (string) $fresh_next !== $occurrence_on' ),
	'generator failures and runs are governed' => str_contains( $scheduler, 'Events::RECURRENCE_GENERATION_FAILED' ) && str_contains( $scheduler, 'Events::RECURRENCE_GENERATOR_RUN' ) && str_contains( $events, 'work.recurrence.generation.failed' ) && str_contains( $events, 'work.recurrence.generator.run' ),
	'Recurring Work is an operational Work submenu' => str_contains( $menu, 'RECURRENCE_SLUG' ) && str_contains( $menu, "__( 'Recurring Work'" ) && str_contains( $menu, 'CONTEXT_RECURRENCE' ),
	'admin schedule identity locks after occurrence history exists' => str_contains( $admin, 'RecurrenceOccurrences::count_for_rule' ) && str_contains( $admin, 'Frequency, interval, start date and end date are locked' ),
	'admin exposes create edit toggle and run-now but no delete action' => str_contains( $actions, 'cb_work_create_recurrence_rule' ) && str_contains( $actions, 'cb_work_update_recurrence_rule' ) && str_contains( $actions, 'cb_work_toggle_recurrence_rule' ) && str_contains( $actions, 'cb_work_run_recurrence_generator' ) && ! str_contains( strtolower( $actions ), 'delete' ),
	'admin mutations are capability and nonce gated' => str_contains( $actions, 'current_user_can( Capabilities::MANAGE )' ) && str_contains( $actions, 'check_admin_referer( $nonce_action )' ),
	'customer selection reuses CRM public transport contract' => str_contains( $actions, 'CRMCustomers::reference( $identifier )' ) && ! str_contains( $actions, 'CB\\CRM\\Repository' ) && ! str_contains( $actions, 'cb_crm_' ),
	'Recurring Work reuses Base Object Picker for customer and assignees' => str_contains( $admin, 'Pickers::customer' ) && str_contains( $admin, 'Pickers::assignees' ) && str_contains( $pickers, 'Menu::RECURRENCE_SLUG' ),
	'E2 introduces no cross-plugin SQL foreign key or sibling private table coupling' => ! str_contains( strtoupper( $schema ), 'FOREIGN KEY' ) && ! str_contains( $occurrences, 'cb_crm_' ) && ! str_contains( $scheduler, 'cb_crm_' ),
];

foreach ( $checks as $label => $passed ) {
	if ( ! $passed ) {
		fwrite( STDERR, "Recurrence scheduler smoke failed: {$label}\n" );
		exit( 1 );
	}
}

echo "Recurrence scheduler smoke passed.\n";

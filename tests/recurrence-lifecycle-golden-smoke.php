<?php
declare(strict_types=1);

/** Recurring Work lifecycle source and governance Golden contract. */
$root = dirname( __DIR__ );
$read = static fn( string $p ): string => (string) file_get_contents( $root . '/' . $p );
$schema = $read( 'src/Database/Schema.php' );
$bootstrap = $read( 'core-blueprint-work.php' );
$repo = $read( 'src/Repository/RecurrenceRules.php' );
$admin = $read( 'src/Admin/Recurrence.php' );
$actions = $read( 'src/Admin/RecurrenceActions.php' );
$events = $read( 'src/Governance/Events.php' );
$scheduler = $read( 'src/Recurrence/Scheduler.php' );
$css = $read( 'assets/css/recurrence-workspace.css' );
$js = $read( 'assets/recurrence-workspace.js' );
$checks = [
	'Schema 2.4 preserves nullable Trash state with actor attribution' =>
		str_contains( $bootstrap, "CB_WORK_SCHEMA_VERSION', '2.4'" )
		&& str_contains( $schema, 'trashed_at datetime NULL' )
		&& str_contains( $schema, 'trashed_by bigint(20) unsigned NOT NULL DEFAULT 0' ),
	'Trash and restore use row locks and caller-aware transactions' =>
		str_contains( $repo, 'public static function trash(' )
		&& str_contains( $repo, 'public static function restore(' )
		&& substr_count( $repo, 'FOR UPDATE' ) >= 4
		&& substr_count( $repo, 'WorkSqlTransaction::run(' ) >= 5,
	'Trashed rules are omitted from normal lists and generator queries' =>
		str_contains( $repo, "' WHERE trashed_at IS NULL'" )
		&& str_contains( $repo, "'trashed_at IS NOT NULL'" )
		&& str_contains( $repo, 'WHERE is_active = 1 AND trashed_at IS NULL' )
		&& str_contains( $repo, '! empty( $rule[\'is_trashed\'] )' ),
	'Restore never reactivates generation' =>
		str_contains( $repo, 'public static function restore(' )
		&& str_contains( $repo, "'is_active' => 0" ),
	'Permanent delete protects occurrence ledger and never deletes Work Items' =>
		str_contains( $repo, 'public static function delete_permanently(' )
		&& str_contains( $repo, 'empty( $rule[\'is_trashed\'] ) || self::has_occurrences( $id )' )
		&& str_contains( $repo, '$wpdb->delete( Schema::recurrence_assignments_table()' )
		&& ! str_contains( substr( $repo, strpos( $repo, 'public static function delete_permanently(' ), 1600 ), 'wp_delete_post(' ),
	'Ledger history lookup fails closed on SQL errors' => str_contains( $repo, "return '' !== (string) \$wpdb->last_error || (bool) \$exists;" )
		&& str_contains( $repo, 'private static function has_occurrences(' ),
	'Generator rejects Trash before and after claiming occurrences' =>
		str_contains( $scheduler, '! empty( $rule[\'is_trashed\'] )' )
		&& str_contains( $scheduler, '$current_rule = RecurrenceRules::get( $rule_id );' )
		&& str_contains( $scheduler, 'RecurrenceOccurrences::release( $occurrence_id, $token )' ),
	'Admin POST actions enforce capability nonce and explicit permanent confirmation' =>
		str_contains( $actions, 'admin_post_cb_work_trash_recurrence_rule' )
		&& str_contains( $actions, 'admin_post_cb_work_restore_recurrence_rule' )
		&& str_contains( $actions, 'admin_post_cb_work_delete_recurrence_rule' )
		&& str_contains( $actions, 'self::guard( \'cb_work_trash_recurrence_rule_\' . $rule_id )' )
		&& str_contains( $actions, 'self::guard( \'cb_work_restore_recurrence_rule_\' . $rule_id )' )
		&& str_contains( $actions, 'self::guard( \'cb_work_delete_recurrence_rule_\' . $rule_id )' )
		&& str_contains( $actions, 'isset( $_POST[\'confirm_permanent\'] )' ),
	'Admin has Trash view, Restore and confirmed Delete including no-JS checkbox' =>
		str_contains( $admin, "'cb_view' => 'trash'" )
		&& str_contains( $admin, 'cb_work_trash_recurrence_rule' )
		&& str_contains( $admin, 'cb_work_restore_recurrence_rule' )
		&& str_contains( $admin, 'cb_work_delete_recurrence_rule' )
		&& str_contains( $admin, 'name="confirm_permanent" value="1" required' )
		&& str_contains( $admin, 'RecurrenceOccurrences::count_for_rule( $edit_id ) > 0' ),
	'Lifecycle audit event types are registered and recorded after transaction success' =>
		str_contains( $events, 'RECURRENCE_RULE_TRASHED' )
		&& str_contains( $events, 'RECURRENCE_RULE_RESTORED' )
		&& str_contains( $events, 'RECURRENCE_RULE_DELETED' )
		&& str_contains( $actions, 'Events::RECURRENCE_RULE_DELETED' ),
	'Generator diagnostics are separated using Base workspace design tokens' =>
		str_contains( $css, 'margin: var(--cb-space-6, 24px)' )
		&& str_contains( $css, 'var(--cb-border' )
		&& str_contains( $css, 'var(--cb-surface-1' )
		&& str_contains( $admin, 'class="cb-work-recurrence-generator-form"' )
		&& ! str_contains( $admin, 'style="margin:12px 0;"' ),
	'Trash confirmation is accessible, localized and no hidden JS requirement' =>
		str_contains( $js, '[data-cb-recurrence-trash]' )
		&& str_contains( $admin, 'trashConfirm' )
		&& str_contains( $admin, '<label class="cb-work-recurrence-delete-confirm">' ),
];
foreach ($checks as $label => $ok) {
	if (! $ok) {
		fwrite( STDERR, "Recurrence Lifecycle Golden FAILED: {$label}\n" );
		exit(1);
	}
}
echo "Recurrence Lifecycle Golden source smoke: PASS\n";

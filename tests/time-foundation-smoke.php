<?php
declare(strict_types=1);

define( 'ABSPATH', '/tmp/wp/' );
function wp_timezone(): DateTimeZone { return new DateTimeZone( 'Europe/Amsterdam' ); }

$root = dirname( __DIR__ );
require $root . '/src/Domain/TimeRange.php';

use CB\Work\Domain\TimeRange;

$bootstrap   = file_get_contents( $root . '/core-blueprint-work.php' );
$schema      = file_get_contents( $root . '/src/Database/Schema.php' );
$caps        = file_get_contents( $root . '/src/Capabilities.php' );
$access      = file_get_contents( $root . '/src/Time/Access.php' );
$entries     = file_get_contents( $root . '/src/Repository/TimeEntries.php' );
$timers      = file_get_contents( $root . '/src/Repository/Timers.php' );
$actions     = file_get_contents( $root . '/src/Admin/TimeActions.php' );
$admin       = file_get_contents( $root . '/src/Admin/Time.php' );
$events      = file_get_contents( $root . '/src/Governance/Events.php' );
$plugin      = file_get_contents( $root . '/src/Plugin.php' );
$menu        = file_get_contents( $root . '/src/Admin/Menu.php' );
$workMeta    = file_get_contents( $root . '/src/Content/WorkItemMeta.php' );
$recurrence  = file_get_contents( $root . '/src/Repository/RecurrenceRules.php' );

$started = TimeRange::local_to_utc( '2026-01-15', '10:00' );
$ended   = TimeRange::local_to_utc( '2026-01-15', '11:30' );
$parts   = null === $started ? null : TimeRange::utc_to_local_parts( $started );

$checks = [
	'plugin stays rc1 while E3 advances only Work schema to 1.6' => str_contains( $bootstrap, "CB_WORK_VERSION', '1.0.0-rc1'" ) && str_contains( $bootstrap, "CB_WORK_SCHEMA_VERSION', '1.6'" ),
	'Work owns exactly named Time Entry and Active Timer tables' => str_contains( $schema, "'cb_work_time_entries'" ) && str_contains( $schema, "'cb_work_active_timers'" ),
	'Time storage has no SQL foreign keys' => ! str_contains( strtoupper( $schema ), 'FOREIGN KEY' ),
	'one active timer per WordPress user is a database boundary' => str_contains( $schema, 'PRIMARY KEY  (user_id)' ) && str_contains( $schema, 'UNIQUE KEY time_entry_id (time_entry_id)' ),
	'Time Entries own UTC actual timestamps duration and revision' => str_contains( $schema, 'started_at datetime NOT NULL' ) && str_contains( $schema, 'ended_at datetime NULL' ) && str_contains( $schema, 'duration_seconds int unsigned' ) && str_contains( $schema, 'revision int unsigned NOT NULL DEFAULT 1' ),
	'Work Item estimate is separate planning metadata' => str_contains( $workMeta, '_cb_work_item_estimated_minutes' ) && str_contains( $workMeta, 'estimated_minutes' ),
	'Recurring Work inherits estimate into future canonical Work Items' => str_contains( $schema, 'estimated_minutes int unsigned NOT NULL DEFAULT 0' ) && str_contains( $recurrence, "'estimated_minutes'   => \$rule['estimated_minutes']" ),
	'Time capability is separate from Work management' => str_contains( $caps, "TRACK_TIME = 'cb_track_work_time'" ) && str_contains( $caps, 'self::TRACK_TIME' ) && str_contains( $schema, "version_compare( \$previous_version, '1.6', '<' )" ),
	'tracker access is own-user and Work Item assignment bounded' => str_contains( $access, 'get_current_user_id() !== $user_id' ) && str_contains( $access, "in_array( \$user_id, (array) ( \$item['assigned_user_ids'] ?? [] ), true )" ),
	'manager access remains a strict superset for Time management' => str_contains( $access, 'if ( self::can_manage() )' ),
	'a tracker can stop their own already-running timer after assignment removal' => str_contains( $access, 'can_stop_user_timer' ) && str_contains( $access, 'get_current_user_id() === $user_id' ),
	'local manual times canonicalize to UTC and back' => '2026-01-15 09:00:00' === $started && '2026-01-15 10:30:00' === $ended && [ 'date' => '2026-01-15', 'time' => '10:00' ] === $parts,
	'duration is server-domain arithmetic' => null !== $started && null !== $ended && 5400 === TimeRange::duration_seconds( $started, $ended ) && 0 === TimeRange::duration_seconds( $started, $started ),
	'manual entries require positive duration and validate canonical Work context' => str_contains( $entries, '$duration <= 0' ) && str_contains( $entries, 'WorkItems::get( $work_item_id )' ) && str_contains( $entries, 'get_userdata( $user_id )' ),
	'manual and corrected entries reject future actual end timestamps at the repository boundary' => substr_count( $entries, '! self::is_actual_end( $ended_at )' ) >= 2 && str_contains( $entries, "\$ended_at <= current_time( 'mysql', true )" ),
	'completed Time corrections use compare-and-swap revision protection' => str_contains( $entries, 'revision = revision + 1' ) && str_contains( $entries, 'WHERE id = %d AND revision = %d AND ended_at IS NOT NULL' ),
	'timer start creates entry and active row in one transaction' => str_contains( $timers, "'START TRANSACTION'" ) && str_contains( $timers, "'ROLLBACK'" ) && str_contains( $timers, "'COMMIT'" ) && str_contains( $timers, 'Schema::active_timers_table()' ),
	'timer stop locks authoritative rows and computes duration server-side' => substr_count( $timers, 'FOR UPDATE' ) >= 2 && str_contains( $timers, "current_time( 'mysql', true )" ) && str_contains( $timers, 'TimeRange::duration_seconds' ),
	'Time actions use nonce and explicit authorization boundaries' => str_contains( $actions, 'check_admin_referer' ) && str_contains( $actions, 'Access::can_track_work_item' ) && str_contains( $actions, 'Access::can_edit_entry' ) && str_contains( $actions, 'Access::can_stop_user_timer' ),
	'tracker-submitted target user cannot override own identity' => str_contains( $actions, "? absint( \$input['user_id'] ?? \$actor )" ) && str_contains( $actions, ': $actor;' ),
	'Time mutations are governance-audited without note payloads' => str_contains( $events, 'work.time.entry.created' ) && str_contains( $events, 'work.time.entry.updated' ) && str_contains( $events, 'work.time.timer.started' ) && str_contains( $events, 'work.time.timer.stopped' ) && ! preg_match( "/Audit::record\([^;]*['\"]note['\"]\s*=>/s", $actions ),
	'Work Time UI reuses Base TimePicker and native forms' => str_contains( $admin, 'Assets::enqueue_time_picker()' ) && str_contains( $admin, 'data-cb-time-picker' ) && str_contains( $admin, "admin_url( 'admin-post.php' )" ),
	'manager user selection reuses Base single-user Object Picker' => str_contains( $admin, "Pickers::assignee( 'time[user_id]'" ),
	'Work menu gives tracker-only users a Time landing without management submenu access' => str_contains( $menu, 'Capabilities::TRACK_TIME' ) && str_contains( $menu, '[ Time::class, \'render\' ]' ) && str_contains( $menu, 'if ( $can_manage )' ),
	'plugin wires Time only in WordPress Admin' => str_contains( $plugin, 'Time::init();' ) && str_contains( $plugin, 'TimeActions::init();' ),
	'E3 introduces no destructive Time delete flow' => ! str_contains( strtolower( $actions ), 'delete' ) && ! str_contains( strtolower( $admin ), 'delete' ),
	'E3 does not import old workspace Calendar rate or timesheet domains' => ! str_contains( $entries . $timers, 'workspace_id' ) && ! str_contains( strtolower( $entries . $timers ), 'calendar' ) && ! str_contains( strtolower( $entries . $timers ), 'timesheet' ),
];

foreach ( $checks as $label => $passed ) {
	if ( ! $passed ) {
		fwrite( STDERR, "Time foundation smoke failed: {$label}\n" );
		exit( 1 );
	}
}

echo "Time foundation smoke passed.\n";

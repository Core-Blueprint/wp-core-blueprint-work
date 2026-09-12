<?php
declare(strict_types=1);

define( 'ABSPATH', '/tmp/wp/' );

$root = dirname( __DIR__ );
require $root . '/src/Domain/RecurrenceSchedule.php';

use CB\Work\Domain\RecurrenceSchedule;

$bootstrap  = file_get_contents( $root . '/core-blueprint-work.php' );
$schema     = file_get_contents( $root . '/src/Database/Schema.php' );
$repository = file_get_contents( $root . '/src/Repository/RecurrenceRules.php' );

$monthly_1 = RecurrenceSchedule::next_after( '2027-01-31', '2027-01-31', 'monthly', 1 );
$monthly_2 = null === $monthly_1 ? null : RecurrenceSchedule::next_after( '2027-01-31', $monthly_1, 'monthly', 1 );
$yearly_1  = RecurrenceSchedule::next_after( '2028-02-29', '2028-02-29', 'yearly', 1 );
$yearly_4  = RecurrenceSchedule::next_after( '2028-02-29', '2028-02-29', 'yearly', 4 );

$checks = [
	'plugin identity stays rc1 while later Work domains advance only the schema' => str_contains( $bootstrap, "CB_WORK_VERSION', '1.0.0-rc1'" ) && str_contains( $bootstrap, "CB_WORK_SCHEMA_VERSION', '1.8'" ),
	'canonical recurrence frequencies are bounded' => RecurrenceSchedule::frequencies() === [ 'daily', 'weekly', 'monthly', 'yearly' ],
	'interval and date normalization rejects invalid schedules' => null === RecurrenceSchedule::normalize( 'hourly', 1, '2027-01-01' ) && null === RecurrenceSchedule::normalize( 'daily', 0, '2027-01-01' ) && null === RecurrenceSchedule::normalize( 'daily', 1, '2027-02-30' ),
	'daily and weekly interval stepping is deterministic' => '2027-01-03' === RecurrenceSchedule::next_after( '2027-01-01', '2027-01-01', 'daily', 2 ) && '2027-01-15' === RecurrenceSchedule::next_after( '2027-01-01', '2027-01-01', 'weekly', 2 ),
	'month-end recurrence remains anchored to the original calendar day' => '2027-02-28' === $monthly_1 && '2027-03-31' === $monthly_2,
	'leap-day yearly recurrence clamps safely and returns to leap day' => '2029-02-28' === $yearly_1 && '2032-02-29' === $yearly_4,
	'finite recurrence has a real terminal state rather than a sentinel date' => null === RecurrenceSchedule::next_after( '2027-01-01', '2027-01-08', 'weekly', 1, '2027-01-08' ),
	'due offset uses date arithmetic independent of scheduler runtime' => '2027-04-05' === RecurrenceSchedule::add_days( '2027-04-01', 4 ),
	'rules assignments and occurrence ledger are separate Work-owned tables' => str_contains( $schema, "'cb_work_recurrence_rules'" ) && str_contains( $schema, "'cb_work_recurrence_rule_assignments'" ) && str_contains( $schema, "'cb_work_recurrence_occurrences'" ),
	'finite rules allow nullable next occurrence' => str_contains( $schema, 'next_occurrence_on date NULL' ) && ! str_contains( $schema, 'next_occurrence_on date NOT NULL' ),
	'occurrence ledger enforces hard rule/date idempotency' => str_contains( $schema, 'UNIQUE KEY rule_occurrence (rule_id,occurrence_on)' ),
	'recurrence storage introduces no SQL foreign keys' => ! str_contains( strtoupper( $schema ), 'FOREIGN KEY' ),
	'rule generation window is evaluated per rule' => str_contains( $repository, 'DATE_ADD(%s, INTERVAL create_ahead_days DAY)' ),
	'reserved occurrences project into the canonical Work Item create contract' => str_contains( $repository, 'occurrence_work_item_input' ) && str_contains( $repository, "'source_type'         => 'recurrence_occurrence'" ) && str_contains( $repository, "'assigned_user_ids'   => \$rule['assigned_user_ids']" ),
	'schedule identity cannot rewind after an occurrence exists' => str_contains( $repository, '$schedule_changed && self::has_occurrences' ) && str_contains( $repository, 'SELECT id FROM ' . "' . Schema::recurrence_occurrences_table() . '" . ' WHERE rule_id = %d LIMIT 1' ),
	'foundation contains no cron scheduler or recurrence admin UI' => ! str_contains( $repository, 'wp_schedule_event' ) && ! str_contains( $repository, 'wp_schedule_single_event' ) && ! str_contains( $repository, 'add_action(' ),
];

foreach ( $checks as $label => $passed ) {
	if ( ! $passed ) {
		fwrite( STDERR, "Recurrence foundation smoke failed: {$label}\n" );
		exit( 1 );
	}
}

echo "Recurrence foundation smoke passed.\n";

<?php
declare(strict_types=1);

// Golden: seconds and daylight-saving correction semantics, without a WordPress DB.
define( 'ABSPATH', '/tmp/wp/' );
function wp_timezone(): DateTimeZone { return new DateTimeZone( 'Europe/Amsterdam' ); }
function sanitize_text_field( string $value ): string { return trim( strip_tags( $value ) ); }

$root = dirname( __DIR__ );
require $root . '/src/Domain/TimeRange.php';
require $root . '/src/Admin/TimeActions.php';

use CB\Work\Domain\TimeRange;
use CB\Work\Admin\TimeActions;

$fail = static function ( string $message ): never {
    fwrite( STDERR, "Time Golden precision smoke FAILED: {$message}\n" );
    exit( 1 );
};

$short_start = '2026-10-08 13:28:13';
$short_end = '2026-10-08 13:28:26';
$precise = TimeRange::utc_to_local_parts( $short_start, true );

if ( [ 'date' => '2026-10-08', 'time' => '15:28:13' ] !== $precise
    || [ 'date' => '2026-10-08', 'time' => '15:28' ] !== TimeRange::utc_to_local_parts( $short_start )
    || $short_start !== TimeRange::local_to_utc( '2026-10-08', '15:28:13', $short_start )
    || $short_start !== TimeRange::local_to_utc( '2026-10-08', '15:28', $short_start )
    || '2026-10-08 13:29:00' !== TimeRange::local_to_utc( '2026-10-08', '15:29', $short_start )
    || 13 !== TimeRange::duration_seconds( $short_start, $short_end ) ) {
    $fail( 'second-precision round-trip or legacy minute preservation' );
}

$range_from_input = new ReflectionMethod( TimeActions::class, 'range_from_input' );
$original = [ 'started_at' => $short_start, 'ended_at' => $short_end ];
$input = [
    'start_date' => '2026-10-08', 'start_time' => '15:28:13',
    'end_date' => '2026-10-08', 'end_time' => '15:28:26',
];
$range = $range_from_input->invoke( null, $input, $original );
if ( $range !== $original || $range_from_input->invoke( null, [
        ...$input, 'start_time' => '15:28', 'end_time' => '15:28',
    ], $original ) !== $original ) {
    $fail( 'full and quick corrections must not silently modify untouched timestamps' );
}
$changed = $range_from_input->invoke( null, [ ...$input, 'end_time' => '15:29:26' ], $original );
if ( [ 'started_at' => $short_start, 'ended_at' => '2026-10-08 13:29:26' ] !== $changed ) {
    $fail( 'actual clock edits must update only the changed endpoint' );
}

// A stopped timer may legitimately have zero seconds. Preserve its exact
// timestamps for note/Work Item corrections; never synthesize zero manual
// entries or change a previously positive entry to zero.
$zero_utc = '2026-10-08 13:28:13';
$zero_input = [
    'start_date' => '2026-10-08', 'start_time' => '15:28:13',
    'end_date' => '2026-10-08', 'end_time' => '15:28:13',
];
$zero_timer = [
    'started_at' => $zero_utc,
    'ended_at' => $zero_utc,
    'duration_seconds' => 0,
    'entry_source' => 'timer',
];
if ( $range_from_input->invoke( null, $zero_input, $zero_timer )
        !== [ 'started_at' => $zero_utc, 'ended_at' => $zero_utc ]
    || null !== $range_from_input->invoke( null, $zero_input, null )
    || null !== $range_from_input->invoke( null, $zero_input, [
        ...$zero_timer, 'entry_source' => 'manual',
    ] )
    || null !== $range_from_input->invoke( null, $zero_input, [
        ...$zero_timer, 'duration_seconds' => 13,
    ] )
    || null !== $range_from_input->invoke( null, [
        ...$zero_input, 'start_time' => '15:27:13', 'end_time' => '15:27:13',
    ], $zero_timer ) ) {
    $fail( 'zero-second correction must preserve only an existing timer instant' );
}

// Europe/Amsterdam switches from +02:00 to +01:00 at 2026-10-25 01:00 UTC.
// Both instants display as 02:30:13, but they must never collapse on note-only save.
$fold_early = '2026-10-25 00:30:13';
$fold_late = '2026-10-25 01:30:13';
foreach ( [ $fold_early, $fold_late ] as $instant ) {
    $local = TimeRange::utc_to_local_parts( $instant, true );
    if ( [ 'date' => '2026-10-25', 'time' => '02:30:13' ] !== $local
        || $instant !== TimeRange::local_to_utc( $local['date'], $local['time'], $instant ) ) {
        $fail( 'the original DST fold offset must survive an unchanged correction' );
    }
}
if ( null !== TimeRange::local_to_utc( '2026-10-25', '02:30:13' )
    || null !== TimeRange::local_to_utc( '2026-10-25', '02:30' )
    || null !== TimeRange::local_to_utc( '2026-03-29', '02:30:00' )
    || '2026-10-25 02:15:13' !== TimeRange::local_to_utc( '2026-10-25', '03:15:13' ) ) {
    $fail( 'new ambiguous or nonexistent local times must be rejected' );
}

$first = $range_from_input->invoke( null, [
    'start_date' => '2026-10-25', 'start_time' => '02:30:13',
    'end_date' => '2026-10-25', 'end_time' => '02:30:26',
], [ 'started_at' => $fold_early, 'ended_at' => '2026-10-25 00:30:26' ] );
if ( [ 'started_at' => $fold_early, 'ended_at' => '2026-10-25 00:30:26' ] !== $first ) {
    $fail( 'DST fold correction must preserve both original instants' );
}

$time_view = (string) file_get_contents( $root . '/src/Admin/Time.php' );
$quick_view = (string) file_get_contents( $root . '/src/Admin/TimeEntryQuickEdit.php' );
$actions = (string) file_get_contents( $root . '/src/Admin/TimeActions.php' );
if ( ! str_contains( $time_view, "TimeRange::utc_to_local_parts( (string) \$entry['started_at'], true )" )
    || ! str_contains( $time_view, 'type="time" id="cb-work-time-start" name="time[start_time]" step="1"' )
    || ! str_contains( $time_view, 'type="time" id="cb-work-time-end" name="time[end_time]" step="1"' )
    || ! str_contains( $time_view, "self::time_picker( 'time[start_time]'" )
    || ! str_contains( $quick_view, "TimeRange::utc_to_local_parts( (string) \$entry['started_at'], true )" )
    || str_contains( $quick_view, 'private static function local_parts' )
    || ! str_contains( $actions, 'self::range_from_input( $input, $entry )' ) ) {
    $fail( 'edit views and write path must use one canonical seconds-aware domain contract' );
}

echo "Time Golden precision smoke passed.\n";

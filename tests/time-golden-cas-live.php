<?php
declare(strict_types=1);

/**
 * Opt-in, destructive-fixture-safe WP-CLI test of TimeEntries CAS against
 * a disposable local WordPress/MariaDB TEST database.
 *
 * Invocation (never via tools/check, never production):
 *   CB_WORK_TIME_CAS_TEST=1 wp --path=/path/to/test-wordpress eval-file tests/time-golden-cas-live.php
 *
 * A single database transaction encloses the entire fixture. A finally-block
 * always rolls it back. It does not exercise concurrent requests on two DB
 * connections; it tests real SQL stale revisions and partial per-row outcomes.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || '1' !== getenv( 'CB_WORK_TIME_CAS_TEST' ) ) {
    throw new RuntimeException( 'Time CAS fixture requires WP-CLI and explicit CB_WORK_TIME_CAS_TEST=1.' );
}
if ( ! defined( 'DB_NAME' ) || ! preg_match( '/(?:^|[_-])test(?:$|[_-])|testing|testdb/i', (string) DB_NAME ) ) {
    throw new RuntimeException( 'Time CAS fixture refuses databases without a test-specific DB_NAME.' );
}
if ( ! defined( 'CB_WORK_SCHEMA_VERSION' ) || ! class_exists( \CB\Work\Repository\TimeEntries::class ) ) {
    throw new RuntimeException( 'Activate Core Blueprint Base + Work in the disposable WordPress test site.' );
}

use CB\Work\Content\PostTypes;
use CB\Work\Database\Schema;
use CB\Work\Repository\TimeEntries;

global $wpdb;
$table = Schema::time_entries_table();

// Rollback safety requires transactional tables. wp_insert_post and the Work
// entry repository write to these tables in this isolated process.
foreach ( [ $wpdb->posts, $wpdb->postmeta, $table ] as $checked_table ) {
    $engine = $wpdb->get_var( $wpdb->prepare(
        'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
        $checked_table
    ) );
    if ( 'InnoDB' !== $engine ) {
        throw new RuntimeException( 'Fixture requires InnoDB table: ' . $checked_table );
    }
}
if ( (string) get_option( Schema::OPTION, '' ) !== CB_WORK_SCHEMA_VERSION ) {
    throw new RuntimeException( 'Work test database schema is not ready.' );
}
$users = get_users( [ 'number' => 1, 'fields' => 'ids' ] );
$actor = (int) ( $users[0] ?? 0 );
if ( $actor <= 0 || false === get_userdata( $actor ) ) {
    throw new RuntimeException( 'Disposable WordPress test installation needs at least one user.' );
}

$assert = static function ( bool $condition, string $description ): void {
    if ( ! $condition ) {
        throw new RuntimeException( 'Time live CAS FAILED: ' . $description );
    }
};

if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
    throw new RuntimeException( 'Could not start isolated Time CAS fixture transaction.' );
}
try {
    $work_item_id = wp_insert_post( [
        'post_type' => PostTypes::WORK_ITEM,
        'post_status' => 'draft',
        'post_title' => 'Temporary Time CAS fixture',
        'post_author' => $actor,
    ], true );
    $assert( is_int( $work_item_id ) && $work_item_id > 0, 'create disposable Work Item' );

    $start = gmdate( 'Y-m-d H:i:s', time() - 3600 );
    $end = gmdate( 'Y-m-d H:i:s', time() - 3480 );
    $entry_id = TimeEntries::create_manual( $work_item_id, $actor, $start, $end, 'original', $actor );
    $assert( $entry_id > 0, 'create disposable completed Time entry' );

    // Two operators read the same revision. Only one can commit a correction.
    $operator_a = TimeEntries::get( $entry_id );
    $operator_b = TimeEntries::get( $entry_id );
    $assert( is_array( $operator_a ) && is_array( $operator_b ), 'both revision snapshots exist' );
    $rev = (int) $operator_a['revision'];
    $assert( $rev === (int) $operator_b['revision'], 'both operators read the same revision' );

    $first = TimeEntries::update_completed(
        $entry_id, $rev, $work_item_id, $actor, $start, $end, 'operator-a', $actor
    );
    $stale = TimeEntries::update_completed(
        $entry_id, $rev, $work_item_id, $actor, $start, $end, 'operator-b', $actor
    );
    $after = TimeEntries::get( $entry_id );
    $assert( $first && ! $stale, 'stale operator must not overwrite the first save' );
    $assert(
        is_array( $after ) && 'operator-a' === $after['note']
        && $rev + 1 === (int) $after['revision']
        && $start === $after['started_at'] && $end === $after['ended_at']
        && 120 === (int) $after['duration_seconds'],
        'canonical note, revision and timestamps after conflict'
    );

    // Simulate preflight-then-interleaved-write: one row survives bulk CAS,
    // another has become stale since preflight. This is intentionally partial.
    $other_id = TimeEntries::create_manual( $work_item_id, $actor, $start, $end, 'other', $actor );
    $assert( $other_id > 0, 'create second disposable Time entry' );
    $snapshot_one = TimeEntries::get( $entry_id );
    $snapshot_two = TimeEntries::get( $other_id );
    $assert( is_array( $snapshot_one ) && is_array( $snapshot_two ), 'bulk snapshots exist' );

    // Interleaved change happens after preflight but before the bulk SQL.
    $interleaved = TimeEntries::update_completed(
        $other_id, (int) $snapshot_two['revision'], $work_item_id, $actor,
        $start, $end, 'interleaved', $actor
    );
    $assert( $interleaved, 'interleaved edit must succeed' );

    $updated = 0;
    $failed = 0;
    foreach ( [ $snapshot_one, $snapshot_two ] as $snapshot ) {
        $ok = TimeEntries::update_completed(
            (int) $snapshot['id'], (int) $snapshot['revision'],
            $work_item_id, $actor, $start, $end, 'bulk-update', $actor
        );
        $ok ? ++$updated : ++$failed;
    }
    $assert( 1 === $updated && 1 === $failed, 'one bulk update succeeds and stale row fails' );
    $assert( 'bulk-update' === (string) ( TimeEntries::get( $entry_id )['note'] ?? '' ),
        'successful bulk row is preserved' );
    $assert( 'interleaved' === (string) ( TimeEntries::get( $other_id )['note'] ?? '' ),
        'newer interleaved row must not be overwritten' );

    echo "Time live CAS fixture PASS: stale edit rejected; partial bulk writes preserved; transaction rolled back.\n";
} finally {
    $rollback = $wpdb->query( 'ROLLBACK' );
    if ( false === $rollback ) {
        throw new RuntimeException( 'CRITICAL: Time CAS fixture rollback failed. Inspect disposable test DB.' );
    }
}

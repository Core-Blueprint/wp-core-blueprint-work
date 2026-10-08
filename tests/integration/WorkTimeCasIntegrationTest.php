<?php
declare(strict_types=1);

use CB\Work\Content\PostTypes;
use CB\Work\Database\Schema;
use CB\Work\Repository\TimeEntries;

/**
 * Real MariaDB revision/CAS integration in the existing Base PHPUnit runtime.
 *
 * Only the PHPUnit-managed test DB is used. A TEMPORARY InnoDB table shadows
 * Work's Time Entries table on this connection. The normal WordPress test
 * transaction rolls back posts, users and options; tear_down drops the temp table.
 *
 * This models stale revisions in real SQL, NOT simultaneous DB sessions.
 */
final class WorkTimeCasIntegrationTest extends WP_UnitTestCase {
    private string $entry_table = '';

    public function set_up(): void {
        parent::set_up();

        // Refuse a non-test or remote database before issuing any SQL.
        if ( ! defined( 'DB_NAME' )
            || ! preg_match( '/(?:^|[_-])test(?:$|[_-])|testing|testdb/i', (string) DB_NAME )
            || ! defined( 'DB_HOST' )
            || ! preg_match( '/^(?:localhost|127\\.0\\.0\\.1|\\[?::1\\]?)(?::[0-9]+)?$/i', (string) DB_HOST )
        ) {
            self::fail( 'Work Time CAS requires the established local WordPress PHPUnit test DB.' );
        }
        self::assertTrue( defined( 'CB_WORK_SCHEMA_VERSION' ) );
        self::assertTrue( class_exists( TimeEntries::class ) );

        global $wpdb;
        $this->entry_table = Schema::time_entries_table();
        register_post_type( PostTypes::WORK_ITEM, [ 'public' => false ] );

        $created = $wpdb->query( 'CREATE TEMPORARY TABLE ' . $this->entry_table . ' (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            work_item_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            entry_source varchar(16) NOT NULL,
            started_at datetime NOT NULL,
            ended_at datetime NULL,
            duration_seconds int unsigned NOT NULL DEFAULT 0,
            note text NOT NULL,
            revision int unsigned NOT NULL DEFAULT 1,
            created_by bigint(20) unsigned NOT NULL,
            updated_by bigint(20) unsigned NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB' );
        self::assertNotFalse( $created, 'Could not create temporary InnoDB Time Entries test table.' );

        update_option( Schema::OPTION, CB_WORK_SCHEMA_VERSION );
    }

    public function tear_down(): void {
        global $wpdb;
        if ( '' !== $this->entry_table ) {
            $wpdb->query( 'DROP TEMPORARY TABLE IF EXISTS ' . $this->entry_table );
        }
        if ( post_type_exists( PostTypes::WORK_ITEM ) ) {
            unregister_post_type( PostTypes::WORK_ITEM );
        }
        parent::tear_down();
    }

    public function test_stale_revision_and_interleaved_bulk_partial_outcome(): void {
        $user_id = self::factory()->user->create();
        $work_item_id = self::factory()->post->create( [
            'post_type' => PostTypes::WORK_ITEM,
            'post_status' => 'draft',
            'post_title' => 'Temporary CAS Work Item',
        ] );
        self::assertGreaterThan( 0, $user_id );
        self::assertGreaterThan( 0, $work_item_id );

        $start = gmdate( 'Y-m-d H:i:s', time() - 3600 );
        $end = gmdate( 'Y-m-d H:i:s', time() - 3480 );
        $id = TimeEntries::create_manual( $work_item_id, $user_id, $start, $end, 'initial', $user_id );
        self::assertGreaterThan( 0, $id );

        $snapshot_a = TimeEntries::get( $id );
        $snapshot_b = TimeEntries::get( $id );
        self::assertIsArray( $snapshot_a );
        self::assertIsArray( $snapshot_b );
        $revision = (int) $snapshot_a['revision'];
        self::assertSame( $revision, (int) $snapshot_b['revision'] );

        self::assertTrue( TimeEntries::update_completed(
            $id, $revision, $work_item_id, $user_id, $start, $end, 'operator-a', $user_id
        ) );
        self::assertFalse( TimeEntries::update_completed(
            $id, $revision, $work_item_id, $user_id, $start, $end, 'operator-b', $user_id
        ) );
        $after = TimeEntries::get( $id );
        self::assertIsArray( $after );
        self::assertSame( 'operator-a', $after['note'] );
        self::assertSame( $revision + 1, $after['revision'] );
        self::assertSame( $start, $after['started_at'] );
        self::assertSame( $end, $after['ended_at'] );
        self::assertSame( 120, $after['duration_seconds'] );

        $id2 = TimeEntries::create_manual( $work_item_id, $user_id, $start, $end, 'second', $user_id );
        self::assertGreaterThan( 0, $id2 );
        $preflight_one = TimeEntries::get( $id );
        $preflight_two = TimeEntries::get( $id2 );
        self::assertIsArray( $preflight_one );
        self::assertIsArray( $preflight_two );

        // Another editor saves after bulk preflight but before the per-row CAS.
        self::assertTrue( TimeEntries::update_completed(
            $id2, (int) $preflight_two['revision'], $work_item_id, $user_id,
            $start, $end, 'newer-second', $user_id
        ) );

        $updated = 0;
        $failed = 0;
        foreach ( [ $preflight_one, $preflight_two ] as $snapshot ) {
            $ok = TimeEntries::update_completed(
                (int) $snapshot['id'], (int) $snapshot['revision'],
                $work_item_id, $user_id, $start, $end, 'bulk-note', $user_id
            );
            $ok ? ++$updated : ++$failed;
        }

        self::assertSame( 1, $updated, 'First bulk row should update.' );
        self::assertSame( 1, $failed, 'Second bulk row must reject its stale revision.' );
        self::assertSame( 'bulk-note', (string) ( TimeEntries::get( $id )['note'] ?? '' ) );
        self::assertSame( 'newer-second', (string) ( TimeEntries::get( $id2 )['note'] ?? '' ) );
    }
}

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
    /** @var string[] Temporary table names (never persistent Work tables). */
    private array $temporary_tables = [];

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
        register_post_type( PostTypes::WORK_ITEM, [ 'public' => false ] );

        $entries = Schema::time_entries_table();
        $created = $wpdb->query( 'CREATE TEMPORARY TABLE ' . $entries . ' (
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
        $this->temporary_tables[] = $entries;

        // WorkItems::get() hydrates assignments and relations as part of
        // the repository's real context validation. Shadow those tables too.
        $assignments = Schema::assignments_table();
        $created = $wpdb->query( 'CREATE TEMPORARY TABLE ' . $assignments . ' (
            work_item_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            assigned_at datetime NOT NULL,
            PRIMARY KEY (work_item_id, user_id)
        ) ENGINE=InnoDB' );
        self::assertNotFalse( $created, 'Could not create temporary assignments table.' );
        $this->temporary_tables[] = $assignments;

        $relations = Schema::relations_table();
        $created = $wpdb->query( 'CREATE TEMPORARY TABLE ' . $relations . ' (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            work_item_id bigint(20) unsigned NOT NULL,
            provider varchar(64) NOT NULL,
            relation_type varchar(64) NOT NULL,
            external_id varchar(191) NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB' );
        self::assertNotFalse( $created, 'Could not create temporary relations table.' );
        $this->temporary_tables[] = $relations;

        update_option( Schema::OPTION, CB_WORK_SCHEMA_VERSION );
    }

    public function tear_down(): void {
        global $wpdb;
        foreach ( array_reverse( $this->temporary_tables ) as $table ) {
            $wpdb->query( 'DROP TEMPORARY TABLE IF EXISTS ' . $table );
        }
        $this->temporary_tables = [];
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
    /**
     * WT-G-012: a stopped timer may have a genuinely zero-second duration.
     * Corrections must keep the authoritative instants and revision guard.
     */
    public function test_zero_second_timer_can_be_corrected_without_creating_zero_manual_entries(): void {
        global $wpdb;
        $user_id = self::factory()->user->create();
        $work_item_id = self::factory()->post->create( [
            'post_type' => PostTypes::WORK_ITEM,
            'post_status' => 'draft',
            'post_title' => 'Zero-duration timer fixture',
        ] );
        self::assertGreaterThan( 0, $user_id );
        self::assertGreaterThan( 0, $work_item_id );

        $instant = gmdate( 'Y-m-d H:i:s', time() - 3600 );
        $positive_end = gmdate( 'Y-m-d H:i:s', time() - 3540 );

        self::assertSame( 0, TimeEntries::create_manual(
            $work_item_id, $user_id, $instant, $instant, 'not allowed', $user_id
        ), 'Manual creation must continue to reject zero-second ranges.' );

        // Model the canonical output of Timers::stop() when both instants
        // fall in the same second. The temporary table is connection-local.
        $inserted = $wpdb->insert(
            Schema::time_entries_table(),
            [
                'work_item_id' => $work_item_id,
                'user_id' => $user_id,
                'entry_source' => TimeEntries::SOURCE_TIMER,
                'started_at' => $instant,
                'ended_at' => $instant,
                'duration_seconds' => 0,
                'note' => 'timer stopped',
                'revision' => 2,
                'created_by' => $user_id,
                'updated_by' => $user_id,
                'created_at' => $instant,
                'updated_at' => $instant,
            ]
        );
        self::assertSame( 1, $inserted );
        $timer_id = (int) $wpdb->insert_id;
        self::assertGreaterThan( 0, $timer_id );

        self::assertTrue( TimeEntries::update_completed(
            $timer_id, 2, $work_item_id, $user_id,
            $instant, $instant, 'corrected note', $user_id
        ) );
        $zero = TimeEntries::get( $timer_id );
        self::assertIsArray( $zero );
        self::assertSame( 0, $zero['duration_seconds'] );
        self::assertSame( $instant, $zero['started_at'] );
        self::assertSame( $instant, $zero['ended_at'] );
        self::assertSame( 3, $zero['revision'] );
        self::assertSame( 'corrected note', $zero['note'] );

        self::assertFalse( TimeEntries::update_completed(
            $timer_id, 2, $work_item_id, $user_id,
            $instant, $instant, 'stale edit', $user_id
        ), 'A stale revision must still fail for zero-second timer entries.' );

        $other_instant = gmdate( 'Y-m-d H:i:s', time() - 3000 );
        self::assertFalse( TimeEntries::update_completed(
            $timer_id, 3, $work_item_id, $user_id,
            $other_instant, $other_instant, 'moved zero', $user_id
        ), 'Changing both timestamps to a different zero-second range is forbidden.' );

        $manual_id = TimeEntries::create_manual(
            $work_item_id, $user_id, $instant, $positive_end, 'positive', $user_id
        );
        self::assertGreaterThan( 0, $manual_id );
        self::assertFalse( TimeEntries::update_completed(
            $manual_id, 1, $work_item_id, $user_id,
            $instant, $instant, 'converted to zero', $user_id
        ), 'Positive manual time cannot be converted to zero seconds.' );

        // A legitimate correction can turn a zero-second timer into
        // positive time by extending its end timestamp.
        self::assertTrue( TimeEntries::update_completed(
            $timer_id, 3, $work_item_id, $user_id,
            $instant, $positive_end, 'elapsed time corrected', $user_id
        ) );
        $corrected = TimeEntries::get( $timer_id );
        self::assertIsArray( $corrected );
        self::assertSame( 60, $corrected['duration_seconds'] );
        self::assertSame( 4, $corrected['revision'] );
    }

}

<?php
declare(strict_types=1);

use CB\Work\Capabilities;
use CB\Work\Content\PostTypes;
use CB\Work\Database\Schema;
use CB\Work\Domain\TimeNote;
use CB\Work\Domain\TimeRange;
use CB\Work\Time\Access;

/**
 * WT-G-006: two real MariaDB sessions observing the same revisioned row.
 *
 * The production TimeEntries::update_completed() repository is separately
 * exercised against a connection-local temporary InnoDB table by the existing
 * WorkTimeCasIntegrationTest. This fixture reproduces its SQL CAS shape on a
 * shared, uniquely named test-only InnoDB table so that TWO real connections
 * can see the same committed rows. It models interleaved stale writes and
 * partial-bulk outcomes, not wall-clock parallel requests or AJAX transport.
 *
 * Never run against a non-local/non-test database. All persistent test DDL
 * happens through dedicated connections, never through WordPress PHPUnit's
 * main (transactional) $wpdb connection.
 */
final class WorkTimeTwoConnectionCasIntegrationTest extends WP_UnitTestCase {
    private ?wpdb $connection_a = null;
    private ?wpdb $connection_b = null;
    private string $shared_table = '';
    private bool $owns_shared_table = false;

    /** @var string[] Main-connection TEMPORARY table names only. */
    private array $temporary_tables = [];

    public function set_up(): void {
        // Stronger than the default WordPress PHPUnit naming convention: this
        // opt-in fixture is ONLY for our confirmed local Docker database.
        if ( ! defined( 'DB_NAME' )
            || 'core_blueprint_work_test' !== (string) DB_NAME
            || ! defined( 'DB_HOST' )
            || '127.0.0.1:3307' !== (string) DB_HOST
        ) {
            self::fail( 'WT-G-006 requires local core_blueprint_work_test at 127.0.0.1:3307.' );
        }
        parent::set_up();
    }

    public function tear_down(): void {
        // Do not issue a DROP unless CREATE succeeded for our exact unique
        // table. An interrupted process may leave a test-only table; it can
        // never be mistaken for an actual WordPress or Work runtime table.
        try {
            if ( $this->owns_shared_table
                && null !== $this->connection_a
                && preg_match( '/^cb_wtg006_time_[a-f0-9]{16}$/D', $this->shared_table )
            ) {
                $dropped = mysqli_query(
                    $this->connection_a->dbh,
                    'DROP TABLE `' . $this->shared_table . '`'
                );
                if ( true !== $dropped ) {
                    throw new RuntimeException( 'Failed to clean up WT-G-006 shared fixture: ' . mysqli_error( $this->connection_a->dbh ) );
                }
                $this->owns_shared_table = false;
            }

            global $wpdb;
            foreach ( array_reverse( $this->temporary_tables ) as $table ) {
                $wpdb->query( 'DROP TEMPORARY TABLE IF EXISTS ' . $table );
            }
            $this->temporary_tables = [];
        } finally {
            if ( null !== $this->connection_b ) {
                $this->connection_b->close();
                $this->connection_b = null;
            }
            if ( null !== $this->connection_a ) {
                $this->connection_a->close();
                $this->connection_a = null;
            }
            wp_set_current_user( 0 );
            if ( post_type_exists( PostTypes::WORK_ITEM ) ) {
                unregister_post_type( PostTypes::WORK_ITEM );
            }
            parent::tear_down();
        }
    }

    public function test_two_independent_sessions_reject_stale_writes_and_report_partial_bulk(): void {
        $this->open_shared_test_connections();
        $a = $this->connection_a;
        $b = $this->connection_b;
        self::assertInstanceOf( wpdb::class, $a );
        self::assertInstanceOf( wpdb::class, $b );

        $connection_a_id = (int) $a->get_var( 'SELECT CONNECTION_ID()' );
        $connection_b_id = (int) $b->get_var( 'SELECT CONNECTION_ID()' );
        self::assertGreaterThan( 0, $connection_a_id );
        self::assertGreaterThan( 0, $connection_b_id );
        self::assertNotSame( $connection_a_id, $connection_b_id, 'Must have two independent server sessions.' );

        $started = gmdate( 'Y-m-d H:i:s', time() - 3600 );
        $ended = gmdate( 'Y-m-d H:i:s', time() - 3480 );
        $entry_id = $this->seed_entry( $a, $started, $ended, 'initial-full' );

        // Each client independently reads the SAME committed revision.
        $snapshot_a = $this->read_entry( $a, $entry_id );
        $snapshot_b = $this->read_entry( $b, $entry_id );
        self::assertSame( $snapshot_a['revision'], $snapshot_b['revision'] );
        self::assertSame( $snapshot_a['note'], $snapshot_b['note'] );

        // A completes a full correction (with changed elapsed time). B's
        // stale Quick Edit note may not restore the older range/note.
        $extended_end = gmdate( 'Y-m-d H:i:s', time() - 3420 );
        self::assertSame( 1, $this->correct_with_repository_cas_sql(
            $a, $snapshot_a, 'full-from-a', 101, $started, $extended_end
        ) );
        self::assertSame( 0, $this->correct_with_repository_cas_sql(
            $b, $snapshot_b, 'stale-quick-from-b', 102
        ) );
        $after = $this->read_entry( $b, $entry_id );
        self::assertSame( 2, (int) $after['revision'] );
        self::assertSame( 'full-from-a', $after['note'] );
        self::assertSame( $extended_end, $after['ended_at'] );
        self::assertSame( 180, (int) $after['duration_seconds'] );
        self::assertSame( 101, (int) $after['updated_by'] );

        // Reverse the client roles: another stale full correction must not
        // overwrite a newer Quick Edit, even if it changes timestamps.
        $stale_full = $this->read_entry( $a, $entry_id );
        $fresh_quick = $this->read_entry( $b, $entry_id );
        self::assertSame( 1, $this->correct_with_repository_cas_sql(
            $b, $fresh_quick, 'quick-from-b', 102
        ) );
        self::assertSame( 0, $this->correct_with_repository_cas_sql(
            $a, $stale_full, 'stale-full-from-a', 101, $started, $ended
        ) );
        $fresh = $this->read_entry( $a, $entry_id );
        self::assertSame( 'quick-from-b', $fresh['note'] );
        self::assertSame( 3, (int) $fresh['revision'] );
        self::assertSame( 180, (int) $fresh['duration_seconds'] );

        // Both selected rows pass the bulk preflight on A. B then commits
        // a correction to only one row before A writes its prepared batch.
        $first_id = $this->seed_entry( $a, $started, $ended, 'bulk-first' );
        $second_id = $this->seed_entry( $a, $started, $ended, 'bulk-second' );
        $selected_a = [
            $this->read_entry( $a, $first_id ),
            $this->read_entry( $a, $second_id ),
        ];
        $second_b = $this->read_entry( $b, $second_id );
        self::assertSame( 1, $this->correct_with_repository_cas_sql(
            $b, $second_b, 'newer-single-edit', 102
        ) );

        $updated = 0;
        $failed = 0;
        foreach ( $selected_a as $entry ) {
            $result = $this->correct_with_repository_cas_sql(
                $a, $entry, 'bulk-from-a', 101
            );
            self::assertContains( $result, [ 0, 1 ] );
            1 === $result ? ++$updated : ++$failed;
        }
        self::assertSame( 1, $updated, 'One bulk row should commit.' );
        self::assertSame( 1, $failed, 'The stale bulk row must fail.' );
        self::assertSame( 'bulk-from-a', $this->read_entry( $b, $first_id )['note'] );
        self::assertSame( 'newer-single-edit', $this->read_entry( $b, $second_id )['note'] );
        self::assertSame( 2, (int) $this->read_entry( $b, $first_id )['revision'] );
        self::assertSame( 2, (int) $this->read_entry( $a, $second_id )['revision'] );
    }

    public function test_zero_second_timer_keeps_atomic_guards_between_sessions(): void {
        $this->open_shared_test_connections();
        $a = $this->connection_a;
        $b = $this->connection_b;
        self::assertInstanceOf( wpdb::class, $a );
        self::assertInstanceOf( wpdb::class, $b );

        $instant = gmdate( 'Y-m-d H:i:s', time() - 3600 );
        $id = $this->seed_entry( $a, $instant, $instant, 'stopped timer', 'timer' );
        $first = $this->read_entry( $a, $id );
        $stale = $this->read_entry( $b, $id );
        self::assertSame( 0, (int) $first['duration_seconds'] );
        self::assertSame( $first['revision'], $stale['revision'] );

        self::assertSame( 1, $this->correct_with_repository_cas_sql(
            $a, $first, 'timer note updated', 101
        ) );
        self::assertSame( 0, $this->correct_with_repository_cas_sql(
            $b, $stale, 'stale timer note', 102
        ) );

        $latest = $this->read_entry( $b, $id );
        self::assertSame( 'timer note updated', $latest['note'] );
        self::assertSame( $instant, $latest['started_at'] );
        self::assertSame( $instant, $latest['ended_at'] );
        self::assertSame( 0, (int) $latest['duration_seconds'] );
        self::assertSame( 2, (int) $latest['revision'] );

        $shifted = gmdate( 'Y-m-d H:i:s', time() - 3300 );
        self::assertSame( 0, $this->correct_with_repository_cas_sql(
            $b, $latest, 'disallowed move', 102, $shifted, $shifted
        ), 'A zero-second timer may not be relocated while preserving zero duration.' );
        self::assertSame( 'timer note updated', $this->read_entry( $a, $id )['note'] );
    }

    public function test_two_distinct_user_identities_obey_time_entry_permissions(): void {
        global $wpdb;
        register_post_type( PostTypes::WORK_ITEM, [ 'public' => false ] );

        // The primary PHPUnit session already wraps WordPress users/posts in
        // a rollback transaction. Shadow only the two Work joins it needs.
        foreach ( [
            Schema::assignments_table() => '(
                work_item_id bigint(20) unsigned NOT NULL,
                user_id bigint(20) unsigned NOT NULL,
                assigned_at datetime NOT NULL,
                PRIMARY KEY (work_item_id, user_id)
            )',
            Schema::relations_table() => '(
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                work_item_id bigint(20) unsigned NOT NULL,
                provider varchar(64) NOT NULL,
                relation_type varchar(64) NOT NULL,
                external_id varchar(191) NOT NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY (id)
            )',
        ] as $table => $definition ) {
            self::assertNotFalse( $wpdb->query(
                'CREATE TEMPORARY TABLE ' . $table . ' ' . $definition . ' ENGINE=InnoDB'
            ) );
            $this->temporary_tables[] = $table;
        }
        update_option( Schema::OPTION, CB_WORK_SCHEMA_VERSION );

        $manager_a = self::factory()->user->create( [ 'role' => 'subscriber' ] );
        $manager_b = self::factory()->user->create( [ 'role' => 'subscriber' ] );
        $tracker_a = self::factory()->user->create( [ 'role' => 'subscriber' ] );
        $tracker_b = self::factory()->user->create( [ 'role' => 'subscriber' ] );
        foreach ( [ $manager_a, $manager_b ] as $id ) {
            ( new WP_User( $id ) )->add_cap( Capabilities::MANAGE );
        }
        foreach ( [ $tracker_a, $tracker_b ] as $id ) {
            ( new WP_User( $id ) )->add_cap( Capabilities::TRACK_TIME );
        }

        $work_item_id = self::factory()->post->create( [
            'post_type' => PostTypes::WORK_ITEM,
            'post_status' => 'draft',
            'post_title' => 'WT-G-006 authorization fixture',
        ] );
        self::assertGreaterThan( 0, $work_item_id );
        self::assertSame( 1, $wpdb->insert(
            Schema::assignments_table(),
            [
                'work_item_id' => $work_item_id,
                'user_id' => $tracker_a,
                'assigned_at' => current_time( 'mysql', true ),
            ]
        ) );
        $entry = [ 'user_id' => $tracker_a, 'work_item_id' => $work_item_id ];

        foreach ( [ $manager_a, $manager_b ] as $manager ) {
            wp_set_current_user( $manager );
            self::assertTrue( Access::can_manage() );
            self::assertTrue( Access::can_edit_entry( $entry, $work_item_id ) );
        }
        wp_set_current_user( $tracker_a );
        self::assertFalse( Access::can_manage() );
        self::assertTrue( Access::can_track_work_item( $work_item_id, $tracker_a ) );
        self::assertTrue( Access::can_edit_entry( $entry, $work_item_id ) );

        wp_set_current_user( $tracker_b );
        self::assertFalse( Access::can_manage() );
        self::assertFalse( Access::can_track_work_item( $work_item_id, $tracker_b ) );
        self::assertFalse( Access::can_edit_entry( $entry, $work_item_id ) );
        self::assertFalse( Access::can_track_work_item( $work_item_id, $tracker_a ),
            'Tracker B cannot impersonate tracker A by supplying a user ID.' );
    }

    private function open_shared_test_connections(): void {
        $this->connection_a = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
        $this->connection_b = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
        $this->shared_table = 'cb_wtg006_time_' . bin2hex( random_bytes( 8 ) );

        self::assertMatchesRegularExpression( '/^cb_wtg006_time_[a-f0-9]{16}$/D', $this->shared_table );
        $a = $this->connection_a;
        $b = $this->connection_b;
        self::assertInstanceOf( wpdb::class, $a );
        self::assertInstanceOf( wpdb::class, $b );

        // Use the actual mysqli connections rather than wpdb's query
        // wrapper for DDL and visibility diagnostics. Explicitly select and
        // verify the same test schema on BOTH independent server sessions.
        // This also catches a misleading zero-row DDL return without a table.
        self::assertInstanceOf( mysqli::class, $a->dbh );
        self::assertInstanceOf( mysqli::class, $b->dbh );
        self::assertTrue( mysqli_select_db( $a->dbh, DB_NAME ), 'Session A could not select local test DB.' );
        self::assertTrue( mysqli_select_db( $b->dbh, DB_NAME ), 'Session B could not select local test DB.' );
        self::assertTrue( mysqli_autocommit( $a->dbh, true ), 'Session A must commit visible changes.' );
        self::assertTrue( mysqli_autocommit( $b->dbh, true ), 'Session B must read committed changes.' );

        $schema_a = mysqli_query( $a->dbh, 'SELECT DATABASE()' );
        $schema_b = mysqli_query( $b->dbh, 'SELECT DATABASE()' );
        self::assertInstanceOf( mysqli_result::class, $schema_a, 'Session A schema query failed.' );
        self::assertInstanceOf( mysqli_result::class, $schema_b, 'Session B schema query failed.' );
        self::assertSame( DB_NAME, (string) $schema_a->fetch_row()[0] );
        self::assertSame( DB_NAME, (string) $schema_b->fetch_row()[0] );
        $schema_a->free();
        $schema_b->free();

        $table = '`' . $this->shared_table . '`';
        $created = mysqli_query( $a->dbh, 'CREATE TABLE ' . $table . ' (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            work_item_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            entry_source varchar(16) NOT NULL,
            started_at datetime NOT NULL,
            ended_at datetime NULL,
            duration_seconds int unsigned NOT NULL,
            note text NOT NULL,
            revision int unsigned NOT NULL,
            updated_by bigint(20) unsigned NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB' );
        self::assertTrue(
            $created,
            'Dedicated test-only shared table creation failed: ' . mysqli_error( $a->dbh )
        );
        $this->owns_shared_table = true;

        // Verify visibility twice, directly through each distinct MariaDB
        // session. Never coerce a failed SELECT (false) into a zero count.
        foreach ( [ 'A' => $a, 'B' => $b ] as $identity => $client ) {
            $result = mysqli_query( $client->dbh, 'SELECT COUNT(*) FROM ' . $table );
            self::assertInstanceOf(
                mysqli_result::class,
                $result,
                'The shared InnoDB fixture is not visible to session ' . $identity .
                ': ' . mysqli_error( $client->dbh )
            );
            self::assertSame( '0', (string) $result->fetch_row()[0] );
            $result->free();
        }
    }

    private function seed_entry( wpdb $client, string $start, string $end, string $note, string $source = 'manual' ): int {
        $seconds = TimeRange::duration_seconds( $start, $end );
        self::assertNotNull( $seconds );
        $inserted = $client->insert(
            $this->shared_table,
            [
                'work_item_id' => 701,
                'user_id' => 801,
                'entry_source' => $source,
                'started_at' => $start,
                'ended_at' => $end,
                'duration_seconds' => $seconds,
                'note' => $note,
                'revision' => 1,
                'updated_by' => 801,
                'updated_at' => $end,
            ]
        );
        self::assertSame( 1, $inserted );
        return (int) $client->insert_id;
    }

    /** @return array<string,mixed> */
    private function read_entry( wpdb $client, int $id ): array {
        $row = $client->get_row(
            $client->prepare(
                'SELECT * FROM `' . $this->shared_table . '` WHERE id = %d LIMIT 1',
                $id
            ),
            ARRAY_A
        );
        self::assertIsArray( $row );
        return $row;
    }

    /**
     * Exact CAS SQL layout of TimeEntries::update_completed() on a dedicated
     * test-only table, retaining the revision and stopped-timer zero guard.
     * This helper models the SQL, not the complete public PHP write method;
     * the existing WorkTimeCasIntegrationTest invokes that method directly.
     *
     * @param array<string,mixed> $snapshot Row and expected revision as read
     *                                      on this client's earlier request.
     */
    private function correct_with_repository_cas_sql(
        wpdb $client,
        array $snapshot,
        string $note,
        int $actor_id,
        ?string $start_override = null,
        ?string $end_override = null
    ): int|false {
        $start = $start_override ?? (string) $snapshot['started_at'];
        $end = $end_override ?? (string) $snapshot['ended_at'];
        $duration = TimeRange::duration_seconds( $start, $end );
        self::assertNotNull( $duration );

        $zero_guard = 0 === $duration
            ? ' AND entry_source = %s AND duration_seconds = 0 AND started_at = %s AND ended_at = %s'
            : '';
        $args = [
            (int) $snapshot['work_item_id'],
            (int) $snapshot['user_id'],
            $start,
            $end,
            $duration,
            TimeNote::normalize( $note ),
            $actor_id,
            current_time( 'mysql', true ),
            (int) $snapshot['id'],
            (int) $snapshot['revision'],
        ];
        if ( 0 === $duration ) {
            array_push( $args, 'timer', $start, $end );
        }
        return $client->query(
            $client->prepare(
                'UPDATE `' . $this->shared_table . '` SET work_item_id = %d, user_id = %d, started_at = %s, ended_at = %s, duration_seconds = %d, note = %s, revision = revision + 1, updated_by = %d, updated_at = %s WHERE id = %d AND revision = %d AND ended_at IS NOT NULL' . $zero_guard,
                ...$args
            )
        );
    }
}

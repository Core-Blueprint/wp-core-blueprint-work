<?php
declare(strict_types=1);

use CB\Work\Capabilities;
use CB\Work\Content\PostTypes;
use CB\Work\Database\Schema;
use CB\Work\Time\WorkItemPickerSearch;

/**
 * Opt-in real MariaDB regression for the >500 Work Item Time picker boundary.
 * Uses the canonical Base WordPress PHPUnit test database and rollback.
 */
final class WorkTimePickerScalabilityTest extends WP_UnitTestCase {
    private string $temporary_assignments = '';

    public function set_up(): void {
        parent::set_up();
        if ( ! defined( 'DB_NAME' )
            || ! preg_match( '/(?:^|[_-])test(?:$|[_-])|testing|testdb/i', (string) DB_NAME )
            || ! defined( 'DB_HOST' )
            || ! preg_match( '/^(?:localhost|127\\.0\\.0\\.1|\\[?::1\\]?)(?::[0-9]+)?$/i', (string) DB_HOST )
        ) {
            self::fail( 'Time picker scale fixture requires the local WordPress PHPUnit database.' );
        }

        global $wpdb;
        $table = Schema::assignments_table();
        $result = $wpdb->query( 'CREATE TEMPORARY TABLE ' . $table . ' (
            work_item_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            assigned_at datetime NOT NULL,
            PRIMARY KEY (work_item_id, user_id)
        ) ENGINE=InnoDB' );
        self::assertNotFalse( $result, 'Could not create isolated assignment fixture.' );
        $this->temporary_assignments = $table;

        update_option( Schema::OPTION, CB_WORK_SCHEMA_VERSION );
    }

    public function tear_down(): void {
        global $wpdb;
        if ( '' !== $this->temporary_assignments ) {
            $wpdb->query( 'DROP TEMPORARY TABLE IF EXISTS ' . $this->temporary_assignments );
        }
        wp_set_current_user( 0 );
        parent::tear_down();
    }

    public function test_bounded_search_can_reach_520th_item_without_exposing_unassigned_titles(): void {
        global $wpdb;
        $manager = self::factory()->user->create( [ 'role' => 'administrator' ] );
        wp_set_current_user( $manager );

        $target_id = 0;
        for ( $i = 1; $i <= 520; ++$i ) {
            // Direct SQL fixture avoids running hundreds of post-save hooks;
            // PHPUnit's test transaction rolls back all inserted posts.
            $ok = $wpdb->insert(
                $wpdb->posts,
                [
                    'post_type' => PostTypes::WORK_ITEM,
                    'post_status' => 'draft',
                    'post_title' => sprintf( 'Scale Item %04d', $i ),
                    'post_author' => $manager,
                ]
            );
            self::assertSame( 1, $ok, 'Could not seed Work Item search fixture.' );
            if ( 520 === $i ) {
                $target_id = (int) $wpdb->insert_id;
            }
        }

        self::assertGreaterThan( 0, $target_id );
        $matches = WorkItemPickerSearch::results( 'Scale Item', $manager, true );
        self::assertCount( 20, $matches, 'Search responses must never exceed 20 results.' );
        $last = WorkItemPickerSearch::results( 'Scale Item 0520', $manager, true );
        self::assertCount( 1, $last );
        self::assertSame( $target_id, $last[0]['id'], 'Work Item 520 must be searchable.' );

        $tracker = self::factory()->user->create( [ 'role' => 'subscriber' ] );
        ( new \WP_User( $tracker ) )->add_cap( Capabilities::TRACK_TIME );
        self::assertSame(
            1,
            $wpdb->insert(
                Schema::assignments_table(),
                [
                    'work_item_id' => $target_id,
                    'user_id' => $tracker,
                    'assigned_at' => current_time( 'mysql', true ),
                ]
            )
        );

        wp_set_current_user( $tracker );
        $authorized = WorkItemPickerSearch::results( 'Scale Item 0520', $tracker, false );
        self::assertCount( 1, $authorized, 'Assigned Work Item beyond 500 must be selectable.' );
        self::assertSame( $target_id, $authorized[0]['id'] );
        self::assertSame(
            [],
            WorkItemPickerSearch::results( 'Scale Item 0519', $tracker, false ),
            'Unassigned Work Item titles must never be returned to trackers.'
        );
        self::assertCount( 1, WorkItemPickerSearch::results( 'Scale Item', $tracker, false ) );
        self::assertSame(
            [],
            WorkItemPickerSearch::results( 'Scale Item 0520', $manager, false ),
            'Tracker search may not change identity by passing another user ID.'
        );
    }
}

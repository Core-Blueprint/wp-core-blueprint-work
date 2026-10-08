<?php
declare(strict_types=1);

// Isolated authorization matrix for the Time tracker/manager boundary.
namespace {
    define( 'ABSPATH', '/tmp/wp/' );
    $GLOBALS['time_golden_actor'] = 7;
    $GLOBALS['time_golden_manager'] = false;
    $GLOBALS['time_golden_tracker'] = true;
    $GLOBALS['time_golden_assignees'] = [ 7 ];

    function get_current_user_id(): int { return $GLOBALS['time_golden_actor']; }
    function current_user_can( string $capability ): bool {
        return match ( $capability ) {
            'cb_manage_work' => $GLOBALS['time_golden_manager'],
            'cb_track_work_time' => $GLOBALS['time_golden_tracker'],
            default => false,
        };
    }
    function get_userdata( int $user_id ): object|false {
        return $user_id > 0 && $user_id <= 100 ? (object) [ 'ID' => $user_id ] : false;
    }
}

namespace CB\Work {
    final class Capabilities {
        public const MANAGE = 'cb_manage_work';
        public const TRACK_TIME = 'cb_track_work_time';
    }
}

namespace CB\Work\Repository {
    final class WorkItems {
        public static function get( int $id ): ?array {
            return 30 === $id
                ? [ 'id' => 30, 'assigned_user_ids' => $GLOBALS['time_golden_assignees'] ]
                : null;
        }
    }
}

namespace {
    require dirname( __DIR__ ) . '/src/Time/Access.php';

    use CB\Work\Time\Access;
    $fail = static function ( string $message ): never {
        fwrite( STDERR, "Time Golden access smoke FAILED: {$message}\n" );
        exit( 1 );
    };
    $own = [ 'id' => 42, 'work_item_id' => 30, 'user_id' => 7 ];
    $other = [ 'id' => 43, 'work_item_id' => 30, 'user_id' => 8 ];

    if ( ! Access::can_track() || Access::can_manage()
        || ! Access::can_view_entry( $own )
        || ! Access::can_edit_entry( $own, 30 )
        || Access::can_view_entry( $other )
        || Access::can_edit_entry( $other, 30 )
        || Access::can_track_work_item( 30, 8 )
        || Access::can_track_work_item( 0, 7 )
        || Access::can_edit_entry( $own, 0 ) ) {
        $fail( 'tracker must remain strictly owner-and-assignment scoped' );
    }
    $GLOBALS['time_golden_assignees'] = [];
    if ( Access::can_edit_entry( $own, 30 )
        || ! Access::can_stop_user_timer( 7 )
        || Access::can_stop_user_timer( 8 ) ) {
        $fail( 'assignment revocation blocks corrections without stranding running timer' );
    }
    $GLOBALS['time_golden_manager'] = true;
    $GLOBALS['time_golden_tracker'] = false;
    if ( ! Access::can_manage() || ! Access::can_track()
        || ! Access::can_view_entry( $other )
        || ! Access::can_edit_entry( $other, 30 )
        || ! Access::can_track_work_item( 30, 8 ) ) {
        $fail( 'manager capabilities must provide authorized administrative correction' );
    }
    $GLOBALS['time_golden_manager'] = false;
    if ( Access::can_track() || Access::can_view_entry( $own )
        || Access::can_edit_entry( $own, 30 ) ) {
        $fail( 'user without tracking role must be denied' );
    }
    echo "Time Golden access smoke passed.\n";
}

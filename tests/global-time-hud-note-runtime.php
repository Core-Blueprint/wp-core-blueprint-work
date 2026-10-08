<?php
declare(strict_types=1);

// Focused repository-level transactional regression without WordPress bootstrap.
namespace {
    define( 'ABSPATH', '/tmp/wp/' );
    define( 'CB_WORK_SCHEMA_VERSION', '2.0' );
    function get_option( string $key, string $default = '0' ): string { return '2.0'; }
    function sanitize_textarea_field( string $value ): string { return trim( $value ); }
    function current_time( string $format, bool $gmt = false ): string { return '2026-10-08 15:00:00'; }

    final class FakeTimeDatabase {
        public array $queries = [];
        public array $updates = [];
        public ?array $timer = [ 'time_entry_id' => 19 ];
        public ?array $entry = [
            'id' => 19, 'user_id' => 7, 'ended_at' => null,
            'entry_source' => 'timer', 'note' => 'Old', 'revision' => 3,
        ];
        private int $lookups = 0;

        public function prepare( string $sql, mixed ...$args ): string { return $sql; }
        public function query( string $sql ): int { $this->queries[] = $sql; return 1; }
        public function get_row( string $query, mixed $output = null ): ?array {
            return 0 === $this->lookups++ ? $this->timer : $this->entry;
        }
        public function update( string $table, array $data, array $where, array $format, array $where_format ): int {
            $this->updates[] = [ 'data' => $data, 'where' => $where ];
            return 1;
        }
    }
    define( 'ARRAY_A', 'ARRAY_A' );
}

namespace CB\Work\Database {
    final class Schema {
        public const OPTION = 'cb_work_test_schema';
        public static function active_timers_table(): string { return 'active_timers'; }
        public static function time_entries_table(): string { return 'time_entries'; }
    }
}

namespace CB\Work\Repository {
    final class TimeEntries { public const SOURCE_TIMER = 'timer'; }
}

namespace {
    use CB\Work\Repository\Timers;
    require dirname( __DIR__ ) . '/src/Repository/Timers.php';

    $fail = static function ( string $reason ): never {
        fwrite( STDERR, "Global Timer HUD note runtime FAILED: {$reason}\n" );
        exit( 1 );
    };
    $wpdb = new FakeTimeDatabase();
    if ( Timers::update_active_note( 7, 19, 'New', 8 ) || [] !== $wpdb->queries ) {
        $fail( 'another actor cannot change this timer' );
    }
    $wpdb = new FakeTimeDatabase();
    if ( Timers::update_active_note( 7, 20, 'New', 7 ) || ! in_array( 'ROLLBACK', $wpdb->queries, true ) || [] !== $wpdb->updates ) {
        $fail( 'stale entry id cannot update a different timer' );
    }
    $wpdb = new FakeTimeDatabase();
    $wpdb->entry['ended_at'] = '2026-10-08 15:00:00';
    if ( Timers::update_active_note( 7, 19, 'New', 7 ) || ! in_array( 'ROLLBACK', $wpdb->queries, true ) || [] !== $wpdb->updates ) {
        $fail( 'already completed timer entry cannot be changed' );
    }
    $wpdb = new FakeTimeDatabase();
    if ( ! Timers::update_active_note( 7, 19, 'New', 7 ) || 1 !== count( $wpdb->updates ) ) {
        $fail( 'authorized active note update was rejected' );
    }
    $updated = $wpdb->updates[0];
    if ( 4 !== $updated['data']['revision'] || 'New' !== $updated['data']['note'] || 7 !== $updated['where']['user_id'] || null !== $updated['where']['ended_at'] || ! in_array( 'COMMIT', $wpdb->queries, true ) ) {
        $fail( 'revision, owner or atomic commit contract failed' );
    }
    $wpdb = new FakeTimeDatabase();
    if ( ! Timers::update_active_note( 7, 19, 'Old', 7 ) || [] !== $wpdb->updates ) {
        $fail( 'unchanged note should not create a write' );
    }
    echo "Global Timer HUD note runtime passed.\n";
}

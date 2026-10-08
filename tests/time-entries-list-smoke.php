<?php
declare(strict_types=1);

// Isolated list-domain smoke with a fake read-only DB. No WP database writes.
namespace {
    define( 'ABSPATH', '/tmp/wp/' );
    define( 'ARRAY_A', 'ARRAY_A' );
    define( 'CB_WORK_SCHEMA_VERSION', '2.0' );
    function get_option( string $name, mixed $default = false ): mixed { return '2.0'; }
    function get_current_user_id(): int { return 7; }
    function wp_timezone(): \DateTimeZone { return new \DateTimeZone( 'Europe/Amsterdam' ); }
    function wp_unslash( string $value ): string { return stripslashes( $value ); }
    function sanitize_key( string $s ): string { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $s ) ) ?? ''; }
    function sanitize_text_field( string $s ): string { return trim( strip_tags( $s ) ); }
    function absint( mixed $v ): int { return abs( (int) $v ); }
}

namespace CB\Work\Database {
    final class Schema {
        public const OPTION = 'cb_work_schema_version';
        public static function time_entries_table(): string { return 'wp_cb_work_time_entries'; }
    }
}
namespace CB\Work\Content {
    final class PostTypes { public const WORK_ITEM = 'cb_work_item'; }
}
namespace CB\Work\Admin {
    final class Time { public const VIEW_ENTRIES = 'entries'; }
}
namespace {
    final class ListFakeDb {
        public string $posts = 'wp_posts';
        public array $prepared = [];
        public array $selects = [];
        public int $total = 60;
        public int $seconds = 3754;
        public function prepare( string $sql, mixed ...$args ): string {
            $this->prepared[] = [ 'sql' => $sql, 'args' => $args ];
            return $sql;
        }
        public function esc_like( string $s ): string { return addcslashes( $s, '_%\\' ); }
        public function get_row( string $sql, string $output ): array {
            $this->selects[] = $sql;
            return [ 'total' => $this->total, 'total_seconds' => $this->seconds ];
        }
        public function get_results( string $sql, string $output ): array {
            $this->selects[] = $sql;
            return [];
        }
    }
    require dirname( __DIR__ ) . '/src/Domain/TimeRange.php';
    require dirname( __DIR__ ) . '/src/Repository/TimeEntries.php';
    require dirname( __DIR__ ) . '/src/Admin/TimeEntryListState.php';
    require dirname( __DIR__ ) . '/src/Admin/TimeEntryList.php';

    use CB\Work\Admin\TimeEntryListState;
    use CB\Work\Admin\TimeEntryList;
    use CB\Work\Repository\TimeEntries;

    $fail = static function ( string $message ): never {
        fwrite( STDERR, "Time Entries list smoke FAILED: {$message}\n" );
        exit( 1 );
    };
    $state = TimeEntryListState::from_request( [
        'te_search' => '  <b>Acquisition</b> ',
        'te_from' => '2026-10-08',
        'te_to' => '2026-10-08',
        'te_source' => 'timer',
        'te_user' => '999',
        'te_sort' => 'longest',
        'te_page' => '9',
    ], false );
    if ( 7 !== $state['user_id'] || $state['is_manager'] || 'Acquisition' !== $state['search']
        || 'timer' !== $state['source'] || 'longest' !== $state['sort'] ) {
        $fail( 'tracker self-scope, filtering, or search normalization' );
    }
    if ( '2026-10-07 22:00:00' !== $state['from_utc']
        || '2026-10-08 22:00:00' !== $state['to_utc'] ) {
        $fail( 'local-day UTC boundaries and exclusive end' );
    }
    $bad = TimeEntryListState::from_request( [
        'te_from' => '2026-02-30', 'te_to' => [ 'bad' ],
        'te_source' => 'injection', 'te_sort' => '1; DROP TABLE', 'te_page' => '-5',
    ], true );
    if ( '' !== $bad['from'] || '' !== $bad['to'] || '' !== $bad['source']
        || 'newest' !== $bad['sort'] || $bad['page'] < 1 || $bad['user_id'] !== 0 ) {
        $fail( 'malformed filters must not influence SQL' );
    }
    $spring = TimeEntryListState::from_request( [ 'te_from' => '2026-03-29', 'te_to' => '2026-03-29' ], true );
    if ( '2026-03-28 23:00:00' !== $spring['from_utc'] || '2026-03-29 22:00:00' !== $spring['to_utc'] ) {
        $fail( 'daylight saving transition must form a 23-hour local day' );
    }
    $wpdb = new ListFakeDb();
    $r = TimeEntries::query_completed( $state );
    if ( 60 !== $r['total'] || 3754 !== $r['total_seconds'] || 3 !== $r['page'] || 3 !== $r['pages']
        || '01:02:34' !== TimeEntryList::duration( 3754 ) || '00:00:10' !== TimeEntryList::duration( 10 ) ) {
        $fail( 'summary, clamp or seconds precision' );
    }
    if ( 2 !== count( $wpdb->prepared ) || ! str_contains( $wpdb->prepared[0]['sql'], 'COUNT(*)' )
        || ! str_contains( $wpdb->prepared[0]['sql'], 'SUM(te.duration_seconds)' )
        || ! str_contains( $wpdb->prepared[0]['sql'], 'EXISTS (' )
        || ! str_contains( $wpdb->prepared[1]['sql'], 'ORDER BY te.duration_seconds DESC, te.id DESC LIMIT %d OFFSET %d' )
        || array_slice( $wpdb->prepared[1]['args'], -2 ) !== [ 25, 50 ] ) {
        $fail( 'filtered summary and paginated SQL shape' );
    }
    if ( ! in_array( 7, $wpdb->prepared[0]['args'], true )
        || ! in_array( 'cb_work_item', $wpdb->prepared[0]['args'], true )
        || ! in_array( '%Acquisition%', $wpdb->prepared[0]['args'], true ) ) {
        $fail( 'query must enforce tracker owner and escape search' );
    }
    $urls = TimeEntryListState::url_args( [ ...$state, 'is_manager' => false ], 2 );
    if ( isset( $urls['te_user'] ) || 'Acquisition' !== $urls['te_search']
        || 2 !== $urls['te_page'] || 'timer' !== $urls['te_source'] ) {
        $fail( 'filter preservation without exposing tracker identity parameter' );
    }
    $manager = TimeEntryListState::from_request( [ 'te_user' => '12', 'te_sort' => 'source' ], true );
    if ( 12 !== $manager['user_id'] || ! $manager['is_manager'] || ! isset( TimeEntryListState::url_args( $manager )['te_user'] ) ) {
        $fail( 'manager-only user filtering and pagination URL' );
    }
    echo "Time Entries list smoke passed.\n";
}

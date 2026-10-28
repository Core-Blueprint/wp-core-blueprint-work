<?php
declare(strict_types=1);

// UX-02B: real Project list Assets method with deterministic WordPress stubs.
// Read-only, no database and no WordPress CLI requirement.
namespace {
    define( 'ABSPATH', '/tmp/work-ux-02b/' );
    define( 'CB_WORK_DIR', dirname( __DIR__ ) . '/' );
    define( 'CB_WORK_URL', '/wp-content/plugins/core-blueprint-work/' );
    define( 'CB_WORK_VERSION', '1.0.0-rc1' );

    $GLOBALS['ux02b_screen'] = (object) [ 'base' => 'edit', 'post_type' => 'cb_work_project' ];
    $GLOBALS['ux02b_authorized'] = true;
    $GLOBALS['ux02b_localized'] = [];

    function get_current_screen(): object { return $GLOBALS['ux02b_screen']; }
    function current_user_can( string $capability ): bool {
        return 'cb_manage_work' === $capability && $GLOBALS['ux02b_authorized'];
    }
    function wp_enqueue_style( string $handle, string $url, array $dependencies = [], string|false|null $version = null ): void {}
    function wp_enqueue_script( string $handle, string $url, array $dependencies = [], string|false|null $version = null, bool $footer = false ): void {}
    function wp_localize_script( string $handle, string $name, array $data ): void {
        $GLOBALS['ux02b_localized'] = $data;
    }
    function __( string $message, string $domain ): string {
        if ( 'core-blueprint-work' !== $domain ) { throw new \RuntimeException( 'Text domain drift.' ); }
        return $message;
    }
    function wp_unslash( mixed $value ): mixed { return $value; }
    function sanitize_key( string $key ): string {
        return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', $key ) ?? '' );
    }
}
namespace CB\Work {
    final class Capabilities { public const MANAGE = 'cb_manage_work'; }
}
namespace CB\Work\Content {
    final class PostTypes { public const PROJECT = 'cb_work_project'; }
}
namespace CB\Work\Repository {
    final class Projects {
        public static int $total = 0;
        public static int $calls = 0;
        public static function count(): int { ++self::$calls; return self::$total; }
    }
}
namespace CB\Work\Admin {
    final class Menu {
        public static function projects_url(): string { return '/wp-admin/edit.php?post_type=cb_work_project'; }
    }
    final class ProjectDataExchange {
        public static function available(): bool { return false; }
        public static function import_url(): string { throw new \RuntimeException( 'Import unavailable.' ); }
    }
}
namespace {
    require dirname( __DIR__ ) . '/src/Admin/Assets.php';

    use CB\Work\Admin\Assets;
    use CB\Work\Repository\Projects;

    $checks = 0;
    $expect = static function ( bool $value, string $message ) use ( &$checks ): void {
        ++$checks;
        if ( ! $value ) {
            fwrite( STDERR, "UX-02B Project empty-state runtime FAIL: {$message}\n" );
            exit( 1 );
        }
    };
    $read = static function ( array $query, int $total ): array {
        $_GET = $query;
        Projects::$total = $total;
        Projects::$calls = 0;
        $GLOBALS['ux02b_localized'] = [];
        Assets::enqueue_project_list_action();
        return $GLOBALS['ux02b_localized']['empty'] ?? [];
    };

    $first = $read( [], 0 );
    $expect( 'first_run' === ( $first['kind'] ?? '' ), 'true zero is first run' );
    $expect( 'No projects yet.' === $first['title'], 'first-run title' );
    $expect( str_contains( $first['description'], 'Internal Project' ), 'Internal and Customer paths explained' );
    $expect( '' === $first['actionUrl'] && '' === $first['actionLabel'], 'first run has no duplicate Add button' );
    $expect( 1 === Projects::$calls, 'one canonical Project count for unfiltered list' );

    $normal = $read( [], 3 );
    $expect( 'filtered' === $normal['kind'], 'nonzero universe never claims first run' );
    $expect( 1 === Projects::$calls, 'count is bounded to single canonical read' );
    $expect( 'No matching Projects.' === $normal['title'], 'fallback only used when WP renders no-items row' );

    foreach ( [
        [ 's' => 'unmatched' ],
        [ 'cb_work_context' => 'internal' ],
        [ 'post_status' => 'draft' ],
        [ 'author' => '3' ],
        [ 'm' => '202608' ],
        [ 'paged' => '2' ],
    ] as $query ) {
        $filtered = $read( $query, 0 );
        $expect( 'filtered' === $filtered['kind'], 'active search/filter/page never mistaken for first-run: ' . (string) array_key_first( $query ) );
        $expect( str_contains( $filtered['actionUrl'], 'edit.php?post_type=cb_work_project' ),
            'reset action preserves canonical native route' );
        $expect( 'Clear filters' === $filtered['actionLabel'], 'one clear filter action' );
        $expect( 0 === Projects::$calls, 'filtered contexts do not perform unrelated Project count' );
    }

    $trash = $read( [ 'post_status' => 'trash' ], 0 );
    $expect( 'trash' === $trash['kind'], 'trash recognized explicitly' );
    $expect( 'Trash is empty.' === $trash['title'], 'trash truthful empty state' );
    $expect( 'View all Projects' === $trash['actionLabel'], 'trash return action' );
    $expect( 0 === Projects::$calls, 'trash does not call canonical project count' );

    $all = $read( [ 'post_status' => 'all', 'paged' => '1' ], 0 );
    $expect( 'first_run' === $all['kind'], 'WordPress default status and first page do not masquerade as filtering' );

    $malformed = $read( [ 's' => [ '<script>' ], 'post_status' => [ 'trash' ] ], 0 );
    $expect( 'first_run' === $malformed['kind'], 'non-scalar inputs ignored, never reflected to UI' );

    // No Work manager access: do not derive or expose project metadata.
    $_GET = [];
    $GLOBALS['ux02b_authorized'] = false;
    Projects::$calls = 0;
    $GLOBALS['ux02b_localized'] = [];
    Assets::enqueue_project_list_action();
    $expect( [] === $GLOBALS['ux02b_localized'] && 0 === Projects::$calls,
        'unprivileged actor cannot read or receive Project list state' );

    $keys = [
        'No projects yet.',
        'Create an Internal Project for your own work, or a Customer Project for client work.',
        'No matching Projects.',
        'Try another search or clear your filters.',
        'View all Projects',
        'Trash is empty.',
        'Clear filters',
    ];
    foreach ( [ 'pot', 'nl_NL', 'de_DE', 'fr_FR', 'es_ES', 'it_IT', 'pt_PT' ] as $locale ) {
        $file = 'pot' === $locale ? 'core-blueprint-work.pot' : 'core-blueprint-work-' . $locale . '.po';
        $catalog = file_get_contents( CB_WORK_DIR . 'languages/' . $file );
        $expect( is_string( $catalog ), 'catalog readable ' . $locale );
        foreach ( $keys as $key ) {
            $found = is_string( $catalog ) && preg_match(
                '/msgid "' . preg_quote( $key, '/' ) . '"\s*\nmsgstr "([^"]*)"/u',
                $catalog, $matches
            ) === 1;
            $expect( $found && ( 'pot' === $locale || '' !== trim( $matches[1] ) ),
                'catalog has localized string: ' . $locale . ' ' . $key );
        }
    }

    echo "Work UX-02B Project list empty-state PHP runtime: PASS ({$checks} assertions).\n";
}

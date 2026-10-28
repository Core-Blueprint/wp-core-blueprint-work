<?php
declare(strict_types=1);

/**
 * UX-01B standalone runtime: actual Overview output across first-run,
 * quiet established work, and populated queues. No database or writes.
 */
namespace {
    define( 'ABSPATH', '/tmp/ux-01b/' );

    function current_user_can( string $capability ): bool { return 'cb_manage_work' === $capability; }
    function get_current_user_id(): int { return 7; }
    function current_time( string $format ): string { return 'Y-m-d' === $format ? '2026-10-28' : ''; }
    function wp_timezone(): \DateTimeZone { return new \DateTimeZone( 'UTC' ); }
    function get_option( string $name ): string { return 'date_format' === $name ? 'F j, Y' : ''; }
    function wp_date( string $format, int $timestamp, ?\DateTimeZone $timezone = null ): string {
        return ( new \DateTimeImmutable( '@' . $timestamp ) )->setTimezone( $timezone ?? wp_timezone() )->format( $format );
    }
    function __( string $text, string $domain ): string {
        if ( 'core-blueprint-work' !== $domain ) { throw new \RuntimeException( 'Wrong translation domain.' ); }
        return $text;
    }
    function esc_html_e( string $text, string $domain ): void { echo htmlspecialchars( __( $text, $domain ), ENT_QUOTES, 'UTF-8' ); }
    function esc_html__( string $text, string $domain ): string { return htmlspecialchars( __( $text, $domain ), ENT_QUOTES, 'UTF-8' ); }
    function esc_html( string $text ): string { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
    function esc_attr( string $text ): string { return esc_html( $text ); }
    function esc_url( string $url ): string { return esc_html( $url ); }
    function admin_url( string $path = '' ): string { return '/wp-admin/' . $path; }
    function add_query_arg( array $args, string $url ): string { return $url . '?' . http_build_query( $args ); }
    function wp_die( string $message ): never { throw new \RuntimeException( $message ); }
}
namespace CB\Work {
    final class Capabilities { public const MANAGE = 'cb_manage_work'; }
}
namespace CB\Work\Content {
    final class PostTypes { public const PROJECT = 'cb_work_project'; }
}
namespace CB\Work\Repository {
    final class Projects {
        public static function all( int $limit = 500 ): array { return []; }
    }
    final class WorkItems {
        public static int $count = 0;
        public static function count(): int { return self::$count; }
        public static function get( int $id ): ?array { return null; }
    }
}
namespace CB\Work\Admin {
    final class Menu {
        public const WORK_ITEMS_SLUG = 'core-blueprint-work-items';
        public static function new_work_item_url(): string { return '/wp-admin/post-new.php?post_type=cb_work_item'; }
        public static function time_url(): string { return '/wp-admin/admin.php?page=core-blueprint-work-time'; }
        public static function edit_work_item_url( int $id ): string { return '/wp-admin/post.php?post=' . $id; }
    }
    final class DailyOverview {
        public static array $data = [];
        public static function snapshot( int $user_id ): array { return self::$data; }
    }
}
namespace {
    require dirname( __DIR__ ) . '/src/Admin/Overview.php';

    use CB\Work\Admin\DailyOverview;
    use CB\Work\Admin\Overview;
    use CB\Work\Repository\WorkItems;

    $assertions = 0;
    $check = static function ( bool $condition, string $name ) use ( &$assertions ): void {
        ++$assertions;
        if ( ! $condition ) {
            fwrite( STDERR, "Work UX-01B overview runtime failed: {$name}\n" );
            exit( 1 );
        }
    };
    $capture = static function (): string {
        ob_start();
        try {
            Overview::render();
            return (string) ob_get_clean();
        } catch ( \Throwable $error ) {
            ob_end_clean();
            throw $error;
        }
    };
    $empty_snapshot = [
        'today' => [], 'overdue' => [], 'blocked' => [], 'in_progress' => [],
        'due_soon' => [], 'active_timer' => null, 'ready_to_bill' => [],
    ];
    $messages = [
        'No work scheduled or due today.',
        'No overdue or blocked work.',
        'No work in progress.',
        'No work due in the next seven days.',
    ];
    $panels = static function ( string $html ): array {
        preg_match_all( '~<section class="cb-work-daily-overview__panel(?: cb-work-daily-overview__panel--attention)?">.*?</section>~s', $html, $found );
        return $found[0];
    };

    // First-run: queue clarity without four redundant calls to action.
    DailyOverview::$data = $empty_snapshot;
    WorkItems::$count = 0;
    $first = $capture();
    $first_panels = $panels( $first );
    $check( count( $first_panels ) === 5, 'four queues and commercial handoff panel remain' );
    $check( str_contains( $first, 'cb-work-daily-overview__getting-started' ), 'first-run onboarding remains' );
    $check( 0 === substr_count( $first, 'cb-work-daily-overview__empty-link' ), 'no redundant actions in zero-item workspace' );
    foreach ( $messages as $index => $message ) {
        $check( str_contains( $first_panels[ $index ], $message ), "correct first-run queue {$index}" );
        $check( ! str_contains( $first_panels[ $index ], 'Open Work Items' ), "no first-run queue CTA {$index}" );
    }

    // Established but quiet workspace: exactly two useful navigation links.
    WorkItems::$count = 3;
    $quiet = $capture();
    $quiet_panels = $panels( $quiet );
    $check( ! str_contains( $quiet, 'cb-work-daily-overview__getting-started' ), 'existing user has no onboarding banner' );
    $check( count( $quiet_panels ) === 5, 'all four queues and billing panel remain' );
    foreach ( $messages as $index => $message ) {
        $check( str_contains( $quiet_panels[ $index ], $message ), "context-specific empty message {$index}" );
        $links = substr_count( $quiet_panels[ $index ], 'class="cb-work-daily-overview__empty-link"' );
        $check( ( 0 === $index || 2 === $index ? 1 : 0 ) === $links, "deliberate navigation density {$index}" );
        if ( 1 === $links ) {
            $check( str_contains( $quiet_panels[ $index ], '/wp-admin/admin.php?page=core-blueprint-work-items' ),
                "canonical Work Items route {$index}" );
        }
    }
    $check( str_contains( $quiet_panels[4], 'Nothing is waiting for commercial handoff.' ),
        'Ready to bill keeps its existing truthful empty state' );

    // Populated queues render their real work; no empty copy/links are shown.
    $item = [ 'id' => 42, 'title' => 'Actual work', 'project_id' => 0, 'due_on' => '', 'status' => 'in_progress' ];
    DailyOverview::$data = [
        ...$empty_snapshot,
        'today' => [ $item ],
        'overdue' => [ $item ],
        'blocked' => [ $item ], // duplicate should still be deduplicated by attention queue
        'in_progress' => [ $item ],
        'due_soon' => [ $item ],
    ];
    $populated = $capture();
    $populated_panels = $panels( $populated );
    $check( count( $populated_panels ) === 5, 'populated view structure intact' );
    foreach ( $messages as $index => $message ) {
        $check( ! str_contains( $populated_panels[ $index ], $message ), "empty text hidden in populated queue {$index}" );
        $check( ! str_contains( $populated_panels[ $index ], 'cb-work-daily-overview__empty-link' ), "no empty actions in populated queue {$index}" );
        $check( 1 === substr_count( $populated_panels[ $index ], 'Actual work' ), "work row preserved / deduplicated {$index}" );
    }

    // i18n: each new literal must be accounted for in POT and all six POs.
    $root = dirname( __DIR__ );
    foreach ( [ 'pot', 'nl_NL', 'de_DE', 'fr_FR', 'es_ES', 'it_IT', 'pt_PT' ] as $locale ) {
        $path = 'pot' === $locale
            ? $root . '/languages/core-blueprint-work.pot'
            : $root . '/languages/core-blueprint-work-' . $locale . '.po';
        $catalog = file_get_contents( $path );
        $check( is_string( $catalog ), "catalog readable {$locale}" );
        foreach ( $messages as $message ) {
            $pattern = '/msgid "' . preg_quote( $message, '/' ) . '"\s*\nmsgstr "([^"]*)"/u';
            $matched = is_string( $catalog ) && preg_match( $pattern, $catalog, $found ) === 1;
            $check( $matched && ( 'pot' === $locale || '' !== trim( $found[1] ) ),
                "translated empty text {$message} / {$locale}" );
        }
    }

    $css = file_get_contents( $root . '/assets/work-overview.css' );
    $check( is_string( $css )
        && str_contains( $css, '.cb-work-daily-overview__empty-link' )
        && str_contains( $css, 'var(--cb-accent)' )
        && str_contains( $css, '.cb-work-daily-overview__empty-link:focus-visible' ),
        'Work-scoped native link contrast and focus remain Base-token owned' );
    $check( is_string( $css )
        && str_contains( $css, 'grid-template-columns: repeat(2, minmax(0, 1fr))' ),
        'Today and Needs attention keep the two-column desktop grid' );

    echo "Work UX-01B Overview empty-state runtime: PASS ({$assertions} assertions; new/existing/populated, locale, Base and layout).\n";
}

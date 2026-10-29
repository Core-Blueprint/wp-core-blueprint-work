<?php
declare(strict_types=1);

// UX-01A: runtime first-use behavior, no WordPress DB or production mutation.
namespace {
    define( 'ABSPATH', '/tmp/ux-01a/' );

    $GLOBALS['ux01a_can_manage'] = true;

    function current_user_can( string $capability ): bool {
        return 'cb_manage_work' === $capability && $GLOBALS['ux01a_can_manage'];
    }
    function get_current_user_id(): int { return 7; }
    function current_time( string $format ): string { return 'Y-m-d' === $format ? '2026-10-28' : ''; }
    function wp_timezone(): \DateTimeZone { return new \DateTimeZone( 'UTC' ); }
    function get_option( string $name ): string { return 'date_format' === $name ? 'F j, Y' : ''; }
    function wp_date( string $format, int $timestamp, ?\DateTimeZone $timezone = null ): string {
        return ( new \DateTimeImmutable( '@' . $timestamp ) )->setTimezone( $timezone ?? wp_timezone() )->format( $format );
    }
    function __( string $text, string $domain ): string {
        if ( 'core-blueprint-work' !== $domain ) { throw new \RuntimeException( 'Text domain drift.' ); }
        return $text;
    }
    function esc_html_e( string $text, string $domain ): void { echo htmlspecialchars( __( $text, $domain ), ENT_QUOTES, 'UTF-8' ); }
    function esc_html__( string $text, string $domain ): string { return htmlspecialchars( __( $text, $domain ), ENT_QUOTES, 'UTF-8' ); }
    function esc_html( string $text ): string { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
    function esc_attr( string $text ): string { return esc_html( $text ); }
    function esc_url( string $url ): string { return esc_html( $url ); }
    function admin_url( string $path = '' ): string { return '/wp-admin/' . $path; }
    function add_query_arg( array $params, string $url ): string { return $url . '?' . http_build_query( $params ); }
    function wp_die( string $message ): never { throw new \RuntimeException( $message ); }
}
namespace CB\Work {
    final class Capabilities { public const MANAGE = 'cb_manage_work'; }
}
namespace CB\Work\Content {
    final class PostTypes { public const PROJECT = 'cb_work_project'; }
}
namespace CB\Work\Repository {
    final class WorkItems {
        public static int $count = 0;
        public static int $calls = 0;
        public static function count(): int { ++self::$calls; return self::$count; }
        public static function get( int $id ): ?array { return null; }
    }
    final class Projects {
        public static array $rows = [];
        public static function all( int $limit = 500 ): array { return self::$rows; }
    }
}
namespace CB\Work\Admin {
    final class Menu {
        public const WORK_ITEMS_SLUG = 'core-blueprint-work-items';
        public const UNIFIED_WORKSPACE_SLUG = 'core-blueprint-work-workspace';
        public static function new_work_item_url(): string { return '/wp-admin/post-new.php?post_type=cb_work_item'; }
        public static function time_url(): string { return '/wp-admin/admin.php?page=core-blueprint-work-time'; }
        public static function edit_work_item_url( int $id ): string { return '/wp-admin/post.php?post=' . $id; }
    }
    final class DailyOverview {
        public static function snapshot( int $user_id ): array {
            return [
                'today' => [],
                'overdue' => [],
                'blocked' => [],
                'in_progress' => [],
                'due_soon' => [],
                'active_timer' => null,
                'ready_to_bill' => [],
            ];
        }
    }
}
namespace {
    require dirname( __DIR__ ) . '/src/Admin/Overview.php';

    use CB\Work\Admin\Overview;
    use CB\Work\Repository\Projects;
    use CB\Work\Repository\WorkItems;

    $checks = 0;
    $assert = static function ( bool $condition, string $label ) use ( &$checks ): void {
        ++$checks;
        if ( ! $condition ) {
            fwrite( STDERR, "UX-01A Overview first-run FAIL: {$label}.\n" );
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

    // Blank workspace: offer two choices without creating any demo records.
    WorkItems::$count = 0;
    WorkItems::$calls = 0;
    Projects::$rows = [];
    $blank = $capture();
    $assert( 1 === substr_count( $blank, 'class="cb-work-daily-overview__getting-started"' ), 'one first-run panel' );
    $assert( str_contains( $blank, 'aria-labelledby="cb-work-daily-overview-getting-started-title"' ), 'accessible section name' );
    $assert( str_contains( $blank, 'No Work Items yet.' ), 'clear first step' );
    $assert( str_contains( $blank, '/wp-admin/post-new.php?post_type=cb_work_item' ), 'Work Item canonical creation route' );
    $assert( str_contains( $blank, '/wp-admin/post-new.php?post_type=cb_work_project' ), 'Project canonical native creation route' );
    $assert( 1 === substr_count( $blank, '/wp-admin/post-new.php?post_type=cb_work_item' )
        && ! str_contains( $blank, 'Open Time' ), 'first-run shows one primary Work Item action without duplicate header actions' );
    $assert( 1 === WorkItems::$calls, 'single canonical Work Item count check' );
    $assert( 1 === substr_count( $blank, 'class="cb-work-daily-overview__grid"' )
        && str_contains( $blank, 'Needs attention' )
        && str_contains( $blank, 'Scheduled or due today.' ), 'Today and Needs attention panels preserved' );

    // Projects are optional: existing Projects without Work Items still offer first-run.
    Projects::$rows = [ [ 'id' => 17, 'title' => 'Test Project' ] ];
    $project_only = $capture();
    $assert( str_contains( $project_only, 'cb-work-daily-overview__getting-started' ), 'Project-only workspace can still start work' );

    // An established workspace must never show onboarding, even with no due tasks.
    Projects::$rows = [];
    WorkItems::$count = 2;
    $established = $capture();
    $assert( ! str_contains( $established, 'cb-work-daily-overview__getting-started' ), 'existing Work Items hide onboarding' );
    $assert( 1 === substr_count( $established, '/wp-admin/post-new.php?post_type=cb_work_item' )
        && str_contains( $established, 'Open Time' ), 'established Work keeps normal header actions' );
    $assert( str_contains( $established, '/wp-admin/admin.php?page=core-blueprint-work-workspace&amp;section=work-items' )
        && ! str_contains( $established, '/wp-admin/admin.php?page=core-blueprint-work-items' ),
        'Overview queue routes target unified Work, not hidden native Work Items' );
    $assert( str_contains( $established, 'cb-work-daily-overview__focus' )
        && str_contains( $established, 'cb-work-daily-overview__ready' ), 'existing KPIs and billing snapshot remain' );

    // No privilege: no page output and no Work Item count access.
    $GLOBALS['ux01a_can_manage'] = false;
    WorkItems::$calls = 0;
    try {
        $capture();
        $assert( false, 'unauthorized render must throw' );
    } catch ( \RuntimeException $error ) {
        $assert( str_contains( $error->getMessage(), 'permission' ), 'capability gate remains enforced' );
    }
    $assert( 0 === WorkItems::$calls, 'unauthorized actor never queries Work Items' );

    // No new UI message IDs: existing six locale catalogs cover this patch.
    $pot = file_get_contents( dirname( __DIR__ ) . '/languages/core-blueprint-work.pot' );
    foreach ( [
        'Getting started',
        'No Work Items yet.',
        'Add Work Item',
        'Add Project',
    ] as $message ) {
        $assert( is_string( $pot ) && str_contains( $pot, 'msgid "' . $message . '"' ), 'existing i18n catalog covers ' . $message );
    }

    $css = file_get_contents( dirname( __DIR__ ) . '/assets/work-overview.css' );
    $assert( is_string( $css )
        && str_contains( $css, '.cb-work-daily-overview__getting-started' )
        && str_contains( $css, 'var(--cb-work-overview-raised)' )
        && str_contains( $css, '@media screen and (max-width: 782px)' ), 'scoped Base tokens and responsive first-run CSS' );
    $assert( is_string( $css )
        && str_contains( $css, 'grid-template-columns: repeat(2, minmax(0, 1fr))' ), 'desktop Today and Needs attention stay side by side' );

    echo "Work UX-01A Overview first-run runtime: PASS ({$checks} assertions; first-run, established, permissions, locale and layout).\n";
}

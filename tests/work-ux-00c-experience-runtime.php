<?php
declare(strict_types=1);

/**
 * UX-00C runtime contract: WordPress-native Work navigation and presentation
 * behave consistently across roles and screen contexts.
 *
 * Standalone test fixture. No WP bootstrap, database, application mutations,
 * theme changes or mocks of Work production methods.
 */
namespace {
    define( 'ABSPATH', '/tmp/ux-00c/' );

    final class WP_Screen {
        public function __construct( public string $post_type = '' ) {}
    }

    $GLOBALS['ux00c_capabilities'] = [];
    $GLOBALS['ux00c_screen'] = new WP_Screen();
    $GLOBALS['ux00c_menu'] = [];
    $GLOBALS['ux00c_submenu'] = [];
    $GLOBALS['ux00c_removed'] = [];

    function current_user_can( string $capability ): bool {
        return in_array( $capability, $GLOBALS['ux00c_capabilities'], true );
    }
    function __( string $label, string $domain ): string {
        if ( 'core-blueprint-work' !== $domain ) {
            throw new \RuntimeException( 'Unexpected text domain.' );
        }
        return $label;
    }
    function sanitize_key( string $value ): string {
        return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ) ?? '';
    }
    function wp_unslash( string $value ): string {
        return $value;
    }
    function get_current_screen(): ?WP_Screen {
        return $GLOBALS['ux00c_screen'];
    }
    function add_menu_page( mixed ...$arguments ): void {
        $GLOBALS['ux00c_menu'][] = $arguments;
    }
    function add_submenu_page( mixed ...$arguments ): void {
        $GLOBALS['ux00c_submenu'][] = $arguments;
    }
    function remove_submenu_page( mixed ...$arguments ): void {
        $GLOBALS['ux00c_removed'][] = $arguments;
    }
    function admin_url( string $path = '' ): string {
        return '/wp-admin/' . $path;
    }
    function add_query_arg( array $args, string $url ): string {
        return $url . '?' . http_build_query( $args );
    }
}

namespace CB\Work {
    final class Capabilities {
        public const MANAGE = 'cb_manage_work';
        public const TRACK_TIME = 'cb_track_time';
    }
}
namespace CB\Work\Content {
    final class PostTypes {
        public const PROJECT = 'cb_work_project';
        public const SERVICE = 'cb_work_service';
        public const WORK_ITEM = 'cb_work_item';
    }
}
namespace CoreBlueprint\Core\UI {
    final class AdminTheme {
        public static array $registered = [];
        public static function register_screen( string $hook_suffix ): void {
            self::$registered[] = $hook_suffix;
        }
    }
}

namespace {
    require_once dirname( __DIR__ ) . '/src/Admin/Menu.php';
    require_once dirname( __DIR__ ) . '/src/Admin/Workspace.php';

    use CB\Work\Admin\Menu;
    use CB\Work\Admin\Workspace;
    use CB\Work\Capabilities;
    use CB\Work\Content\PostTypes;
    use CoreBlueprint\Core\UI\AdminTheme;

    $assertions = 0;
    $expect = static function ( bool $pass, string $what ) use ( &$assertions ): void {
        ++$assertions;
        if ( ! $pass ) {
            fwrite( STDERR, "UX-00C runtime FAIL: {$what}.\n" );
            exit( 1 );
        }
    };
    $role = static function ( string $name ): void {
        $GLOBALS['ux00c_capabilities'] = match ( $name ) {
            'manager' => [ Capabilities::MANAGE, Capabilities::TRACK_TIME ],
            'tracker' => [ Capabilities::TRACK_TIME ],
            default => [],
        };
        $GLOBALS['ux00c_menu'] = [];
        $GLOBALS['ux00c_submenu'] = [];
        $GLOBALS['ux00c_removed'] = [];
    };

    // Native WP menu registration with the UX-00A order, never a custom shell.
    $role( 'manager' );
    Menu::register();
    $expect( 1 === count( $GLOBALS['ux00c_menu'] ), 'manager has one Work top-level menu' );
    $expect( Menu::TOP_LEVEL_SLUG === $GLOBALS['ux00c_menu'][0][3], 'manager Work route unchanged' );
    $expect( Capabilities::MANAGE === $GLOBALS['ux00c_menu'][0][2], 'manager capability unchanged' );

    $expected = [
        [ 'Overview', Menu::TOP_LEVEL_SLUG, 5 ],
        [ 'Projects', Menu::projects_path(), 10 ],
        [ 'Work Items', Menu::WORK_ITEMS_SLUG, 15 ],
        [ 'Time', Menu::TIME_SLUG, 20 ],
        [ 'Service Operations', Menu::SERVICE_OPERATIONS_SLUG, 25 ],
        [ 'Recurring Work', Menu::RECURRENCE_SLUG, 30 ],
        [ 'Services', Menu::services_path(), 35 ],
        [ 'Work Types', Menu::WORK_TYPES_SLUG, 40 ],
    ];
    $expect( 10 === count( $GLOBALS['ux00c_submenu'] ), 'manager keeps eight visible and two contextual routes' );
    foreach ( $expected as $index => [ $label, $slug, $position ] ) {
        $args = $GLOBALS['ux00c_submenu'][ $index ];
        $expect( Menu::TOP_LEVEL_SLUG === $args[0]
            && $label === $args[2]
            && Capabilities::MANAGE === $args[3]
            && $slug === $args[4]
            && $position === $args[6], 'manager order/route/capability for ' . $label );
    }
    Menu::hide_contextual_submenu_pages();
    $expect( [
        [ Menu::TOP_LEVEL_SLUG, Menu::PROJECT_WORKSPACE_SLUG ],
        [ Menu::TOP_LEVEL_SLUG, Menu::PROJECT_IMPORT_SLUG ],
    ] === $GLOBALS['ux00c_removed'], 'manager contextual pages hidden after registration' );
    $expect( str_contains( Menu::time_url(), 'page=' . Menu::TIME_SLUG ), 'manager Time URL stays subordinate' );

    // Tracker-only users must land in Time, without any management links.
    $role( 'tracker' );
    Menu::register();
    $expect( 1 === count( $GLOBALS['ux00c_menu'] ), 'tracker has one top-level menu' );
    $expect( Capabilities::TRACK_TIME === $GLOBALS['ux00c_menu'][0][2]
        && 'CB\\Work\\Admin\\Time' === $GLOBALS['ux00c_menu'][0][4][0], 'tracker top-level callback is Time' );
    $expect( 2 === count( $GLOBALS['ux00c_submenu'] )
        && Menu::TOP_LEVEL_SLUG === $GLOBALS['ux00c_submenu'][0][4]
        && Menu::TIME_SLUG === $GLOBALS['ux00c_submenu'][1][4], 'tracker registers only Time and its hidden alias' );
    Menu::hide_contextual_submenu_pages();
    $expect( [ [ Menu::TOP_LEVEL_SLUG, Menu::TIME_SLUG ] ] === $GLOBALS['ux00c_removed'], 'tracker Time alias hidden' );
    $expect( str_contains( Menu::time_url(), 'page=' . Menu::TOP_LEVEL_SLUG ), 'tracker Time URLs use top-level route' );

    $role( 'none' );
    Menu::register();
    $expect( [] === $GLOBALS['ux00c_menu'] && [] === $GLOBALS['ux00c_submenu'], 'no Work menu without access' );

    // Exercise the actual screen-context mapping, active WP submenu and body
    // class. Contextual native CPT screens must stay under Work's parent menu.
    $screen_cases = [
        [ 'manager', Menu::TOP_LEVEL_SLUG, '', Menu::CONTEXT_OVERVIEW, Menu::TOP_LEVEL_SLUG ],
        [ 'manager', Menu::WORK_ITEMS_SLUG, '', Menu::CONTEXT_WORK_ITEMS, Menu::WORK_ITEMS_SLUG ],
        [ 'manager', Menu::TIME_SLUG, '', Menu::CONTEXT_TIME, Menu::TIME_SLUG ],
        [ 'manager', Menu::SERVICE_OPERATIONS_SLUG, '', Menu::CONTEXT_SERVICE_OPERATIONS, Menu::SERVICE_OPERATIONS_SLUG ],
        [ 'manager', Menu::RECURRENCE_SLUG, '', Menu::CONTEXT_RECURRENCE, Menu::RECURRENCE_SLUG ],
        [ 'manager', Menu::WORK_TYPES_SLUG, '', Menu::CONTEXT_WORK_TYPES, Menu::WORK_TYPES_SLUG ],
        [ 'manager', Menu::PROJECT_WORKSPACE_SLUG, '', Menu::CONTEXT_PROJECTS, Menu::projects_path() ],
        [ 'manager', Menu::PROJECT_IMPORT_SLUG, '', Menu::CONTEXT_PROJECTS, Menu::projects_path() ],
        [ 'manager', '', PostTypes::PROJECT, Menu::CONTEXT_PROJECTS, Menu::projects_path() ],
        [ 'manager', '', PostTypes::SERVICE, Menu::CONTEXT_SERVICES, Menu::services_path() ],
        [ 'manager', '', PostTypes::WORK_ITEM, Menu::CONTEXT_WORK_ITEMS, Menu::WORK_ITEMS_SLUG ],
        [ 'tracker', Menu::TOP_LEVEL_SLUG, '', Menu::CONTEXT_TIME, Menu::TOP_LEVEL_SLUG ],
        [ 'tracker', Menu::TIME_SLUG, '', Menu::CONTEXT_TIME, Menu::TOP_LEVEL_SLUG ],
        [ 'manager', '', 'post', '', 'index.php' ],
    ];
    foreach ( $screen_cases as [ $who, $page, $post_type, $context, $submenu ] ) {
        $role( $who );
        $_GET = '' === $page ? [] : [ 'page' => $page ];
        $GLOBALS['ux00c_screen'] = new WP_Screen( $post_type );
        $prefix = "{$who}/{$page}/{$post_type}";
        $expect( $context === Menu::screen_context(), "screen context {$prefix}" );
        $expected_parent = '' === $context ? 'index.php' : Menu::TOP_LEVEL_SLUG;
        $expect( $expected_parent === Menu::parent_file( 'index.php' ), "WordPress parent {$prefix}" );
        $expect( $submenu === Menu::submenu_file( 'index.php' ), "active submenu {$prefix}" );
        $body = Workspace::body_class( 'wp-admin-screen' );
        $expect( ( '' === $context ? 'wp-admin-screen' : 'wp-admin-screen cb-work-workspace-screen' ) === $body,
            "Work-only body class {$prefix}" );
    }

    // Base AdminTheme is registered via the public hook for Work screens
    // only. An unrelated WordPress screen must remain untouched.
    $role( 'manager' );
    $_GET = [];
    $GLOBALS['hook_suffix'] = 'edit.php';
    AdminTheme::$registered = [];
    Menu::register_admin_theme_screen( new WP_Screen( PostTypes::PROJECT ) );
    Menu::register_admin_theme_screen( new WP_Screen( 'post' ) );
    $expect( [ 'edit.php' ] === AdminTheme::$registered, 'Base Admin Theme is enabled for Work CPT only' );
    unset( $GLOBALS['hook_suffix'] );

    // UX-00B CSS is exclusively scoped to the Work body class, not global
    // wp-admin, Base's admin bar, buttons, theme or native CPT list tables.
    $css = file_get_contents( dirname( __DIR__ ) . '/assets/work-workspace.css' );
    $expect( is_string( $css ), 'shared stylesheet readable' );
    $without_comments = preg_replace( '~\/\*.*?\*\/~s', '', (string) $css );
    $expect( is_string( $without_comments ), 'shared CSS can be parsed' );
    preg_match_all( '/([^{}]+)\{/', (string) $without_comments, $rules );
    $expect( count( $rules[1] ) >= 10, 'shared CSS contains Work header and responsive rules' );
    foreach ( $rules[1] as $selector_group ) {
        foreach ( explode( ',', trim( $selector_group ) ) as $selector ) {
            $selector = trim( $selector );
            $expect( str_starts_with( $selector, '.cb-work-workspace-screen' )
                || str_starts_with( $selector, '@media ' ), "CSS selector stays Work-scoped: {$selector}" );
        }
    }
    $expect( str_contains( $css, '@media screen and (max-width: 782px)' )
        && str_contains( $css, '@media screen and (max-width: 480px)' ), 'desktop and narrow responsive rules retained' );

    echo "Work UX-00C experience runtime: PASS ({$assertions} assertions, native navigation, role contexts, Base and CSS scope).\n";
}

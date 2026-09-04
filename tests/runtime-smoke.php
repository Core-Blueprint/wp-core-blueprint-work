<?php
declare(strict_types=1);

namespace {
	define( 'ABSPATH', '/tmp/wp/' );
	define( 'ARRAY_A', 'ARRAY_A' );
	define( 'CB_CORE_API_VERSION', '1.0' );

	$GLOBALS['hooks'] = [];
	$GLOBALS['did'] = [];
	$GLOBALS['current_hook'] = null;
	$GLOBALS['activation'] = null;
	$GLOBALS['options'] = [];
	$GLOBALS['post_types'] = [];
	$GLOBALS['post_meta'] = [];
	$GLOBALS['menus'] = [];
	$GLOBALS['submenus'] = [];
	$GLOBALS['roles'] = [
		'administrator' => new class { public array $caps = []; public function add_cap( string $cap ): void { $this->caps[] = $cap; } },
		'cb_operator' => new class { public array $caps = []; public function add_cap( string $cap ): void { $this->caps[] = $cap; } },
	];

	$GLOBALS['wpdb'] = new class {
		public string $prefix = 'wp_';
		public int $insert_id = 0;
		public function get_var( string $sql ): mixed { return str_contains( $sql, 'COUNT(*)' ) ? 2 : null; }
		public function get_results( string $sql, string $output ): array { return []; }
		public function get_row( string $sql, string $output ): ?array { return null; }
		public function prepare( string $sql, mixed ...$args ): string { return vsprintf( str_replace( [ '%d', '%s' ], [ '%d', "'%s'" ], $sql ), $args ); }
		public function get_charset_collate(): string { return 'CHARSET=utf8mb4'; }
	};

	function add_action( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool { $GLOBALS['hooks'][ $hook ][ $priority ][] = [ $callback, $accepted_args, false ]; return true; }
	function add_filter( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool { $GLOBALS['hooks'][ $hook ][ $priority ][] = [ $callback, $accepted_args, true ]; return true; }
	function do_action( string $hook, mixed ...$args ): void {
		$GLOBALS['did'][ $hook ] = ( $GLOBALS['did'][ $hook ] ?? 0 ) + 1;
		$previous = $GLOBALS['current_hook'];
		$GLOBALS['current_hook'] = $hook;
		$priorities = $GLOBALS['hooks'][ $hook ] ?? [];
		ksort( $priorities );
		foreach ( $priorities as $callbacks ) {
			foreach ( $callbacks as [ $callback, $accepted_args ] ) { $callback( ...array_slice( $args, 0, $accepted_args ) ); }
		}
		$GLOBALS['current_hook'] = $previous;
	}
	function apply_filters( string $hook, mixed $value, mixed ...$args ): mixed {
		$previous = $GLOBALS['current_hook'];
		$GLOBALS['current_hook'] = $hook;
		$priorities = $GLOBALS['hooks'][ $hook ] ?? [];
		ksort( $priorities );
		foreach ( $priorities as $callbacks ) {
			foreach ( $callbacks as [ $callback, $accepted_args ] ) { $value = $callback( ...array_slice( [ $value, ...$args ], 0, $accepted_args ) ); }
		}
		$GLOBALS['current_hook'] = $previous;
		return $value;
	}
	function did_action( string $hook ): int { return (int) ( $GLOBALS['did'][ $hook ] ?? 0 ); }
	function doing_action( ?string $hook = null ): bool { return null === $hook ? null !== $GLOBALS['current_hook'] : $GLOBALS['current_hook'] === $hook; }
	function plugin_dir_path( string $file ): string { return dirname( $file ) . '/'; }
	function plugin_dir_url( string $file ): string { return 'https://example.test/wp-content/plugins/core-blueprint-work/'; }
	function plugin_basename( string $file ): string { return 'core-blueprint-work/' . basename( $file ); }
	function register_activation_hook( string $file, callable $callback ): void { $GLOBALS['activation'] = $callback; }
	function register_deactivation_hook( string $file, callable $callback ): void {}
	function load_plugin_textdomain( string $domain, bool $deprecated = false, string $path = '' ): bool { return true; }
	function is_admin(): bool { return true; }
	function current_user_can( string $capability ): bool { return true; }
	function admin_url( string $path = '' ): string { return 'https://example.test/wp-admin/' . ltrim( $path, '/' ); }
	function sanitize_key( string $key ): string { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', $key ) ?? '' ); }
	function sanitize_title( string $value ): string { $v = strtolower( trim( $value ) ); return trim( preg_replace( '/[^a-z0-9]+/', '-', $v ) ?? '', '-' ); }
	function sanitize_text_field( string $value ): string { return trim( strip_tags( $value ) ); }
	function __( string $text, string $domain = 'default' ): string { return $text; }
	function esc_html__( string $text, string $domain = 'default' ): string { return $text; }
	function esc_html( string $text ): string { return $text; }
	function wp_die( string $message = '', string $title = '', array $args = [] ): never { throw new \RuntimeException( $title . ': ' . $message ); }
	function get_role( string $role ): ?object { return $GLOBALS['roles'][ $role ] ?? null; }
	function deactivate_plugins( string $plugin ): void { throw new \RuntimeException( 'Unexpected deactivation: ' . $plugin ); }
	function get_option( string $key, mixed $default = false ): mixed { return $GLOBALS['options'][ $key ] ?? $default; }
	function update_option( string $key, mixed $value, bool $autoload = true ): bool { $GLOBALS['options'][ $key ] = $value; return true; }
	function register_post_type( string $type, array $args ): void { $GLOBALS['post_types'][ $type ] = $args; }
	function register_post_meta( string $type, string $key, array $args ): void { $GLOBALS['post_meta'][ $type ][ $key ] = $args; }
	function wp_count_posts( string $post_type ): object { return (object) [ 'publish' => 2, 'draft' => 1, 'trash' => 4 ]; }
	function add_menu_page( string $page_title, string $menu_title, string $capability, string $menu_slug, callable $callback, string $icon_url = '', int|float|null $position = null ): string {
		$GLOBALS['menus'][ $menu_slug ] = compact( 'page_title', 'menu_title', 'capability', 'menu_slug', 'callback', 'icon_url', 'position' );
		return 'toplevel_page_' . $menu_slug;
	}
	function add_submenu_page( string $parent_slug, string $page_title, string $menu_title, string $capability, string $menu_slug, callable|string $callback = '', int|float|null $position = null ): string {
		$GLOBALS['submenus'][ $parent_slug ][ $menu_slug ] = compact( 'parent_slug', 'page_title', 'menu_title', 'capability', 'menu_slug', 'callback', 'position' );
		return $parent_slug . '_page_' . sanitize_key( $menu_slug );
	}

	function assert_true( bool $condition, string $message ): void { if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); } }
}

namespace CB\Core\Admin {
	interface Page { public function slug(): string; public function title(): string; public function menu_title(): string; public function capability(): string; public function position(): ?int; public function render(): void; }
	final class PageRegistry { public static array $registrations = []; public static function register( Page $page, array $requirements = [] ): bool { self::$registrations[ $page->slug() ] = [ $page, $requirements ]; return true; } }
}

namespace CB\Core\Dashboard {
	final class CardRegistry { public static array $shortcuts = []; public static function register_shortcut( string $card_id, array $shortcut ): bool { self::$shortcuts[ $card_id ][ $shortcut['id'] ] = $shortcut; return true; } }
}

namespace CB\Core\Database {
	final class SchemaRegistry { public static array $definitions = []; public static function register( array $definition ): bool { self::$definitions[ $definition['id'] ] = $definition; return true; } }
}

namespace CB\Core {
	final class ExtensionRegistry { public static array $registrations = []; public static function register( array $definition ): bool { self::$registrations[ $definition['id'] ] = $definition; return true; } }
}

namespace CB\Core\Governance {
	final class Audit { public static function record( string $id, string $severity = 'info', array $context = [] ): bool { return true; } }
	final class EventRegistry { public static array $events = []; public static function register( array $definition ): bool { self::$events[ $definition['id'] ] = $definition; return true; } }
}

namespace CB\Core\UI {
	final class Notice { public const SUCCESS = 'success'; public const INFO = 'info'; public const ERROR = 'error'; public static function render( array $args ): string { return '<div></div>'; } }
}

namespace {
	require dirname( __DIR__ ) . '/core-blueprint-work.php';

	add_action( 'plugins_loaded', static function (): void {
		if ( isset( \CB\Core\Database\SchemaRegistry::$definitions['core-blueprint-work'] ) ) { $GLOBALS['options']['cb_work_db_version'] = '1.0'; }
	}, 5 );
	add_action( 'plugins_loaded', static function (): void { do_action( 'cb_core_booted' ); }, 25 );

	do_action( 'plugins_loaded' );
	assert_true( '1.0.0-rc2.1' === CB_WORK_VERSION, 'Candidate exposes unambiguous rc2.1 staging version.' );
	assert_true( isset( \CB\Core\Database\SchemaRegistry::$definitions['core-blueprint-work'] ), 'Work schema registers before Base sweep.' );
	assert_true( \CB\Work\Plugin::is_booted(), 'Product runtime boots after Base signal.' );

	do_action( 'init' );
	assert_true( isset( $GLOBALS['post_types']['cb_work_service'] ), 'Canonical Work service post type registers.' );
	assert_true( isset( $GLOBALS['post_meta']['cb_work_service']['_cb_work_service_pricing_model'] ), 'Service pricing model meta registers.' );
	assert_true( isset( \CB\Core\Governance\EventRegistry::$events['work.tax.rate.created'] ), 'Work VAT governance events register.' );

	do_action( 'admin_menu' );
	assert_true( isset( $GLOBALS['menus']['core-blueprint-work'] ), 'Work owns a normal top-level WP Admin menu.' );
	assert_true( isset( $GLOBALS['submenus']['core-blueprint-work']['edit.php?post_type=cb_work_service'] ), 'Services are mounted under the Work menu.' );

	do_action( 'cb_core_register_extensions' );
	$extension = \CB\Core\ExtensionRegistry::$registrations['core-blueprint-work'] ?? null;
	assert_true( is_array( $extension ), 'Extension registers through Base public contract.' );
	assert_true( ! array_key_exists( 'requires_base', $extension ), 'Extension does not pin an internal Base RC.' );
	assert_true( str_contains( (string) ( $extension['menu_url'] ?? '' ), 'page=core-blueprint-work' ), 'Suite extension link opens operational Work.' );

	$status_defs = apply_filters( 'cb_core_module_status_definitions', [] );
	$status = ( $status_defs['work']['provider'] )();
	assert_true( 'ok' === ( $status['state'] ?? '' ), 'Work health is ok after schema/runtime boot.' );
	assert_true( '3 services · 2 VAT rates' === ( $status['detail'] ?? '' ), 'Work health exposes bounded factual product counts.' );
	assert_true( str_contains( (string) ( $status['url'] ?? '' ), 'page=core-blueprint-work' ), 'Work health opens the operational Work workspace.' );

	do_action( 'cb_core_register_pages' );
	$page = \CB\Core\Admin\PageRegistry::$registrations['core-blueprint-work-settings'] ?? null;
	assert_true( is_array( $page ), 'Work settings register through Base PageRegistry.' );
	$components = $page[1]['components'] ?? [];
	assert_true( ! in_array( 'nav-tabs', $components, true ), 'Work settings do not request operational nav tabs.' );
	assert_true( ! in_array( 'tables', $components, true ), 'Work settings do not request unsupported tables component.' );
	assert_true( str_contains( \CB\Work\Admin\Page::settings_url(), 'page=core-blueprint-work-settings' ), 'VAT Settings route stays in Core Blueprint settings.' );
	assert_true( ! str_contains( \CB\Work\Admin\Page::settings_url(), 'view=' ), 'VAT Settings route has no legacy operational view parameter.' );

	do_action( 'cb_core_dashboard_register_cards' );
	assert_true( isset( \CB\Core\Dashboard\CardRegistry::$shortcuts['core-blueprint-work']['workspace'] ), 'Work workspace shortcut registers.' );
	assert_true( str_contains( (string) \CB\Core\Dashboard\CardRegistry::$shortcuts['core-blueprint-work']['workspace']['url'], 'page=core-blueprint-work' ), 'Work workspace shortcut opens operational Work.' );
	assert_true( isset( \CB\Core\Dashboard\CardRegistry::$shortcuts['core-blueprint-work']['services'] ), 'Work services shortcut registers.' );

	$catalog = apply_filters( 'cb_core_capability_catalog', [] );
	assert_true( isset( $catalog['cb_manage_work'] ), 'Work capability is in Base capability catalog.' );

	$activation = $GLOBALS['activation'];
	assert_true( is_callable( $activation ), 'Activation hook is registered.' );
	$activation();
	foreach ( [ 'administrator', 'cb_operator' ] as $role ) { assert_true( in_array( 'cb_manage_work', $GLOBALS['roles'][ $role ]->caps, true ), "{$role} receives cb_manage_work." ); }

	echo "Runtime smoke passed.\n";
}

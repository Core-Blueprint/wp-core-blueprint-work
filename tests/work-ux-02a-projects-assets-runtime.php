<?php
declare(strict_types=1);

// UX-02A: exercise the real Work Assets dispatcher with bounded WP stubs.
// No WordPress boot, persistence, requests or remote service calls.
namespace {
	define( 'ABSPATH', '/tmp/work-ux-02a/' );
	define( 'CB_WORK_DIR', dirname( __DIR__ ) . '/' );
	define( 'CB_WORK_URL', '/wp-content/plugins/core-blueprint-work/' );
	define( 'CB_WORK_VERSION', '1.0.0-rc1' );

	$GLOBALS['ux02a_screen'] = (object) [ 'base' => 'edit', 'post_type' => 'cb_work_project' ];
	$GLOBALS['ux02a_manage'] = true;
	$GLOBALS['ux02a_styles'] = [];
	$GLOBALS['ux02a_scripts'] = [];
	$GLOBALS['ux02a_localizations'] = [];

	function get_current_screen(): object {
		return $GLOBALS['ux02a_screen'];
	}
	function current_user_can( string $capability ): bool {
		return 'cb_manage_work' === $capability && $GLOBALS['ux02a_manage'];
	}
	function wp_enqueue_script( string $handle, string $url, array $deps = [], string|false|null $version = null, bool $footer = false ): void {
		$GLOBALS['ux02a_scripts'][ $handle ] = compact( 'url', 'deps', 'version', 'footer' );
	}
	function wp_enqueue_style( string $handle, string $url, array $deps = [], string|false|null $version = null ): void {
		$GLOBALS['ux02a_styles'][ $handle ] = compact( 'url', 'deps', 'version' );
	}
	function wp_localize_script( string $handle, string $name, array $data ): void {
		$GLOBALS['ux02a_localizations'][ $handle ] = compact( 'name', 'data' );
	}
	function __( string $message, string $domain ): string {
		if ( 'core-blueprint-work' !== $domain ) {
			throw new \RuntimeException( 'Unexpected translation domain.' );
		}
		return $message;
	}
}
namespace CB\Work {
	final class Capabilities { public const MANAGE = 'cb_manage_work'; }
}
namespace CB\Work\Content {
	final class PostTypes { public const PROJECT = 'cb_work_project'; }
}
namespace CB\Work\Admin {
	final class ProjectDataExchange {
		public static bool $available = true;
		public static int $import_calls = 0;
		public static function available(): bool {
			return self::$available;
		}
		public static function import_url(): string {
			++self::$import_calls;
			return '/wp-admin/admin.php?page=core-blueprint-work-project-import';
		}
	}
}
namespace {
	require dirname( __DIR__ ) . '/src/Admin/Assets.php';

	use CB\Work\Admin\Assets;
	use CB\Work\Admin\ProjectDataExchange;

	$assertions = 0;
	$check = static function ( bool $condition, string $label ) use ( &$assertions ): void {
		++$assertions;
		if ( ! $condition ) {
			fwrite( STDERR, "UX-02A Project assets FAIL: {$label}\n" );
			exit( 1 );
		}
	};
	$clear = static function (): void {
		$GLOBALS['ux02a_styles'] = [];
		$GLOBALS['ux02a_scripts'] = [];
		$GLOBALS['ux02a_localizations'] = [];
	};
	$handle = 'cb-work-projects-list';

	// Native Project list, manager, import installed.
	$clear();
	Assets::enqueue_project_list_action();
	$check( isset( $GLOBALS['ux02a_styles'][ $handle ] ), 'scoped Project list CSS enqueued' );
	$check( str_ends_with( $GLOBALS['ux02a_styles'][ $handle ]['url'], 'assets/projects-list.css' ), 'correct CSS route' );
	$check( isset( $GLOBALS['ux02a_scripts'][ $handle ] ), 'Project list JS enqueued' );
	$check( str_ends_with( $GLOBALS['ux02a_scripts'][ $handle ]['url'], 'assets/projects-list.js' ), 'correct JS route' );
	$check( true === $GLOBALS['ux02a_scripts'][ $handle ]['footer'], 'deferred JS in footer' );
	$data = $GLOBALS['ux02a_localizations'][ $handle ]['data'];
	$check( str_contains( $data['description'], 'internal and customer projects' ), 'localizable context description' );
	$check( str_contains( $data['importUrl'], 'project-import' ), 'optional existing Import route' );
	$check( 'Import project' === $data['importLabel'], 'Import action label retained' );

	// Import absent: still enhance layout and keep native Add Project.
	$clear();
	ProjectDataExchange::$available = false;
	ProjectDataExchange::$import_calls = 0;
	Assets::enqueue_project_list_action();
	$data = $GLOBALS['ux02a_localizations'][ $handle ]['data'];
	$check( isset( $GLOBALS['ux02a_styles'][ $handle ], $GLOBALS['ux02a_scripts'][ $handle ] ), 'Project list UX works without Import' );
	$check( '' === $data['importUrl'] && '' === $data['importLabel'], 'Import absent when provider unavailable' );
	$check( 0 === ProjectDataExchange::$import_calls, 'missing provider import URL never requested' );

	// Guard unrelated WordPress lists, editors and users with no Work manage cap.
	foreach ( [
		[ 'edit', 'post', true ],
		[ 'post', 'cb_work_project', true ],
		[ 'edit', 'cb_work_service', true ],
		[ 'edit', 'cb_work_project', false ],
	] as [ $base, $post_type, $manager ] ) {
		$clear();
		$GLOBALS['ux02a_screen'] = (object) [ 'base' => $base, 'post_type' => $post_type ];
		$GLOBALS['ux02a_manage'] = $manager;
		Assets::enqueue_project_list_action();
		$check( [] === $GLOBALS['ux02a_styles'] && [] === $GLOBALS['ux02a_scripts']
			&& [] === $GLOBALS['ux02a_localizations'], "no Project assets for {$base}/{$post_type}/manager=" . (int) $manager );
	}

	// Canonical gettext key in POT and all six PO catalogs.
	$message = 'Organize internal and customer projects. Open a workspace to see its Work Items.';
	foreach ( [ 'pot', 'nl_NL', 'de_DE', 'fr_FR', 'es_ES', 'it_IT', 'pt_PT' ] as $locale ) {
		$filename = 'pot' === $locale ? 'core-blueprint-work.pot' : 'core-blueprint-work-' . $locale . '.po';
		$catalog = file_get_contents( CB_WORK_DIR . 'languages/' . $filename );
		$check( is_string( $catalog ) && str_contains( $catalog, 'msgid "' . $message . '"' ), "gettext key in {$locale}" );
	}

	$css = file_get_contents( CB_WORK_DIR . 'assets/projects-list.css' );
	$check( is_string( $css )
		&& str_contains( $css, '.cb-work-workspace-screen .cb-work-projects-list__header' )
		&& str_contains( $css, '@media screen and (max-width: 782px)' )
		&& str_contains( $css, '@media screen and (max-width: 480px)' ),
		'Work-only layout and mobile breakpoints' );
	$check( is_string( $css ) && ! str_contains( $css, '#wpbody' )
		&& ! str_contains( $css, '#adminmenu' ) && ! str_contains( $css, 'data-cb-theme' ),
		'Base and WordPress global administration stay untouched' );

	echo "Work UX-02A Project list assets runtime: PASS ({$assertions} assertions).\n";
}

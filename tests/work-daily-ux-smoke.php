<?php
declare(strict_types=1);

$root      = dirname( __DIR__ );
$menu      = file_get_contents( $root . '/src/Admin/Menu.php' );
$workspace = file_get_contents( $root . '/src/Admin/Workspace.php' );
$overview  = file_get_contents( $root . '/src/Admin/Overview.php' );
$daily     = file_get_contents( $root . '/src/Admin/DailyOverview.php' );
$quick     = file_get_contents( $root . '/src/Admin/QuickAdd.php' );
$plugin    = file_get_contents( $root . '/src/Plugin.php' );
$assets    = file_get_contents( $root . '/src/Admin/Assets.php' );
$operations= file_get_contents( $root . '/src/Admin/Operations.php' );
$quick_js  = file_get_contents( $root . '/assets/work-quick-add.js' );
$fast_js   = file_get_contents( $root . '/assets/work-fast-paths.js' );
$overview_css = file_get_contents( $root . '/assets/work-overview.css' );

function daily_ux_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "Work daily UX smoke failed: {$message}\n" );
		exit( 1 );
	}
}

daily_ux_assert(
	false !== $menu && false !== $workspace && false !== $overview && false !== $daily
	&& false !== $quick && false !== $plugin && false !== $assets && false !== $operations
	&& false !== $quick_js && false !== $fast_js && false !== $overview_css,
	'Daily UX source files are readable.'
);

daily_ux_assert(
	str_contains( $menu, "[ Overview::class, 'render' ]" )
	&& str_contains( $overview, 'DailyOverview::snapshot' )
	&& str_contains( $overview, "__( 'Ready to bill'" ),
	'Work manager landing is the action-first Daily Overview.'
);

daily_ux_assert(
	str_contains( $daily, 'private const QUEUE_LIMIT = 8' )
	&& str_contains( $daily, "'per_page' => self::QUEUE_LIMIT" )
	&& str_contains( $daily, 'Timers::active_for_user' )
	&& str_contains( $daily, 'Billing::ready( self::QUEUE_LIMIT )' )
	&& ! preg_match( '/\b(?:update_post_meta|add_post_meta|delete_post_meta|wp_insert_post|wp_update_post|wpdb->)\b/', $daily ),
	'Daily Overview is bounded and read-only over canonical Work truth.'
);

daily_ux_assert(
	str_contains( $workspace, "add_filter( 'admin_body_class', [ self::class, 'body_class' ] )" )
	&& str_contains( $workspace, 'Menu::screen_context()' )
	&& str_contains( $workspace, 'cb-work-workspace-screen' )
	&& ! str_contains( $workspace, 'remove_submenu_page' )
	&& ! str_contains( $workspace, 'hide_duplicate_submenus' ),
	'WordPress native sidebar navigation remains canonical while Work adds only scoped workspace context.'
);

daily_ux_assert(
	str_contains( $quick, "add_action( 'admin_post_' . self::ACTION" )
	&& str_contains( $quick, 'check_admin_referer( self::ACTION )' )
	&& str_contains( $quick, 'current_user_can( Capabilities::MANAGE )' )
	&& str_contains( $quick, 'WorkItemActions::create' )
	&& ! preg_match( '/\b(?:update_post_meta|add_post_meta|delete_post_meta|wp_insert_post|wp_update_post|wpdb->)\b/', $quick ),
	'Quick Add uses nonce/capability protection and the governed WorkItemActions seam.'
);

daily_ux_assert(
	str_contains( $plugin, 'QuickAdd::init();' )
	&& str_contains( $assets, "assets/work-quick-add.js" )
	&& str_contains( $quick_js, 'post-new.php' )
	&& str_contains( $quick_js, 'post_type=cb_work_item' )
	&& str_contains( $quick_js, 'data-cb-work-quick-add-full' ),
	'Quick Add progressively enhances canonical Gutenberg links without removing fallback.'
);

daily_ux_assert(
	str_contains( $assets, "assets/work-fast-paths.js" )
	&& str_contains( $operations, "__( 'My work', 'core-blueprint-work' )" )
	&& str_contains( $operations, "__( 'Blocked', 'core-blueprint-work' )" )
	&& str_contains( $operations, "__( 'Overdue', 'core-blueprint-work' )" )
	&& str_contains( $operations, 'cb-work-fast-paths' )
	&& str_contains( $fast_js, "event.key === '/'" )
	&& str_contains( $fast_js, "cb-work-filter-search" ),
	'Work Items exposes server-rendered low-cognitive-load focus presets and a keyboard search fast path.'
);

daily_ux_assert(
	str_contains( $overview_css, '@media screen and (max-width: 782px)' )
	&& ! str_contains( $quick_js, 'jQuery' )
	&& ! str_contains( $fast_js, 'jQuery' ),
	'Daily UX remains responsive and vanilla JS.'
);

echo "Work daily UX smoke passed.\n";

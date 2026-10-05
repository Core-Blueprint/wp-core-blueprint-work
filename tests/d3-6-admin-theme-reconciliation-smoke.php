<?php
declare(strict_types=1);

$root   = dirname( __DIR__ );
$menu   = file_get_contents( $root . '/src/Admin/Menu.php' );
$assets = file_get_contents( $root . '/src/Admin/Assets.php' );
$css    = file_get_contents( $root . '/assets/work-admin.css' );
$js         = file_get_contents( $root . '/assets/work-admin.js' );
$operations = file_get_contents( $root . '/src/Admin/Operations.php' );
$plugin     = file_get_contents( $root . '/core-blueprint-work.php' );

$checks = [
	'Work release identity remains rc1' => str_contains( $plugin, "define( 'CB_WORK_VERSION', '1.0.0-rc1' )" ) && str_contains( $plugin, "define( 'CB_WORK_SCHEMA_VERSION', '1.9' )" ),
	'Work declares its admin screens compatible with Base Admin Theme' => str_contains( $menu, "add_action( 'current_screen', [ self::class, 'register_admin_theme_screen' ] )" ) && str_contains( $menu, '\\CoreBlueprint\\Core\\UI\\AdminTheme::register_screen( $hook_suffix );' ),
	'Admin Theme declaration covers canonical Work screen contexts' => str_contains( $menu, "self::screen_context( \$screen )" ) && str_contains( $menu, "PostTypes::WORK_ITEM" ) && str_contains( $menu, "PostTypes::PROJECT" ) && str_contains( $menu, "PostTypes::SERVICE" ),
	'Work presentation uses the current public Base Admin Theme enqueue hook' => str_contains( $assets, "add_action( 'core_blueprint_admin_theme_enqueue', [ self::class, 'enqueue' ], 10, 4 )" ) && ! str_contains( $assets, "add_action( '" . 'cb_admin_theme_' . "enqueue'" ),
	'Work does not couple to Base internal asset handles or URLs' => ! str_contains( $assets, 'cb-core-css-admin-theme' ) && ! str_contains( $assets, 'cb-core-css-tokens' ) && ! str_contains( $assets, 'CB_CORE_URL' ),
	'Work CSS consumes semantic Base tokens' => str_contains( $css, 'var(--cb-surface-1)' ) && str_contains( $css, 'var(--cb-border)' ) && str_contains( $css, 'var(--cb-space-4)' ) && str_contains( $css, 'var(--cb-interactive-hover)' ),
	'Work CSS does not detect theme slugs or modes' => ! str_contains( $css, 'data-cb-theme' ) && ! str_contains( $css, 'data-cb-mode' ) && ! str_contains( $css, 'core_blueprint_dark' ) && ! str_contains( $css, 'core_blueprint_light' ),
	'Work CSS does not own WordPress admin canvas or chrome' => ! str_contains( $css, '#wpcontent' ) && ! str_contains( $css, '#wpbody' ) && ! str_contains( $css, '#wpadminbar' ) && ! str_contains( $css, '#adminmenu' ),
	'Work CSS does not theme native nav tabs' => ! str_contains( $css, '.cb-work-items-page .nav-tab {' ) && ! str_contains( $css, '.nav-tab-active' ),
	'Work CSS contains no hardcoded presentation colours' => ! preg_match( '/#[0-9a-f]{3,8}\b/i', $css ) && ! preg_match( '/\brgba?\s*\(/i', $css ),
	'Work keeps domain-specific toolbar Board and Calendar composition' => str_contains( $css, '.cb-work-toolbar' ) && str_contains( $css, '.cb-work-items-kanban' ) && str_contains( $css, '.cb-work-items-calendar' ),
	'active filter summary is preserved as Work-specific UX' => str_contains( $js, 'cb-work-filter-summary' ) && str_contains( $js, 'appendSummaryChip' ) && str_contains( $css, '.cb-work-filter-chip' ),
	'Calendar Today action is preserved as server-rendered navigation without domain persistence' => str_contains( $operations, "current_time( 'Y-m' )" )
		&& str_contains( $operations, "'calendar_month' => \$current_month" )
		&& str_contains( $operations, "'Today', 'core-blueprint-work'" )
		&& str_contains( $operations, 'self::work_items_url( $state' )
		&& ! str_contains( $js, 'enhanceCalendarNavigation' ),
	'presentation JS does not own Admin Theme state' => ! str_contains( $js, 'cbAdminTheme' ) && ! str_contains( $js, 'data-cb-theme' ) && ! str_contains( $js, 'data-cb-mode' ) && ! str_contains( $js, 'matchMedia' ),
	'presentation JS remains transport and storage free' => ! str_contains( $js, 'fetch(' ) && ! str_contains( $js, 'XMLHttpRequest' ) && ! str_contains( $js, 'localStorage' ) && ! str_contains( $js, 'sessionStorage' ),
];

$failed = false;
foreach ( $checks as $label => $passed ) {
	if ( ! $passed ) {
		fwrite( STDERR, "D3.6 Admin Theme reconciliation smoke failed: {$label}\n" );
		$failed = true;
	}
}

exit( $failed ? 1 : 0 );

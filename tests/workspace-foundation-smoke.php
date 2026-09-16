<?php
declare(strict_types=1);

$root      = dirname( __DIR__ );
$plugin    = file_get_contents( $root . '/src/Plugin.php' );
$workspace = file_get_contents( $root . '/src/Admin/Workspace.php' );
$menu      = file_get_contents( $root . '/src/Admin/Menu.php' );
$assets    = file_get_contents( $root . '/src/Admin/Assets.php' );
$css       = file_get_contents( $root . '/assets/work-workspace.css' );

$fail = static function ( string $message ): never {
	fwrite( STDERR, "Workspace foundation smoke failed: {$message}\n" );
	exit( 1 );
};

if ( false === $plugin || false === $workspace || false === $menu || false === $assets || false === $css ) {
	$fail( 'Expected workspace source files are readable.' );
}

$checks = [
	[
		str_contains( $plugin, 'use CB\\Work\\Admin\\Workspace;' )
			&& str_contains( $plugin, 'Workspace::init();' ),
		'Plugin boot wires the shared Work admin context.',
	],
	[
		str_contains( $workspace, "add_filter( 'admin_body_class'" )
			&& ! str_contains( $workspace, "add_action( 'all_admin_notices'" )
			&& ! str_contains( $workspace, 'remove_submenu_page' ),
		'Workspace keeps only scoped presentation context and does not replace native navigation.',
	],
	[
		! str_contains( $workspace, 'cb-work-workspace-shell' )
			&& ! str_contains( $workspace, 'cb-work-workspace-nav' )
			&& ! str_contains( $workspace, 'Operational workspace' ),
		'Custom in-page Work application header and navigation remain absent.',
	],
	[
		str_contains( $menu, 'add_submenu_page( self::TOP_LEVEL_SLUG' )
			&& str_contains( $menu, "__( 'Overview', 'core-blueprint-work' )" )
			&& str_contains( $menu, "__( 'Work Items', 'core-blueprint-work' )" )
			&& str_contains( $menu, "__( 'Recurring Work', 'core-blueprint-work' )" )
			&& str_contains( $menu, "__( 'Time', 'core-blueprint-work' )" )
			&& str_contains( $menu, "__( 'Projects', 'core-blueprint-work' )" )
			&& str_contains( $menu, "__( 'Services', 'core-blueprint-work' )" )
			&& str_contains( $menu, "__( 'Work Types', 'core-blueprint-work' )" ),
		'Canonical Work destinations remain available through WordPress-native submenu routes.',
	],
	[
		str_contains( $assets, '$context = Menu::screen_context();' )
			&& str_contains( $assets, 'self::enqueue_workspace_style();' )
			&& str_contains( $assets, 'Menu::CONTEXT_WORK_ITEMS !== $context' ),
		'Shared Work token styling loads across Work while Work Item JS stays scoped.',
	],
	[
		str_contains( $css, 'var(--cb-surface-1)' )
			&& str_contains( $css, 'var(--cb-border)' )
			&& str_contains( $css, 'var(--cb-interactive-transition)' ),
		'Work presentation continues to consume Base design tokens.',
	],
	[
		! str_contains( $css, '.cb-work-workspace-shell' )
			&& ! str_contains( $css, '.cb-work-workspace-nav' ),
		'Legacy custom workspace shell styling remains removed.',
	],
];

foreach ( $checks as [ $passed, $message ] ) {
	if ( ! $passed ) {
		$fail( $message );
	}
}

echo "Workspace foundation smoke passed.\n";

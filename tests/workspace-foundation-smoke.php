<?php
declare(strict_types=1);

$root      = dirname( __DIR__ );
$plugin    = file_get_contents( $root . '/src/Plugin.php' );
$workspace = file_get_contents( $root . '/src/Admin/Workspace.php' );
$assets    = file_get_contents( $root . '/src/Admin/Assets.php' );
$css       = file_get_contents( $root . '/assets/work-workspace.css' );

$fail = static function ( string $message ): never {
	fwrite( STDERR, "Workspace foundation smoke failed: {$message}\n" );
	exit( 1 );
};

if ( false === $plugin || false === $workspace || false === $assets || false === $css ) {
	$fail( 'Expected workspace source files are readable.' );
}

$checks = [
	[
		str_contains( $plugin, 'use CB\\Work\\Admin\\Workspace;' )
			&& str_contains( $plugin, 'Workspace::init();' ),
		'Plugin boot wires the shared Work workspace shell.',
	],
	[
		str_contains( $workspace, "add_action( 'all_admin_notices'" )
			&& str_contains( $workspace, "add_filter( 'admin_body_class'" ),
		'Workspace composition is centralized instead of duplicated across renderers.',
	],
	[
		str_contains( $workspace, "__( 'Overview', 'core-blueprint-work' )" )
			&& str_contains( $workspace, "__( 'Projects', 'core-blueprint-work' )" )
			&& str_contains( $workspace, "__( 'Work Items', 'core-blueprint-work' )" )
			&& str_contains( $workspace, "__( 'Time', 'core-blueprint-work' )" ),
		'Primary workspace navigation exposes the four daily operational anchors.',
	],
	[
		str_contains( $workspace, "__( 'Recurring Work', 'core-blueprint-work' )" )
			&& str_contains( $workspace, "__( 'Services', 'core-blueprint-work' )" )
			&& str_contains( $workspace, "__( 'Work Types', 'core-blueprint-work' )" )
			&& str_contains( $workspace, "__( 'Settings', 'core-blueprint-work' )" ),
		'Management destinations remain available without crowding primary navigation.',
	],
	[
		! str_contains( $workspace, 'Ready to Bill' )
			&& ! str_contains( $workspace, 'Invoice' )
			&& ! str_contains( $workspace, 'CRM' ),
		'Foundation does not introduce dead-end or sibling-domain navigation.',
	],
	[
		str_contains( $workspace, 'Capabilities::MANAGE' )
			&& str_contains( $workspace, 'Capabilities::TRACK_TIME' ),
		'Workspace navigation remains capability-aware.',
	],
	[
		str_contains( $assets, '$context = Menu::screen_context();' )
			&& str_contains( $assets, 'self::enqueue_workspace_style();' )
			&& str_contains( $assets, 'Menu::CONTEXT_WORK_ITEMS !== $context' ),
		'Shared workspace styling loads across Work while Work Item JS stays scoped.',
	],
	[
		str_contains( $css, 'var(--cb-surface-1)' )
			&& str_contains( $css, 'var(--cb-border)' )
			&& str_contains( $css, 'var(--cb-interactive-hover)' ),
		'Workspace composition consumes Base design tokens instead of inventing a parallel theme.',
	],
	[
		! str_contains( $workspace, '<script' )
			&& ! str_contains( $workspace, 'jQuery' ),
		'Workspace shell requires no client-side framework or router.',
	],
];

foreach ( $checks as [ $passed, $message ] ) {
	if ( ! $passed ) {
		$fail( $message );
	}
}

echo "Workspace foundation smoke passed.\n";

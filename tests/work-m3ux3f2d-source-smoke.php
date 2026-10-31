<?php
declare(strict_types=1);

/**
 * M3-UX3-F2D: view density follows Base tokens, with Work-only scoped knobs.
 * Do not change the larger main navigation or native Work Items renderer.
 */
$root = dirname( __DIR__ );
$css = (string) file_get_contents( $root . '/assets/css/unified-workspace.css' );
$shell = (string) file_get_contents( $root . '/src/Admin/UnifiedWorkspace.php' );
$renderer = (string) file_get_contents( $root . '/src/Admin/Operations.php' );

$checks = [
	'Base control height and button spacing are the source of Work density tokens' =>
		str_contains( $css, '--cb-work-view-switch-height: var(--cb-control-height, 32px);' ) &&
		str_contains( $css, '--cb-work-view-switch-touch-height: calc(var(--cb-control-height, 32px) + var(--cb-space-3, 12px));' ) &&
		str_contains( $css, '--cb-work-view-switch-padding-block: var(--cb-button-padding-block-compact, var(--cb-space-1, 4px));' ) &&
		str_contains( $css, '--cb-work-view-switch-padding-inline: var(--cb-button-padding-inline-compact, var(--cb-space-3, 12px));' ),
	'Compact desktop segmented view uses Work geometry tokens, not hardcoded px' =>
		str_contains( $css, 'min-height: var(--cb-work-view-switch-height);' ) &&
		str_contains( $css, 'padding: var(--cb-work-view-switch-padding-block) var(--cb-work-view-switch-padding-inline);' ) &&
		str_contains( $css, 'gap: var(--cb-work-view-switch-inset);' ) &&
		str_contains( $css, 'padding: var(--cb-work-view-switch-inset);' ),
	'Context row is tighter only inside Tasks; touch targets stay larger' =>
		str_contains( $css, '[data-cb-work-uwe-panel="work-items"] .cb-work-uwe__context {' ) &&
		str_contains( $css, 'padding-block: var(--cb-work-view-context-padding-block);' ) &&
		str_contains( $css, 'min-height: var(--cb-work-view-switch-touch-height);' ) &&
		str_contains( $css, '.cb-work-uwe__tab { min-height: 44px; }' ),
	'Real SPA switcher and Golden native renderer are unmodified' =>
		str_contains( $shell, 'data-cb-work-uwe-view-switcher' ) &&
		sha1( 'blob ' . strlen( $renderer ) . "\0" . $renderer ) === '92a4814f24b43f96d16e3d96ce61a49ee9f21500',
];
foreach ( $checks as $label => $ok ) {
	if ( ! $ok ) {
		fwrite( STDERR, "Work M3-UX3-F2D SOURCE FAIL: {$label}\n" );
		exit( 1 );
	}
}
echo "Work M3-UX3-F2D SOURCE: PASS (Base tokens, compact view, accessible touch, native Golden).\n";

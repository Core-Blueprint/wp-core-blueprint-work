<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$shell = (string) file_get_contents( $root . '/src/Admin/UnifiedWorkspace.php' );
$operations = (string) file_get_contents( $root . '/src/Admin/WorkspaceOperationsPanel.php' );
$engagements = (string) file_get_contents( $root . '/assets/js/workspace/engagements.js' );
$app = (string) file_get_contents( $root . '/assets/js/workspace/app.js' );
$css = (string) file_get_contents( $root . '/assets/css/unified-workspace.css' );
$panel = (string) file_get_contents( $root . '/src/Admin/WorkspaceEngagementsPanel.php' );
$native = (string) file_get_contents( $root . '/src/Admin/Operations.php' );
$checks = [
	'Service Operations is a Services subview, not a global operation shortcut' =>
		str_contains( $shell, "if ( 'services' === \$key && current_user_can( Capabilities::MANAGE ) )" ) &&
		str_contains( $shell, 'data-cb-work-m3-service-list' ) &&
		str_contains( $shell, 'data-cb-work-m3-services-inline' ) &&
		str_contains( $shell, 'WorkspaceOperationsPanel::render_service_link();' ) &&
		str_contains( $operations, 'public static function render_service_link(): void' ) &&
		substr_count( $operations, 'data-cb-work-m3-service-link' ) === 1 &&
		str_contains( $operations, 'Menu::SERVICE_OPERATIONS_SLUG' ),
	'Direct Services URL-state and Base modal child actions remain guarded' =>
		str_contains( $engagements, 'export function isServiceOperationsView(href)' ) &&
		str_contains( $engagements, 'export function serviceOperationsUrl(href, showOperations)' ) &&
		str_contains( $engagements, "browser.history.pushState({cbWorkServicesView:true}" ) &&
		str_contains( $engagements, "browser.addEventListener?.('popstate', onPopState)" ) &&
		str_contains( $engagements, 'const host = inlineMode ? null : createOperationDialog(' ) &&
		str_contains( $engagements, 'openChild(() => billingDialog.open(' ) &&
		str_contains( $engagements, 'openChild(() => ruleDialog.open(' ) &&
		str_contains( $app, 'operationalViews.engagements?.sync?.(section)' ),
	'Errors retain an explicit native fallback and no-JS link' =>
		str_contains( $panel, 'Menu::SERVICE_OPERATIONS_SLUG' ) &&
		str_contains( $engagements, "panel.classList.add('is-error')" ) &&
		str_contains( $css, '.cb-work-m3-engagements.is-error > a.button' ),
	'Button hover is never underlined, real text links and focus indicators remain' =>
		! str_contains( $css, "border-color: currentColor;\n\ttext-decoration: underline;" ) &&
		str_contains( $css, '.cb-work-uwe__service-view:focus-visible' ) &&
		str_contains( $css, '.cb-work-uwe__service-view[hidden]' ) &&
		str_contains( $css, '.cb-work-m3-operations__item[data-cb-work-m3-time-link]' ) &&
		str_contains( $css, 'text-decoration: none;' ),
	'Golden native operations renderer remains unchanged' =>
		sha1( 'blob ' . strlen( $native ) . "\0" . $native ) ===
			'92a4814f24b43f96d16e3d96ce61a49ee9f21500',
];
foreach ($checks as $name => $ok) {
	if (!$ok) { fwrite(STDERR, "Work M3-UX4 source FAIL: {$name}\n"); exit(1); }
}
echo "Work M3-UX4 source: PASS (Services subview, URL, fallback, focus, native Golden).\n";

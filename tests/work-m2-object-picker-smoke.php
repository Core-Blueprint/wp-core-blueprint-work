<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$bridge = (string) file_get_contents( $root . '/src/Admin/UnifiedEditorPickers.php' );
$actions = (string) file_get_contents( $root . '/src/Admin/UnifiedEditorActions.php' );
$view = (string) file_get_contents( $root . '/src/Admin/UnifiedWorkspace.php' );
$editor = (string) file_get_contents( $root . '/assets/js/workspace/editors.js' );
$assets = (string) file_get_contents( $root . '/src/Admin/Assets.php' );
$tests = (string) file_get_contents( $root . '/tools/check' );
$contracts = [
	'CRM references validated via the existing Work CRM adapter' =>
		str_contains( $bridge, 'CRMCustomers::reference(' )
		&& str_contains( $bridge, 'CRMCustomers::selected(' )
		&& str_contains( $bridge, 'CRMCustomers::available()' )
		&& str_contains( $bridge, 'work_editor_customer_inherited' ),
	'User selection checked through existing Work assignment domain' =>
		str_contains( $bridge, 'WorkItemLinks::normalize_assignments(' )
		&& str_contains( $bridge, 'work_editor_assignees_invalid' )
		&& str_contains( $bridge, 'count( $selected ) <= 100' ),
	'Managed, nonce-gated read and typed write boundary' =>
		str_contains( $actions, 'check_ajax_referer( self::NONCE_ACTION' )
		&& str_contains( $actions, 'current_user_can( Capabilities::MANAGE )' )
		&& str_contains( $actions, 'UnifiedEditorPickers::model(' )
		&& str_contains( $actions, 'UnifiedEditorPickers::normalize(' )
		&& ! str_contains( $actions, 'update_post_meta(' ),
	'Base picker is dependency and dynamically initialized, not reimplemented' =>
		str_contains( $assets, 'Assets::enqueue_object_picker()' )
		&& str_contains( $assets, "'@cb-core/object-picker'" )
		&& str_contains( $editor, "import '@cb-core/object-picker';" )
		&& str_contains( $editor, 'browser.cbCore.objectPicker.init(body)' )
		&& str_contains( $editor, "'cb_work_search_customers'" )
		&& str_contains( $editor, "'cb_work_search_users'" )
		&& ! str_contains( $editor, 'innerHTML =' ),
	'All new user-facing picker labels use Work translation domain' =>
		str_contains( $view, "'Search WordPress users…', 'core-blueprint-work'" )
		&& str_contains( $view, "'Search contacts and organizations…', 'core-blueprint-work'" ),
	'Level 1 runs the new picker runtime gate' =>
		str_contains( $tests, 'work-m2-object-picker-runtime.mjs' ),
];
foreach ( $contracts as $name => $ok ) {
	if ( ! $ok ) {
		fwrite( STDERR, "M2 Base picker source contract FAILED: {$name}\n" );
		exit( 1 );
	}
}
echo "M2 Base picker and Work customer/assignee source contracts: PASS.\n";

<?php
declare(strict_types=1);
$root = dirname( __DIR__ );
$api = (string) file_get_contents( $root . '/src/Transport/Rest/WorkspaceTimeController.php' );
$js = (string) file_get_contents( $root . '/assets/js/workspace/time-entry.js' );
$panel = (string) file_get_contents( $root . '/src/Admin/WorkspaceTimePanel.php' );
$time = (string) file_get_contents( $root . '/assets/js/workspace/time.js' );
$compiler = (string) file_get_contents( $root . '/tools/prepare-workspace-modules.py' );
$checks = [
	'own-entry read/write with guarded routes' =>
		str_contains( $api, "'callback' => [ self::class, 'entry_detail' ]" ) &&
		str_contains( $api, "'callback' => [ self::class, 'write_entry' ]" ) &&
		str_contains( $api, "'permission_callback' => $permission" ) &&
		substr_count( $api, 'self::actor( $request )' ) >= 5,
	'ownership authorization and CAS' =>
		str_contains( $api, "(int) $entry['user_id'] !== $actor" ) &&
		str_contains( $api, "(int) $original['user_id'] !== $actor" ) &&
		str_contains( $api, 'Access::can_edit_entry(' ) &&
		str_contains( $api, 'TimeEntries::update_completed(' ) &&
		str_contains( $api, "'status' => 409" ),
	'canonical range conversion, manual creation and audit events' =>
		str_contains( $api, 'TimeRange::local_to_utc(' ) &&
		str_contains( $api, 'TimeEntries::create_manual(' ) &&
		str_contains( $api, 'Events::TIME_ENTRY_CREATED' ) &&
		str_contains( $api, 'Events::TIME_ENTRY_UPDATED' ),
	'Base Modal, explicit revision, seconds and safe DOM' =>
		str_contains( $js, 'modal.show({' ) &&
		str_contains( $js, 'payload.expected_revision = detail.revision' ) &&
		str_contains( $js, "input.step = '1'" ) &&
		! str_contains( $js, 'innerHTML' ) && ! str_contains( $js, 'toISOString' ),
	'native fallback, presentation, immutable ESM' =>
		str_contains( $panel, 'Menu::time_url(' ) &&
		str_contains( $time, 'createTimeEntryDialog(' ) &&
		str_contains( $compiler, '"time-entry.js"' ),
];
foreach ( $checks as $name => $pass ) {
	if ( ! $pass ) { fwrite( STDERR, "M3-C2 source FAIL: {$name}\n" ); exit( 1 ); }
}
echo "Work M3-C2 Time source: PASS.\n";

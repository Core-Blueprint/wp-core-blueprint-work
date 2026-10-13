<?php
declare(strict_types=1);

/**
 * G0 immutable-source acceptance for both Table (B1.1) and List (B1.2).
 *
 * Reinsert List into the B1.1 Operations source, then Table into the
 * pre-B1 source. Check both exact Git object hashes to prevent drift in markup,
 * status actions, nonce fields, localization and original private helpers.
 */
$root       = dirname( __DIR__ );
$operations = file_get_contents( $root . '/src/Admin/Operations.php' );
$table      = file_get_contents( $root . '/src/Admin/WorkItemTableRenderer.php' );
$list       = file_get_contents( $root . '/src/Admin/WorkItemListRenderer.php' );
$bootstrap  = file_get_contents( $root . '/core-blueprint-work.php' );

$fail = static function ( string $message ): never {
	fwrite( STDERR, "Work G0 Table/List Renderer contract failed: {$message}\n" );
	exit( 1 );
};

if ( ! is_string( $operations ) || ! is_string( $table ) || ! is_string( $list ) || ! is_string( $bootstrap ) ) {
	$fail( 'Required Work sources are unavailable.' );
}

$start_marker = "trait WorkItemTableRenderer {\n";
$start        = strpos( $table, $start_marker );
$end          = strrpos( $table, "}\n" );
if ( false === $start || false === $end || $end <= $start ) {
	$fail( 'Table trait block is missing.' );
}

$table_methods = substr( $table, $start + strlen( $start_marker ), $end - $start - strlen( $start_marker ) );
$mount         = "\tuse WorkItemTableRenderer;\n\n";
if ( 1 !== substr_count( $operations, $mount ) ) {
	$fail( 'Operations must mount the Table view exactly once.' );
}
if ( 0 !== substr_count( $operations, 'private static function render_work_item_table(' ) ) {
	$fail( 'Table view still lives in Operations.' );
}

$methods = [
	'render_work_item_table',
	'render_work_item_quick_edit_row',
	'work_item_table_sort_key',
	'render_work_item_table_header',
	'work_item_table_columns',
	'render_work_item_table_cell',
];
foreach ( $methods as $method ) {
	if ( 1 !== preg_match_all( '/private static function ' . preg_quote( $method, '/' ) . '\(/', $table_methods ) ) {
		$fail( 'Table view member was lost or duplicated: ' . $method );
	}
}

$list_marker   = "trait WorkItemListRenderer {\n";
$list_start    = strpos( $list, $list_marker );
$list_end      = strrpos( $list, "}\n" );
$list_mount    = "\tuse WorkItemListRenderer;\n\n";
if ( false === $list_start || false === $list_end || $list_end <= $list_start ) {
	$fail( 'List trait block is missing.' );
}
$list_methods = substr( $list, $list_start + strlen( $list_marker ), $list_end - $list_start - strlen( $list_marker ) );
if ( 1 !== substr_count( $operations, $list_mount ) || str_contains( $operations, 'private static function render_work_item_list(' ) ) {
	$fail( 'List view must be mounted once and removed from Operations.' );
}
foreach ( [
	'render_work_item_list',
	'work_item_project_groups',
	'render_work_item_list_item',
	'render_work_item_list_details',
	'render_work_item_list_actions',
] as $method ) {
	if ( 1 !== preg_match_all( '/private static function ' . preg_quote( $method, '/' ) . '\(/', $list_methods ) ) {
		$fail( 'List renderer member was lost or duplicated: ' . $method );
	}
}

$b11_source    = str_replace( $list_mount, $list_methods, $operations );
$b11_git_sha1  = sha1( 'blob ' . strlen( $b11_source ) . "\0" . $b11_source );
if ( '8c87f5911d6baafc0f283627f139f38970d2fd3b' !== $b11_git_sha1 ) {
	$fail( "Exact B1.1 Operations source changed ({$b11_git_sha1})." );
}

$reconstructed = str_replace( $mount, $table_methods, $b11_source );
$git_blob_sha1 = sha1( 'blob ' . strlen( $reconstructed ) . "\0" . $reconstructed );
if ( '1473f8fa1aa8d38fb102019b47d768f421a962a8' !== $git_blob_sha1 ) {
	$fail( "Exact pre-extraction renderer source changed ({$git_blob_sha1})." );
}

if (
	! str_contains( $bootstrap, "str_replace( '\\\\', '/', \$relative )" )
	|| ! str_contains( $table, 'namespace CB\Work\Admin;' )
	|| ! str_contains( $list, 'namespace CB\Work\Admin;' )
	|| ! str_contains( $list, 'data-cb-work-list-group-items' )
	|| ! str_contains( $table, 'trait WorkItemTableRenderer {' )
	|| ! str_contains( $table, 'StateBadge::render' )
	|| ! str_contains( $table, 'data-cb-work-quick-edit-row' )
) {
	$fail( 'Autoload, scoped trait or Table/Quick Edit contracts are missing.' );
}

echo "Work G0/B1 Table+List exact Git-blob reconstruction: PASS (6 Table + 5 List methods, no markup drift).\n";

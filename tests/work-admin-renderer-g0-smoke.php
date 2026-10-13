<?php
declare(strict_types=1);

/**
 * G0 immutable-source acceptance for Board (B1.3), List (B1.2), Table (B1.1).
 *
 * Reinsert Board into B1.2, List into B1.1, then Table into the
 * pre-B1 source. Check both exact Git object hashes to prevent drift in markup,
 * status actions, nonce fields, localization and original private helpers.
 */
$root       = dirname( __DIR__ );
$operations = file_get_contents( $root . '/src/Admin/Operations.php' );
$table      = file_get_contents( $root . '/src/Admin/WorkItemTableRenderer.php' );
$list       = file_get_contents( $root . '/src/Admin/WorkItemListRenderer.php' );
$board      = file_get_contents( $root . '/src/Admin/WorkItemBoardRenderer.php' );
$bootstrap  = file_get_contents( $root . '/core-blueprint-work.php' );

$fail = static function ( string $message ): never {
	fwrite( STDERR, "Work G0 Table/List/Board Renderer contract failed: {$message}\n" );
	exit( 1 );
};

if ( ! is_string( $operations ) || ! is_string( $table ) || ! is_string( $list ) || ! is_string( $board ) || ! is_string( $bootstrap ) ) {
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

$board_marker = "trait WorkItemBoardRenderer {\n";
$board_start  = strpos( $board, $board_marker );
$board_end    = strrpos( $board, "}\n" );
$board_mount  = "\tuse WorkItemBoardRenderer;\n\n";
if ( false === $board_start || false === $board_end || $board_end <= $board_start ) {
	$fail( 'Board trait block is missing.' );
}
$board_methods = substr( $board, $board_start + strlen( $board_marker ), $board_end - $board_start - strlen( $board_marker ) );
if ( 1 !== substr_count( $operations, $board_mount ) ) {
	$fail( 'Operations must mount Board renderer exactly once.' );
}
foreach ( [ 'render_work_item_board_actions', 'render_work_item_kanban' ] as $method ) {
	if ( str_contains( $operations, 'private static function ' . $method . '(' )
		|| 1 !== preg_match_all( '/private static function ' . preg_quote( $method, '/' ) . '\(/', $board_methods ) ) {
		$fail( 'Board renderer member missing, duplicated or still in Operations: ' . $method );
	}
}
$b12_source   = str_replace( $board_mount, $board_methods, $operations );
$b12_git_sha1 = sha1( 'blob ' . strlen( $b12_source ) . "\0" . $b12_source );
if ( '8a1f353f3248999d35b0a29749f0211e3247575a' !== $b12_git_sha1 ) {
	$fail( "Exact B1.2 Operations source changed ({$b12_git_sha1})." );
}

$b11_source    = str_replace( $list_mount, $list_methods, $b12_source );
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
	|| ! str_contains( $board, 'namespace CB\Work\Admin;' )
	|| ! str_contains( $board, 'data-cb-work-board-reorder' )
	|| ! str_contains( $board, 'data-cb-core-reorder-handle' )
	|| ! str_contains( $board, 'self::transition_menu_form( $item, $state, $from, $to )' )
	|| ! str_contains( $list, 'data-cb-work-list-group-items' )
	|| ! str_contains( $table, 'trait WorkItemTableRenderer {' )
	|| ! str_contains( $table, 'StateBadge::render' )
	|| ! str_contains( $table, 'data-cb-work-quick-edit-row' )
) {
	$fail( 'Autoload, scoped trait or Table/Quick Edit contracts are missing.' );
}

echo "Work G0/B1 Table+List+Board exact Git-blob reconstruction: PASS (6 Table + 5 List + 2 Board methods, no markup drift).\n";

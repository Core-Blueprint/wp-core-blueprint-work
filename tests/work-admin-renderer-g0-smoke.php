<?php
declare(strict_types=1);

/**
 * G0 immutable-source acceptance for the first B1 renderer extraction.
 *
 * Reinsert the extracted Table implementation at its original position
 * and reconstruct the exact pre-B1 Git blob. This protects all markup,
 * status actions, nonce fields, localization and original private helpers.
 */
$root       = dirname( __DIR__ );
$operations = file_get_contents( $root . '/src/Admin/Operations.php' );
$table      = file_get_contents( $root . '/src/Admin/WorkItemTableRenderer.php' );
$bootstrap  = file_get_contents( $root . '/core-blueprint-work.php' );

$fail = static function ( string $message ): never {
	fwrite( STDERR, "Work G0 Table Renderer contract failed: {$message}\n" );
	exit( 1 );
};

if ( ! is_string( $operations ) || ! is_string( $table ) || ! is_string( $bootstrap ) ) {
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

$reconstructed = str_replace( $mount, $table_methods, $operations );
$git_blob_sha1 = sha1( 'blob ' . strlen( $reconstructed ) . "\0" . $reconstructed );
if ( '1473f8fa1aa8d38fb102019b47d768f421a962a8' !== $git_blob_sha1 ) {
	$fail( "Exact pre-extraction renderer source changed ({$git_blob_sha1})." );
}

if (
	! str_contains( $bootstrap, "str_replace( '\\\\', '/', \$relative )" )
	|| ! str_contains( $table, 'namespace CB\Work\Admin;' )
	|| ! str_contains( $table, 'trait WorkItemTableRenderer {' )
	|| ! str_contains( $table, 'StateBadge::render' )
	|| ! str_contains( $table, 'data-cb-work-quick-edit-row' )
) {
	$fail( 'Autoload, scoped trait or Table/Quick Edit contracts are missing.' );
}

echo "Work G0/B1 Table renderer exact Git-blob reconstruction: PASS (six methods, unchanged canonical source).\n";

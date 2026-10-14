<?php
declare(strict_types=1);

/**
 * B2 Golden: exact pre-extraction member bodies against immutable Work main,
 * plus stable public facade and explicit internal delegation.
 *
 * Local-only source check. Never queries the network or WordPress.
 */
$root = dirname( __DIR__ );
$fail = static function ( string $reason ): never {
	fwrite( STDERR, "Work B2 Golden source FAILED: {$reason}\n" );
	exit( 1 );
};
$path = 'src/Repository/WorkItems.php';
$baseline_sha = '5c820a789f0d99a734ef2c2fb3c2f722ad0c49e0';
$original = shell_exec( 'git -C ' . escapeshellarg( $root ) . ' show ' . $baseline_sha . ':' . $path . ' 2>/dev/null' );
$facade = file_get_contents( $root . '/' . $path );
$links = file_get_contents( $root . '/src/Repository/WorkItemLinks.php' );
$shape = file_get_contents( $root . '/src/Repository/WorkItemShape.php' );
if ( ! is_string( $original ) || ! is_string( $facade ) || ! is_string( $links ) || ! is_string( $shape ) ) {
	$fail( 'Missing immutable baseline or current source.' );
}
$blob = static fn( string $value ): string => sha1( 'blob ' . strlen( $value ) . "\0" . $value );
if ( '3c1c59dc44c66de7ef50e99a3450e582d79af23d' !== $blob( $original ) ) {
	$fail( 'Canonical B3.2 WorkItems source blob no longer matches.' );
}
$method = static function ( string $source, string $name ) use ( $fail ): string {
	$pattern = '/\t(?:public|private) static function ' . preg_quote( $name, '/' ) . '\(/';
	if ( 1 !== preg_match( $pattern, $source, $match, PREG_OFFSET_CAPTURE ) ) {
		$fail( 'Missing method ' . $name );
	}
	$from = $match[0][1];
	$to = strpos( $source, "\n\t}\n", $from );
	if ( false === $to ) {
		$fail( 'Unterminated method ' . $name );
	}
	return substr( $source, $from, $to + 3 - $from );
};
$links_methods = [ 'assignments', 'relations', 'replace_assignments', 'write_assignments', 'add_relation', 'purge_links', 'normalize_assignments' ];
$shape_methods = [ 'query_meta_filters', 'empty_search_result', 'normalize_write', 'hydrate', 'reference', 'date' ];
foreach ( $links_methods as $name ) {
	$expected = $method( $original, $name );
	if ( in_array( $name, [ 'write_assignments', 'normalize_assignments' ], true ) ) {
		$expected = str_replace( 'private static function', 'public static function', $expected );
	}
	if ( $expected !== $method( $links, $name ) ) {
		$fail( 'Link persistence body drift: ' . $name );
	}
}
foreach ( $shape_methods as $name ) {
	$expected = $method( $original, $name );
	if ( 'date' !== $name ) {
		$expected = str_replace( 'private static function', 'public static function', $expected );
	}
	if ( 'normalize_write' === $name ) {
		$expected = str_replace( 'self::normalize_assignments(', 'WorkItemLinks::normalize_assignments(', $expected );
	}
	if ( 'hydrate' === $name ) {
		$expected = str_replace( 'self::assignments(', 'WorkItemLinks::assignments(', $expected );
		$expected = str_replace( 'self::relations(', 'WorkItemLinks::relations(', $expected );
	}
	if ( $expected !== $method( $shape, $name ) ) {
		$fail( 'Shape normalization/read body drift: ' . $name );
	}
}
$signatures = static function ( string $source ): array {
	preg_match_all( '/^\tpublic static function [^\n]+\{/m', $source, $matches );
	return $matches[0];
};
if ( $signatures( $original ) !== $signatures( $facade ) ) {
	$fail( 'WorkItems canonical public method signatures changed.' );
}
foreach ( [
	'WorkItemLinks::assignments(',
	'WorkItemLinks::relations(',
	'WorkItemLinks::replace_assignments(',
	'WorkItemLinks::write_assignments(',
	'WorkItemLinks::add_relation(',
	'WorkItemLinks::purge_links(',
	'WorkItemShape::normalize_write(',
	'WorkItemShape::query_meta_filters(',
	'WorkItemShape::empty_search_result(',
	'WorkItemShape::hydrate(',
	'WorkItemShape::reference(',
] as $call ) {
	if ( ! str_contains( $facade, $call ) ) {
		$fail( 'Canonical WorkItems delegation missing: ' . $call );
	}
}
foreach ( [ 'write_assignments', 'reference', 'normalize_assignments', 'date' ] as $removed ) {
	if ( preg_match( '/\t(?:public|private) static function ' . preg_quote( $removed, '/' ) . '\(/', $facade ) ) {
		$fail( 'Extracted implementation remains in facade: ' . $removed );
	}
}
if ( str_contains( $links, "\$wpdb->query( 'START TRANSACTION' )" )
	|| str_contains( $shape, "\$wpdb->query( 'START TRANSACTION' )" )
	|| ! str_contains( $links, 'WorkItemMutationTransaction::run(' )
	|| ! str_contains( $facade, 'WorkItemMutationTransaction::run(' ) ) {
	$fail( 'A1 transaction/savepoint responsibility changed.' );
}
echo "Work B2 Golden: PASS (13 extracted methods, stable WorkItems API, A1 boundary).\n";

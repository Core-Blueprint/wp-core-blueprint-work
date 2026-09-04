<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$required = [
	'core-blueprint-work.php',
	'src/Admin/Page.php',
	'src/Capabilities.php',
	'src/Integration/Suite.php',
	'src/Lifecycle.php',
	'src/Plugin.php',
	'src/Support/Requirements.php',
	'docs/ARCHITECTURE.md',
];

$failed = false;
foreach ( $required as $relative ) {
	if ( ! is_file( $root . '/' . $relative ) ) {
		fwrite( STDERR, "Missing required file: {$relative}\n" );
		$failed = true;
	}
}

$bootstrap = file_get_contents( $root . '/core-blueprint-work.php' );
$suite     = file_get_contents( $root . '/src/Integration/Suite.php' );
$page      = file_get_contents( $root . '/src/Admin/Page.php' );
$arch      = file_get_contents( $root . '/docs/ARCHITECTURE.md' );

$checks = [
	'bootstrap initializes lightweight suite integration' => str_contains( $bootstrap, 'Integration\\Suite::init();' ),
	'bootstrap waits for public Base boot signal' => str_contains( $bootstrap, "add_action( 'cb_core_booted'" ),
	'bootstrap does not pin an internal Base RC' => ! str_contains( $bootstrap, 'CB_WORK_REQUIRED_BASE' ),
	'suite registers through canonical extension hook' => str_contains( $suite, "cb_core_register_extensions" ),
	'suite declares canonical extension id' => str_contains( $suite, "core-blueprint-work" ),
	'suite relies on Core API rather than requires_base' => ! str_contains( $suite, "'requires_base'" ),
	'suite declares non-empty status id' => str_contains( $suite, "STATUS_ID    = 'work'" ),
	'admin page does not request unsupported tables component' => ! str_contains( $page, "'tables'" ),
	'architecture forbids direct sibling SQL' => str_contains( $arch, 'No direct sibling-table reads/writes.' ),
	'architecture keeps Bricks optional' => str_contains( $arch, 'No hard dependency on CRM, Helpdesk or Bricks.' ),
];

foreach ( $checks as $label => $passed ) {
	if ( ! $passed ) {
		fwrite( STDERR, "Conformance failed: {$label}\n" );
		$failed = true;
	}
}

exit( $failed ? 1 : 0 );

<?php
declare(strict_types=1);

namespace {
	define( 'ABSPATH', '/tmp/wp/' );
	define( 'CB_CORE_API_VERSION', '1.0' );
	define( 'CB_WORK_REQUIRED_API', '1.0' );

	function __( string $text, string $domain = 'default' ): string { return $text; }
	function assert_true( bool $condition, string $message ): void {
		if ( ! $condition ) {
			fwrite( STDERR, "Requirements contract smoke failed: {$message}\n" );
			exit( 1 );
		}
	}

	require dirname( __DIR__ ) . '/src/Support/Requirements.php';

	assert_true(
		[] === \CB\Work\Support\Requirements::issues(),
		'Bootstrap accepts a compatible public Core API marker without probing product-specific Base classes.'
	);
	assert_true( \CB\Work\Support\Requirements::api_compatible( '1.0', '1.0' ), 'Exact Core API version is compatible.' );
	assert_true( \CB\Work\Support\Requirements::api_compatible( '1.4', '1.0' ), 'Newer compatible minor Core API is accepted.' );
	assert_true( ! \CB\Work\Support\Requirements::api_compatible( '0.9', '1.0' ), 'Older Core API major is rejected.' );
	assert_true( ! \CB\Work\Support\Requirements::api_compatible( '2.0', '1.0' ), 'Different Core API major is rejected.' );
	assert_true( ! \CB\Work\Support\Requirements::api_compatible( 'dev', '1.0' ), 'Malformed Core API versions fail closed.' );

	$source = file_get_contents( dirname( __DIR__ ) . '/src/Support/Requirements.php' );
	assert_true( is_string( $source ), 'Requirements source is readable.' );
	assert_true( ! str_contains( $source, 'class_exists(' ), 'Bootstrap Requirements does not probe Base implementation classes.' );
	assert_true( ! str_contains( $source, 'base-contract-unavailable' ), 'Product-contract availability is not a Bootstrap dependency issue.' );

	echo "Requirements contract smoke passed.\n";
}

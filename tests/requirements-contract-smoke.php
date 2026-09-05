<?php
declare(strict_types=1);

namespace {
	define( 'ABSPATH', '/tmp/wp/' );
	define( 'CB_CORE_API_VERSION', '1.0' );
	define( 'CB_WORK_REQUIRED_API', '1.0' );

	function assert_true( bool $condition, string $message ): void {
		if ( ! $condition ) {
			fwrite( STDERR, "Requirements contract smoke failed: {$message}\n" );
			exit( 1 );
		}
	}
}

namespace CB\Core {
	final class ExtensionRegistry {}
}

namespace CB\Core\Admin {
	interface Page {}
	final class PageRegistry {}
}

namespace CB\Core\Dashboard {
	final class CardRegistry {}
}

namespace CB\Core\Database {
	final class SchemaRegistry {}
}

namespace CB\Core\Governance {
	final class Audit {}
	final class EventRegistry {}
}

namespace CB\Core\UI {
	final class Notice {}
}

namespace {
	require dirname( __DIR__ ) . '/src/Support/Requirements.php';

	assert_true(
		[ 'base-contract-unavailable' ] === \CB\Work\Support\Requirements::issues(),
		'Work fails closed when consumed Base ObjectPicker contracts are absent.'
	);

	eval( 'namespace CB\\Core\\UI; final class Assets {} final class ObjectPicker {}' );

	assert_true(
		[] === \CB\Work\Support\Requirements::issues(),
		'Work accepts the compatible Base contract set once Assets and ObjectPicker are available.'
	);

	echo "Requirements contract smoke passed.\n";
}

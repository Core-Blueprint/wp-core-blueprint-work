<?php
declare(strict_types=1);

namespace {
	define( 'ABSPATH', '/tmp/wp/' );

	function sanitize_key( string $value ): string {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', $value ) ?? '' );
	}
}

namespace CB\Work {
	final class Capabilities {
		public const MANAGE = 'cb_manage_work';
	}
}

namespace {
	require dirname( __DIR__ ) . '/src/Admin/WorkItemTablePreferences.php';

	$defaults = \CB\Work\Admin\WorkItemTablePreferences::defaults();
	$expected = [ 'work_item', 'status', 'priority', 'project', 'due', 'assigned', 'actions', 'customer', 'type', 'billing' ];

	if ( $expected !== $defaults['order'] ) {
		fwrite( STDERR, "Work Item table preferences smoke failed: Golden default order drifted.\n" );
		exit( 1 );
	}
	if ( [ 'customer', 'type', 'billing' ] !== $defaults['hidden'] ) {
		fwrite( STDERR, "Work Item table preferences smoke failed: Golden hidden defaults drifted.\n" );
		exit( 1 );
	}

	$normalized = \CB\Work\Admin\WorkItemTablePreferences::normalize( [
		'order'  => [ 'due', 'work_item', 'unknown', 'due', 'status' ],
		'hidden' => [ 'work_item', 'billing', 'unknown', 'billing' ],
	] );
	if ( 'due' !== $normalized['order'][0] || 'work_item' !== $normalized['order'][1] ) {
		fwrite( STDERR, "Work Item table preferences smoke failed: valid personal order is not preserved.\n" );
		exit( 1 );
	}
	if ( in_array( 'unknown', $normalized['order'], true ) || count( $normalized['order'] ) !== count( $expected ) ) {
		fwrite( STDERR, "Work Item table preferences smoke failed: policy normalization is not bounded to canonical columns.\n" );
		exit( 1 );
	}
	if ( in_array( 'work_item', $normalized['hidden'], true ) || [ 'billing' ] !== $normalized['hidden'] ) {
		fwrite( STDERR, "Work Item table preferences smoke failed: protected visibility invariant failed.\n" );
		exit( 1 );
	}

	$operations = file_get_contents( dirname( __DIR__ ) . '/src/Admin/Operations.php' );
	$assets     = file_get_contents( dirname( __DIR__ ) . '/src/Admin/Assets.php' );
	$module     = file_get_contents( dirname( __DIR__ ) . '/assets/work-items-reorder.js' );
	$css        = file_get_contents( dirname( __DIR__ ) . '/assets/work-admin.css' );
	if (
		! is_string( $operations )
		|| ! is_string( $assets )
		|| ! is_string( $module )
		|| ! is_string( $css )
		|| ! str_contains( $operations, 'data-cb-work-table-preferences' )
		|| ! str_contains( $operations, 'data-cb-core-reorder-list="columns"' )
		|| ! str_contains( $operations, 'data-cb-work-column-visible' )
		|| ! str_contains( $assets, '\\CoreBlueprint\\Core\\UI\\Assets::enqueue_reorder()' )
		|| ! str_contains( $assets, "'@cb-core/reorder'" )
		|| ! str_contains( $module, "import '@cb-core/reorder';" )
		|| ! str_contains( $module, 'reorder.enhance' )
		|| ! str_contains( $module, "persist(root, 'save', policy)" )
		|| ! str_contains( $css, '.cb-work-table-preferences__panel' )
	) {
		fwrite( STDERR, "Work Item table preferences smoke failed: Base Reorder integration contract is incomplete.\n" );
		exit( 1 );
	}

	echo "Work Item table preferences smoke passed.\n";
}

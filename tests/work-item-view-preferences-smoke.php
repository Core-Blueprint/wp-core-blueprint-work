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

namespace CB\Work\Admin {
	final class WorkItemViewState {
		public const VIEW_LIST = 'list';
		public const VIEW_KANBAN = 'kanban';
		public const VIEW_TABLE = 'table';
		public const VIEW_CALENDAR = 'calendar';

		/** @return string[] */
		public static function views(): array {
			return [ self::VIEW_LIST, self::VIEW_KANBAN, self::VIEW_TABLE, self::VIEW_CALENDAR ];
		}
	}
}

namespace {
	require dirname( __DIR__ ) . '/src/Admin/WorkItemViewPreferences.php';

	use CB\Work\Admin\WorkItemViewPreferences;

	$defaults = WorkItemViewPreferences::defaults();
	$expected_order = [ 'table', 'list', 'kanban', 'calendar' ];
	if ( 'table' !== $defaults['default_view'] || $expected_order !== $defaults['order'] ) {
		fwrite( STDERR, "Work Item view preferences smoke failed: Golden defaults drifted.\n" );
		exit( 1 );
	}

	$normalized = WorkItemViewPreferences::normalize( [
		'default_view' => 'calendar',
		'order'        => [ 'calendar', 'table', 'bogus', 'calendar', 'kanban' ],
	] );
	if ( 'calendar' !== $normalized['default_view'] ) {
		fwrite( STDERR, "Work Item view preferences smoke failed: valid personal default was not preserved.\n" );
		exit( 1 );
	}
	if ( [ 'calendar', 'table', 'kanban', 'list' ] !== $normalized['order'] ) {
		fwrite( STDERR, "Work Item view preferences smoke failed: bounded order normalization failed.\n" );
		exit( 1 );
	}

	$invalid = WorkItemViewPreferences::normalize( [
		'default_view' => 'timeline',
		'order'        => [ 'timeline' ],
	] );
	if ( 'table' !== $invalid['default_view'] || $expected_order !== $invalid['order'] ) {
		fwrite( STDERR, "Work Item view preferences smoke failed: invalid policy did not fail closed to Golden defaults.\n" );
		exit( 1 );
	}

	$implicit = WorkItemViewPreferences::apply_default_to_request( [], 0 );
	if ( 'table' !== ( $implicit['view'] ?? '' ) ) {
		fwrite( STDERR, "Work Item view preferences smoke failed: missing view did not receive personal fallback.\n" );
		exit( 1 );
	}

	$explicit = WorkItemViewPreferences::apply_default_to_request( [ 'view' => 'calendar' ], 0 );
	if ( 'calendar' !== ( $explicit['view'] ?? '' ) ) {
		fwrite( STDERR, "Work Item view preferences smoke failed: explicit URL view was overridden.\n" );
		exit( 1 );
	}

	$invalid_explicit = WorkItemViewPreferences::apply_default_to_request( [ 'view' => 'timeline' ], 0 );
	if ( 'timeline' !== ( $invalid_explicit['view'] ?? '' ) || 'table' !== WorkItemViewPreferences::resolve_request_view( [ 'view' => 'timeline' ], 0 ) ) {
		fwrite( STDERR, "Work Item view preferences smoke failed: explicit invalid URL state did not remain canonical-state owned.\n" );
		exit( 1 );
	}

	$plugin     = file_get_contents( dirname( __DIR__ ) . '/src/Plugin.php' );
	$operations = file_get_contents( dirname( __DIR__ ) . '/src/Admin/Operations.php' );
	$assets     = file_get_contents( dirname( __DIR__ ) . '/src/Admin/Assets.php' );
	$module     = file_get_contents( dirname( __DIR__ ) . '/assets/work-view-preferences.js' );
	$css        = file_get_contents( dirname( __DIR__ ) . '/assets/work-admin.css' );

	if (
		! is_string( $plugin )
		|| ! is_string( $operations )
		|| ! is_string( $assets )
		|| ! is_string( $module )
		|| ! is_string( $css )
		|| ! str_contains( $plugin, 'WorkItemViewPreferences::init()' )
		|| ! str_contains( $operations, 'WorkItemViewPreferences::apply_default_to_request( $_GET, get_current_user_id() )' )
		|| ! str_contains( $operations, 'WorkItemViewPreferences::get( get_current_user_id() )' )
		|| ! str_contains( $operations, 'data-cb-work-view-preferences-open' )
		|| ! str_contains( $operations, 'data-cb-core-reorder-list="views"' )
		|| ! str_contains( $operations, 'name="cb-work-default-view"' )
		|| ! str_contains( $operations, "'Default', 'core-blueprint-work'" )
		|| ! str_contains( $operations, "'Reset to defaults', 'core-blueprint-work'" )
		|| ! str_contains( $assets, 'enqueue_view_preferences_assets()' )
		|| ! str_contains( $assets, 'WorkItemViewPreferences::resolve_request_view( $_GET, get_current_user_id() )' )
		|| ! str_contains( $assets, "'@cb-core/modal', '@cb-core/reorder'" )
		|| ! str_contains( $module, "import '@cb-core/modal';" )
		|| ! str_contains( $module, "import '@cb-core/reorder';" )
		|| ! str_contains( $module, 'reorder.enhance(reorderRoot)' )
		|| ! str_contains( $module, 'policyFromBody' )
		|| ! str_contains( $module, 'applyDefaults' )
		|| ! str_contains( $module, 'onConfirm: async () =>' )
		|| ! str_contains( $module, 'window.location.reload()' )
		|| ! str_contains( $css, '.cb-work-view-switcher-shell' )
		|| ! str_contains( $css, '.cb-work-view-preferences__item' )
		|| ! str_contains( $css, 'var(--cb-space-3)' )
	) {
		fwrite( STDERR, "Work Item view preferences smoke failed: integration contract is incomplete.\n" );
		exit( 1 );
	}

	echo "Work Item view preferences smoke passed.\n";
}

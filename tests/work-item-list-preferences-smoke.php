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
	require dirname( __DIR__ ) . '/src/Admin/WorkItemListPreferences.php';

	$defaults = \CB\Work\Admin\WorkItemListPreferences::defaults();
	if ( [ 'group_by' => 'project', 'project_order' => 'asc' ] !== $defaults ) {
		fwrite( STDERR, "Work Item list preferences smoke failed: Golden defaults drifted.\n" );
		exit( 1 );
	}

	$valid = \CB\Work\Admin\WorkItemListPreferences::normalize( [
		'group_by'      => 'none',
		'project_order' => 'desc',
	] );
	if ( 'none' !== $valid['group_by'] || 'desc' !== $valid['project_order'] ) {
		fwrite( STDERR, "Work Item list preferences smoke failed: valid preferences were not preserved.\n" );
		exit( 1 );
	}

	$invalid = \CB\Work\Admin\WorkItemListPreferences::normalize( [
		'group_by'      => 'customer',
		'project_order' => 'random',
	] );
	if ( 'project' !== $invalid['group_by'] || 'asc' !== $invalid['project_order'] ) {
		fwrite( STDERR, "Work Item list preferences smoke failed: invalid preferences did not fail closed to Golden defaults.\n" );
		exit( 1 );
	}

	$plugin     = file_get_contents( dirname( __DIR__ ) . '/src/Plugin.php' );
	$operations = file_get_contents( dirname( __DIR__ ) . '/src/Admin/Operations.php' );
	$script     = file_get_contents( dirname( __DIR__ ) . '/assets/work-admin.js' );
	$css        = file_get_contents( dirname( __DIR__ ) . '/assets/work-items-refinement.css' );

	if (
		! is_string( $plugin )
		|| ! is_string( $operations )
		|| ! is_string( $script )
		|| ! is_string( $css )
		|| ! str_contains( $plugin, 'WorkItemListPreferences::init()' )
		|| ! str_contains( $operations, 'data-cb-work-list-display' )
		|| ! str_contains( $operations, 'work_item_project_groups' )
		|| ! str_contains( $operations, '$list_grouping_active' )
		|| ! str_contains( $operations, "\$query['per_page'] = 500" )
		|| ! str_contains( $operations, "WorkItemListPreferences::GROUP_PROJECT" )
		|| ! str_contains( $operations, "WorkItemListPreferences::ORDER_DESC" )
		|| ! str_contains( $operations, "'No project', 'core-blueprint-work'" )
		|| ! str_contains( $script, 'initListDisplay' )
		|| ! str_contains( $script, "window.location.reload()" )
		|| ! str_contains( $script, "event.key !== 'Escape'" )
		|| ! str_contains( $css, '.cb-work-list-group__header' )
		|| ! str_contains( $css, '.cb-work-list-display__menu' )
	) {
		fwrite( STDERR, "Work Item list preferences smoke failed: grouping/display integration contract is incomplete.\n" );
		exit( 1 );
	}

	echo "Work Item list preferences smoke passed.\n";
}

<?php
declare(strict_types=1);

$root      = dirname( __DIR__ );
$assets    = file_get_contents( $root . '/src/Admin/Assets.php' );
$workspace = file_get_contents( $root . '/src/Admin/Workspace.php' );
$state     = file_get_contents( $root . '/src/Admin/WorkItemViewState.php' );
$script      = file_get_contents( $root . '/assets/work-items-refinement.js' );
$css         = file_get_contents( $root . '/assets/work-items-refinement.css' );
$operations  = file_get_contents( $root . '/src/Admin/Operations.php' );
$adminScript = file_get_contents( $root . '/assets/work-admin.js' );
$adminCss    = file_get_contents( $root . '/assets/work-admin.css' );

function refinement_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "Work Items UX refinement smoke failed: {$message}\n" );
		exit( 1 );
	}
}

refinement_assert(
	false !== $assets && false !== $workspace && false !== $state && false !== $script && false !== $css && false !== $operations && false !== $adminScript && false !== $adminCss,
	'Refinement source files are readable.'
);

refinement_assert(
	str_contains( $assets, "assets/work-items-refinement.css" )
	&& str_contains( $assets, "assets/work-items-refinement.js" )
	&& str_contains( $assets, "'blocked'" )
	&& str_contains( $assets, "'showClosed'" ),
	'Refinement assets and Board vocabulary are registered only through Work admin assets.'
);

refinement_assert(
	str_contains( $workspace, "add_filter( 'admin_body_class', [ self::class, 'body_class' ] )" )
	&& str_contains( $workspace, 'Menu::screen_context()' )
	&& str_contains( $workspace, 'cb-work-workspace-screen' )
	&& ! str_contains( $workspace, 'remove_submenu_page' )
	&& ! str_contains( $workspace, 'hide_duplicate_submenus' ),
	'WordPress native navigation remains canonical while Work adds only scoped workspace context.'
);

refinement_assert(
	str_contains( $state, '$calendar_from' )
	&& str_contains( $state, '$calendar_to' )
	&& str_contains( $state, "'calendar_from'        => \$calendar_from" )
	&& str_contains( $state, 'Explicit user filters only. Calendar month bounds are query viewport state.' )
	&& str_contains( $state, "'calendar_month' === \$state_key && self::VIEW_CALENDAR !== \$view" ),
	'Calendar viewport state is structurally separate from explicit scheduled filters.'
);

refinement_assert(
	str_contains( $operations, 'cb-work-calendar-navigation__controls' )
	&& str_contains( $operations, "'Today', 'core-blueprint-work'" )
	&& str_contains( $operations, "'Scheduled', 'core-blueprint-work'" )
	&& str_contains( $operations, "'Due', 'core-blueprint-work'" )
	&& str_contains( $operations, 'cb-work-calendar-entry--' )
	&& ! str_contains( $adminScript, 'enhanceCalendarNavigation' )
	&& str_contains( $adminCss, '.cb-work-calendar-navigation__controls' )
	&& str_contains( $adminCss, '.cb-work-calendar-entry--due' ),
	'Calendar renders aligned server-side navigation and distinguishes scheduled work from deadlines.'
);

refinement_assert(
	str_contains( $script, 'advanced.hidden = true' )
	&& str_contains( $script, "strings.filters || 'Filters'" )
	&& str_contains( $script, "cb-work-filter-field--service" ),
	'Advanced filter power stays available but starts progressively disclosed.'
);

refinement_assert(
	str_contains( $script, "[ 'planned', 'in_progress', 'blocked', 'completed', 'skipped', 'cancelled' ]" )
	&& str_contains( $script, "const closed = [ byStatus.skipped, byStatus.cancelled ].filter( Boolean )" )
	&& str_contains( $script, 'cb-work-board-closed-toggle' ),
	'Board prioritizes active workflow while preserving access to closed statuses.'
);

refinement_assert(
	str_contains( $script, 'cb-work-empty-state--primary' )
	&& str_contains( $script, 'cb-work-calendar-empty-note' )
	&& str_contains( $script, 'cb-work-page-header' ),
	'Header and empty states provide clear next actions without removing canonical functionality.'
);

refinement_assert(
	str_contains( $css, '@media screen and (max-width: 782px)' )
	&& str_contains( $css, '.cb-work-board__lane[hidden]' )
	&& str_contains( $css, '.cb-work-page-header__actions' )
	&& str_contains( $css, '.cb-work-page-header__actions .button' ),
	'Refinement stays responsive and preserves native button semantics.'
);

echo "Work Items UX refinement smoke passed.\n";

<?php
declare(strict_types=1);

// CV-G-003 source and asset scope: no WordPress runtime or theme override.
$root       = dirname( __DIR__ );
$assets     = (string) file_get_contents( $root . '/src/Admin/Assets.php' );
$view       = (string) file_get_contents( $root . '/src/Admin/Operations.php' );
$css        = (string) file_get_contents( $root . '/assets/work-toolbar-golden.css' );
$refinement = (string) file_get_contents( $root . '/assets/work-items-refinement.css' );
$calendar   = (string) file_get_contents( $root . '/src/Admin/WorkItemCalendarView.php' );

$assertions = [
	'Work-only asset loaded once after accepted refinement styles' =>
		str_contains( $assets, "private const TOOLBAR_GOLDEN_STYLE_HANDLE = 'cb-work-toolbar-golden';" )
		&& str_contains( $assets, 'self::enqueue_toolbar_golden_style();' )
		&& str_contains( $assets, "CB_WORK_URL . 'assets/work-toolbar-golden.css', [ self::REFINEMENT_STYLE_HANDLE ]" )
		&& substr_count( $assets, 'self::enqueue_toolbar_golden_style();' ) === 1
		&& strpos( $assets, 'self::enqueue_toolbar_golden_style();' ) > strpos( $assets, "if ( Menu::CONTEXT_WORK_ITEMS !== \$context )" ),
	'Canonical toolbar renderer still shares three work regions in each view' =>
		str_contains( $view, 'class="cb-work-toolbar__row"' )
		&& str_contains( $view, 'class="cb-work-toolbar__head"' )
		&& str_contains( $view, 'class="cb-work-toolbar__primary"' )
		&& str_contains( $view, 'class="cb-work-toolbar__search"' )
		&& str_contains( $view, 'id="cb-work-filter-search"' )
		&& str_contains( $view, 'name="project_id"' )
		&& str_contains( $view, 'data-cb-work-auto-submit' ),
	'Wide viewport uses deliberate aligned regions and an elastic Search field' =>
		str_contains( $css, 'container: cb-work-toolbar / inline-size;' )
		&& str_contains( $css, '@container cb-work-toolbar (min-width: 1480px)' )
		&& str_contains( $css, 'grid-template-columns: max-content max-content minmax(0, 1fr);' )
		&& str_contains( $css, 'justify-content: flex-start;' )
		&& str_contains( $css, 'flex: 1 1 280px;' )
		&& str_contains( $css, 'max-width: none;' ),
	'Tablet desktop wraps Search instead of clipping Table-only controls' =>
		str_contains( $css, '@container cb-work-toolbar (min-width: 1100px) and (max-width: 1479px)' )
		&& str_contains( $css, 'grid-template-columns: max-content minmax(0, 1fr);' )
		&& str_contains( $css, 'grid-column: 1 / -1;' )
		&& str_contains( $css, 'border-top: 1px solid var(--cb-work-border);' )
		&& str_contains( $css, '@container cb-work-toolbar (max-width: 1099px)' )
		&& str_contains( $css, 'grid-template-columns: minmax(0, 1fr);' )
		&& str_contains( $refinement, '@media screen and (max-width: 782px)' ),
	'Table List controls retain their own popup markup and dimensions' =>
		str_contains( $view, 'data-cb-work-list-display-toggle' )
		&& str_contains( $view, 'data-cb-work-table-display-toggle' )
		&& str_contains( $view, 'data-cb-work-table-columns-toggle' )
		&& str_contains( $css, '.cb-work-list-display,' )
		&& str_contains( $css, '.cb-work-table-display,' )
		&& str_contains( $css, '.cb-work-columns-toggle' )
		&& str_contains( $css, 'flex: 0 0 auto;' ),
	'Secondary action hierarchy only uses semantic Base Light/Dark tokens' =>
		str_contains( $css, 'body.cb-admin-theme .cb-work-items-page--refined .cb-work-toolbar .button.cb-work-more-filters-toggle' )
		&& str_contains( $css, 'body.cb-admin-theme .cb-work-items-page--refined .cb-work-toolbar .button.cb-work-columns-toggle' )
		&& str_contains( $css, 'body.cb-admin-theme .cb-work-items-page--refined .cb-work-calendar-navigation__controls .button' )
		&& str_contains( $css, 'var(--cb-surface-1)' )
		&& str_contains( $css, 'var(--cb-border)' )
		&& str_contains( $css, 'var(--cb-interactive-hover)' )
		&& str_contains( $css, 'var(--cb-interactive-focus)' )
		&& ! preg_match( '/#[0-9a-f]{3,8}\b/i', $css ),
	'Calendar navigation, selected preset and view switcher contracts untouched' =>
		str_contains( $calendar, 'cb-work-calendar-navigation__controls' )
		&& str_contains( $view, 'cb-work-view-switcher__option' )
		&& str_contains( $view, "aria-current=\"page\"" )
		&& str_contains( $view, 'cb-work-fast-path' )
		&& ! str_contains( $css, '.cb-work-fast-path' )
		&& ! str_contains( $css, '.cb-work-view-switcher__option' ),
];

foreach ( $assertions as $label => $pass ) {
	if ( ! $pass ) {
		fwrite( STDERR, "Work toolbar Golden composition smoke FAILED: {$label}\n" );
		exit( 1 );
	}
}

echo "Work toolbar Golden composition smoke passed (responsive Search, scoped actions, Base tokens).\n";

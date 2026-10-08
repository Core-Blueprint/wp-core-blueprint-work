<?php
declare(strict_types=1);

define( 'ABSPATH', '/tmp/wp/' );
$root       = dirname( __DIR__ );
require_once $root . '/src/Domain/RecurrenceSchedule.php';
require_once $root . '/src/Admin/Recurrence.php';
$menu       = file_get_contents( $root . '/src/Admin/Menu.php' );
$recurrence = file_get_contents( $root . '/src/Admin/Recurrence.php' );
$actions    = file_get_contents( $root . '/src/Admin/RecurrenceActions.php' );
$styles     = file_get_contents( $root . '/assets/css/recurrence-workspace.css' );
$repository = file_get_contents( $root . '/src/Repository/RecurrenceRules.php' );
$script     = file_get_contents( $root . '/assets/recurrence-workspace.js' );

$render_pos = strpos( $recurrence, 'public static function render(): void' );
$render     = false === $render_pos ? '' : substr( $recurrence, $render_pos );

$checks = [
	'Recurrence stylesheet is registered before admin head renders' => str_contains( $menu, "add_action( 'admin_enqueue_scripts', [ Recurrence::class, 'enqueue_assets' ] );" )
		&& str_contains( $recurrence, 'public static function enqueue_assets(): void' )
		&& str_contains( $recurrence, 'Menu::RECURRENCE_SLUG !== $page' )
		&& str_contains( $recurrence, "wp_enqueue_style( 'cb-work-recurrence-workspace'" )
		&& ! str_contains( $render, 'wp_enqueue_style(' ),
	'Stylesheet cache key tracks CSS content across deterministic ZIP builds' => str_contains( $recurrence, "hash_file( 'sha256', \$stylesheet )" )
		&& ! str_contains( $recurrence, 'filemtime( $stylesheet )' ),
	'List is the default workspace and editor is explicitly opened' => str_contains( $recurrence, "'add' === sanitize_key" )
		&& str_contains( $recurrence, 'if ( $show_editor ) : ?>' )
		&& str_contains( $recurrence, "self::url( [ 'mode' => 'add' ] )" ),
	'Editor keeps existing create and update governance' => str_contains( $recurrence, 'cb_work_create_recurrence_rule' )
		&& str_contains( $recurrence, 'cb_work_update_recurrence_rule' )
		&& str_contains( $recurrence, 'wp_nonce_field(' )
		&& str_contains( $actions, "self::guard( 'cb_work_create_recurrence_rule' )" )
		&& str_contains( $actions, "self::guard( 'cb_work_update_recurrence_rule_' . \$rule_id )" ),
	'History-locked schedule fields are preserved' => str_contains( $recurrence, 'RecurrenceOccurrences::count_for_rule( $edit_id ) > 0' )
		&& str_contains( $recurrence, 'if ( $locked ) : ?>' ),
	'Context-dependent rows stay hidden despite the flex field layout' => 1 === preg_match( '/\.cb-work-recurrence-fields\s+tr\[hidden\]\s*\{\s*display:\s*none\s*;/s', $styles )
		&& str_contains( $recurrence, 'data-cb-work-customer-row' )
		&& str_contains( $recurrence, 'data-cb-work-context-row' ),
	'Editor fields are grouped semantically and respond by class' => str_contains( $recurrence, 'cb-work-recurrence-section--rule' )
		&& str_contains( $recurrence, 'cb-work-recurrence-section--defaults' )
		&& str_contains( $recurrence, 'cb-work-recurrence-section--schedule' )
		&& str_contains( $recurrence, 'cb-work-recurrence-field--wide' )
		&& str_contains( $styles, 'grid-template-columns: repeat(4, minmax(0, 1fr));' ),
	'G2 project-first layout keeps dependent context fields grouped' => strpos( $recurrence, 'cb-work-recurrence-project' ) < strpos( $recurrence, 'data-cb-work-context-row' )
		&& strpos( $recurrence, 'data-cb-work-context-row' ) < strpos( $recurrence, 'data-cb-work-customer-row' )
		&& str_contains( $styles, '.cb-work-recurrence-fields [data-cb-work-customer-row] {' ),
	'G2 billing, assignees, frequency and dates use balanced field widths' => str_contains( $recurrence, 'class="cb-work-recurrence-field--wide" data-cb-work-advanced><th scope="row"><label for="cb-work-recurrence-billing"' )
		&& str_contains( $recurrence, "Pickers::assignees( 'recurrence[assigned_user_ids]', 'cb-work-recurrence-assignees', \$assignees, false )" )
		&& str_contains( $styles, '.cb-work-recurrence-field--frequency td select {' )
		&& str_contains( $styles, '.cb-work-recurrence-section--schedule input[type="date"] {' ),
	'Rule Builder preview uses canonical month end stepping and locked occurrence cursor' => \CB\Work\Admin\Recurrence::preview_dates( 'monthly', 1, '2027-01-31', null ) === [ '2027-01-31', '2027-02-28', '2027-03-31' ]
		&& \CB\Work\Admin\Recurrence::preview_dates( 'monthly', 1, '2027-01-31', null, '2027-03-31', true ) === [ '2027-03-31', '2027-04-30', '2027-05-31' ]
		&& \CB\Work\Admin\Recurrence::preview_dates( 'weekly', 0, '2027-01-01', null ) === null,
	'New rules start inactive and save preserves status until separate governed toggle' => str_contains( $actions, "null === \$current ? false : ! empty( \$current['is_active'] )" )
		&& str_contains( $recurrence, 'cb-work-recurrence-activation' )
		&& ! str_contains( $recurrence, 'name="recurrence[is_active]"' )
		&& str_contains( $actions, "self::guard( 'cb_work_toggle_recurrence_rule_' . \$rule_id )" ),
	'Server-backed list has bounded pagination and whitelisted sorting' => str_contains( $repository, 'public static function search_page(' )
		&& str_contains( $repository, 'SELECT COUNT(*)' )
		&& str_contains( $repository, 'LIMIT %d OFFSET %d' )
		&& str_contains( $repository, "'next_occurrence' => 'next_occurrence_on'" )
		&& str_contains( $recurrence, 'RecurrenceRules::search_page(' )
		&& ! str_contains( $recurrence, 'RecurrenceRules::all( false, 500 )' ),
	'List filters and quick edit actions remain governed and do not update schedule' => str_contains( $recurrence, 'cb-work-recurrence-filterbar' )
		&& str_contains( $recurrence, 'data-cb-quick-edit-form' )
		&& str_contains( $recurrence, 'data-cb-inline-toggle' )
		&& str_contains( $actions, "check_ajax_referer( 'cb_work_quick_edit_recurrence_rule_' . \$rule_id )" )
		&& str_contains( $actions, "check_ajax_referer( 'cb_work_toggle_recurrence_rule_' . \$rule_id )" )
		&& str_contains( $actions, "[ 'title' => \$title, 'priority' => \$priority, 'updated_by' => get_current_user_id() ]" ),
	'Preview is read-only and uses authenticated nonce and canonical schedule' => str_contains( $actions, "check_ajax_referer( 'cb_work_recurrence_preview' )" )
		&& str_contains( $actions, 'Recurrence::preview_dates(' )
		&& str_contains( $script, "body.set('_ajax_nonce', config.previewNonce)" )
		&& str_contains( $script, "body.set('action', 'cb_work_preview_recurrence_rule')" ),
	'Rule Builder progressively reveals advanced fields and responds to smaller screens' => str_contains( $recurrence, 'data-cb-work-advanced-toggle' )
		&& str_contains( $recurrence, 'data-cb-recurrence-preview' )
		&& str_contains( $script, "row.hidden = !expanded" )
		&& str_contains( $styles, '.cb-work-recurrence-builder {' )
		&& str_contains( $styles, '@media (max-width: 1220px)' ),
	'Natural-language schedule selection retains canonical frequency values' => str_contains( $recurrence, 'cb-work-recurrence-interval' )
		&& str_contains( $recurrence, 'data-cb-unit-singular' )
		&& str_contains( $recurrence, 'data-cb-unit-plural' )
		&& str_contains( $script, 'const syncUnits = () => {' )
		&& str_contains( $recurrence, 'RecurrenceSchedule::frequencies()' ),
	'Explicit editor activation guards unsaved changes and has no nested forms' => str_contains( $recurrence, 'data-cb-editor-toggle' )
		&& str_contains( $script, 'const markDirty = () => {' )
		&& str_contains( $script, "data.set('action', 'cb_work_toggle_recurrence_rule_inline')" )
		&& str_contains( $script, "body.set('action', 'cb_work_toggle_recurrence_rule_inline')" ),
	'Rules table shows context, schedule, and state' => str_contains( $recurrence, "'Work context', 'core-blueprint-work'" )
		&& str_contains( $recurrence, '$project_titles' )
		&& str_contains( $recurrence, 'cb-work-recurrence-status--active' )
		&& str_contains( $recurrence, 'self::schedule_label( $configured )' ),
	'Generator remains an explicit governed action in diagnostics' => str_contains( $recurrence, 'cb-work-recurrence-diagnostics' )
		&& str_contains( $recurrence, 'cb_work_run_recurrence_generator' )
		&& str_contains( $recurrence, "wp_nonce_field( 'cb_work_run_recurrence_generator' )" ),
	'Styling adapts to light and dark mode without a hardcoded dark fallback' => str_contains( $styles, 'color-mix(in srgb, currentColor' )
		&& ! str_contains( $styles, '#11161e' )
		&& ! str_contains( $styles, ':has(' )
		&& str_contains( $styles, '@media (max-width: 680px)' ),
	'Rules and form are not fixed to a narrow maximum width' => str_contains( $styles, ".cb-work-recurrence-editor,\n.cb-work-recurrence-list" )
		&& str_contains( $styles, 'max-width: none;' ),
];

foreach ( $checks as $label => $passed ) {
	if ( ! $passed ) {
		fwrite( STDERR, "Recurring Work workspace smoke FAILED: {$label}\n" );
		exit( 1 );
	}
}

echo "Recurring Work workspace smoke passed.\n";

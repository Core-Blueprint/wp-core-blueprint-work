<?php
declare(strict_types=1);

$root       = dirname( __DIR__ );
$menu       = file_get_contents( $root . '/src/Admin/Menu.php' );
$recurrence = file_get_contents( $root . '/src/Admin/Recurrence.php' );
$actions    = file_get_contents( $root . '/src/Admin/RecurrenceActions.php' );
$styles     = file_get_contents( $root . '/assets/css/recurrence-workspace.css' );

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
	'Editor fields are grouped semantically and respond by class' => str_contains( $recurrence, 'cb-work-recurrence-section--rule' )
		&& str_contains( $recurrence, 'cb-work-recurrence-section--defaults' )
		&& str_contains( $recurrence, 'cb-work-recurrence-section--schedule' )
		&& str_contains( $recurrence, 'cb-work-recurrence-field--wide' )
		&& str_contains( $styles, 'grid-template-columns: repeat(4, minmax(0, 1fr));' ),
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

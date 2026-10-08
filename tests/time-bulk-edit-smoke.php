<?php
declare(strict_types=1);

// T2-C isolated semantic and source contract regression. No WordPress DB writes.
define( 'ABSPATH', '/tmp/wp/' );

require dirname( __DIR__ ) . '/src/Admin/TimeEntryBulkEdit.php';

use CB\Work\Admin\TimeEntryBulkEdit;

$fail = static function ( string $message ): never {
    fwrite( STDERR, "Time Entries Bulk Edit smoke FAILED: {$message}\n" );
    exit( 1 );
};

$method = new ReflectionMethod( TimeEntryBulkEdit::class, 'next_note' );
if ( 'existing' !== $method->invoke( null, 'existing', 'keep', 'ignored' )
    || "existing\nadded" !== $method->invoke( null, 'existing', 'append', 'added' )
    || 'added' !== $method->invoke( null, '', 'append', 'added' )
    || '' !== $method->invoke( null, 'existing', 'replace', '' )
    || 'replacement' !== $method->invoke( null, 'existing', 'replace', 'replacement' ) ) {
    $fail( 'note transformations must preserve, append, replace or explicitly clear' );
}

$limit = ( new ReflectionClass( TimeEntryBulkEdit::class ) )->getReflectionConstant( 'LIMIT' );
if ( ! $limit || 25 !== $limit->getValue() ) {
    $fail( 'selected rows must be bounded to one 25-row page' );
}

$root = dirname( __DIR__ );
$handler = (string) file_get_contents( $root . '/src/Admin/TimeEntryBulkEdit.php' );
$actions = (string) file_get_contents( $root . '/src/Admin/TimeActions.php' );
$list = (string) file_get_contents( $root . '/src/Admin/TimeEntryList.php' );
$script = (string) file_get_contents( $root . '/assets/time-entry-bulk-edit.js' );
$time = (string) file_get_contents( $root . '/src/Admin/Time.php' );
$style = (string) file_get_contents( $root . '/assets/time-workspace.css' );

$checks = [
    'registered and CSRF protected' =>
        str_contains( $actions, 'TimeEntryBulkEdit::init();' )
        && str_contains( $handler, "add_action( 'admin_post_' . self::ACTION" )
        && str_contains( $handler, 'check_admin_referer( self::ACTION );' ),
    'selection is bounded, unique, and revisioned' =>
        str_contains( $handler, 'count( $raw_ids ) > self::LIMIT' )
        && str_contains( $handler, 'isset( $ids[ $id ] )' )
        && str_contains( $handler, '$raw_revisions[ $id ] ?? null' )
        && str_contains( $handler, "'time-bulk-conflict'" ),
    'all selected entries preflight before any write' =>
        strpos( $handler, 'foreach ( $ids as $id )' ) < strpos( $handler, 'foreach ( $updates as $change )' )
        && str_contains( $handler, 'Access::can_edit_entry( $entry, $work_item_id )' )
        && str_contains( $handler, 'Access::can_track_work_item( $work_item_id' ),
    'canonical CAS keeps timestamps, owner, source, duration' =>
        str_contains( $handler, 'TimeEntries::update_completed(' )
        && str_contains( $handler, "(string) $entry['started_at']" )
        && str_contains( $handler, "(string) $entry['ended_at']" )
        && str_contains( $handler, "(int) $entry['user_id']" )
        && ! str_contains( $handler, "TimeEntries::create_manual(" ),
    'each successful change is audited as bulk' =>
        str_contains( $handler, "Audit::record( Events::TIME_ENTRY_UPDATED" )
        && str_contains( $handler, "'bulk'            => true" )
        && str_contains( $handler, 'time-bulk-partial' ),
    'form and row inputs are associated without nesting' =>
        str_contains( $list, 'id="cb-work-time-bulk-form"' )
        && str_contains( $list, 'form="cb-work-time-bulk-form"' )
        && str_contains( $list, 'name="entry_ids[]"')
        && str_contains( $list, 'name="entry_revisions[' )
        && str_contains( $list, 'data-cb-work-time-bulk-select-all' ),
    'no-change choices and explicit clear exist' =>
        str_contains( $list, 'value="keep"' )
        && str_contains( $list, 'value="append"' )
        && str_contains( $list, 'value="replace"' )
        && str_contains( $list, 'name="bulk_work_item_id"' ),
    'JavaScript enhancement retains progressive fallback' =>
        str_contains( $script, 'new FormData(bulk)' )
        && str_contains( $script, "current.replaceWith(document.importNode(updatedList, true))" )
        && str_contains( $script, 'selectAll.indeterminate' )
        && str_contains( $script, 'notice(serverNotice)' )
        && str_contains( $script, 'window.history.replaceState(' )
        && str_contains( $time, 'assets/time-entry-bulk-edit.js' )
        && str_contains( $style, '.cb-work-time-bulk-fields' ),
    'dedicated outcome messages are rendered' =>
        str_contains( $time, "'time-bulk-updated'" )
        && str_contains( $time, "'time-bulk-partial'" )
        && str_contains( $time, "'time-bulk-invalid'" )
        && str_contains( $time, "'time-bulk-conflict'" ),
];

foreach ( $checks as $label => $passed ) {
    if ( ! $passed ) {
        $fail( $label );
    }
}

echo "Time Entries Bulk Edit smoke passed.\n";

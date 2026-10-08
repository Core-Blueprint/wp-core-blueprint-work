<?php
declare(strict_types=1);

// Static contract: Work consumes Base's public Toast Foundation and preserves
// server notices for progressive enhancement. No WordPress DB needed.
$root = dirname( __DIR__ );
$assets = (string) file_get_contents( $root . '/src/Admin/Assets.php' );
$adapter = (string) file_get_contents( $root . '/assets/work-toast.js' );
$time = (string) file_get_contents( $root . '/src/Admin/Time.php' );
$operations = (string) file_get_contents( $root . '/src/Admin/Operations.php' );
$recurrence = (string) file_get_contents( $root . '/src/Admin/Recurrence.php' );
$quick = (string) file_get_contents( $root . '/src/Admin/TimeEntryQuickEdit.php' );
$quickJs = (string) file_get_contents( $root . '/assets/time-entry-quick-edit.js' );
$bulkJs = (string) file_get_contents( $root . '/assets/time-entry-bulk-edit.js' );
$quickAdd = (string) file_get_contents( $root . '/src/Admin/QuickAdd.php' );
$checks = [
    'enqueue only on Work screens' =>
        str_contains( $assets, "if ( '' === Menu::screen_context() )" )
        && str_contains( $assets, 'enqueue_toast_feedback' ),
    'Work Settings is scoped to Work provider' =>
        str_contains( $assets, 'Settings::SLUG === $page' )
        && str_contains( $assets, 'Suite::EXTENSION_ID === $extension' )
        && str_contains( (string) file_get_contents( $root . '/src/Admin/Page.php' ), 'data-cb-work-toast=' ),
    'Base owns toast presentation and module' =>
        str_contains( $assets, 'Assets::enqueue_toasts(' )
        && str_contains( $assets, 'Assets::TOAST_PRESENTATION_CORE' )
        && str_contains( $assets, "'@cb-core/toast'" )
        && str_contains( $adapter, "import { toast } from '@cb-core/toast';" ),
    'redirect notices are marked as transient' =>
        str_contains( $time, 'data-cb-work-toast=' )
        && str_contains( $operations, 'data-cb-work-toast=' )
        && str_contains( $recurrence, 'data-cb-work-toast=' ),
    'async Time editors keep their inline fallback' =>
        str_contains( $quickJs, 'window.cbWorkToast?.showNotice(serverNotice)' )
        && str_contains( $bulkJs, 'window.cbWorkToast?.showNotice(source)' )
        && str_contains( $quickJs, 'document.importNode(serverNotice, true)' )
        && str_contains( $bulkJs, 'document.importNode(source, true)' ),
    'Quick Add retains action link and shows error toast' =>
        str_contains( $quickAdd, 'Menu::edit_work_item_url( $id )' )
        && str_contains( $quickAdd, 'data-cb-work-toast="error"' ),
    'form content not globally converted into toast' =>
        str_contains( $adapter, "const SELECTOR = '[data-cb-work-toast]'" )
        && str_contains( $adapter, 'if (!node?.matches?.(SELECTOR)) return false;' )
        && ! str_contains( $quick, 'data-cb-work-toast=' ),
];
foreach ( $checks as $label => $ok ) {
    if ( ! $ok ) {
        fwrite( STDERR, "Work Base Toast contract FAILED: {$label}\n" );
        exit( 1 );
    }
}
echo "Work Base Toast contract passed.\n";

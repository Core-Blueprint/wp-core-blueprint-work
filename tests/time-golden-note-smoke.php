<?php
declare(strict_types=1);

define( 'ABSPATH', '/tmp/wp/' );
function sanitize_textarea_field( string $value ): string {
    return trim( strip_tags( $value ) );
}
require dirname( __DIR__ ) . '/src/Domain/TimeNote.php';

use CB\Work\Domain\TimeNote;

$fail = static function ( string $reason ): never {
    fwrite( STDERR, "Time Golden note smoke FAILED: {$reason}\n" );
    exit( 1 );
};

if ( 4000 !== TimeNote::MAX_LENGTH
    || 'A' !== TimeNote::normalize( ' <b>A</b> ' )
    || 4000 !== strlen( TimeNote::normalize( str_repeat( 'X', 4200 ) ) )
    || '' !== TimeNote::normalize( '' ) ) {
    $fail( 'canonical bounded plain-text note policy' );
}
$root = dirname( __DIR__ );
$entries = (string) file_get_contents( $root . '/src/Repository/TimeEntries.php' );
$timers = (string) file_get_contents( $root . '/src/Repository/Timers.php' );
if ( substr_count( $entries, 'TimeNote::normalize(' ) < 2
    || substr_count( $timers, 'TimeNote::normalize(' ) < 2
    || str_contains( $entries, 'private static function note(' )
    || str_contains( $timers, 'private static function note(' ) ) {
    $fail( 'duplicate repository note processing has not been removed' );
}
echo "Time Golden note smoke passed.\n";

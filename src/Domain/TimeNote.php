<?php
declare(strict_types=1);

namespace CB\Work\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * One canonical normalization policy for manual, corrected, and timed notes.
 * Storage is plain text, never HTML. A single bounded text contract avoids
 * divergence between active timers and completed entries.
 */
final class TimeNote {
    public const MAX_LENGTH = 4000;

    public static function normalize( string $note ): string {
        $note = sanitize_textarea_field( $note );
        return function_exists( 'mb_substr' )
            ? mb_substr( $note, 0, self::MAX_LENGTH )
            : substr( $note, 0, self::MAX_LENGTH );
    }

    private function __construct() {}
}

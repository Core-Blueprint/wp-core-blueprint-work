<?php
declare(strict_types=1);

namespace CB\Work\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Text-only description path for M2. Content with Gutenberg blocks, HTML,
 * long bodies or invalid UTF-8 stays in the native rich editor. Never
 * serialize structured blocks through the modal's plain-text transport.
 */
final class UnifiedEditorDescription {
    public const MAX_LENGTH = 16000;

    /** @return array{mode:string,value:string} */
    public static function model( string $content ): array {
        return self::editable( $content )
            ? [ 'mode' => 'plain', 'value' => $content ]
            : [ 'mode' => 'advanced', 'value' => '' ];
    }

    public static function editable( string $content ): bool {
        // Even an HTML comment or a malformed block marker belongs to the
        // rich editor. No conversion or round-tripping of markup is allowed.
        return strlen( $content ) <= self::MAX_LENGTH
            && ! str_contains( $content, '<' )
            && (bool) preg_match( '//u', $content );
    }

    /** @return string|\WP_Error */
    public static function normalize( string $submitted, ?string $current = null ): string|\WP_Error {
        if ( ! self::editable( $submitted ) || ( null !== $current && ! self::editable( $current ) ) ) {
            return new \WP_Error( 'work_editor_description_advanced_required', '', [ 'status' => 422 ] );
        }
        $clean = sanitize_textarea_field( $submitted );
        if ( ! self::editable( $clean ) ) {
            return new \WP_Error( 'work_editor_description_invalid', '', [ 'status' => 422 ] );
        }
        return $clean;
    }

    private function __construct() {}
}

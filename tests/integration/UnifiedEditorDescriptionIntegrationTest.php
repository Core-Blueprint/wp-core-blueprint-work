<?php
declare(strict_types=1);

use CB\Work\Admin\UnifiedEditorDescription;

final class UnifiedEditorDescriptionIntegrationTest extends WP_UnitTestCase {
    public function test_plain_text_is_editable_and_keeps_line_breaks(): void {
        $plain = "First line\nSecond line";
        self::assertSame( [ 'mode' => 'plain', 'value' => $plain ],
            UnifiedEditorDescription::model( $plain ) );
        self::assertSame( $plain, UnifiedEditorDescription::normalize( $plain ) );
    }

    public function test_html_and_gutenberg_blocks_are_not_editable_or_exposed_as_text(): void {
        $documents = [
            '<!-- wp:paragraph --><p>Keep <strong>blocks</strong>.</p><!-- /wp:paragraph -->',
            '<p>Legacy HTML content</p>',
            'Text <not-markup> text',
            str_repeat( 'x', UnifiedEditorDescription::MAX_LENGTH + 1 ),
        ];
        foreach ( $documents as $document ) {
            self::assertSame( [ 'mode' => 'advanced', 'value' => '' ],
                UnifiedEditorDescription::model( $document ) );
            $mutation = UnifiedEditorDescription::normalize( 'Overwrite attempt', $document );
            self::assertWPError( $mutation );
            self::assertSame( 'work_editor_description_advanced_required', $mutation->get_error_code() );
        }
    }

    public function test_markup_submission_fails_even_when_existing_content_is_plain(): void {
        $injection = UnifiedEditorDescription::normalize( '<script>alert(1)</script>', 'Safe' );
        self::assertWPError( $injection );
        self::assertSame( 'work_editor_description_advanced_required', $injection->get_error_code() );
        $clear = UnifiedEditorDescription::normalize( '', 'Old text' );
        self::assertSame( '', $clear );
    }
}

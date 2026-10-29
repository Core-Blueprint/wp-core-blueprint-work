<?php
declare(strict_types=1);

use CB\Work\Admin\UnifiedEditorPickers;
use CB\Work\Integration\CRMCustomers;

/**
 * No external CRM required for the assignment path. CRM validation is routed
 * through the pre-existing first-party adapter, never inferred from a label.
 */
final class UnifiedEditorPickerIntegrationTest extends WP_UnitTestCase {
    public function set_up(): void {
        if ( ! defined( 'DB_NAME' ) || 'core_blueprint_work_test' !== (string) DB_NAME
            || ! defined( 'DB_HOST' ) || '127.0.0.1:3307' !== (string) DB_HOST ) {
            self::fail( 'M2 picker tests require the isolated Work test database.' );
        }
        parent::set_up();
    }

    public function test_existing_user_assignment_selection_round_trips_through_canonical_normalization(): void {
        $first = self::factory()->user->create( [ 'display_name' => 'Person One' ] );
        $second = self::factory()->user->create( [ 'display_name' => 'Person Two' ] );
        $model = UnifiedEditorPickers::model( 'work-item', [
            'customer_provider' => '',
            'customer_type' => '',
            'customer_id' => '',
            'assigned_user_ids' => [ $first, $second ],
        ] );
        self::assertCount( 2, $model['assignees']['selected'] );
        self::assertSame( $first, $model['assignees']['selected'][0]['id'] );
        $normalized = UnifiedEditorPickers::normalize( 'work-item', [
            'title' => 'Review', 'assigned_user_ids' => $first . ',' . $second,
        ] );
        self::assertIsArray( $normalized );
        self::assertSame( [ $first, $second ], $normalized['assigned_user_ids'] );
        $clear = UnifiedEditorPickers::normalize( 'work-item', [ 'assigned_user_ids' => '' ] );
        self::assertIsArray( $clear );
        self::assertSame( [], $clear['assigned_user_ids'] );
    }

    public function test_malformed_ids_and_invalid_context_fail_before_domain_persistence(): void {
        foreach ( [ '0', '-4', '12;13', '1.2', 'x', '1,2,3x' ] as $value ) {
            $invalid = UnifiedEditorPickers::normalize( 'work-item', [ 'assigned_user_ids' => $value ] );
            self::assertWPError( $invalid );
            self::assertSame( 'work_editor_assignees_invalid', $invalid->get_error_code() );
        }
        $unknown = UnifiedEditorPickers::normalize( 'work-item', [ 'assigned_user_ids' => '999999999' ] );
        self::assertWPError( $unknown );

        $context = UnifiedEditorPickers::normalize( 'project', [ 'work_context' => 'unknown' ] );
        self::assertWPError( $context );
        self::assertSame( 'work_editor_context_invalid', $context->get_error_code() );
        $inherited = UnifiedEditorPickers::normalize( 'work-item', [
            'project_id' => '3', 'customer' => 'crm:contact:12',
        ] );
        self::assertWPError( $inherited );
        self::assertSame( 'work_editor_customer_inherited', $inherited->get_error_code() );

        if ( ! CRMCustomers::available() ) {
            $unavailable = UnifiedEditorPickers::normalize( 'project', [ 'customer' => 'crm:contact:12' ] );
            self::assertWPError( $unavailable );
            self::assertSame( 'work_crm_unavailable', $unavailable->get_error_code() );
        }
    }
}

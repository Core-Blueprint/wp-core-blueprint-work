<?php
declare(strict_types=1);

use CB\Work\Capabilities;
use CB\Work\Content\PostTypes;
use CB\Work\Database\Schema;
use CB\Work\PublicApi\ServiceActions;
use CB\Work\PublicApi\ProjectActions;

final class UnifiedServiceEditorIntegrationTest extends WP_UnitTestCase {
    private array $types_created = [];

    public function set_up(): void {
        if ( ! defined( 'DB_NAME' ) || 'core_blueprint_work_test' !== (string) DB_NAME
            || ! defined( 'DB_HOST' ) || '127.0.0.1:3307' !== (string) DB_HOST ) {
            self::fail( 'M2 Service integration may use only the canonical disposable Work test database.' );
        }
        parent::set_up();
        if ( ! post_type_exists( PostTypes::SERVICE ) ) {
            PostTypes::register();
            $this->types_created = [ PostTypes::SERVICE, PostTypes::PROJECT, PostTypes::WORK_ITEM ];
        }
        update_option( Schema::OPTION, CB_WORK_SCHEMA_VERSION );
        $user_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
        $user = get_userdata( $user_id );
        self::assertInstanceOf( WP_User::class, $user );
        $user->add_cap( Capabilities::MANAGE );
        wp_set_current_user( $user_id );
    }

    public function tear_down(): void {
        foreach ( $this->types_created as $type ) {
            if ( post_type_exists( $type ) ) {
                unregister_post_type( $type );
            }
        }
        parent::tear_down();
    }

    private function input( string $title, string $amount = '95.00' ): array {
        return [
            'title' => $title,
            'amount' => $amount,
            'currency' => 'EUR',
            'pricing_model' => 'fixed',
            'recurring_period' => '',
            'tax_mode' => 'exclusive',
            'tax_rate_id' => 0,
        ];
    }

    public function test_manager_can_create_modify_and_reject_stale_service_without_rewriting_blocks(): void {
        global $wpdb;
        self::assertSame( 1, (int) $wpdb->get_var( 'SELECT @@in_transaction' ) );
        $created = ServiceActions::save( 0, $this->input( 'Managed Maintenance' ) );
        self::assertIsArray( $created );
        $id = (int) $created['id'];
        self::assertGreaterThan( 0, $id );
        self::assertSame( 9500, $created['pricing']['amount_minor'] );
        self::assertSame( 'EUR', $created['pricing']['currency'] );
        self::assertSame( 64, strlen( $created['version'] ) );
        self::assertSame( 1, (int) $wpdb->get_var( 'SELECT @@in_transaction' ), 'Nested Work writes may not commit the test-owned transaction.' );

        $blocks = '<!-- wp:paragraph --><p>Keep <strong>blocks</strong>.</p><!-- /wp:paragraph -->';
        wp_update_post( [ 'ID' => $id, 'post_content' => $blocks ] );
        $before = get_post( $id );
        self::assertInstanceOf( WP_Post::class, $before );
        $revision = ServiceActions::get( $id )['version'];
        $changed = ServiceActions::save( $id, $this->input( 'Managed Maintenance Updated' ), $revision );
        self::assertIsArray( $changed );
        self::assertSame( 'Managed Maintenance Updated', get_post( $id )->post_title );
        self::assertSame( $before->post_content, get_post( $id )->post_content );
        self::assertSame( 9500, $changed['pricing']['amount_minor'] );

        $stale = ServiceActions::save( $id, $this->input( 'Overwrite Attempt' ), $revision );
        self::assertWPError( $stale );
        self::assertSame( 'work_service_stale', $stale->get_error_code() );
        self::assertSame( 'Managed Maintenance Updated', get_post( $id )->post_title );
        self::assertSame( 1, (int) $wpdb->get_var( 'SELECT @@in_transaction' ) );
    }

    public function test_project_partial_edit_keeps_gutenberg_blocks(): void {
        $created = ProjectActions::create( [ 'title' => 'Customer work planning',
            'work_context' => 'internal' ] );
        self::assertIsArray( $created );
        $id = (int) $created['id'];
        self::assertGreaterThan( 0, $id );
        $blocks = '<!-- wp:paragraph --><p>Project description untouched.</p><!-- /wp:paragraph -->';
        wp_update_post( [ 'ID' => $id, 'post_content' => $blocks ] );
        $before = get_post( $id );
        self::assertInstanceOf( WP_Post::class, $before );
        $result = ProjectActions::update( $id, [ 'title' => 'Planning updated' ] );
        self::assertIsArray( $result );
        self::assertSame( 'Planning updated', get_post( $id )->post_title );
        self::assertSame( $before->post_content, get_post( $id )->post_content );
    }

    public function test_invalid_amount_and_unprivileged_actor_cannot_create_or_mutate(): void {
        $invalid = ServiceActions::save( 0, $this->input( 'Invalid', '8.999' ) );
        self::assertWPError( $invalid );
        self::assertSame( 'work_service_pricing_invalid', $invalid->get_error_code() );

        $created = ServiceActions::save( 0, $this->input( 'Governed' ) );
        self::assertIsArray( $created );
        $id = (int) $created['id'];
        $version = $created['version'];

        wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
        $denied = ServiceActions::save( $id, $this->input( 'Unauthorized' ), $version );
        self::assertWPError( $denied );
        self::assertSame( 'work_service_forbidden', $denied->get_error_code() );
        self::assertNull( ServiceActions::get( $id ) );
        self::assertSame( 'Governed', get_post( $id )->post_title );
    }
}

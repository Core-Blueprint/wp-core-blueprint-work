<?php
declare(strict_types=1);

namespace {
	define( 'ABSPATH', '/tmp/wp/' );

	final class WP_Error {
		public function __construct(
			public string $code = '',
			public string $message = ''
		) {}

		public function get_error_message(): string {
			return $this->message;
		}
	}

	function __( string $text, string $domain = 'default' ): string { unset( $domain ); return $text; }
	function sanitize_text_field( string $value ): string { return trim( strip_tags( $value ) ); }
	function sanitize_key( string $value ): string { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', $value ) ?? '' ); }
	function absint( mixed $value ): int { return abs( (int) $value ); }
	function is_wp_error( mixed $value ): bool { return $value instanceof WP_Error; }
	function assert_true( bool $condition, string $message ): void {
		if ( ! $condition ) {
			fwrite( STDERR, "CRM customer picker smoke failed: {$message}\n" );
			exit( 1 );
		}
	}
}

namespace CB\CRM\Frontend\Queries {
	final class Contacts {
		public static function staff( array $args = [] ): array {
			if ( isset( $args['include_ids'] ) && ! in_array( 42, (array) $args['include_ids'], true ) ) {
				return [ 'items' => [] ];
			}
			return [
				'items' => [
					[ 'id' => 42, 'display_name' => 'Contact 42' ],
				],
			];
		}
	}

	final class Organizations {
		public static function staff( array $args = [] ): array {
			if ( isset( $args['include_ids'] ) && ! in_array( 42, (array) $args['include_ids'], true ) ) {
				return [ 'items' => [] ];
			}
			return [
				'items' => [
					[ 'id' => 42, 'name' => 'Organization 42' ],
				],
			];
		}
	}
}

namespace {
	require dirname( __DIR__ ) . '/src/Integration/CRMCustomers.php';

	use CB\Work\Integration\CRMCustomers;

	$results = CRMCustomers::search( '42', 10 );
	assert_true( is_array( $results ), 'Combined CRM search succeeds.' );
	assert_true( 2 === count( $results ), 'Contact 42 and Organization 42 both remain selectable.' );

	$ids = array_column( $results, 'id' );
	sort( $ids );
	assert_true(
		[ 'crm:contact:42', 'crm:organization:42' ] === $ids,
		'Identical numeric CRM ids receive distinct opaque transport identifiers.'
	);

	$contact_selected = CRMCustomers::selected( 'crm', 'contact', '42' );
	$organization_selected = CRMCustomers::selected( 'crm', 'organization', '42' );
	assert_true( 'crm:contact:42' === ( $contact_selected['id'] ?? '' ), 'Existing Contact 42 hydrates to the Contact transport token.' );
	assert_true( 'crm:organization:42' === ( $organization_selected['id'] ?? '' ), 'Existing Organization 42 hydrates to the Organization transport token.' );

	$contact_reference = CRMCustomers::reference( 'crm:contact:42' );
	$organization_reference = CRMCustomers::reference( 'crm:organization:42' );
	assert_true(
		[ 'provider' => 'crm', 'type' => 'contact', 'id' => '42' ] === $contact_reference,
		'Contact transport token resolves to canonical Contact metadata.'
	);
	assert_true(
		[ 'provider' => 'crm', 'type' => 'organization', 'id' => '42' ] === $organization_reference,
		'Organization transport token resolves to canonical Organization metadata.'
	);
	assert_true( $contact_reference !== $organization_reference, 'Colliding numeric ids never collapse to the same canonical customer reference.' );

	assert_true( null === CRMCustomers::reference( '' ), 'Empty picker value still clears the customer reference.' );
	assert_true( is_wp_error( CRMCustomers::reference( '42' ) ), 'Legacy ambiguous numeric-only customer transport is rejected fail-closed.' );
	assert_true( is_wp_error( CRMCustomers::reference( 'crm:organization:042' ) ), 'Non-canonical opaque identifiers are rejected fail-closed.' );

	$projects = file_get_contents( dirname( __DIR__ ) . '/src/Admin/Projects.php' );
	$work_items = file_get_contents( dirname( __DIR__ ) . '/src/Admin/WorkItems.php' );
	assert_true( is_string( $projects ) && is_string( $work_items ), 'Admin customer save sources are readable.' );
	assert_true( ! str_contains( $projects, "absint( \$input['customer_object_id'] )" ), 'Project save no longer destroys opaque customer identifiers.' );
	assert_true( ! str_contains( $work_items, "absint( \$input['customer_object_id'] )" ), 'Work Item save no longer destroys opaque customer identifiers.' );
	assert_true( str_contains( $projects, 'CRMCustomers::reference( $customer_identifier )' ), 'Project save resolves customer tokens through the canonical Work adapter.' );
	assert_true( str_contains( $work_items, 'CRMCustomers::reference( $customer_identifier )' ), 'Work Item save resolves customer tokens through the canonical Work adapter.' );

	echo "CRM customer picker smoke passed.\n";
}

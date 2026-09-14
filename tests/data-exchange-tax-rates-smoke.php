<?php
declare(strict_types=1);

namespace {
	define( 'ABSPATH', '/tmp/wp/' );
	define( 'CB_WORK_SCHEMA_VERSION', '1.7' );

	final class WP_Error {
		public function __construct( private string $code, private string $message = '' ) {}
		public function get_error_code(): string { return $this->code; }
		public function get_error_message(): string { return $this->message; }
	}
	$GLOBALS['cb_work_test_authorized'] = true;
	function is_wp_error( mixed $value ): bool { return $value instanceof WP_Error; }
	function current_user_can( string $capability ): bool { unset( $capability ); return (bool) $GLOBALS['cb_work_test_authorized']; }
	function get_current_user_id(): int { return 7; }
	function get_option( string $name, mixed $default = false ): mixed { unset( $name ); return CB_WORK_SCHEMA_VERSION ?: $default; }
	function sanitize_text_field( string $value ): string { return trim( strip_tags( $value ) ); }
	function sanitize_title( string $value ): string {
		$value = strtolower( trim( $value ) );
		$value = preg_replace( '/[^a-z0-9]+/', '-', $value ) ?? '';
		return trim( $value, '-' );
	}
	function __( string $text, string $domain ): string { unset( $domain ); return $text; }
	function assert_true( bool $condition, string $message ): void {
		if ( ! $condition ) {
			fwrite( STDERR, "FAIL: {$message}\n" );
			exit( 1 );
		}
	}
}

namespace CB\Core\Governance {
	final class Audit {
		public static array $records = [];
		public static function record( string $event, string $severity, array $context = [] ): void {
			self::$records[] = compact( 'event', 'severity', 'context' );
		}
	}
}

namespace CB\Core\DataExchange {
	interface EntityInterface {
		public function is_available(): bool;
		public function schema_version(): int;
		public function supports_schema_version( int $schema_version ): bool;
		public function can_export( array $context = [] ): bool;
		public function can_import( array $context = [] ): bool;
		public function export_records( array $context = [] ): iterable|\WP_Error;
		public function plan_import( array $record, string $mode, int $source_schema_version, array $context = [] ): array|\WP_Error;
		public function apply_import( array $plan, array $context = [] ): array|\WP_Error;
	}
	interface CsvEntityInterface extends EntityInterface {
		public function csv_columns( int $schema_version ): array;
		public function to_csv_row( array $record ): array|\WP_Error;
		public function from_csv_row( array $row, int $schema_version ): array|\WP_Error;
	}
	interface MappingEntityInterface extends EntityInterface {
		public function mapping_fields( int $schema_version ): array|\WP_Error;
	}
	final class Foundation {
		public const MODE_CREATE_ONLY = 'create_only';
		public const MODE_UPDATE_EXISTING = 'update_existing';
		public const MODE_CREATE_UPDATE = 'create_update';
		public const OP_CREATE = 'create';
		public const OP_UPDATE = 'update';
		public const OP_SKIP = 'skip';
	}
}

namespace CB\Work {
	final class Capabilities { public const MANAGE = 'cb_manage_work'; }
}

namespace CB\Work\Database {
	final class Schema { public const OPTION = 'cb_work_schema_version'; }
}

namespace CB\Work\Governance {
	final class Events {
		public const TAX_RATE_CREATED = 'work.tax.rate.created';
		public const TAX_RATE_UPDATED = 'work.tax.rate.updated';
		public const TAX_RATE_ACTIVATED = 'work.tax.rate.activated';
		public const TAX_RATE_DEACTIVATED = 'work.tax.rate.deactivated';
	}
}

namespace CB\Work\Repository {
	final class TaxRates {
		public static array $store = [];
		private static int $next_id = 2;

		public static function seed( array $row ): void { self::$store[(int) $row['id']] = $row; }
		public static function get( int $id ): ?array { return self::$store[$id] ?? null; }
		public static function get_by_code( string $code ): ?array {
			foreach ( self::$store as $row ) {
				if ( (string) $row['code'] === $code ) return $row;
			}
			return null;
		}
		public static function all( bool $include_inactive = true ): array {
			return array_values( array_filter( self::$store, static fn( array $row ): bool => $include_inactive || 1 === (int) $row['is_active'] ) );
		}
		public static function create( array $input ): int {
			$code = self::normalize_code( (string) ( $input['code'] ?? $input['label'] ?? '' ) );
			if ( '' === $code || self::get_by_code( $code ) ) return 0;
			$rate = self::parse_rate_bp( (string) ( $input['rate'] ?? '' ) );
			if ( null === $rate ) return 0;
			$id = self::$next_id++;
			self::$store[$id] = [
				'id' => $id,
				'code' => $code,
				'label' => (string) $input['label'],
				'country_code' => (string) ( $input['country_code'] ?? '' ),
				'rate_bp' => $rate,
				'is_active' => ( $input['is_active'] ?? true ) ? 1 : 0,
				'valid_from' => (string) ( $input['valid_from'] ?? '' ),
				'valid_until' => (string) ( $input['valid_until'] ?? '' ),
			];
			return $id;
		}
		public static function update( int $id, array $input ): bool {
			if ( ! isset( self::$store[$id] ) ) return false;
			self::$store[$id] = [
				...self::$store[$id],
				'label' => (string) $input['label'],
				'country_code' => (string) $input['country_code'],
				'rate_bp' => (int) $input['rate_bp'],
				'is_active' => $input['is_active'] ? 1 : 0,
				'valid_from' => (string) ( $input['valid_from'] ?? '' ),
				'valid_until' => (string) ( $input['valid_until'] ?? '' ),
			];
			return true;
		}
		public static function set_active( int $id, bool $active ): bool {
			if ( ! isset( self::$store[$id] ) ) return false;
			self::$store[$id]['is_active'] = $active ? 1 : 0;
			return true;
		}
		public static function normalize_code( string $value ): string { return substr( \sanitize_title( $value ), 0, 64 ); }
		public static function parse_rate_bp( string $value ): ?int {
			$value = str_replace( [ '%', ' ' ], '', trim( $value ) );
			$value = str_replace( ',', '.', $value );
			if ( 1 !== preg_match( '/^(\d{1,3})(?:\.(\d{1,2}))?$/D', $value, $matches ) ) return null;
			$bp = ( (int) $matches[1] * 100 ) + ( isset( $matches[2] ) ? (int) str_pad( $matches[2], 2, '0' ) : 0 );
			return $bp <= 10000 ? $bp : null;
		}
		public static function format_rate_bp( int $rate_bp ): string {
			$whole = intdiv( $rate_bp, 100 );
			$fraction = $rate_bp % 100;
			return 0 === $fraction ? (string) $whole : $whole . '.' . rtrim( str_pad( (string) $fraction, 2, '0', STR_PAD_LEFT ), '0' );
		}
	}
}

namespace CB\Work\PublicApi {
	final class TaxRates {
		public static function get( int $id ): ?array { return \CB\Work\Repository\TaxRates::get( $id ); }
		public static function get_by_code( string $code ): ?array { return \CB\Work\Repository\TaxRates::get_by_code( $code ); }
		public static function all( bool $include_inactive = true ): array { return \CB\Work\Repository\TaxRates::all( $include_inactive ); }
	}
}

namespace {
	use CB\Core\DataExchange\Foundation;
	use CB\Core\Governance\Audit;
	use CB\Work\Integration\DataExchange\TaxRateEntity;
	use CB\Work\PublicApi\TaxRateActions;
	use CB\Work\Repository\TaxRates as RepositoryTaxRates;

	RepositoryTaxRates::seed( [
		'id' => 1,
		'code' => 'nl-standard-21',
		'label' => 'NL Standard',
		'country_code' => 'NL',
		'rate_bp' => 2100,
		'is_active' => 1,
		'valid_from' => '',
		'valid_until' => '',
	] );

	require dirname( __DIR__ ) . '/src/PublicApi/TaxRateActions.php';
	require dirname( __DIR__ ) . '/src/Integration/DataExchange/TaxRateEntity.php';

	$entity = new TaxRateEntity();
	assert_true( $entity->is_available(), 'Tax Rate Data Exchange entity is available on the current Work schema.' );
	assert_true( $entity->can_export() && $entity->can_import(), 'Manage capability authorizes Data Exchange.' );

	$records = $entity->export_records();
	assert_true( is_array( $records ) && 1 === count( $records ), 'Export returns the Work-owned VAT catalog.' );
	assert_true( ! array_key_exists( 'id', $records[0] ), 'Internal database IDs are not portable export data.' );
	assert_true( 'nl-standard-21' === $records[0]['code'], 'Stable Work VAT code is the portable identity.' );

	$fields = $entity->mapping_fields( 1 );
	assert_true( is_array( $fields ) && [ 'code', 'label', 'country_code', 'rate_bp', 'is_active', 'valid_from', 'valid_until' ] === array_column( $fields, 'id' ), 'Mapper schema is extension-owned and stable.' );

	$same = $entity->plan_import( $records[0], Foundation::MODE_CREATE_UPDATE, 1 );
	assert_true( is_array( $same ) && Foundation::OP_SKIP === $same['operation'], 'Unchanged portable record plans a skip.' );
	assert_true( 'tax-rate:nl-standard-21' === $same['reference'], 'Plan reference uses stable code rather than database ID.' );

	$changed = $records[0];
	$changed['label'] = 'NL Standard 21%';
	$update = $entity->plan_import( $changed, Foundation::MODE_CREATE_UPDATE, 1 );
	assert_true( is_array( $update ) && Foundation::OP_UPDATE === $update['operation'], 'Changed existing code plans an update.' );
	$applied = $entity->apply_import( $update );
	assert_true( is_array( $applied ) && 'tax-rate:nl-standard-21' === $applied['reference'], 'Update applies through the canonical Work action.' );
	assert_true( 'NL Standard 21%' === RepositoryTaxRates::get( 1 )['label'], 'Canonical Work state changed after apply.' );
	assert_true( in_array( 'work.tax.rate.updated', array_column( Audit::$records, 'event' ), true ), 'Canonical update is audited by Work.' );

	$new = [
		'code' => 'be-standard-21',
		'label' => 'BE Standard 21%',
		'country_code' => 'BE',
		'rate_bp' => 2100,
		'is_active' => false,
		'valid_from' => null,
		'valid_until' => null,
	];
	$create = $entity->plan_import( $new, Foundation::MODE_CREATE_UPDATE, 1 );
	assert_true( is_array( $create ) && Foundation::OP_CREATE === $create['operation'], 'Unknown stable code plans create in create/update mode.' );
	$created = $entity->apply_import( $create );
	assert_true( is_array( $created ) && null !== RepositoryTaxRates::get_by_code( 'be-standard-21' ), 'Create applies through the canonical Work action.' );
	assert_true( 0 === (int) RepositoryTaxRates::get_by_code( 'be-standard-21' )['is_active'], 'Imported inactive state is created atomically.' );

	$missing_update = $entity->plan_import(
		[ ...$new, 'code' => 'de-standard-19', 'country_code' => 'DE', 'label' => 'DE Standard 19%', 'rate_bp' => 1900 ],
		Foundation::MODE_UPDATE_EXISTING,
		1
	);
	assert_true( is_array( $missing_update ) && Foundation::OP_SKIP === $missing_update['operation'], 'Missing code skips in update-existing mode.' );

	$existing_create = $entity->plan_import( $changed, Foundation::MODE_CREATE_ONLY, 1 );
	assert_true( is_array( $existing_create ) && Foundation::OP_SKIP === $existing_create['operation'], 'Existing code skips in create-only mode.' );

	$bad_code = $entity->plan_import( [ ...$new, 'code' => 'BE Standard 21' ], Foundation::MODE_CREATE_UPDATE, 1 );
	assert_true( is_wp_error( $bad_code ) && 'work_data_exchange_tax_rate_invalid_code' === $bad_code->get_error_code(), 'Portable identity never silently normalizes.' );

	$csv = $entity->from_csv_row(
		[
			'code' => 'fr-standard-20',
			'label' => 'FR Standard 20%',
			'country_code' => 'FR',
			'rate_bp' => '2000',
			'is_active' => '1',
			'valid_from' => '',
			'valid_until' => '',
		],
		1
	);
	assert_true( is_array( $csv ) && true === $csv['is_active'] && 2000 === $csv['rate_bp'], 'CSV adapter maps flat cells into strict canonical types.' );
	$bad_csv = $entity->from_csv_row(
		[
			'code' => 'fr-standard-20',
			'label' => 'FR Standard 20%',
			'country_code' => 'FR',
			'rate_bp' => '2000',
			'is_active' => 'yes',
			'valid_from' => '',
			'valid_until' => '',
		],
		1
	);
	assert_true( is_wp_error( $bad_csv ), 'CSV booleans fail closed instead of accepting truthy strings.' );

	$mapped_external = $entity->plan_import(
		[
			'code' => 'it-standard-22',
			'label' => 'IT Standard 22%',
			'country_code' => 'IT',
			'rate_bp' => '2200',
			'is_active' => '1',
			'valid_from' => null,
			'valid_until' => null,
		],
		Foundation::MODE_CREATE_UPDATE,
		1
	);
	assert_true( is_array( $mapped_external ) && Foundation::OP_CREATE === $mapped_external['operation'], 'Mapper-style scalar strings can enter provider semantic normalization.' );
	assert_true( 2200 === $mapped_external['payload']['record']['rate_bp'] && true === $mapped_external['payload']['record']['is_active'], 'Provider normalizes only unambiguous typed scalar strings before fingerprint/apply.' );

	$immutable = TaxRateActions::update( 1, [ 'code' => 'different-code' ] );
	assert_true( is_wp_error( $immutable ) && 'work_tax_rate_code_immutable' === $immutable->get_error_code(), 'Canonical Work action keeps portable code immutable.' );

	$GLOBALS['cb_work_test_authorized'] = false;
	assert_true( ! $entity->can_import() && ! $entity->can_export(), 'Provider authorization fails closed without cb_manage_work.' );
	$forbidden = TaxRateActions::set_active( 1, false );
	assert_true( is_wp_error( $forbidden ) && 'work_action_forbidden' === $forbidden->get_error_code(), 'Canonical mutation also enforces server-side authorization.' );

	$requirements = file_get_contents( dirname( __DIR__ ) . '/src/Support/Requirements.php' );
	$registration = file_get_contents( dirname( __DIR__ ) . '/src/Integration/DataExchange.php' );
	assert_true( is_string( $requirements ) && ! str_contains( $requirements, 'DataExchange' ), 'Unmerged Base DX is not a hard Work runtime requirement.' );
	assert_true( is_string( $registration ) && str_contains( $registration, 'interface_exists' ) && str_contains( $registration, 'Registry::register_implementation' ), 'DX registration is fail-soft and uses public Generic Interoperability.' );

	echo "Data Exchange Tax Rates smoke passed.\n";
}

<?php
declare(strict_types=1);

namespace CB\Work\Integration\DataExchange;

use CB\Core\DataExchange\CsvEntityInterface;
use CB\Core\DataExchange\Foundation;
use CB\Core\DataExchange\MappingEntityInterface;
use CB\Work\Capabilities;
use CB\Work\Database\Schema;
use CB\Work\PublicApi\TaxRateActions;
use CB\Work\PublicApi\TaxRates;
use CB\Work\Repository\TaxRates as TaxRateRepository;
use WP_Error;

defined( 'ABSPATH' ) || exit;

final class TaxRateEntity implements CsvEntityInterface, MappingEntityInterface {
	private const SCHEMA_VERSION = 1;
	private const REFERENCE_PREFIX = 'tax-rate:';
	private const COLUMNS = [ 'code', 'label', 'country_code', 'rate_bp', 'is_active', 'valid_from', 'valid_until' ];

	public function is_available(): bool {
		return defined( 'CB_WORK_SCHEMA_VERSION' )
			&& CB_WORK_SCHEMA_VERSION === (string) get_option( Schema::OPTION, '0' );
	}

	public function schema_version(): int {
		return self::SCHEMA_VERSION;
	}

	public function supports_schema_version( int $schema_version ): bool {
		return self::SCHEMA_VERSION === $schema_version;
	}

	public function can_export( array $context = [] ): bool {
		unset( $context );
		return current_user_can( Capabilities::MANAGE );
	}

	public function can_import( array $context = [] ): bool {
		unset( $context );
		return current_user_can( Capabilities::MANAGE );
	}

	public function export_records( array $context = [] ): iterable|WP_Error {
		unset( $context );
		$records = [];
		foreach ( TaxRates::all( true ) as $row ) {
			$record = self::project_record( $row );
			if ( is_wp_error( $record ) ) {
				return $record;
			}
			$records[] = $record;
		}
		return $records;
	}

	public function plan_import( array $record, string $mode, int $source_schema_version, array $context = [] ): array|WP_Error {
		unset( $context );
		if ( ! $this->supports_schema_version( $source_schema_version ) ) {
			return new WP_Error( 'work_data_exchange_tax_rate_schema' );
		}

		$record = self::normalize_record( $record );
		if ( is_wp_error( $record ) ) {
			return $record;
		}

		$current   = TaxRates::get_by_code( $record['code'] );
		$reference = self::reference( $record['code'] );
		if ( null === $current ) {
			$operation = Foundation::MODE_UPDATE_EXISTING === $mode
				? Foundation::OP_SKIP
				: Foundation::OP_CREATE;
			$tax_rate_id = 0;
		} else {
			$tax_rate_id = (int) $current['id'];
			if ( Foundation::MODE_CREATE_ONLY === $mode || self::same_record( $current, $record ) ) {
				$operation = Foundation::OP_SKIP;
			} else {
				$operation = Foundation::OP_UPDATE;
			}
		}

		return [
			'operation' => $operation,
			'reference' => $reference,
			'payload'   => [
				'tax_rate_id' => $tax_rate_id,
				'record'      => $record,
			],
			'warnings'  => [],
		];
	}

	public function apply_import( array $plan, array $context = [] ): array|WP_Error {
		unset( $context );
		$operation = (string) ( $plan['operation'] ?? '' );
		$payload   = $plan['payload'] ?? null;
		if ( ! is_array( $payload ) || ! isset( $payload['record'] ) || ! is_array( $payload['record'] ) ) {
			return new WP_Error( 'work_data_exchange_tax_rate_apply_contract' );
		}

		$record = self::normalize_record( $payload['record'] );
		if ( is_wp_error( $record ) ) {
			return $record;
		}

		if ( Foundation::OP_CREATE === $operation ) {
			$result = TaxRateActions::create( $record );
		} elseif ( Foundation::OP_UPDATE === $operation ) {
			$id = isset( $payload['tax_rate_id'] ) && is_int( $payload['tax_rate_id'] )
				? $payload['tax_rate_id']
				: 0;
			if ( $id <= 0 ) {
				return new WP_Error( 'work_data_exchange_tax_rate_apply_contract' );
			}
			$result = TaxRateActions::update( $id, $record );
		} elseif ( Foundation::OP_SKIP === $operation ) {
			return [ 'reference' => self::reference( $record['code'] ) ];
		} else {
			return new WP_Error( 'work_data_exchange_tax_rate_apply_contract' );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return [ 'reference' => self::reference( (string) $result['code'] ) ];
	}

	public function csv_columns( int $schema_version ): array {
		return self::SCHEMA_VERSION === $schema_version ? self::COLUMNS : [];
	}

	public function to_csv_row( array $record ): array|WP_Error {
		$record = self::normalize_record( $record );
		if ( is_wp_error( $record ) ) {
			return $record;
		}
		return [
			'code'         => $record['code'],
			'label'        => $record['label'],
			'country_code' => $record['country_code'],
			'rate_bp'      => $record['rate_bp'],
			'is_active'    => $record['is_active'],
			'valid_from'   => $record['valid_from'],
			'valid_until'  => $record['valid_until'],
		];
	}

	public function from_csv_row( array $row, int $schema_version ): array|WP_Error {
		if ( self::SCHEMA_VERSION !== $schema_version || array_keys( $row ) !== self::COLUMNS ) {
			return new WP_Error( 'work_data_exchange_tax_rate_csv_contract' );
		}

		$rate = (string) $row['rate_bp'];
		if ( 1 !== preg_match( '/^(?:0|[1-9][0-9]{0,4})$/D', $rate ) || (int) $rate > 10000 ) {
			return new WP_Error( 'work_data_exchange_tax_rate_invalid_rate' );
		}
		$active = (string) $row['is_active'];
		if ( ! in_array( $active, [ '0', '1' ], true ) ) {
			return new WP_Error( 'work_data_exchange_tax_rate_invalid_status' );
		}

		return self::normalize_record( [
			'code'         => (string) $row['code'],
			'label'        => (string) $row['label'],
			'country_code' => (string) $row['country_code'],
			'rate_bp'      => (int) $rate,
			'is_active'    => '1' === $active,
			'valid_from'   => '' === (string) $row['valid_from'] ? null : (string) $row['valid_from'],
			'valid_until'  => '' === (string) $row['valid_until'] ? null : (string) $row['valid_until'],
		] );
	}

	public function mapping_fields( int $schema_version ): array|WP_Error {
		if ( self::SCHEMA_VERSION !== $schema_version ) {
			return new WP_Error( 'work_data_exchange_tax_rate_schema' );
		}
		return [
			self::mapping_field( 'code', __( 'Code', 'core-blueprint-work' ), 'string', true, [ 'tax_code', 'vat_code' ] ),
			self::mapping_field( 'label', __( 'Label', 'core-blueprint-work' ), 'string', true, [ 'name', 'title' ] ),
			self::mapping_field( 'country_code', __( 'Country code', 'core-blueprint-work' ), 'string', false, [ 'countrycode', 'iso_country_code' ] ),
			self::mapping_field( 'rate_bp', __( 'Rate (basis points)', 'core-blueprint-work' ), 'integer', true, [ 'basis_points', 'rate_basis_points' ] ),
			self::mapping_field( 'is_active', __( 'Active', 'core-blueprint-work' ), 'boolean', true, [ 'active', 'enabled' ] ),
			self::mapping_field( 'valid_from', __( 'Valid from', 'core-blueprint-work' ), 'date', false, [ 'from', 'start_date' ] ),
			self::mapping_field( 'valid_until', __( 'Valid until', 'core-blueprint-work' ), 'date', false, [ 'until', 'end_date' ] ),
		];
	}

	/** @param array<string,mixed> $row @return array{code:string,label:string,country_code:string,rate_bp:int,is_active:bool,valid_from:?string,valid_until:?string}|WP_Error */
	private static function project_record( array $row ): array|WP_Error {
		return self::normalize_record( [
			'code'         => (string) ( $row['code'] ?? '' ),
			'label'        => (string) ( $row['label'] ?? '' ),
			'country_code' => (string) ( $row['country_code'] ?? '' ),
			'rate_bp'      => isset( $row['rate_bp'] ) ? (int) $row['rate_bp'] : -1,
			'is_active'    => 1 === (int) ( $row['is_active'] ?? 0 ),
			'valid_from'   => self::nullable_date( $row['valid_from'] ?? null ),
			'valid_until'  => self::nullable_date( $row['valid_until'] ?? null ),
		] );
	}

	/** @param array<string,mixed> $record @return array{code:string,label:string,country_code:string,rate_bp:int,is_active:bool,valid_from:?string,valid_until:?string}|WP_Error */
	private static function normalize_record( array $record ): array|WP_Error {
		$keys = array_keys( $record );
		sort( $keys, SORT_STRING );
		$expected = self::COLUMNS;
		sort( $expected, SORT_STRING );
		if ( $keys !== $expected ) {
			return new WP_Error( 'work_data_exchange_tax_rate_shape' );
		}

		if ( ! is_string( $record['code'] ) ) {
			return new WP_Error( 'work_data_exchange_tax_rate_invalid_code' );
		}
		$code = $record['code'];
		if ( '' === $code || $code !== TaxRateRepository::normalize_code( $code ) ) {
			return new WP_Error( 'work_data_exchange_tax_rate_invalid_code' );
		}

		if ( ! is_string( $record['label'] ) ) {
			return new WP_Error( 'work_data_exchange_tax_rate_invalid_label' );
		}
		$label = sanitize_text_field( $record['label'] );
		if ( '' === $label || $label !== $record['label'] ) {
			return new WP_Error( 'work_data_exchange_tax_rate_invalid_label' );
		}

		if ( ! is_string( $record['country_code'] ) ) {
			return new WP_Error( 'work_data_exchange_tax_rate_invalid_country' );
		}
		$country = $record['country_code'];
		if ( '' !== $country && 1 !== preg_match( '/^[A-Z]{2}$/D', $country ) ) {
			return new WP_Error( 'work_data_exchange_tax_rate_invalid_country' );
		}

		$rate_bp = $record['rate_bp'];
		if ( is_string( $rate_bp ) && 1 === preg_match( '/^(?:0|[1-9][0-9]{0,4})$/D', $rate_bp ) ) {
			$rate_bp = (int) $rate_bp;
		}
		if ( ! is_int( $rate_bp ) || $rate_bp < 0 || $rate_bp > 10000 ) {
			return new WP_Error( 'work_data_exchange_tax_rate_invalid_rate' );
		}

		$is_active = $record['is_active'];
		if ( is_string( $is_active ) && in_array( $is_active, [ '0', '1' ], true ) ) {
			$is_active = '1' === $is_active;
		}
		if ( ! is_bool( $is_active ) ) {
			return new WP_Error( 'work_data_exchange_tax_rate_invalid_status' );
		}

		$valid_from  = self::validated_date( $record['valid_from'] );
		$valid_until = self::validated_date( $record['valid_until'] );
		if ( is_wp_error( $valid_from ) || is_wp_error( $valid_until ) ) {
			return new WP_Error( 'work_data_exchange_tax_rate_invalid_date' );
		}
		if ( null !== $valid_from && null !== $valid_until && $valid_until < $valid_from ) {
			return new WP_Error( 'work_data_exchange_tax_rate_invalid_date_range' );
		}

		return [
			'code'         => $code,
			'label'        => $label,
			'country_code' => $country,
			'rate_bp'      => $rate_bp,
			'is_active'    => $is_active,
			'valid_from'   => $valid_from,
			'valid_until'  => $valid_until,
		];
	}

	/** @param array<string,mixed> $current @param array<string,mixed> $record */
	private static function same_record( array $current, array $record ): bool {
		$projected = self::project_record( $current );
		return ! is_wp_error( $projected ) && $projected === $record;
	}

	private static function reference( string $code ): string {
		return self::REFERENCE_PREFIX . $code;
	}

	private static function validated_date( mixed $value ): string|WP_Error|null {
		if ( null === $value || '' === $value ) {
			return null;
		}
		if ( ! is_string( $value ) ) {
			return new WP_Error( 'work_data_exchange_tax_rate_invalid_date' );
		}
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $value );
		return $date && $date->format( 'Y-m-d' ) === $value
			? $value
			: new WP_Error( 'work_data_exchange_tax_rate_invalid_date' );
	}

	private static function nullable_date( mixed $value ): ?string {
		$value = (string) ( $value ?? '' );
		return '' === $value ? null : $value;
	}

	/** @return array<string,mixed> */
	private static function mapping_field( string $id, string $label, string $type, bool $required, array $aliases ): array {
		return [
			'id'          => $id,
			'label'       => $label,
			'type'        => $type,
			'required'    => $required,
			'readable'    => true,
			'writable'    => true,
			'aliases'     => $aliases,
			'description' => '',
		];
	}
}

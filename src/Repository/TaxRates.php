<?php
declare(strict_types=1);

namespace CB\Work\Repository;

use CB\Work\Database\Schema;
defined( 'ABSPATH' ) || exit;

final class TaxRates {
	/** @return array<int,array<string,mixed>> */
	public static function all( bool $include_inactive = true ): array {
		if ( ! self::schema_ready() ) {
			return [];
		}
		global $wpdb;
		$sql = 'SELECT * FROM ' . Schema::tax_rates_table();
		if ( ! $include_inactive ) {
			$sql .= ' WHERE is_active = 1';
		}
		$sql .= ' ORDER BY is_active DESC, country_code ASC, label ASC, id ASC';
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		return is_array( $rows ) ? $rows : [];
	}

	public static function count(): int {
		if ( ! self::schema_ready() ) {
			return 0;
		}
		global $wpdb;
		return max( 0, (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Schema::tax_rates_table() ) );
	}

	/** @return array<int,array<string,mixed>> */
	public static function available( ?string $on_date = null ): array {
		return array_values( array_filter(
			self::all( false ),
			static fn( array $row ): bool => self::is_available( $row, $on_date )
		) );
	}

	/** @return array<string,mixed>|null */
	public static function get( int $id ): ?array {
		if ( ! self::schema_ready() ) {
			return null;
		}
		global $wpdb;
		if ( $id <= 0 ) {
			return null;
		}
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . Schema::tax_rates_table() . ' WHERE id = %d LIMIT 1', $id ),
			ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}

	/** @param array<string,mixed> $row */
	public static function is_available( array $row, ?string $on_date = null ): bool {
		if ( empty( $row['is_active'] ) ) {
			return false;
		}

		$date = self::date( (string) ( $on_date ?? current_time( 'Y-m-d' ) ) );
		if ( null === $date ) {
			$date = current_time( 'Y-m-d' );
		}
		$from  = self::date( (string) ( $row['valid_from'] ?? '' ) );
		$until = self::date( (string) ( $row['valid_until'] ?? '' ) );
		if ( null !== $from && $date < $from ) {
			return false;
		}
		if ( null !== $until && $date > $until ) {
			return false;
		}
		return true;
	}

	/** @param array<string,mixed> $input */
	public static function create( array $input ): int {
		if ( ! self::schema_ready() ) {
			return 0;
		}
		global $wpdb;

		$label       = sanitize_text_field( (string) ( $input['label'] ?? '' ) );
		$raw_code    = trim( (string) ( $input['code'] ?? '' ) );
		$code        = self::normalize_code( '' !== $raw_code ? $raw_code : $label );
		$country     = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) ( $input['country_code'] ?? '' ) ) ?? '' );
		$country     = 2 === strlen( $country ) ? $country : '';
		$rate_bp     = self::parse_rate_bp( (string) ( $input['rate'] ?? '' ) );
		$valid_from  = self::date( (string) ( $input['valid_from'] ?? '' ) );
		$valid_until = self::date( (string) ( $input['valid_until'] ?? '' ) );

		if ( '' === $code || '' === $label || null === $rate_bp ) {
			return 0;
		}
		if ( $valid_from && $valid_until && $valid_until < $valid_from ) {
			return 0;
		}
		$exists = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . Schema::tax_rates_table() . ' WHERE code = %s LIMIT 1', $code ) );
		if ( $exists ) {
			return 0;
		}

		$now = current_time( 'mysql', true );
		$ok  = $wpdb->insert(
			Schema::tax_rates_table(),
			[
				'code'         => $code,
				'label'        => $label,
				'country_code' => $country,
				'rate_bp'      => $rate_bp,
				'is_active'    => 1,
				'valid_from'   => $valid_from,
				'valid_until'  => $valid_until,
				'created_at'   => $now,
				'updated_at'   => $now,
			],
			[ '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s' ]
		);
		return false === $ok ? 0 : (int) $wpdb->insert_id;
	}

	public static function set_active( int $id, bool $active ): bool {
		if ( ! self::schema_ready() ) {
			return false;
		}
		global $wpdb;
		if ( ! self::get( $id ) ) {
			return false;
		}
		$updated = $wpdb->update(
			Schema::tax_rates_table(),
			[ 'is_active' => $active ? 1 : 0, 'updated_at' => current_time( 'mysql', true ) ],
			[ 'id' => $id ],
			[ '%d', '%s' ],
			[ '%d' ]
		);
		return false !== $updated;
	}

	public static function normalize_code( string $value ): string {
		return substr( sanitize_title( $value ), 0, 64 );
	}

	public static function parse_rate_bp( string $value ): ?int {
		$value = str_replace( [ '%', ' ' ], '', trim( $value ) );
		$value = str_replace( ',', '.', $value );
		if ( 1 !== preg_match( '/^(\d{1,3})(?:\.(\d{1,2}))?$/D', $value, $matches ) ) {
			return null;
		}
		$whole    = (int) $matches[1];
		$fraction = isset( $matches[2] ) ? (int) str_pad( $matches[2], 2, '0' ) : 0;
		$bp       = ( $whole * 100 ) + $fraction;
		return $bp <= 10000 ? $bp : null;
	}

	public static function format_rate_bp( int $rate_bp ): string {
		$whole    = intdiv( max( 0, $rate_bp ), 100 );
		$fraction = max( 0, $rate_bp ) % 100;
		return 0 === $fraction ? (string) $whole : $whole . '.' . rtrim( str_pad( (string) $fraction, 2, '0', STR_PAD_LEFT ), '0' );
	}

	/** @param array<string,mixed> $row */
	public static function display_label( array $row ): string {
		$label   = (string) ( $row['label'] ?? '' );
		$country = (string) ( $row['country_code'] ?? '' );
		$rate    = self::format_rate_bp( (int) ( $row['rate_bp'] ?? 0 ) ) . '%';
		$prefix  = '' !== $country ? $country . ' · ' : '';
		return $prefix . $label . ' — ' . $rate;
	}

	/** @param array<string,mixed> $row */
	public static function validity_label( array $row ): string {
		$from  = (string) ( $row['valid_from'] ?? '' );
		$until = (string) ( $row['valid_until'] ?? '' );
		if ( '' === $from && '' === $until ) {
			return __( 'No date limit', 'core-blueprint-work' );
		}
		if ( '' !== $from && '' !== $until ) {
			return $from . ' → ' . $until;
		}
		return '' !== $from
			? sprintf( __( 'From %s', 'core-blueprint-work' ), $from )
			: sprintf( __( 'Until %s', 'core-blueprint-work' ), $until );
	}

	private static function schema_ready(): bool {
		return defined( 'CB_WORK_SCHEMA_VERSION' )
			&& CB_WORK_SCHEMA_VERSION === (string) get_option( Schema::OPTION, '0' );
	}

	private static function date( string $value ): ?string {
		$value = trim( $value );
		if ( '' === $value ) {
			return null;
		}
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $value );
		return $date && $date->format( 'Y-m-d' ) === $value ? $value : null;
	}
}

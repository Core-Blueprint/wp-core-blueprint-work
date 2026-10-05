<?php
declare(strict_types=1);

namespace CB\Work\Repository;

use CB\Work\Content\PostTypes;
use CB\Work\Database\Schema;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Stable portable identity registry for Work-owned entities.
 *
 * Database IDs remain local implementation details. Portable keys are immutable
 * UUIDv4 values with hard database uniqueness across the Work installation.
 */
final class PortableIdentities {
	public const PROJECT   = 'project';
	public const WORK_ITEM = 'work_item';

	private const TYPES = [ self::PROJECT, self::WORK_ITEM ];

	public static function init(): void {
		add_action( 'before_delete_post', [ self::class, 'before_delete_post' ], 5, 2 );
	}

	public static function get_or_create( string $entity_type, int $local_id ): string|WP_Error {
		$entity_type = self::entity_type( $entity_type );
		if ( '' === $entity_type || $local_id <= 0 || ! self::schema_ready() ) {
			return new WP_Error( 'work_portable_identity_invalid' );
		}

		$existing = self::portable_key( $entity_type, $local_id );
		if ( '' !== $existing ) {
			return $existing;
		}

		for ( $attempt = 0; $attempt < 5; $attempt++ ) {
			$key = strtolower( wp_generate_uuid4() );
			$result = self::claim( $entity_type, $local_id, $key );
			if ( ! is_wp_error( $result ) ) {
				return $result;
			}
			$existing = self::portable_key( $entity_type, $local_id );
			if ( '' !== $existing ) {
				return $existing;
			}
		}

		return new WP_Error( 'work_portable_identity_create_failed' );
	}

	public static function claim( string $entity_type, int $local_id, string $portable_key ): string|WP_Error {
		$entity_type  = self::entity_type( $entity_type );
		$portable_key = self::normalize_key( $portable_key );
		if ( '' === $entity_type || $local_id <= 0 || '' === $portable_key || ! self::schema_ready() ) {
			return new WP_Error( 'work_portable_identity_invalid' );
		}

		$mapped_local = self::local_id( $entity_type, $portable_key );
		if ( $mapped_local > 0 ) {
			return $mapped_local === $local_id
				? $portable_key
				: new WP_Error( 'work_portable_identity_collision' );
		}

		$existing = self::portable_key( $entity_type, $local_id );
		if ( '' !== $existing ) {
			return hash_equals( $existing, $portable_key )
				? $existing
				: new WP_Error( 'work_portable_identity_immutable' );
		}

		global $wpdb;
		$inserted = $wpdb->insert(
			Schema::portable_identities_table(),
			[
				'entity_type'  => $entity_type,
				'local_id'     => $local_id,
				'portable_key' => $portable_key,
				'created_at'   => current_time( 'mysql', true ),
			],
			[ '%s', '%d', '%s', '%s' ]
		);
		if ( false !== $inserted ) {
			return $portable_key;
		}

		$mapped_local = self::local_id( $entity_type, $portable_key );
		$existing     = self::portable_key( $entity_type, $local_id );
		if ( $mapped_local === $local_id && hash_equals( $existing, $portable_key ) ) {
			return $portable_key;
		}

		return new WP_Error( 'work_portable_identity_collision' );
	}

	public static function portable_key( string $entity_type, int $local_id ): string {
		$entity_type = self::entity_type( $entity_type );
		if ( '' === $entity_type || $local_id <= 0 || ! self::schema_ready() ) {
			return '';
		}
		global $wpdb;
		$value = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT portable_key FROM ' . Schema::portable_identities_table() . ' WHERE entity_type = %s AND local_id = %d LIMIT 1',
				$entity_type,
				$local_id
			)
		);
		return is_string( $value ) ? self::normalize_key( $value ) : '';
	}

	public static function local_id( string $entity_type, string $portable_key ): int {
		$entity_type  = self::entity_type( $entity_type );
		$portable_key = self::normalize_key( $portable_key );
		if ( '' === $entity_type || '' === $portable_key || ! self::schema_ready() ) {
			return 0;
		}
		global $wpdb;
		return max(
			0,
			(int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT local_id FROM ' . Schema::portable_identities_table() . ' WHERE entity_type = %s AND portable_key = %s LIMIT 1',
					$entity_type,
					$portable_key
				)
			)
		);
	}

	public static function remove( string $entity_type, int $local_id ): void {
		$entity_type = self::entity_type( $entity_type );
		if ( '' === $entity_type || $local_id <= 0 || ! self::schema_ready() ) {
			return;
		}
		global $wpdb;
		$wpdb->delete(
			Schema::portable_identities_table(),
			[ 'entity_type' => $entity_type, 'local_id' => $local_id ],
			[ '%s', '%d' ]
		);
	}

	public static function normalize_key( mixed $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}
		$value = strtolower( trim( $value ) );
		return 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $value )
			? $value
			: '';
	}

	public static function before_delete_post( int $post_id, \WP_Post $post ): void {
		$entity_type = match ( $post->post_type ) {
			PostTypes::PROJECT   => self::PROJECT,
			PostTypes::WORK_ITEM => self::WORK_ITEM,
			default              => '',
		};
		if ( '' !== $entity_type ) {
			self::remove( $entity_type, $post_id );
		}
	}

	private static function entity_type( string $entity_type ): string {
		$entity_type = sanitize_key( $entity_type );
		return in_array( $entity_type, self::TYPES, true ) ? $entity_type : '';
	}

	private static function schema_ready(): bool {
		return defined( 'CB_WORK_SCHEMA_VERSION' )
			&& CB_WORK_SCHEMA_VERSION === (string) get_option( Schema::OPTION, '0' );
	}

	private function __construct() {}
}

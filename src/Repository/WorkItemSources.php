<?php
declare(strict_types=1);

namespace CB\Work\Repository;

use CB\Work\Content\PostTypes;
use CB\Work\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Canonical external source identity for integration-created Work Items.
 *
 * A source answers one question only: which external record caused this Work
 * Item to exist? It is deliberately separate from ordinary many-to-many Work
 * Item relations.
 */
final class WorkItemSources {
	public const RECOVERY_PROVIDER = 'core-blueprint-work';
	public const RECOVERY_TYPE     = 'source_identity';

	public static function init(): void {
		add_action( 'before_delete_post', [ self::class, 'before_delete_post' ], 10, 2 );
	}

	/**
	 * Claims one external source identity for bounded idempotent creation.
	 *
	 * @return array{status:string,source_id:int,work_item_id:int,claim_token:string}|false
	 */
	public static function claim( string $provider, string $source_type, string $external_id, int $stale_seconds = 900 ): array|false {
		$identity = self::normalize_identity( $provider, $source_type, $external_id );
		if ( false === $identity || ! self::schema_ready() ) {
			return false;
		}

		global $wpdb;
		$token = str_replace( '-', '', wp_generate_uuid4() );
		$now   = current_time( 'mysql', true );
		$ok    = $wpdb->query(
			$wpdb->prepare(
				'INSERT IGNORE INTO ' . Schema::sources_table() . ' (work_item_id, provider, source_type, external_id, claim_token, claimed_at, created_at, linked_at) VALUES (NULL, %s, %s, %s, %s, %s, %s, NULL)',
				$identity['provider'],
				$identity['type'],
				$identity['id'],
				$token,
				$now,
				$now
			)
		);
		if ( 1 === $ok ) {
			return [
				'status'       => 'owned',
				'source_id'    => (int) $wpdb->insert_id,
				'work_item_id' => 0,
				'claim_token'  => $token,
			];
		}

		$row = self::find( $identity['provider'], $identity['type'], $identity['id'] );
		if ( null === $row ) {
			return false;
		}
		if ( (int) $row['work_item_id'] > 0 ) {
			return [
				'status'       => 'existing',
				'source_id'    => (int) $row['id'],
				'work_item_id' => (int) $row['work_item_id'],
				'claim_token'  => '',
			];
		}

		$stale_before = gmdate( 'Y-m-d H:i:s', time() - max( 1, $stale_seconds ) );
		$claimed_at   = (string) ( $row['claimed_at'] ?? '' );
		if ( '' !== $claimed_at && $claimed_at > $stale_before ) {
			return [
				'status'       => 'busy',
				'source_id'    => (int) $row['id'],
				'work_item_id' => 0,
				'claim_token'  => '',
			];
		}

		$updated = $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . Schema::sources_table() . ' SET claim_token = %s, claimed_at = %s WHERE id = %d AND work_item_id IS NULL AND (claimed_at IS NULL OR claimed_at <= %s)',
				$token,
				$now,
				(int) $row['id'],
				$stale_before
			)
		);
		if ( 1 === $updated ) {
			return [
				'status'       => 'owned',
				'source_id'    => (int) $row['id'],
				'work_item_id' => 0,
				'claim_token'  => $token,
			];
		}

		$fresh = self::get( (int) $row['id'] );
		if ( null !== $fresh && (int) $fresh['work_item_id'] > 0 ) {
			return [
				'status'       => 'existing',
				'source_id'    => (int) $fresh['id'],
				'work_item_id' => (int) $fresh['work_item_id'],
				'claim_token'  => '',
			];
		}

		return [
			'status'       => 'busy',
			'source_id'    => (int) $row['id'],
			'work_item_id' => 0,
			'claim_token'  => '',
		];
	}

	public static function attach( int $source_id, int $work_item_id, string $claim_token ): bool {
		if ( $source_id <= 0 || $work_item_id <= 0 || '' === $claim_token || ! self::schema_ready() || null === WorkItems::get( $work_item_id ) ) {
			return false;
		}

		global $wpdb;
		$updated = $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . Schema::sources_table() . " SET work_item_id = %d, claim_token = '', claimed_at = NULL, linked_at = %s WHERE id = %d AND work_item_id IS NULL AND claim_token = %s",
				$work_item_id,
				current_time( 'mysql', true ),
				$source_id,
				$claim_token
			)
		);
		if ( 1 === $updated ) {
			return true;
		}

		$row = self::get( $source_id );
		return null !== $row && $work_item_id === (int) $row['work_item_id'];
	}

	public static function release( int $source_id, string $claim_token ): bool {
		if ( $source_id <= 0 || '' === $claim_token || ! self::schema_ready() ) {
			return false;
		}
		global $wpdb;
		$deleted = $wpdb->query(
			$wpdb->prepare(
				'DELETE FROM ' . Schema::sources_table() . ' WHERE id = %d AND work_item_id IS NULL AND claim_token = %s',
				$source_id,
				$claim_token
			)
		);
		return 1 === $deleted;
	}

	/** @return array<string,mixed>|null */
	public static function find( string $provider, string $source_type, string $external_id ): ?array {
		$identity = self::normalize_identity( $provider, $source_type, $external_id );
		if ( false === $identity || ! self::schema_ready() ) {
			return null;
		}
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT id, work_item_id, provider, source_type, external_id, claim_token, claimed_at, created_at, linked_at FROM ' . Schema::sources_table() . ' WHERE provider = %s AND source_type = %s AND external_id = %s LIMIT 1',
				$identity['provider'],
				$identity['type'],
				$identity['id']
			),
			ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}

	/** @return array<string,mixed>|null */
	public static function get( int $source_id ): ?array {
		if ( $source_id <= 0 || ! self::schema_ready() ) {
			return null;
		}
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT id, work_item_id, provider, source_type, external_id, claim_token, claimed_at, created_at, linked_at FROM ' . Schema::sources_table() . ' WHERE id = %d LIMIT 1',
				$source_id
			),
			ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}

	/** @return array<string,mixed>|null */
	public static function for_work_item( int $work_item_id ): ?array {
		if ( $work_item_id <= 0 || ! self::schema_ready() ) {
			return null;
		}
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT id, work_item_id, provider, source_type, external_id, claim_token, claimed_at, created_at, linked_at FROM ' . Schema::sources_table() . ' WHERE work_item_id = %d LIMIT 1',
				$work_item_id
			),
			ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}

	/** @return int[] */
	public static function recovery_work_item_ids( int $source_id ): array {
		if ( $source_id <= 0 ) {
			return [];
		}
		return WorkItemRelations::find_work_item_ids(
			self::RECOVERY_PROVIDER,
			self::RECOVERY_TYPE,
			(string) $source_id
		);
	}

	/** @return array{id:int,work_item_id:int,provider:string,source_type:string,external_id:string,created_at:string,linked_at:?string}|null */
	public static function projection( ?array $row ): ?array {
		if ( null === $row || (int) ( $row['work_item_id'] ?? 0 ) <= 0 ) {
			return null;
		}
		return [
			'id'           => (int) $row['id'],
			'work_item_id' => (int) $row['work_item_id'],
			'provider'     => (string) $row['provider'],
			'source_type'  => (string) $row['source_type'],
			'external_id'  => (string) $row['external_id'],
			'created_at'   => (string) $row['created_at'],
			'linked_at'    => null === $row['linked_at'] ? null : (string) $row['linked_at'],
		];
	}

	public static function before_delete_post( int $post_id, \WP_Post $post ): void {
		if ( PostTypes::WORK_ITEM !== $post->post_type || ! self::schema_ready() ) {
			return;
		}
		global $wpdb;
		$wpdb->delete( Schema::sources_table(), [ 'work_item_id' => $post_id ], [ '%d' ] );
	}

	/** @return array{provider:string,type:string,id:string}|false */
	private static function normalize_identity( string $provider, string $source_type, string $external_id ): array|false {
		$provider    = substr( sanitize_key( $provider ), 0, 64 );
		$source_type = substr( sanitize_key( $source_type ), 0, 64 );
		$external_id = substr( sanitize_text_field( $external_id ), 0, 191 );
		if ( '' === $provider || '' === $source_type || '' === $external_id ) {
			return false;
		}
		return [ 'provider' => $provider, 'type' => $source_type, 'id' => $external_id ];
	}

	private static function schema_ready(): bool {
		return defined( 'CB_WORK_SCHEMA_VERSION' )
			&& (string) get_option( Schema::OPTION, '0' ) === (string) CB_WORK_SCHEMA_VERSION;
	}
}

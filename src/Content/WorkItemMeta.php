<?php
declare(strict_types=1);

namespace CB\Work\Content;

use CB\Work\Capabilities;
use CB\Work\Domain\WorkContext;
use CB\Work\Domain\WorkItemPriority;
use CB\Work\Domain\WorkItemStatus;

defined( 'ABSPATH' ) || exit;

final class WorkItemMeta {
	public const WORK_CONTEXT         = '_cb_work_item_context';
	public const CUSTOMER_PROVIDER    = '_cb_work_item_customer_provider';
	public const CUSTOMER_TYPE        = '_cb_work_item_customer_type';
	public const CUSTOMER_ID          = '_cb_work_item_customer_id';
	public const PROJECT_ID           = '_cb_work_item_project_id';
	public const SERVICE_ID           = '_cb_work_item_service_id';
	public const WORK_TYPE_ID         = '_cb_work_item_work_type_id';
	public const PRIORITY             = '_cb_work_item_priority';
	public const ESTIMATED_MINUTES    = '_cb_work_item_estimated_minutes';
	public const SCHEDULED_ON         = '_cb_work_item_scheduled_on';
	public const DUE_ON               = '_cb_work_item_due_on';
	public const STATUS               = '_cb_work_item_status';
	public const BILLING_DISPOSITION  = '_cb_work_item_billing_disposition';
	public const COMPLETED_AT         = '_cb_work_item_completed_at';
	public const COMPLETED_BY         = '_cb_work_item_completed_by';
	public const INITIALIZED          = '_cb_work_item_initialized';

	public static function init(): void {
		add_action( 'init', [ self::class, 'register_meta' ], 8 );
	}

	public static function register_meta(): void {
		$string = static fn( callable|string $sanitize ): array => [
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => false,
			'sanitize_callback' => $sanitize,
			'auth_callback'     => static fn(): bool => current_user_can( Capabilities::MANAGE ),
		];
		$integer = static fn(): array => [
			'type'              => 'integer',
			'single'            => true,
			'show_in_rest'      => false,
			'sanitize_callback' => 'absint',
			'auth_callback'     => static fn(): bool => current_user_can( Capabilities::MANAGE ),
		];

		register_post_meta( PostTypes::WORK_ITEM, self::WORK_CONTEXT, $string( [ WorkContext::class, 'sanitize' ] ) );
		register_post_meta( PostTypes::WORK_ITEM, self::CUSTOMER_PROVIDER, $string( [ self::class, 'sanitize_reference_part' ] ) );
		register_post_meta( PostTypes::WORK_ITEM, self::CUSTOMER_TYPE, $string( [ self::class, 'sanitize_reference_part' ] ) );
		register_post_meta( PostTypes::WORK_ITEM, self::CUSTOMER_ID, $string( [ self::class, 'sanitize_reference_id' ] ) );
		register_post_meta( PostTypes::WORK_ITEM, self::PROJECT_ID, $integer() );
		register_post_meta( PostTypes::WORK_ITEM, self::SERVICE_ID, $integer() );
		register_post_meta( PostTypes::WORK_ITEM, self::WORK_TYPE_ID, $integer() );
		register_post_meta( PostTypes::WORK_ITEM, self::PRIORITY, $string( 'sanitize_key' ) );
		register_post_meta( PostTypes::WORK_ITEM, self::ESTIMATED_MINUTES, $integer() );
		register_post_meta( PostTypes::WORK_ITEM, self::SCHEDULED_ON, $string( [ self::class, 'sanitize_date' ] ) );
		register_post_meta( PostTypes::WORK_ITEM, self::DUE_ON, $string( [ self::class, 'sanitize_date' ] ) );
		register_post_meta( PostTypes::WORK_ITEM, self::STATUS, $string( 'sanitize_key' ) );
		register_post_meta( PostTypes::WORK_ITEM, self::BILLING_DISPOSITION, $string( 'sanitize_key' ) );
		register_post_meta( PostTypes::WORK_ITEM, self::COMPLETED_AT, $string( [ self::class, 'sanitize_datetime' ] ) );
		register_post_meta( PostTypes::WORK_ITEM, self::COMPLETED_BY, $integer() );
		register_post_meta( PostTypes::WORK_ITEM, self::INITIALIZED, [
			'type'              => 'boolean',
			'single'            => true,
			'show_in_rest'      => false,
			'sanitize_callback' => 'rest_sanitize_boolean',
			'auth_callback'     => static fn(): bool => current_user_can( Capabilities::MANAGE ),
		] );
	}

	/** @return array<string,mixed> */
	public static function get( int $work_item_id ): array {
		$context  = WorkContext::sanitize( get_post_meta( $work_item_id, self::WORK_CONTEXT, true ) );
		$provider = (string) get_post_meta( $work_item_id, self::CUSTOMER_PROVIDER, true );
		$type     = (string) get_post_meta( $work_item_id, self::CUSTOMER_TYPE, true );
		$id       = (string) get_post_meta( $work_item_id, self::CUSTOMER_ID, true );
		if ( '' === $context && '' !== $provider && '' !== $type && '' !== $id ) {
			$context = WorkContext::CUSTOMER;
		}

		$priority = sanitize_key( (string) get_post_meta( $work_item_id, self::PRIORITY, true ) );
		$status   = sanitize_key( (string) get_post_meta( $work_item_id, self::STATUS, true ) );

		if ( ! WorkItemPriority::is_valid( $priority ) ) {
			$priority = WorkItemPriority::NORMAL;
		}
		if ( ! WorkItemStatus::is_valid( $status ) ) {
			$status = WorkItemStatus::PLANNED;
		}

		return [
			'work_context'        => $context,
			'customer_provider'   => $provider,
			'customer_type'       => $type,
			'customer_id'         => $id,
			'project_id'          => self::optional_id( $work_item_id, self::PROJECT_ID ),
			'service_id'          => self::optional_id( $work_item_id, self::SERVICE_ID ),
			'work_type_id'        => self::optional_id( $work_item_id, self::WORK_TYPE_ID ),
			'priority'            => $priority,
			'estimated_minutes'   => max( 0, (int) get_post_meta( $work_item_id, self::ESTIMATED_MINUTES, true ) ),
			'scheduled_on'        => self::optional_string( $work_item_id, self::SCHEDULED_ON ),
			'due_on'              => self::optional_string( $work_item_id, self::DUE_ON ),
			'status'              => $status,
			'billing_disposition' => (string) get_post_meta( $work_item_id, self::BILLING_DISPOSITION, true ),
			'completed_at'        => self::optional_string( $work_item_id, self::COMPLETED_AT ),
			'completed_by'        => self::optional_id( $work_item_id, self::COMPLETED_BY ),
		];
	}

	/** @param array<string,mixed> $details */
	public static function save_details( int $work_item_id, array $details ): void {
		self::write_string( $work_item_id, self::WORK_CONTEXT, (string) ( $details['work_context'] ?? '' ) );
		self::write_string( $work_item_id, self::CUSTOMER_PROVIDER, (string) ( $details['customer_provider'] ?? '' ) );
		self::write_string( $work_item_id, self::CUSTOMER_TYPE, (string) ( $details['customer_type'] ?? '' ) );
		self::write_string( $work_item_id, self::CUSTOMER_ID, (string) ( $details['customer_id'] ?? '' ) );
		self::write_id( $work_item_id, self::PROJECT_ID, (int) ( $details['project_id'] ?? 0 ) );
		self::write_id( $work_item_id, self::SERVICE_ID, (int) ( $details['service_id'] ?? 0 ) );
		self::write_id( $work_item_id, self::WORK_TYPE_ID, (int) ( $details['work_type_id'] ?? 0 ) );
		update_post_meta( $work_item_id, self::PRIORITY, sanitize_key( (string) ( $details['priority'] ?? WorkItemPriority::NORMAL ) ) );
		self::write_id( $work_item_id, self::ESTIMATED_MINUTES, (int) ( $details['estimated_minutes'] ?? 0 ) );
		self::write_string( $work_item_id, self::SCHEDULED_ON, (string) ( $details['scheduled_on'] ?? '' ) );
		self::write_string( $work_item_id, self::DUE_ON, (string) ( $details['due_on'] ?? '' ) );
		self::write_string( $work_item_id, self::BILLING_DISPOSITION, (string) ( $details['billing_disposition'] ?? '' ) );
	}

	/**
	 * Persist a validated transition while its caller holds the Work Item row lock.
	 * The optional previous raw value provides a metadata compare-and-swap guard.
	 * Callers must roll back the surrounding transaction on false.
	 */
	public static function set_status( int $work_item_id, string $status, int $actor_user_id = 0, ?string $previous_raw = null ): bool {
		$status = sanitize_key( $status );
		if ( ! WorkItemStatus::is_valid( $status ) ) {
			return false;
		}
		if ( null !== $previous_raw && (string) get_post_meta( $work_item_id, self::STATUS, true ) !== $previous_raw ) {
			return false;
		}
		if ( false === update_post_meta( $work_item_id, self::STATUS, $status, $previous_raw ?? '' ) ) {
			return false;
		}
		if ( WorkItemStatus::COMPLETED === $status ) {
			if ( false === update_post_meta( $work_item_id, self::COMPLETED_AT, current_time( 'mysql', true ) ) ) {
				return false;
			}
			if ( $actor_user_id > 0 && false === update_post_meta( $work_item_id, self::COMPLETED_BY, $actor_user_id ) ) {
				return false;
			}
			if ( $actor_user_id <= 0 ) {
				delete_post_meta( $work_item_id, self::COMPLETED_BY );
			}
		} else {
			delete_post_meta( $work_item_id, self::COMPLETED_AT );
			delete_post_meta( $work_item_id, self::COMPLETED_BY );
		}

		wp_cache_delete( $work_item_id, 'post_meta' );
		$actual = self::get( $work_item_id );
		return $actual['status'] === $status
			&& ( WorkItemStatus::COMPLETED === $status
				? null !== $actual['completed_at'] && ( $actor_user_id <= 0 || $actor_user_id === $actual['completed_by'] )
				: null === $actual['completed_at'] && null === $actual['completed_by'] );
	}

	/**
	 * Verifies every Work-owned detail after an attempted write. A failed WordPress
	 * metadata call cannot be mistaken for success simply because it returned void.
	 *
	 * @param array<string,mixed> $details Normalized WorkItems::normalize_write output.
	 */
	public static function details_match( int $work_item_id, array $details ): bool {
		wp_cache_delete( $work_item_id, 'post_meta' );
		$actual = self::get( $work_item_id );
		foreach ( [
			'work_context',
			'customer_provider',
			'customer_type',
			'customer_id',
			'project_id',
			'service_id',
			'work_type_id',
			'priority',
			'estimated_minutes',
			'scheduled_on',
			'due_on',
			'billing_disposition',
		] as $key ) {
			$expected = $details[ $key ] ?? null;
			if ( in_array( $key, [ 'project_id', 'service_id', 'work_type_id', 'estimated_minutes' ], true ) ) {
				if ( (int) $expected !== (int) ( $actual[ $key ] ?? 0 ) ) {
					return false;
				}
			} elseif ( (string) ( $expected ?? '' ) !== (string) ( $actual[ $key ] ?? '' ) ) {
				return false;
			}
		}
		return true;
	}

	public static function ensure_status( int $work_item_id ): void {
		$status = sanitize_key( (string) get_post_meta( $work_item_id, self::STATUS, true ) );
		if ( ! WorkItemStatus::is_valid( $status ) ) {
			update_post_meta( $work_item_id, self::STATUS, WorkItemStatus::PLANNED );
		}
	}

	public static function is_initialized( int $work_item_id ): bool {
		return (bool) get_post_meta( $work_item_id, self::INITIALIZED, true );
	}

	public static function mark_initialized( int $work_item_id ): void {
		update_post_meta( $work_item_id, self::INITIALIZED, 1 );
	}

	public static function sanitize_reference_part( mixed $value ): string {
		return substr( sanitize_key( is_scalar( $value ) ? (string) $value : '' ), 0, 64 );
	}

	public static function sanitize_reference_id( mixed $value ): string {
		return substr( sanitize_text_field( is_scalar( $value ) ? (string) $value : '' ), 0, 191 );
	}

	public static function sanitize_date( mixed $value ): string {
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';
		if ( '' === $value ) {
			return '';
		}
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $value );
		return $date && $date->format( 'Y-m-d' ) === $value ? $value : '';
	}

	public static function sanitize_datetime( mixed $value ): string {
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';
		if ( '' === $value ) {
			return '';
		}
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $value );
		return $date && $date->format( 'Y-m-d H:i:s' ) === $value ? $value : '';
	}

	private static function optional_id( int $post_id, string $key ): ?int {
		$value = absint( get_post_meta( $post_id, $key, true ) );
		return $value > 0 ? $value : null;
	}

	private static function optional_string( int $post_id, string $key ): ?string {
		$value = (string) get_post_meta( $post_id, $key, true );
		return '' !== $value ? $value : null;
	}

	private static function write_string( int $post_id, string $key, string $value ): void {
		$value = trim( $value );
		if ( '' === $value ) {
			delete_post_meta( $post_id, $key );
			return;
		}
		update_post_meta( $post_id, $key, $value );
	}

	private static function write_id( int $post_id, string $key, int $value ): void {
		$value = max( 0, $value );
		if ( 0 === $value ) {
			delete_post_meta( $post_id, $key );
			return;
		}
		update_post_meta( $post_id, $key, $value );
	}
}

<?php
declare(strict_types=1);

namespace CB\Work\Content;

use CB\Work\Capabilities;
use CB\Work\Domain\WorkContext;

defined( 'ABSPATH' ) || exit;

final class ProjectMeta {
	public const WORK_CONTEXT      = '_cb_work_project_context';
	public const CUSTOMER_PROVIDER = '_cb_work_project_customer_provider';
	public const CUSTOMER_TYPE     = '_cb_work_project_customer_type';
	public const CUSTOMER_ID       = '_cb_work_project_customer_id';
	public const STARTS_ON         = '_cb_work_project_starts_on';
	public const DUE_ON            = '_cb_work_project_due_on';
	public const INITIALIZED       = '_cb_work_project_initialized';

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

		register_post_meta( PostTypes::PROJECT, self::WORK_CONTEXT, $string( [ WorkContext::class, 'sanitize' ] ) );
		register_post_meta( PostTypes::PROJECT, self::CUSTOMER_PROVIDER, $string( [ self::class, 'sanitize_reference_part' ] ) );
		register_post_meta( PostTypes::PROJECT, self::CUSTOMER_TYPE, $string( [ self::class, 'sanitize_reference_part' ] ) );
		register_post_meta( PostTypes::PROJECT, self::CUSTOMER_ID, $string( [ self::class, 'sanitize_reference_id' ] ) );
		register_post_meta( PostTypes::PROJECT, self::STARTS_ON, $string( [ self::class, 'sanitize_date' ] ) );
		register_post_meta( PostTypes::PROJECT, self::DUE_ON, $string( [ self::class, 'sanitize_date' ] ) );
		register_post_meta( PostTypes::PROJECT, self::INITIALIZED, [
			'type'              => 'boolean',
			'single'            => true,
			'show_in_rest'      => false,
			'sanitize_callback' => 'rest_sanitize_boolean',
			'auth_callback'     => static fn(): bool => current_user_can( Capabilities::MANAGE ),
		] );
	}

	/** @return array{work_context:string,customer_provider:string,customer_type:string,customer_id:string,starts_on:string,due_on:string} */
	public static function get( int $project_id ): array {
		$provider = (string) get_post_meta( $project_id, self::CUSTOMER_PROVIDER, true );
		$type     = (string) get_post_meta( $project_id, self::CUSTOMER_TYPE, true );
		$id       = (string) get_post_meta( $project_id, self::CUSTOMER_ID, true );
		$context  = WorkContext::sanitize( get_post_meta( $project_id, self::WORK_CONTEXT, true ) );
		if ( '' === $context && self::reference_valid( $provider, $type, $id ) && '' !== $provider ) {
			$context = WorkContext::CUSTOMER;
		}
		return [
			'work_context'      => $context,
			'customer_provider' => $provider,
			'customer_type'     => $type,
			'customer_id'       => $id,
			'starts_on'         => (string) get_post_meta( $project_id, self::STARTS_ON, true ),
			'due_on'            => (string) get_post_meta( $project_id, self::DUE_ON, true ),
		];
	}

	/** @return array{provider:string,type:string,id:string} */
	public static function customer_reference( int $project_id ): array {
		$meta = self::get( $project_id );
		return [
			'provider' => $meta['customer_provider'],
			'type'     => $meta['customer_type'],
			'id'       => $meta['customer_id'],
		];
	}

	/** @param array<string,mixed> $input */
	public static function save( int $project_id, array $input ): bool {
		if ( PostTypes::PROJECT !== get_post_type( $project_id ) ) {
			return false;
		}

		$context  = WorkContext::sanitize( $input['work_context'] ?? '' );
		$provider = self::sanitize_reference_part( $input['customer_provider'] ?? '' );
		$type     = self::sanitize_reference_part( $input['customer_type'] ?? '' );
		$id       = self::sanitize_reference_id( $input['customer_id'] ?? '' );
		$starts   = self::sanitize_date( $input['starts_on'] ?? '' );
		$due      = self::sanitize_date( $input['due_on'] ?? '' );

		if ( '' === $context && '' !== $provider && self::reference_valid( $provider, $type, $id ) ) {
			$context = WorkContext::CUSTOMER;
		}
		if ( '' === $context && self::is_initialized( $project_id ) ) {
			// Legacy initialized Projects may remain pending classification until explicitly resolved.
			if ( ! self::reference_valid( $provider, $type, $id ) ) {
				return false;
			}
		} elseif ( ! WorkContext::is_valid( $context ) ) {
			return false;
		}
		if ( WorkContext::INTERNAL === $context ) {
			$provider = '';
			$type     = '';
			$id       = '';
		} elseif ( WorkContext::CUSTOMER === $context && ( '' === $provider || ! self::reference_valid( $provider, $type, $id ) ) ) {
			return false;
		} elseif ( ! self::reference_valid( $provider, $type, $id ) ) {
			return false;
		}
		if ( '' !== $starts && '' !== $due && $due < $starts ) {
			return false;
		}

		self::write( $project_id, self::WORK_CONTEXT, $context );
		self::write( $project_id, self::CUSTOMER_PROVIDER, $provider );
		self::write( $project_id, self::CUSTOMER_TYPE, $type );
		self::write( $project_id, self::CUSTOMER_ID, $id );
		self::write( $project_id, self::STARTS_ON, $starts );
		self::write( $project_id, self::DUE_ON, $due );
		return true;
	}

	public static function is_initialized( int $project_id ): bool {
		return (bool) get_post_meta( $project_id, self::INITIALIZED, true );
	}

	public static function mark_initialized( int $project_id ): void {
		update_post_meta( $project_id, self::INITIALIZED, 1 );
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

	private static function reference_valid( string $provider, string $type, string $id ): bool {
		return ( '' === $provider && '' === $type && '' === $id )
			|| ( '' !== $provider && '' !== $type && '' !== $id );
	}

	private static function write( int $project_id, string $key, string $value ): void {
		if ( '' === $value ) {
			delete_post_meta( $project_id, $key );
			return;
		}
		update_post_meta( $project_id, $key, $value );
	}
}

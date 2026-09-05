<?php
declare(strict_types=1);

namespace CB\Work\Query;

use CB\Work\Domain\BillingDisposition;
use CB\Work\Domain\WorkItemPriority;
use CB\Work\Domain\WorkItemStatus;

defined( 'ABSPATH' ) || exit;

/**
 * Internal canonical query criteria for operational Work Item views.
 *
 * This is not a D3 frontend/public resource contract. It normalizes Work-owned
 * operational query state so every admin renderer resolves the same dataset.
 */
final class WorkItemQuery {
	public const SORT_WORKLOAD  = 'workload';
	public const SORT_DUE       = 'due';
	public const SORT_SCHEDULED = 'scheduled';
	public const SORT_UPDATED   = 'updated';
	public const SORT_TITLE     = 'title';

	/** @return string[] */
	public static function sorts(): array {
		return [ self::SORT_WORKLOAD, self::SORT_DUE, self::SORT_SCHEDULED, self::SORT_UPDATED, self::SORT_TITLE ];
	}

	/**
	 * @param array<string,mixed> $input
	 * @return array<string,mixed>
	 */
	public static function normalize( array $input = [] ): array {
		$statuses   = self::enum_list( $input['statuses'] ?? [], [ WorkItemStatus::class, 'is_valid' ] );
		$priorities = self::enum_list( $input['priorities'] ?? [], [ WorkItemPriority::class, 'is_valid' ] );
		$billing    = self::enum_list( $input['billing_dispositions'] ?? [], [ BillingDisposition::class, 'is_valid' ] );
		$sort       = sanitize_key( (string) ( $input['sort'] ?? self::SORT_WORKLOAD ) );
		if ( ! in_array( $sort, self::sorts(), true ) ) {
			$sort = self::SORT_WORKLOAD;
		}

		$customer = self::customer_reference( $input['customer'] ?? null );

		return [
			'search'               => sanitize_text_field( trim( (string) ( $input['search'] ?? '' ) ) ),
			'statuses'             => $statuses,
			'priorities'           => $priorities,
			'project_id'           => absint( $input['project_id'] ?? 0 ),
			'service_id'           => absint( $input['service_id'] ?? 0 ),
			'work_type_id'         => absint( $input['work_type_id'] ?? 0 ),
			'assignee_id'          => absint( $input['assignee_id'] ?? 0 ),
			'billing_dispositions' => $billing,
			'customer'             => $customer,
			'scheduled_from'       => self::date( $input['scheduled_from'] ?? '' ),
			'scheduled_to'         => self::date( $input['scheduled_to'] ?? '' ),
			'due_from'             => self::date( $input['due_from'] ?? '' ),
			'due_to'               => self::date( $input['due_to'] ?? '' ),
			'sort'                 => $sort,
			'page'                 => max( 1, absint( $input['page'] ?? 1 ) ),
			'per_page'             => max( 1, min( 500, absint( $input['per_page'] ?? 50 ) ?: 50 ) ),
		];
	}

	/** @return string[] */
	private static function enum_list( mixed $raw, callable $validator ): array {
		if ( is_scalar( $raw ) ) {
			$raw = '' === trim( (string) $raw ) ? [] : [ $raw ];
		}
		if ( ! is_array( $raw ) ) {
			return [];
		}
		$values = [];
		foreach ( $raw as $value ) {
			$value = sanitize_key( is_scalar( $value ) ? (string) $value : '' );
			if ( '' !== $value && $validator( $value ) && ! in_array( $value, $values, true ) ) {
				$values[] = $value;
			}
		}
		return $values;
	}

	/** @return array{provider:string,type:string,id:string}|null */
	private static function customer_reference( mixed $raw ): ?array {
		if ( ! is_array( $raw ) ) {
			return null;
		}
		$provider = substr( sanitize_key( (string) ( $raw['provider'] ?? '' ) ), 0, 64 );
		$type     = substr( sanitize_key( (string) ( $raw['type'] ?? '' ) ), 0, 64 );
		$id       = substr( sanitize_text_field( (string) ( $raw['id'] ?? '' ) ), 0, 191 );
		if ( '' === $provider && '' === $type && '' === $id ) {
			return null;
		}
		return '' !== $provider && '' !== $type && '' !== $id
			? [ 'provider' => $provider, 'type' => $type, 'id' => $id ]
			: null;
	}

	private static function date( mixed $raw ): string {
		$value = is_scalar( $raw ) ? trim( (string) $raw ) : '';
		if ( '' === $value ) {
			return '';
		}
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $value );
		return $date && $date->format( 'Y-m-d' ) === $value ? $value : '';
	}
}

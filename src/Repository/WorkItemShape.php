<?php
declare(strict_types=1);

namespace CB\Work\Repository;

use CB\Work\Content\WorkItemMeta;
use CB\Work\Domain\BillingDisposition;
use CB\Work\Domain\WorkContext;
use CB\Work\Domain\WorkItemPriority;
use CB\Work\Domain\WorkItemStatus;
use CB\Work\PublicApi\Services;

defined( 'ABSPATH' ) || exit;

/**
 * B2.2: Work Item input normalization, bounded query metadata filters
 * and public repository read projection. WorkItems owns orchestration.
 */
final class WorkItemShape {
	public static function query_meta_filters( array $criteria ): array {
		$filters = [];
		if ( [] !== $criteria['statuses'] ) {
			$filters[] = [ 'key' => WorkItemMeta::STATUS, 'value' => $criteria['statuses'], 'compare' => 'IN' ];
		}
		if ( [] !== $criteria['priorities'] ) {
			$filters[] = [ 'key' => WorkItemMeta::PRIORITY, 'value' => $criteria['priorities'], 'compare' => 'IN' ];
		}
		foreach ( [
			WorkItemMeta::PROJECT_ID   => (int) $criteria['project_id'],
			WorkItemMeta::SERVICE_ID   => (int) $criteria['service_id'],
			WorkItemMeta::WORK_TYPE_ID => (int) $criteria['work_type_id'],
		] as $key => $value ) {
			if ( $value > 0 ) {
				$filters[] = [ 'key' => $key, 'value' => $value, 'type' => 'NUMERIC' ];
			}
		}
		if ( [] !== $criteria['billing_dispositions'] ) {
			$filters[] = [ 'key' => WorkItemMeta::BILLING_DISPOSITION, 'value' => $criteria['billing_dispositions'], 'compare' => 'IN' ];
		}
		if ( WorkContext::is_valid( (string) $criteria['work_context'] ) ) {
			$filters[] = [ 'key' => WorkItemMeta::WORK_CONTEXT, 'value' => (string) $criteria['work_context'] ];
		}
		if ( is_array( $criteria['customer'] ) ) {
			$filters[] = [ 'key' => WorkItemMeta::CUSTOMER_PROVIDER, 'value' => $criteria['customer']['provider'] ];
			$filters[] = [ 'key' => WorkItemMeta::CUSTOMER_TYPE, 'value' => $criteria['customer']['type'] ];
			$filters[] = [ 'key' => WorkItemMeta::CUSTOMER_ID, 'value' => $criteria['customer']['id'] ];
		}
		if ( '' !== $criteria['calendar_from'] && '' !== $criteria['calendar_to'] ) {
			$filters[] = [
				'relation' => 'OR',
				[
					'relation' => 'AND',
					[ 'key' => WorkItemMeta::SCHEDULED_ON, 'value' => $criteria['calendar_from'], 'compare' => '>=', 'type' => 'DATE' ],
					[ 'key' => WorkItemMeta::SCHEDULED_ON, 'value' => $criteria['calendar_to'], 'compare' => '<=', 'type' => 'DATE' ],
				],
				[
					'relation' => 'AND',
					[ 'key' => WorkItemMeta::DUE_ON, 'value' => $criteria['calendar_from'], 'compare' => '>=', 'type' => 'DATE' ],
					[ 'key' => WorkItemMeta::DUE_ON, 'value' => $criteria['calendar_to'], 'compare' => '<=', 'type' => 'DATE' ],
				],
			];
		}

		foreach ( [
			[ 'from' => 'scheduled_from', 'to' => 'scheduled_to', 'key' => WorkItemMeta::SCHEDULED_ON ],
			[ 'from' => 'due_from', 'to' => 'due_to', 'key' => WorkItemMeta::DUE_ON ],
		] as $range ) {
			if ( '' !== $criteria[ $range['from'] ] ) {
				$filters[] = [ 'key' => $range['key'], 'value' => $criteria[ $range['from'] ], 'compare' => '>=', 'type' => 'DATE' ];
			}
			if ( '' !== $criteria[ $range['to'] ] ) {
				$filters[] = [ 'key' => $range['key'], 'value' => $criteria[ $range['to'] ], 'compare' => '<=', 'type' => 'DATE' ];
			}
		}
		return $filters;
	}

	public static function empty_search_result( int $page, int $per_page ): array {
		return [ 'items' => [], 'total' => 0, 'page' => $page, 'per_page' => $per_page, 'pages' => 0 ];
	}

	/** @return array<string,mixed>|null */
	public static function normalize_write( array $input, ?array $current = null ): ?array {
		$title             = sanitize_text_field( (string) ( $input['title'] ?? ( $current['title'] ?? '' ) ) );
		$description       = (string) ( $input['description'] ?? ( $current['description'] ?? '' ) );
		$context           = WorkContext::sanitize( $input['work_context'] ?? ( $current['work_context'] ?? '' ) );
		$customer          = self::reference( $input, 'customer_', $current );
		$project_id        = max( 0, (int) ( $input['project_id'] ?? ( $current['project_id'] ?? 0 ) ) );
		$service_id        = max( 0, (int) ( $input['service_id'] ?? ( $current['service_id'] ?? 0 ) ) );
		$work_type_id      = max( 0, (int) ( $input['work_type_id'] ?? ( $current['work_type_id'] ?? 0 ) ) );
		$priority          = sanitize_key( (string) ( $input['priority'] ?? ( $current['priority'] ?? WorkItemPriority::NORMAL ) ) );
		$estimated_minutes = max( 0, (int) ( $input['estimated_minutes'] ?? ( $current['estimated_minutes'] ?? 0 ) ) );
		$scheduled_on      = self::date( (string) ( $input['scheduled_on'] ?? ( $current['scheduled_on'] ?? '' ) ) );
		$due_on            = self::date( (string) ( $input['due_on'] ?? ( $current['due_on'] ?? '' ) ) );
		$billing           = sanitize_key( (string) ( $input['billing_disposition'] ?? ( $current['billing_disposition'] ?? '' ) ) );

		$assignments_changed = array_key_exists( 'assigned_user_ids', $input ) || null === $current;
		$assignments = $assignments_changed
			? WorkItemLinks::normalize_assignments( $input['assigned_user_ids'] ?? [] )
			: (array) ( $current['assigned_user_ids'] ?? [] );

		if ( '' === $title || false === $customer || null === $assignments ) {
			return null;
		}

		if ( $project_id > 0 ) {
			$project = Projects::get( $project_id );
			if ( null === $project ) {
				return null;
			}
			$context = WorkContext::sanitize( $project['work_context'] ?? '' );
			if ( ! WorkContext::is_valid( $context ) ) {
				return null;
			}
			$customer = [
				'provider' => (string) ( $project['customer_provider'] ?? '' ),
				'type'     => (string) ( $project['customer_type'] ?? '' ),
				'id'       => (string) ( $project['customer_id'] ?? '' ),
			];
		} elseif ( '' === $context && '' !== $customer['provider'] ) {
			$context = WorkContext::CUSTOMER;
		}

		$legacy_unclassified = null !== $current
			&& '' === $context
			&& '' === (string) ( $current['work_context'] ?? '' );
		if ( ! WorkContext::is_valid( $context ) && ! $legacy_unclassified ) {
			return null;
		}

		if ( WorkContext::INTERNAL === $context ) {
			$customer = [ 'provider' => '', 'type' => '', 'id' => '' ];
			$billing  = BillingDisposition::NON_BILLABLE;
		} elseif ( WorkContext::CUSTOMER === $context ) {
			if ( '' === $customer['provider'] || '' === $customer['type'] || '' === $customer['id'] ) {
				return null;
			}
		}

		if ( $service_id > 0 && null === Services::get( $service_id ) ) {
			return null;
		}
		if ( $work_type_id > 0 && null === WorkTypes::get( $work_type_id ) ) {
			return null;
		}
		if ( ! WorkItemPriority::is_valid( $priority ) ) {
			return null;
		}
		if ( '' !== $billing && ! BillingDisposition::is_valid( $billing ) ) {
			return null;
		}
		if ( null !== $scheduled_on && null !== $due_on && $due_on < $scheduled_on ) {
			return null;
		}

		return [
			'title'               => $title,
			'description'         => $description,
			'work_context'        => $context,
			'customer_provider'   => $customer['provider'],
			'customer_type'       => $customer['type'],
			'customer_id'         => $customer['id'],
			'project_id'          => $project_id,
			'service_id'          => $service_id,
			'work_type_id'        => $work_type_id,
			'priority'            => $priority,
			'estimated_minutes'   => $estimated_minutes,
			'scheduled_on'        => $scheduled_on,
			'due_on'              => $due_on,
			'billing_disposition' => $billing,
			'assignments'         => $assignments,
			'assignments_changed' => $assignments_changed,
		];
	}

	/** @return array<string,mixed> */
	public static function hydrate( \WP_Post $post, ?array $assigned = null, ?array $links = null ): array {
		$meta = WorkItemMeta::get( (int) $post->ID );
		return [
			'id'                  => (int) $post->ID,
			'title'               => sanitize_text_field( (string) $post->post_title ),
			'description'         => (string) $post->post_content,
			'post_status'         => (string) $post->post_status,
			'work_context'      => $meta['work_context'],
			'customer_provider'   => $meta['customer_provider'],
			'customer_type'       => $meta['customer_type'],
			'customer_id'         => $meta['customer_id'],
			'project_id'          => $meta['project_id'],
			'service_id'          => $meta['service_id'],
			'work_type_id'        => $meta['work_type_id'],
			'priority'            => $meta['priority'],
			'estimated_minutes'   => $meta['estimated_minutes'],
			'scheduled_on'        => $meta['scheduled_on'],
			'due_on'              => $meta['due_on'],
			'status'              => $meta['status'],
			'billing_disposition' => $meta['billing_disposition'],
			'completed_at'        => $meta['completed_at'],
			'completed_by'        => $meta['completed_by'],
			'created_by'          => (int) $post->post_author,
			'created_at'          => (string) $post->post_date_gmt,
			'updated_at'          => (string) $post->post_modified_gmt,
			'assigned_user_ids'   => $assigned ?? WorkItemLinks::assignments( (int) $post->ID ),
			'relations'           => $links ?? WorkItemLinks::relations( (int) $post->ID ),
		];
	}

	/**
	 * @param array<string,mixed> $input
	 * @param array<string,mixed>|null $current
	 * @return array{provider:string,type:string,id:string}|false
	 */
	public static function reference( array $input, string $prefix, ?array $current = null ): array|false {
		$has_any = array_key_exists( $prefix . 'provider', $input )
			|| array_key_exists( $prefix . 'type', $input )
			|| array_key_exists( $prefix . 'id', $input );

		if ( ! $has_any && null !== $current ) {
			return [
				'provider' => (string) ( $current[ $prefix . 'provider' ] ?? '' ),
				'type'     => (string) ( $current[ $prefix . 'type' ] ?? '' ),
				'id'       => (string) ( $current[ $prefix . 'id' ] ?? '' ),
			];
		}

		$provider = substr( sanitize_key( (string) ( $input[ $prefix . 'provider' ] ?? '' ) ), 0, 64 );
		$type     = substr( sanitize_key( (string) ( $input[ $prefix . 'type' ] ?? '' ) ), 0, 64 );
		$id       = substr( sanitize_text_field( (string) ( $input[ $prefix . 'id' ] ?? '' ) ), 0, 191 );
		if ( '' === $provider && '' === $type && '' === $id ) {
			return [ 'provider' => '', 'type' => '', 'id' => '' ];
		}
		if ( '' === $provider || '' === $type || '' === $id ) {
			return false;
		}
		return [ 'provider' => $provider, 'type' => $type, 'id' => $id ];
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

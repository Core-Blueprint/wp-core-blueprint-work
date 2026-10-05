<?php
declare(strict_types=1);

namespace CB\Work\Admin;

use CB\Work\Domain\BillingDisposition;
use CB\Work\Domain\WorkContext;
use CB\Work\Domain\WorkItemPriority;
use CB\Work\Domain\WorkItemStatus;
use CB\Work\Integration\CRMCustomers;
use CB\Work\Query\WorkItemQuery;

defined( 'ABSPATH' ) || exit;

/** Canonical URL/view state for the operational Work Items workspace. */
final class WorkItemViewState {
	public const VIEW_LIST     = 'list';
	public const VIEW_KANBAN   = 'kanban';
	public const VIEW_TABLE    = 'table';
	public const VIEW_CALENDAR = 'calendar';

	/** @return string[] */
	public static function views(): array {
		return [ self::VIEW_LIST, self::VIEW_KANBAN, self::VIEW_TABLE, self::VIEW_CALENDAR ];
	}

	/**
	 * @param array<string,mixed> $request
	 * @return array<string,mixed>
	 */
	public static function from_request( array $request ): array {
		$view = self::key( $request['view'] ?? self::VIEW_TABLE );
		if ( ! in_array( $view, self::views(), true ) ) {
			$view = self::VIEW_TABLE;
		}

		$status = self::key( $request['status'] ?? '' );
		$statuses = [];
		if ( 'active' === $status ) {
			$statuses = WorkItemStatus::active();
		} elseif ( WorkItemStatus::is_valid( $status ) ) {
			$statuses = [ $status ];
		} else {
			$status = '';
		}

		$priority = self::key( $request['priority'] ?? '' );
		if ( ! WorkItemPriority::is_valid( $priority ) ) {
			$priority = '';
		}

		$context = WorkContext::sanitize( $request['work_context'] ?? '' );

		$billing = self::key( $request['billing'] ?? '' );
		if ( ! BillingDisposition::is_valid( $billing ) ) {
			$billing = '';
		}

		$customer_token = self::text( $request['customer'] ?? '' );
		$customer       = null;
		$customer_valid = true;
		if ( '' !== $customer_token ) {
			$reference = CRMCustomers::reference( $customer_token );
			if ( is_array( $reference ) ) {
				$customer = $reference;
			} else {
				$customer_valid = false;
			}
		}

		$sort = self::key( $request['sort'] ?? WorkItemQuery::SORT_WORKLOAD );
		if ( ! in_array( $sort, WorkItemQuery::sorts(), true ) ) {
			$sort = WorkItemQuery::SORT_WORKLOAD;
		}

		$calendar_month          = '';
		$scheduled_from          = self::date( $request['scheduled_from'] ?? '' );
		$scheduled_to            = self::date( $request['scheduled_to'] ?? '' );
		$query_scheduled_from    = $scheduled_from;
		$query_scheduled_to      = $scheduled_to;
		$per_page                = 50;
		if ( self::VIEW_CALENDAR === $view ) {
			$calendar_month = self::month( $request['calendar_month'] ?? '' );
			if ( '' === $calendar_month ) {
				$calendar_month = self::month( current_time( 'Y-m' ) );
			}
			$bounds               = self::month_bounds( $calendar_month );
			$query_scheduled_from = $bounds['from'];
			$query_scheduled_to   = $bounds['to'];
			$per_page             = 500;
		}

		$state = [
			'view'           => $view,
			'search'         => self::text( $request['s'] ?? '' ),
			'status'         => $status,
			'priority'       => $priority,
			'project_id'     => absint( $request['project_id'] ?? 0 ),
			'service_id'     => absint( $request['service_id'] ?? 0 ),
			'work_type_id'   => absint( $request['work_type_id'] ?? 0 ),
			'assignee_id'    => absint( $request['assignee_id'] ?? 0 ),
			'billing'        => $billing,
			'work_context'   => $context,
			'customer'       => $customer_token,
			'customer_valid' => $customer_valid,
			'calendar_month' => $calendar_month,
			// Explicit user filters only. Calendar month bounds are query viewport state.
			'scheduled_from' => $scheduled_from,
			'scheduled_to'   => $scheduled_to,
			'due_from'       => self::date( $request['due_from'] ?? '' ),
			'due_to'         => self::date( $request['due_to'] ?? '' ),
			'sort'           => $sort,
			'page'           => max( 1, absint( $request['paged'] ?? 1 ) ),
			'per_page'       => $per_page,
		];

		$state['query'] = WorkItemQuery::normalize( [
			'search'               => $state['search'],
			'statuses'             => $statuses,
			'priorities'           => '' === $priority ? [] : [ $priority ],
			'project_id'           => $state['project_id'],
			'service_id'           => $state['service_id'],
			'work_type_id'         => $state['work_type_id'],
			'assignee_id'          => $state['assignee_id'],
			'billing_dispositions' => '' === $billing ? [] : [ $billing ],
			'work_context'         => $context,
			'customer'             => $customer,
			'scheduled_from'       => $query_scheduled_from,
			'scheduled_to'         => $query_scheduled_to,
			'due_from'             => $state['due_from'],
			'due_to'               => $state['due_to'],
			'sort'                 => $sort,
			'page'                 => $state['page'],
			'per_page'             => $state['per_page'],
		] );

		return $state;
	}

	/**
	 * @param array<string,mixed> $state
	 * @param array<string,mixed> $overrides
	 * @return array<string,string|int>
	 */
	public static function query_args( array $state, array $overrides = [] ): array {
		$state = array_merge( $state, $overrides );
		$view  = (string) ( $state['view'] ?? self::VIEW_TABLE );
		$args = [
			'page' => Menu::WORK_ITEMS_SLUG,
			'view' => $view,
		];
		$map = [
			'search'         => 's',
			'status'         => 'status',
			'priority'       => 'priority',
			'project_id'     => 'project_id',
			'service_id'     => 'service_id',
			'work_type_id'   => 'work_type_id',
			'assignee_id'    => 'assignee_id',
			'billing'        => 'billing',
			'work_context'   => 'work_context',
			'customer'       => 'customer',
			'calendar_month' => 'calendar_month',
			'scheduled_from' => 'scheduled_from',
			'scheduled_to'   => 'scheduled_to',
			'due_from'       => 'due_from',
			'due_to'         => 'due_to',
			'sort'           => 'sort',
			'page'           => 'paged',
		];
		foreach ( $map as $state_key => $query_key ) {
			if ( 'calendar_month' === $state_key && self::VIEW_CALENDAR !== $view ) {
				continue;
			}
			$value = $state[ $state_key ] ?? '';
			if ( ( is_int( $value ) && $value > 0 ) || ( is_string( $value ) && '' !== $value ) ) {
				if ( 'page' === $state_key && 1 === (int) $value ) {
					continue;
				}
				if ( 'sort' === $state_key && WorkItemQuery::SORT_WORKLOAD === $value ) {
					continue;
				}
				$args[ $query_key ] = $value;
			}
		}
		return $args;
	}

	private static function key( mixed $raw ): string {
		return sanitize_key( is_scalar( $raw ) ? (string) $raw : '' );
	}

	private static function text( mixed $raw ): string {
		return sanitize_text_field( trim( is_scalar( $raw ) ? (string) $raw : '' ) );
	}

	private static function date( mixed $raw ): string {
		$value = is_scalar( $raw ) ? trim( (string) $raw ) : '';
		if ( '' === $value ) {
			return '';
		}
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $value );
		return $date && $date->format( 'Y-m-d' ) === $value ? $value : '';
	}

	private static function month( mixed $raw ): string {
		$value = is_scalar( $raw ) ? trim( (string) $raw ) : '';
		if ( 1 !== preg_match( '/^\d{4}-(0[1-9]|1[0-2])$/', $value ) ) {
			return '';
		}
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $value . '-01' );
		return $date && $date->format( 'Y-m' ) === $value ? $value : '';
	}

	/** @return array{from:string,to:string} */
	private static function month_bounds( string $month ): array {
		$first = \DateTimeImmutable::createFromFormat( '!Y-m-d', $month . '-01' );
		if ( ! $first ) {
			return [ 'from' => '', 'to' => '' ];
		}
		return [
			'from' => $first->format( 'Y-m-d' ),
			'to'   => $first->modify( 'last day of this month' )->format( 'Y-m-d' ),
		];
	}
}

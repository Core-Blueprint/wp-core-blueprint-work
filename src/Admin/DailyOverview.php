<?php
declare(strict_types=1);

namespace CB\Work\Admin;

use CB\Work\Domain\WorkItemStatus;
use CB\Work\PublicApi\Billing;
use CB\Work\Query\WorkItemQuery;
use CB\Work\Repository\Timers;
use CB\Work\Repository\WorkItems;

defined( 'ABSPATH' ) || exit;

/**
 * Bounded read model for the daily operational cockpit.
 *
 * No persistence lives here. It composes existing Work Item, Time and
 * billing-readiness truth into small actionable queues.
 */
final class DailyOverview {
	private const QUEUE_LIMIT = 8;

	/** @return array<string,mixed> */
	public static function snapshot( int $user_id ): array {
		$today = current_time( 'Y-m-d' );
		$date  = \DateTimeImmutable::createFromFormat( '!Y-m-d', $today );
		if ( ! $date ) {
			$date  = new \DateTimeImmutable( 'today', wp_timezone() );
			$today = $date->format( 'Y-m-d' );
		}
		$tomorrow  = $date->modify( '+1 day' )->format( 'Y-m-d' );
		$week_end  = $date->modify( '+7 days' )->format( 'Y-m-d' );
		$yesterday = $date->modify( '-1 day' )->format( 'Y-m-d' );

		$scheduled_today = self::items( [
			'statuses'       => WorkItemStatus::active(),
			'scheduled_from' => $today,
			'scheduled_to'   => $today,
		] );
		$due_today = self::items( [
			'statuses' => WorkItemStatus::active(),
			'due_from' => $today,
			'due_to'   => $today,
		] );

		return [
			'today'         => self::dedupe( [ ...$scheduled_today, ...$due_today ] ),
			'overdue'       => self::items( [
				'statuses' => WorkItemStatus::active(),
				'due_to'   => $yesterday,
			] ),
			'blocked'       => self::items( [ 'statuses' => [ WorkItemStatus::BLOCKED ] ] ),
			'in_progress'   => self::items( [ 'statuses' => [ WorkItemStatus::IN_PROGRESS ] ] ),
			'due_soon'      => self::items( [
				'statuses' => WorkItemStatus::active(),
				'due_from' => $tomorrow,
				'due_to'   => $week_end,
			] ),
			'active_timer'  => $user_id > 0 ? Timers::active_for_user( $user_id ) : null,
			'ready_to_bill' => self::ready_to_bill(),
		];
	}

	/** @param array<string,mixed> $criteria @return array<int,array<string,mixed>> */
	private static function items( array $criteria ): array {
		$result = WorkItems::search( [
			...$criteria,
			'page'     => 1,
			'per_page' => self::QUEUE_LIMIT,
			'sort'     => WorkItemQuery::SORT_WORKLOAD,
		] );
		return (array) ( $result['items'] ?? [] );
	}

	/** @param array<int,array<string,mixed>> $items @return array<int,array<string,mixed>> */
	private static function dedupe( array $items ): array {
		$out = [];
		foreach ( $items as $item ) {
			$id = (int) ( $item['id'] ?? 0 );
			if ( $id > 0 ) {
				$out[ $id ] = $item;
			}
			if ( count( $out ) >= self::QUEUE_LIMIT ) {
				break;
			}
		}
		return array_values( $out );
	}

	/** @return array<int,array<string,mixed>> */
	private static function ready_to_bill(): array {
		$result = Billing::ready( self::QUEUE_LIMIT );
		if ( is_wp_error( $result ) ) {
			return [];
		}
		return array_values( array_filter(
			(array) ( $result['items'] ?? [] ),
			static fn( array $item ): bool => empty( $item['stale'] )
		) );
	}
}

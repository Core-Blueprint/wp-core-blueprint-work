<?php
declare(strict_types=1);

namespace CB\Work\Frontend\Conditions;

use CB\Work\Domain\WorkItemPriority;
use CB\Work\Domain\WorkItemStatus;
use CB\Work\Frontend\Access;
use CB\Work\Frontend\Data\Project;
use CB\Work\Frontend\Data\Service;
use CB\Work\Frontend\Data\WorkItem;

defined( 'ABSPATH' ) || exit;

/** Pure builder-neutral predicates over frontend-authorized Work resources. */
final class Resources {
	public static function service_available( int $service_id ): bool {
		return ! is_wp_error( Service::get( $service_id ) );
	}

	public static function project_available( int $project_id ): bool {
		return ! is_wp_error( Project::get( $project_id ) );
	}

	public static function work_item_available( int $work_item_id ): bool {
		return ! is_wp_error( WorkItem::get( $work_item_id ) );
	}

	public static function work_item_status_is( int $work_item_id, string $status ): bool {
		$status = sanitize_key( $status );
		if ( ! WorkItemStatus::is_valid( $status ) ) {
			return false;
		}
		$item = WorkItem::get( $work_item_id );
		return ! is_wp_error( $item ) && $status === (string) ( $item['status'] ?? '' );
	}

	public static function work_item_priority_is( int $work_item_id, string $priority ): bool {
		$priority = sanitize_key( $priority );
		if ( ! WorkItemPriority::is_valid( $priority ) ) {
			return false;
		}
		$item = WorkItem::get( $work_item_id );
		return ! is_wp_error( $item ) && $priority === (string) ( $item['priority'] ?? '' );
	}

	public static function work_item_has_project( int $work_item_id, int $project_id ): bool {
		$item = WorkItem::get( $work_item_id );
		return $project_id > 0 && ! is_wp_error( $item ) && $project_id === (int) ( $item['project_id'] ?? 0 );
	}

	public static function work_item_has_service( int $work_item_id, int $service_id ): bool {
		$item = WorkItem::get( $work_item_id );
		return $service_id > 0 && ! is_wp_error( $item ) && $service_id === (int) ( $item['service_id'] ?? 0 );
	}

	public static function work_item_can_transition_to( int $work_item_id, string $to ): bool {
		$to = sanitize_key( $to );
		if ( ! WorkItemStatus::is_valid( $to ) || ! Access::can_transition_work_item( $work_item_id, $to ) ) {
			return false;
		}
		$item = WorkItem::get( $work_item_id );
		return ! is_wp_error( $item )
			&& WorkItemStatus::can_transition( (string) ( $item['status'] ?? '' ), $to );
	}
}

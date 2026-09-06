<?php
declare(strict_types=1);

namespace CB\Work\Frontend\Data;

use CB\Work\Frontend\Access;
use CB\Work\PublicApi\WorkItems;

defined( 'ABSPATH' ) || exit;

/** Builder-neutral frontend-safe Work Item projection. */
final class WorkItem {
	/** @return array{id:int,title:string,description:string,project_id:int,service_id:int,work_type_id:int,priority:string,estimated_minutes:int,scheduled_on:string,due_on:string,status:string}|\WP_Error */
	public static function get( int $work_item_id ): array|\WP_Error {
		if ( $work_item_id <= 0 || ! Access::can_read_work_item( $work_item_id ) ) {
			return new \WP_Error( 'work_resource_unavailable' );
		}

		$work_item = WorkItems::get( $work_item_id );
		if ( ! is_array( $work_item ) ) {
			return new \WP_Error( 'work_resource_unavailable' );
		}

		return [
			'id'                => (int) ( $work_item['id'] ?? 0 ),
			'title'             => (string) ( $work_item['title'] ?? '' ),
			'description'       => (string) ( $work_item['description'] ?? '' ),
			'project_id'        => (int) ( $work_item['project_id'] ?? 0 ),
			'service_id'        => (int) ( $work_item['service_id'] ?? 0 ),
			'work_type_id'      => (int) ( $work_item['work_type_id'] ?? 0 ),
			'priority'          => (string) ( $work_item['priority'] ?? '' ),
			'estimated_minutes' => max( 0, (int) ( $work_item['estimated_minutes'] ?? 0 ) ),
			'scheduled_on'      => (string) ( $work_item['scheduled_on'] ?? '' ),
			'due_on'            => (string) ( $work_item['due_on'] ?? '' ),
			'status'            => (string) ( $work_item['status'] ?? '' ),
		];
	}
}

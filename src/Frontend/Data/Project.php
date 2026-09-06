<?php
declare(strict_types=1);

namespace CB\Work\Frontend\Data;

use CB\Work\Frontend\Access;
use CB\Work\PublicApi\Projects;

defined( 'ABSPATH' ) || exit;

/** Builder-neutral frontend-safe Project projection. */
final class Project {
	/** @return array{id:int,title:string,description:string,starts_on:string,due_on:string}|\WP_Error */
	public static function get( int $project_id ): array|\WP_Error {
		if ( $project_id <= 0 || ! Access::can_read_project( $project_id ) ) {
			return new \WP_Error( 'work_resource_unavailable' );
		}

		$project = Projects::get( $project_id );
		if ( ! is_array( $project ) ) {
			return new \WP_Error( 'work_resource_unavailable' );
		}

		return [
			'id'          => (int) ( $project['id'] ?? 0 ),
			'title'       => (string) ( $project['title'] ?? '' ),
			'description' => (string) ( $project['description'] ?? '' ),
			'starts_on'   => (string) ( $project['starts_on'] ?? '' ),
			'due_on'      => (string) ( $project['due_on'] ?? '' ),
		];
	}
}

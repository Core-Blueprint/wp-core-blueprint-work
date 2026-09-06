<?php
declare(strict_types=1);

namespace CB\Work\Frontend\Data;

use CB\Work\Frontend\Access;
use CB\Work\PublicApi\Services;

defined( 'ABSPATH' ) || exit;

/** Builder-neutral frontend-safe Service projection. */
final class Service {
	/** @return array{id:int,title:string,description:string}|\WP_Error */
	public static function get( int $service_id ): array|\WP_Error {
		if ( $service_id <= 0 || ! Access::can_read_service( $service_id ) ) {
			return new \WP_Error( 'work_resource_unavailable' );
		}

		$service = Services::get( $service_id );
		if ( ! is_array( $service ) ) {
			return new \WP_Error( 'work_resource_unavailable' );
		}

		return [
			'id'          => (int) ( $service['id'] ?? 0 ),
			'title'       => (string) ( $service['title'] ?? '' ),
			'description' => (string) ( $service['description'] ?? '' ),
		];
	}
}

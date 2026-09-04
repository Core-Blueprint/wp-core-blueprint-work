<?php
declare(strict_types=1);

namespace CB\Work\PublicApi;

use CB\Work\Content\PostTypes;
use CB\Work\Content\ServicePricing;

defined( 'ABSPATH' ) || exit;

/** Supported read-only service catalog contract for sibling integrations. */
final class Services {
	/** @return array<string,mixed>|null */
	public static function get( int $service_id ): ?array {
		$post = get_post( $service_id );
		if ( ! $post instanceof \WP_Post || PostTypes::SERVICE !== $post->post_type || 'trash' === $post->post_status ) {
			return null;
		}
		return self::project( $post );
	}

	/** @return array<int,array<string,mixed>> */
	public static function all( int $limit = 100, array $statuses = [ 'publish', 'draft', 'private' ] ): array {
		$limit    = max( 1, min( 250, $limit ) );
		$allowed  = [ 'publish', 'draft', 'private', 'pending', 'future' ];
		$statuses = array_values( array_intersect( array_map( 'sanitize_key', $statuses ), $allowed ) );
		if ( [] === $statuses ) {
			$statuses = [ 'publish' ];
		}

		$posts = get_posts( [
			'post_type'      => PostTypes::SERVICE,
			'post_status'    => $statuses,
			'posts_per_page' => $limit,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		] );
		return array_values( array_map( [ self::class, 'project' ], $posts ) );
	}

	/** @return array<string,mixed> */
	private static function project( \WP_Post $post ): array {
		return [
			'id'          => (int) $post->ID,
			'title'       => (string) $post->post_title,
			'description' => (string) $post->post_content,
			'status'      => (string) $post->post_status,
			'pricing'     => ServicePricing::get( (int) $post->ID ),
		];
	}
}

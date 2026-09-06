<?php
declare(strict_types=1);

namespace CB\Work\Frontend;

use CB\Work\Capabilities;
use CB\Work\Content\PostTypes;
use CB\Work\Domain\WorkItemStatus;

defined( 'ABSPATH' ) || exit;

/**
 * Builder-neutral frontend authorization boundary for Work resources.
 *
 * Canonical Work content remains private by default. Work managers may resolve
 * records for authenticated preview/administration. Other users receive no
 * frontend access unless a site/integration explicitly opts a specific record
 * in through the Work-owned read filter. Storage existence is never frontend
 * authorization.
 */
final class Access {
	public static function can_read_service( int|\WP_Post $service ): bool {
		return self::can_read( $service, PostTypes::SERVICE );
	}

	public static function can_read_project( int|\WP_Post $project ): bool {
		return self::can_read( $project, PostTypes::PROJECT );
	}

	public static function can_read_work_item( int|\WP_Post $work_item ): bool {
		return self::can_read( $work_item, PostTypes::WORK_ITEM );
	}

	/**
	 * Frontend mutation authorization is deliberately stricter than read access.
	 * A non-manager must be logged in, be allowed to read the Work Item and be
	 * explicitly opted in for the requested target status.
	 */
	public static function can_transition_work_item( int|\WP_Post $work_item, string $to ): bool {
		$post = self::resolve( $work_item );
		$to   = sanitize_key( $to );
		if (
			! $post instanceof \WP_Post
			|| PostTypes::WORK_ITEM !== $post->post_type
			|| ! WorkItemStatus::is_valid( $to )
			|| ! self::can_read_work_item( $post )
		) {
			return false;
		}

		if ( current_user_can( Capabilities::MANAGE ) ) {
			return true;
		}

		$actor_user_id = get_current_user_id();
		if ( $actor_user_id <= 0 ) {
			return false;
		}

		return true === apply_filters(
			'cb_work_frontend_can_transition_work_item',
			false,
			$post,
			$to,
			$actor_user_id
		);
	}

	private static function can_read( int|\WP_Post $resource, string $expected_post_type ): bool {
		$post = self::resolve( $resource );
		if ( ! $post instanceof \WP_Post || $expected_post_type !== $post->post_type ) {
			return false;
		}

		if ( (int) $post->ID <= 0 || in_array( $post->post_status, [ 'trash', 'auto-draft' ], true ) ) {
			return false;
		}

		if ( current_user_can( Capabilities::MANAGE ) ) {
			return true;
		}

		if ( 'publish' !== $post->post_status || post_password_required( $post ) ) {
			return false;
		}

		return true === apply_filters(
			'cb_work_frontend_can_read',
			false,
			$post,
			$expected_post_type,
			get_current_user_id()
		);
	}

	private static function resolve( int|\WP_Post $resource ): ?\WP_Post {
		$post = $resource instanceof \WP_Post ? $resource : get_post( $resource );
		return $post instanceof \WP_Post ? $post : null;
	}
}

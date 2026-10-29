<?php
declare(strict_types=1);

namespace CB\Work\Repository;

use CB\Work\Governance\DeferredEvents;

use CB\Work\Content\PostTypes;
use CB\Work\Content\ProjectMeta;

defined( 'ABSPATH' ) || exit;

final class Projects {
	/** @return array<int,array<string,mixed>> */
	public static function all( int $limit = 250 ): array {
		$limit = max( 1, min( 500, $limit ) );
		$query = new \WP_Query( [
			'post_type'           => PostTypes::PROJECT,
			'post_status'         => [ 'publish', 'draft', 'pending', 'private', 'future' ],
			'posts_per_page'      => $limit,
			'orderby'             => [ 'title' => 'ASC', 'ID' => 'ASC' ],
			'order'               => 'ASC',
			'ignore_sticky_posts' => true,
		] );

		$projects = [];
		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			$project = self::project( $post );
			if ( null !== $project ) {
				$projects[] = $project;
			}
		}
		return $projects;
	}

	/** @return array<string,mixed>|null */
	public static function get( int $id ): ?array {
		$post = $id > 0 ? get_post( $id ) : null;
		if ( ! $post instanceof \WP_Post || PostTypes::PROJECT !== $post->post_type || 'trash' === $post->post_status ) {
			return null;
		}
		return self::project( $post );
	}

	public static function exists( int $id ): bool {
		return null !== self::get( $id );
	}

	public static function count(): int {
		$counts = wp_count_posts( PostTypes::PROJECT );
		$total  = 0;
		if ( is_object( $counts ) ) {
			foreach ( get_object_vars( $counts ) as $status => $count ) {
				if ( ! in_array( $status, [ 'trash', 'auto-draft' ], true ) ) {
					$total += max( 0, (int) $count );
				}
			}
		}
		return $total;
	}

	/**
	 * Programmatic Project creation through the canonical CPT domain.
	 *
	 * Normal human administration uses WordPress's native Project editor.
	 *
	 * @param array<string,mixed> $input
	 */
	public static function create( array $input ): int {
		$title = sanitize_text_field( (string) ( $input['title'] ?? '' ) );
		if ( '' === $title ) {
			return 0;
		}

		$post_id = wp_insert_post( [
			'post_type'    => PostTypes::PROJECT,
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_content' => wp_kses_post( (string) ( $input['description'] ?? '' ) ),
			'post_author'  => max( 0, (int) ( $input['created_by'] ?? get_current_user_id() ) ),
		], true );

		if ( is_wp_error( $post_id ) || $post_id <= 0 ) {
			return 0;
		}
		if ( ! ProjectMeta::save( $post_id, $input ) ) {
			wp_delete_post( $post_id, true );
			return 0;
		}

		ProjectMeta::mark_initialized( $post_id );
		DeferredEvents::lifecycle( 'cb_work_project_created', $post_id, self::get( $post_id ) );
		return $post_id;
	}


	/** @param array<string,mixed> $input */
	public static function update( int $id, array $input, ?string $expected_revision = null ): bool {
		if ( null !== $expected_revision ) {
			// M2 revision verification and canonical Project update run under one
			// Work-owned row lock. Legacy PHP callers keep their existing API.
			$changed = WorkSqlTransaction::run( static function () use ( $id, $input, $expected_revision ): bool {
				global $wpdb;
				$locked = $wpdb->get_row( $wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE ID = %d AND post_type = %s AND post_status NOT IN ('trash','auto-draft') FOR UPDATE",
					$id, PostTypes::PROJECT
				), ARRAY_A );
				if ( ! is_array( $locked ) || (int) $locked['ID'] !== $id ) {
					return false;
				}
				clean_post_cache( $id );
				wp_cache_delete( $id, 'post_meta' );
				$fresh = self::get( $id );
				if ( null === $fresh || '' === $expected_revision
					|| ! hash_equals( EditorRevisions::for_project( $fresh ), $expected_revision ) ) {
					return false;
				}
				return self::update( $id, $input );
			}, false );
			clean_post_cache( $id );
			wp_cache_delete( $id, 'post_meta' );
			return true === $changed;
		}

		$current = self::get( $id );
		if ( null === $current ) {
			return false;
		}

		$title = sanitize_text_field( (string) ( $input['title'] ?? $current['title'] ) );
		if ( '' === $title ) {
			return false;
		}
		$description = (string) ( $input['description'] ?? $current['description'] );
		$meta = [
			'work_context'      => $input['work_context'] ?? $current['work_context'],
			'customer_provider' => $input['customer_provider'] ?? $current['customer_provider'],
			'customer_type'     => $input['customer_type'] ?? $current['customer_type'],
			'customer_id'       => $input['customer_id'] ?? $current['customer_id'],
			'starts_on'         => $input['starts_on'] ?? $current['starts_on'],
			'due_on'            => $input['due_on'] ?? $current['due_on'],
		];
		if ( null === ProjectMeta::normalize( $id, $meta ) ) {
			return false;
		}

		// Partial Project updates must retain Gutenberg content verbatim.
		$post_update = [ 'ID' => $id, 'post_title' => $title ];
		if ( array_key_exists( 'description', $input ) ) {
			$post_update['post_content'] = wp_kses_post( $description );
		}
		$result = wp_update_post( $post_update, true );
		if ( is_wp_error( $result ) || $result <= 0 || ! ProjectMeta::save( $id, $meta ) ) {
			return false;
		}

		if ( ! ProjectMeta::is_initialized( $id ) ) {
			ProjectMeta::mark_initialized( $id );
		}
		DeferredEvents::lifecycle( 'cb_work_project_updated', $id, self::get( $id ), $current );
		return true;
	}

	/** @return array<string,mixed>|null */
	private static function project( \WP_Post $post ): ?array {
		if ( PostTypes::PROJECT !== $post->post_type ) {
			return null;
		}
		$meta = ProjectMeta::get( (int) $post->ID );
		return [
			'id'                => (int) $post->ID,
			'title'             => sanitize_text_field( (string) $post->post_title ),
			'description'       => (string) $post->post_content,
			'post_status'       => (string) $post->post_status,
			'work_context'      => $meta['work_context'],
			'customer_provider' => $meta['customer_provider'],
			'customer_type'     => $meta['customer_type'],
			'customer_id'       => $meta['customer_id'],
			'starts_on'         => $meta['starts_on'],
			'due_on'            => $meta['due_on'],
			'created_by'        => (int) $post->post_author,
			'created_at'        => (string) $post->post_date_gmt,
			'updated_at'        => (string) $post->post_modified_gmt,
		];
	}
}

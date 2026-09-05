<?php
declare(strict_types=1);

namespace CB\Work\Content;

use CB\Work\Capabilities;

defined( 'ABSPATH' ) || exit;

final class PostTypes {
	public const SERVICE = 'cb_work_service';
	public const PROJECT = 'cb_work_project';

	public static function init(): void {
		add_action( 'init', [ self::class, 'register' ], 6 );
	}

	public static function register(): void {
		self::register_service();
		self::register_project();
	}

	private static function register_service(): void {
		register_post_type( self::SERVICE, [
			'labels' => [
				'name'          => __( 'Services', 'core-blueprint-work' ),
				'singular_name' => __( 'Service', 'core-blueprint-work' ),
				'add_new'       => __( 'Add Service', 'core-blueprint-work' ),
				'add_new_item'  => __( 'Add Service', 'core-blueprint-work' ),
				'edit_item'     => __( 'Edit Service', 'core-blueprint-work' ),
				'new_item'      => __( 'New Service', 'core-blueprint-work' ),
				'view_item'     => __( 'View Service', 'core-blueprint-work' ),
				'search_items'  => __( 'Search Services', 'core-blueprint-work' ),
				'not_found'     => __( 'No services found.', 'core-blueprint-work' ),
			],
			'public'              => false,
			'publicly_queryable'  => false,
			'show_ui'             => true,
			'show_in_menu'        => false,
			'show_in_rest'        => false,
			'exclude_from_search' => true,
			'has_archive'         => false,
			'rewrite'             => false,
			'supports'            => [ 'title', 'editor' ],
			'capability_type'     => [ 'cb_work_service', 'cb_work_services' ],
			'map_meta_cap'        => false,
			'capabilities'        => self::capabilities(),
		] );
	}

	private static function register_project(): void {
		register_post_type( self::PROJECT, [
			'labels' => [
				'name'               => __( 'Projects', 'core-blueprint-work' ),
				'singular_name'      => __( 'Project', 'core-blueprint-work' ),
				'add_new'            => __( 'Add Project', 'core-blueprint-work' ),
				'add_new_item'       => __( 'Add Project', 'core-blueprint-work' ),
				'edit_item'          => __( 'Edit Project', 'core-blueprint-work' ),
				'new_item'           => __( 'New Project', 'core-blueprint-work' ),
				'view_item'          => __( 'View Project', 'core-blueprint-work' ),
				'search_items'       => __( 'Search Projects', 'core-blueprint-work' ),
				'not_found'          => __( 'No projects found.', 'core-blueprint-work' ),
				'not_found_in_trash' => __( 'No projects found in Trash.', 'core-blueprint-work' ),
			],
			'public'              => false,
			'publicly_queryable'  => false,
			'show_ui'             => true,
			'show_in_menu'        => false,
			'show_in_rest'        => false,
			'exclude_from_search' => true,
			'has_archive'         => false,
			'rewrite'             => false,
			'supports'            => [ 'title', 'editor' ],
			'capability_type'     => [ 'cb_work_project', 'cb_work_projects' ],
			'map_meta_cap'        => false,
			'capabilities'        => self::capabilities(),
		] );
	}

	/** @return array<string,string> */
	private static function capabilities(): array {
		return [
			'edit_post'              => Capabilities::MANAGE,
			'read_post'              => Capabilities::MANAGE,
			'delete_post'            => Capabilities::MANAGE,
			'edit_posts'             => Capabilities::MANAGE,
			'edit_others_posts'      => Capabilities::MANAGE,
			'publish_posts'          => Capabilities::MANAGE,
			'read_private_posts'     => Capabilities::MANAGE,
			'delete_posts'           => Capabilities::MANAGE,
			'delete_private_posts'   => Capabilities::MANAGE,
			'delete_published_posts' => Capabilities::MANAGE,
			'delete_others_posts'    => Capabilities::MANAGE,
			'edit_private_posts'     => Capabilities::MANAGE,
			'edit_published_posts'   => Capabilities::MANAGE,
			'create_posts'           => Capabilities::MANAGE,
		];
	}
}

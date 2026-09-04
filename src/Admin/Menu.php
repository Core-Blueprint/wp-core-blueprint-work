<?php
declare(strict_types=1);

namespace CB\Work\Admin;

use CB\Work\Capabilities;
use CB\Work\Content\PostTypes;
defined( 'ABSPATH' ) || exit;

final class Menu {
	public const TOP_LEVEL_SLUG = 'core-blueprint-work';
	public const CONTEXT_OVERVIEW = 'overview';
	public const CONTEXT_SERVICES = 'services';

	public static function init(): void {
		add_action( 'admin_menu', [ self::class, 'register' ], 5 );
		add_filter( 'parent_file', [ self::class, 'parent_file' ] );
		add_filter( 'submenu_file', [ self::class, 'submenu_file' ], 10, 2 );
	}

	public static function register(): void {
		add_menu_page(
			__( 'Work', 'core-blueprint-work' ),
			__( 'Work', 'core-blueprint-work' ),
			Capabilities::MANAGE,
			self::TOP_LEVEL_SLUG,
			[ self::class, 'render_overview' ],
			'dashicons-clipboard',
			26.5
		);

		add_submenu_page(
			self::TOP_LEVEL_SLUG,
			__( 'Overview', 'core-blueprint-work' ),
			__( 'Overview', 'core-blueprint-work' ),
			Capabilities::MANAGE,
			self::TOP_LEVEL_SLUG,
			[ self::class, 'render_overview' ]
		);

		add_submenu_page(
			self::TOP_LEVEL_SLUG,
			__( 'Services', 'core-blueprint-work' ),
			__( 'Services', 'core-blueprint-work' ),
			Capabilities::MANAGE,
			'edit.php?post_type=' . PostTypes::SERVICE
		);
	}

	public static function screen_context( ?\WP_Screen $screen = null ): string {
		$screen = $screen ?? get_current_screen();
		if ( ! $screen ) {
			return '';
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( (string) wp_unslash( $_GET['page'] ) ) : '';
		if ( self::TOP_LEVEL_SLUG === $page ) {
			return self::CONTEXT_OVERVIEW;
		}

		return PostTypes::SERVICE === (string) $screen->post_type ? self::CONTEXT_SERVICES : '';
	}

	public static function parent_file( string $parent_file ): string {
		return '' !== self::screen_context() ? self::TOP_LEVEL_SLUG : $parent_file;
	}

	public static function submenu_file( mixed $submenu_file, mixed $parent_file = '' ): mixed {
		unset( $parent_file );
		$slug = self::submenu_slug( self::screen_context() );
		return '' !== $slug ? $slug : $submenu_file;
	}

	public static function render_overview(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Work.', 'core-blueprint-work' ) );
		}

		$counts = wp_count_posts( PostTypes::SERVICE );
		$total  = 0;
		foreach ( get_object_vars( $counts ) as $status => $count ) {
			if ( ! in_array( $status, [ 'trash', 'auto-draft' ], true ) ) {
				$total += (int) $count;
			}
		}
		?>
		<div class="wrap cb-work-overview-page">
			<h1><?php esc_html_e( 'Core Blueprint Work', 'core-blueprint-work' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Your operational workspace for customer work. Services are available now; projects and work items will join this menu as the Work domain expands.', 'core-blueprint-work' ); ?></p>

			<div class="card">
				<h2><?php esc_html_e( 'Services', 'core-blueprint-work' ); ?></h2>
				<p><strong><?php echo esc_html( (string) $total ); ?></strong> <?php esc_html_e( 'configured services', 'core-blueprint-work' ); ?></p>
				<p><?php esc_html_e( 'Manage the canonical Work service catalog and default commercial model.', 'core-blueprint-work' ); ?></p>
				<p>
					<a class="button button-primary" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . PostTypes::SERVICE ) ); ?>"><?php esc_html_e( 'Manage Services', 'core-blueprint-work' ); ?></a>
					<a class="button" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . PostTypes::SERVICE ) ); ?>"><?php esc_html_e( 'Add Service', 'core-blueprint-work' ); ?></a>
				</p>
			</div>
		</div>
		<?php
	}

	private static function submenu_slug( string $context ): string {
		return match ( $context ) {
			self::CONTEXT_OVERVIEW => self::TOP_LEVEL_SLUG,
			self::CONTEXT_SERVICES => 'edit.php?post_type=' . PostTypes::SERVICE,
			default => '',
		};
	}
}

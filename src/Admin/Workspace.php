<?php
declare(strict_types=1);

namespace CB\Work\Admin;

use CB\Work\Capabilities;
defined( 'ABSPATH' ) || exit;

/**
 * Work-owned application composition inside wp-admin.
 *
 * WordPress/Base remain authoritative for the admin shell and native controls.
 * Work only adds the missing product-level navigation that ties its operational
 * screens together as one familiar workspace.
 */
final class Workspace {
	private static bool $rendered = false;

	public static function init(): void {
		// Keep routes registered through WordPress's access check. Duplicate submenu
		// entries are removed only when admin chrome is about to render.
		add_action( 'admin_head', [ self::class, 'hide_duplicate_submenus' ], 1 );
		add_filter( 'admin_body_class', [ self::class, 'body_class' ] );
		add_action( 'all_admin_notices', [ self::class, 'render' ], 0 );
	}

	/**
	 * Work uses WordPress's top-level menu as the application entry point and the
	 * in-page workspace navigation as the canonical product navigation. Routes
	 * remain registered and capability-gated during user_can_access_admin_page();
	 * only duplicate sidebar links disappear from the rendered admin chrome.
	 */
	public static function hide_duplicate_submenus(): void {
		$slugs = [
			Menu::TOP_LEVEL_SLUG,
			Menu::WORK_ITEMS_SLUG,
			Menu::RECURRENCE_SLUG,
			Menu::TIME_SLUG,
			Menu::projects_path(),
			Menu::services_path(),
			Menu::WORK_TYPES_SLUG,
		];
		foreach ( $slugs as $slug ) {
			remove_submenu_page( Menu::TOP_LEVEL_SLUG, $slug );
		}
	}

	public static function body_class( string $classes ): string {
		if ( '' === Menu::screen_context() ) {
			return $classes;
		}

		return trim( $classes . ' cb-work-workspace-screen' );
	}

	public static function render(): void {
		if ( self::$rendered ) {
			return;
		}

		$context    = Menu::screen_context();
		$can_manage = current_user_can( Capabilities::MANAGE );
		$can_track  = $can_manage || current_user_can( Capabilities::TRACK_TIME );
		if ( '' === $context || ! $can_track ) {
			return;
		}
		self::$rendered = true;

		$home_url = $can_manage
			? add_query_arg( 'page', Menu::TOP_LEVEL_SLUG, admin_url( 'admin.php' ) )
			: Menu::time_url();

		$primary = $can_manage
			? [
				Menu::CONTEXT_OVERVIEW   => [ __( 'Overview', 'core-blueprint-work' ), $home_url ],
				Menu::CONTEXT_PROJECTS   => [ __( 'Projects', 'core-blueprint-work' ), Menu::projects_url() ],
				Menu::CONTEXT_WORK_ITEMS => [ __( 'Work Items', 'core-blueprint-work' ), add_query_arg( 'page', Menu::WORK_ITEMS_SLUG, admin_url( 'admin.php' ) ) ],
				Menu::CONTEXT_TIME       => [ __( 'Time', 'core-blueprint-work' ), Menu::time_url() ],
			]
			: [
				Menu::CONTEXT_TIME => [ __( 'Time', 'core-blueprint-work' ), $home_url ],
			];

		$manage = $can_manage
			? [
				Menu::CONTEXT_RECURRENCE => [ __( 'Recurring Work', 'core-blueprint-work' ), add_query_arg( 'page', Menu::RECURRENCE_SLUG, admin_url( 'admin.php' ) ) ],
				Menu::CONTEXT_SERVICES   => [ __( 'Services', 'core-blueprint-work' ), admin_url( Menu::services_path() ) ],
				Menu::CONTEXT_WORK_TYPES => [ __( 'Work Types', 'core-blueprint-work' ), add_query_arg( 'page', Menu::WORK_TYPES_SLUG, admin_url( 'admin.php' ) ) ],
				'settings'                => [ __( 'Settings', 'core-blueprint-work' ), Page::settings_url() ],
			]
			: [];
		?>
		<div class="cb-work-workspace-shell">
			<div class="cb-work-workspace-shell__masthead">
				<a class="cb-work-workspace-shell__brand" href="<?php echo esc_url( $home_url ); ?>"><?php esc_html_e( 'Work', 'core-blueprint-work' ); ?></a>
				<span class="cb-work-workspace-shell__purpose"><?php esc_html_e( 'Operational workspace', 'core-blueprint-work' ); ?></span>
			</div>
			<nav class="cb-work-workspace-nav" aria-label="<?php esc_attr_e( 'Work workspace', 'core-blueprint-work' ); ?>">
				<div class="cb-work-workspace-nav__group cb-work-workspace-nav__group--primary">
					<?php self::render_links( $primary, $context ); ?>
				</div>
				<?php if ( [] !== $manage ) : ?>
					<div class="cb-work-workspace-nav__group cb-work-workspace-nav__group--manage" aria-label="<?php esc_attr_e( 'Work management', 'core-blueprint-work' ); ?>">
						<?php self::render_links( $manage, $context ); ?>
					</div>
				<?php endif; ?>
			</nav>
		</div>
		<?php
	}

	/** @param array<string,array{0:string,1:string}> $links */
	private static function render_links( array $links, string $context ): void {
		foreach ( $links as $link_context => [ $label, $url ] ) {
			$current = $link_context === $context;
			?>
			<a class="cb-work-workspace-nav__link<?php echo $current ? ' is-current' : ''; ?>" href="<?php echo esc_url( $url ); ?>"<?php echo $current ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
			<?php
		}
	}
}

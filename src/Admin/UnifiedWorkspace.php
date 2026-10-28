<?php
declare(strict_types=1);

namespace CB\Work\Admin;

use CB\Work\Capabilities;
use CB\Work\PublicApi\Projects;
use CB\Work\PublicApi\Services;
use CB\Work\Repository\WorkItems;

defined( 'ABSPATH' ) || exit;

/**
 * UWE-03A: isolated, read-only application shell preview.
 *
 * Existing WordPress editors, navigation and Work write paths remain canonical
 * until a later UWE acceptance explicitly retires them.
 */
final class UnifiedWorkspace {
	private const SECTIONS = [ 'work-items', 'projects', 'services' ];

	public static function render(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Work.', 'core-blueprint-work' ) );
		}

		$requested = isset( $_GET['section'] ) && is_scalar( $_GET['section'] )
			? sanitize_key( (string) wp_unslash( $_GET['section'] ) ) : '';
		$active = in_array( $requested, self::SECTIONS, true ) ? $requested : 'work-items';
		$sections = [
			'work-items' => [
				'label' => __( 'Work Items', 'core-blueprint-work' ),
				'empty' => __( 'No Work Items found.', 'core-blueprint-work' ),
				'items' => WorkItems::all( 25 ),
			],
			'projects' => [
				'label' => __( 'Projects', 'core-blueprint-work' ),
				'empty' => __( 'No projects found.', 'core-blueprint-work' ),
				'items' => Projects::all( 25 ),
			],
			'services' => [
				'label' => __( 'Services', 'core-blueprint-work' ),
				'empty' => __( 'No services found.', 'core-blueprint-work' ),
				'items' => Services::all( 25 ),
			],
		];
		?>
		<div class="wrap cb-work-unified" data-cb-work-uwe data-readonly="true">
			<header class="cb-work-uwe__header">
				<div>
					<h1><?php esc_html_e( 'Work', 'core-blueprint-work' ); ?></h1>
					<p class="description">UWE-03A</p>
				</div>
			</header>
			<nav class="cb-work-uwe__tabs" role="tablist" aria-label="<?php esc_attr_e( 'Work', 'core-blueprint-work' ); ?>">
				<?php foreach ( $sections as $key => $section ) : ?>
					<a
						id="cb-work-uwe-tab-<?php echo esc_attr( $key ); ?>"
						class="cb-work-uwe__tab<?php echo $active === $key ? ' is-active' : ''; ?>"
						role="tab"
						href="<?php echo esc_url( add_query_arg( [ 'page' => Menu::UNIFIED_WORKSPACE_SLUG, 'section' => $key ], admin_url( 'admin.php' ) ) ); ?>"
						aria-controls="cb-work-uwe-panel-<?php echo esc_attr( $key ); ?>"
						aria-selected="<?php echo $active === $key ? 'true' : 'false'; ?>"
						tabindex="<?php echo $active === $key ? '0' : '-1'; ?>"
						data-cb-work-uwe-tab="<?php echo esc_attr( $key ); ?>"
					><?php echo esc_html( $section['label'] ); ?></a>
				<?php endforeach; ?>
			</nav>
			<p class="screen-reader-text" aria-live="polite" aria-atomic="true" data-cb-work-uwe-status></p>
			<?php foreach ( $sections as $key => $section ) : ?>
				<section
					id="cb-work-uwe-panel-<?php echo esc_attr( $key ); ?>"
					class="cb-work-uwe__panel"
					role="tabpanel"
					aria-labelledby="cb-work-uwe-tab-<?php echo esc_attr( $key ); ?>"
					tabindex="0"
					data-cb-work-uwe-panel="<?php echo esc_attr( $key ); ?>"
					<?php echo $active !== $key ? 'hidden' : ''; ?>
				>
					<h2><?php echo esc_html( $section['label'] ); ?></h2>
					<?php if ( [] === $section['items'] ) : ?>
						<p class="cb-work-uwe__empty"><?php echo esc_html( $section['empty'] ); ?></p>
					<?php else : ?>
						<ul class="cb-work-uwe__collection">
							<?php foreach ( $section['items'] as $item ) : ?>
								<li class="cb-work-uwe__record">
									<span class="cb-work-uwe__record-title"><?php echo esc_html( (string) ( $item['title'] ?? '' ) ); ?></span>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</section>
			<?php endforeach; ?>
		</div>
		<?php
	}

	private function __construct() {}
}

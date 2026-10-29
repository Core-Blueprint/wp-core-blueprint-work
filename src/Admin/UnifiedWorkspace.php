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
		<div class="wrap cb-work-unified" data-cb-work-uwe data-readonly="true" data-cb-work-uwe-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>">
			<header class="cb-work-uwe__header">
				<div>
					<h1><?php esc_html_e( 'Work', 'core-blueprint-work' ); ?></h1>
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
					<?php if ( 'work-items' === $key ) : ?>
						data-cb-work-uwe-classic-endpoint="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
						data-cb-work-uwe-classic-action="<?php echo esc_attr( WorkItemViewBridge::ACTION ); ?>"
						data-cb-work-uwe-classic-nonce="<?php echo esc_attr( wp_create_nonce( WorkItemViewBridge::NONCE_ACTION ) ); ?>"
						data-cb-work-uwe-mutation-saved="<?php esc_attr_e( 'Saved', 'core-blueprint-work' ); ?>"
						data-cb-work-uwe-mutation-saving="<?php esc_attr_e( 'Saving…', 'core-blueprint-work' ); ?>"
						data-cb-work-uwe-mutation-failed="<?php esc_attr_e( 'The Work Item status could not be updated.', 'core-blueprint-work' ); ?>"
					<?php endif; ?>
					data-cb-work-uwe-endpoint="<?php echo esc_url( rest_url( 'cb-work/v1/workspace/' . $key ) ); ?>"
					<?php echo $active !== $key ? 'hidden' : ''; ?>
				>
					<?php if ( 'work-items' === $key ) : ?>
						<?php
						$canonical_views = [
							'table' => __( 'Table', 'core-blueprint-work' ),
							'list' => __( 'List', 'core-blueprint-work' ),
							'kanban' => __( 'Board', 'core-blueprint-work' ),
							'calendar' => __( 'Calendar', 'core-blueprint-work' ),
						];
						$preferred_view = WorkItemViewPreferences::resolve_request_view( $_GET, get_current_user_id() );
						$preferred_order = WorkItemViewPreferences::get( get_current_user_id() )['order'];
						?>
						<nav class="cb-work-uwe__view-switcher" data-cb-work-uwe-view-switcher data-cb-work-uwe-default-view="<?php echo esc_attr( $preferred_view ); ?>" aria-label="<?php esc_attr_e( 'Work Item view', 'core-blueprint-work' ); ?>">
							<?php foreach ( $preferred_order as $view_id ) : ?>
								<?php if ( ! isset( $canonical_views[ $view_id ] ) ) { continue; } ?>
								<?php $view_label = $canonical_views[ $view_id ]; ?>
								<a
									class="cb-work-uwe__view-link<?php echo $view_id === $preferred_view ? ' is-active' : ''; ?>"
									href="<?php echo esc_url( add_query_arg( [ 'page' => Menu::WORK_ITEMS_SLUG, 'view' => $view_id ], admin_url( 'admin.php' ) ) ); ?>"
									data-cb-work-uwe-view="<?php echo esc_attr( $view_id ); ?>"
									<?php if ( $view_id === $preferred_view ) : ?>aria-current="page"<?php endif; ?>
								><?php echo esc_html( $view_label ); ?></a>
							<?php endforeach; ?>
							<a class="button cb-work-uwe__legacy-link" href="<?php echo esc_url( admin_url( 'admin.php?page=' . Menu::WORK_ITEMS_SLUG ) ); ?>"><?php esc_html_e( 'Open Work Items', 'core-blueprint-work' ); ?></a>
						</nav>
					<?php endif; ?>
					<h2 data-cb-work-uwe-heading><?php echo esc_html( $section['label'] ); ?></h2>
					<form class="cb-work-uwe__controls" data-cb-work-uwe-controls hidden role="search">
						<label class="cb-work-uwe__field" for="cb-work-uwe-search-<?php echo esc_attr( $key ); ?>">
							<span><?php esc_html_e( 'Search', 'core-blueprint-work' ); ?></span>
							<input id="cb-work-uwe-search-<?php echo esc_attr( $key ); ?>" type="search" name="search" maxlength="160" autocomplete="off">
						</label>
						<label class="cb-work-uwe__field" for="cb-work-uwe-sort-<?php echo esc_attr( $key ); ?>">
							<span><?php esc_html_e( 'Sort by', 'core-blueprint-work' ); ?></span>
							<select id="cb-work-uwe-sort-<?php echo esc_attr( $key ); ?>" name="sort">
								<option value="workload"><?php esc_html_e( 'Workload', 'core-blueprint-work' ); ?></option>
								<option value="title"><?php esc_html_e( 'Title', 'core-blueprint-work' ); ?></option>
								<option value="updated"><?php esc_html_e( 'Recently updated', 'core-blueprint-work' ); ?></option>
								<?php if ( 'work-items' === $key ) : ?>
									<option value="due"><?php esc_html_e( 'Due date', 'core-blueprint-work' ); ?></option>
								<?php endif; ?>
							</select>
						</label>
						<?php if ( 'work-items' === $key ) : ?>
							<label class="cb-work-uwe__field" for="cb-work-uwe-status-<?php echo esc_attr( $key ); ?>">
								<span><?php esc_html_e( 'Status', 'core-blueprint-work' ); ?></span>
								<select id="cb-work-uwe-status-<?php echo esc_attr( $key ); ?>" name="status">
									<option value=""><?php esc_html_e( 'All statuses', 'core-blueprint-work' ); ?></option>
									<option value="planned"><?php esc_html_e( 'Planned', 'core-blueprint-work' ); ?></option>
									<option value="in_progress"><?php esc_html_e( 'In Progress', 'core-blueprint-work' ); ?></option>
									<option value="blocked"><?php esc_html_e( 'Blocked', 'core-blueprint-work' ); ?></option>
									<option value="completed"><?php esc_html_e( 'Completed', 'core-blueprint-work' ); ?></option>
									<option value="skipped"><?php esc_html_e( 'Skipped', 'core-blueprint-work' ); ?></option>
									<option value="cancelled"><?php esc_html_e( 'Cancelled', 'core-blueprint-work' ); ?></option>
								</select>
							</label>
						<?php endif; ?>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Search', 'core-blueprint-work' ); ?></button>
						<button type="button" class="button" data-cb-work-uwe-clear><?php esc_html_e( 'Clear filters', 'core-blueprint-work' ); ?></button>
						<span class="cb-work-uwe__count" data-cb-work-uwe-count aria-live="polite"></span>
					</form>
					<?php if ( 'work-items' === $key ) : ?>
						<div class="cb-work-uwe__canonical-view" data-cb-work-uwe-canonical hidden></div>
					<?php endif; ?>
					<div class="cb-work-uwe__feedback" hidden data-cb-work-uwe-feedback role="status" aria-live="polite"></div>
					<div class="cb-work-uwe__error" hidden data-cb-work-uwe-error role="alert">
						<span><?php esc_html_e( 'Work storage unavailable', 'core-blueprint-work' ); ?></span>
						<button type="button" class="button" data-cb-work-uwe-retry><?php esc_html_e( 'Search', 'core-blueprint-work' ); ?></button>
					</div>
					<p class="cb-work-uwe__empty" data-cb-work-uwe-empty <?php echo [] !== $section['items'] ? 'hidden' : ''; ?>><?php echo esc_html( $section['empty'] ); ?></p>
					<ul class="cb-work-uwe__collection" data-cb-work-uwe-list <?php echo [] === $section['items'] ? 'hidden' : ''; ?>>
						<?php foreach ( $section['items'] as $item ) : ?>
							<li class="cb-work-uwe__record">
								<span class="cb-work-uwe__record-title"><?php echo esc_html( (string) ( $item['title'] ?? '' ) ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
					<div class="cb-work-uwe__pagination" hidden data-cb-work-uwe-pagination>
						<button type="button" class="button" data-cb-work-uwe-prev><?php esc_html_e( 'Previous', 'core-blueprint-work' ); ?></button>
						<span data-cb-work-uwe-page-label data-template="<?php echo esc_attr__( 'Page %1$d of %2$d', 'core-blueprint-work' ); ?>"></span>
						<button type="button" class="button" data-cb-work-uwe-next><?php esc_html_e( 'Next', 'core-blueprint-work' ); ?></button>
					</div>
				</section>
			<?php endforeach; ?>
		</div>
		<?php
	}

	private function __construct() {}
}

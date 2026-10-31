<?php
declare(strict_types=1);

namespace CB\Work\Admin;

use CB\Work\Capabilities;
use CB\Work\PublicApi\Projects;
use CB\Work\PublicApi\Services;
use CB\Work\Repository\WorkItems;
use CB\Work\Repository\RecurrenceRules;

defined( 'ABSPATH' ) || exit;

/**
 * UWE-03A: isolated, read-only application shell preview.
 *
 * Existing WordPress editors, navigation and Work write paths remain canonical
 * until a later UWE acceptance explicitly retires them.
 */
final class UnifiedWorkspace {
	private const SECTIONS = [ 'work-items', 'projects', 'services', 'recurring' ];

	public static function render(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Work.', 'core-blueprint-work' ) );
		}

		$requested = isset( $_GET['section'] ) && is_scalar( $_GET['section'] )
			? sanitize_key( (string) wp_unslash( $_GET['section'] ) ) : '';
		$active = in_array( $requested, self::SECTIONS, true ) ? $requested : 'work-items';
		$section_icons = [ 'work-items' => 'clipboard', 'projects' => 'portfolio', 'services' => 'admin-tools', 'recurring' => 'update' ];
		$sections = [
			'work-items' => [
				'label' => __( 'Tasks', 'core-blueprint-work' ),
				'empty' => __( 'No tasks found.', 'core-blueprint-work' ),
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
			'recurring' => [
				'label' => __( 'Recurring Work', 'core-blueprint-work' ),
				'empty' => __( 'No recurring Work rules configured.', 'core-blueprint-work' ),
				'items' => RecurrenceRules::search_page( [ 'page' => 1 ] )['rows'],
			],
		];
		?>
		<div class="wrap cb-work-unified" data-cb-work-uwe data-readonly="false" data-cb-work-m2-endpoint="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-cb-work-m2-admin-url="<?php echo esc_url( admin_url() ); ?>" data-cb-work-m2-nonce="<?php echo esc_attr( wp_create_nonce( UnifiedEditorActions::NONCE_ACTION ) ); ?>" data-cb-work-m2-description="<?php esc_attr_e( 'Edit planning and other supported fields here. Rich document formatting remains in the advanced editor.', 'core-blueprint-work' ); ?>" data-cb-work-m2-description-label="<?php esc_attr_e( 'Description', 'core-blueprint-work' ); ?>" data-cb-work-m2-plain-description="<?php esc_attr_e( 'Plain text only. Use the advanced editor for formatting.', 'core-blueprint-work' ); ?>" data-cb-work-m2-rich-description="<?php esc_attr_e( 'This record contains rich content. Open the advanced editor to change its description.', 'core-blueprint-work' ); ?>" data-cb-work-m2-advanced="<?php esc_attr_e( 'Open advanced editor', 'core-blueprint-work' ); ?>" data-cb-work-m2-title="<?php esc_attr_e( 'Title', 'core-blueprint-work' ); ?>" data-cb-work-m2-work-item="<?php esc_attr_e( 'Task', 'core-blueprint-work' ); ?>" data-cb-work-m2-project="<?php esc_attr_e( 'Project', 'core-blueprint-work' ); ?>" data-cb-work-m2-service="<?php esc_attr_e( 'Service', 'core-blueprint-work' ); ?>" data-cb-work-m2-type="<?php esc_attr_e( 'Work Type', 'core-blueprint-work' ); ?>" data-cb-work-m2-priority="<?php esc_attr_e( 'Priority', 'core-blueprint-work' ); ?>" data-cb-work-m2-estimate="<?php esc_attr_e( 'Estimated minutes', 'core-blueprint-work' ); ?>" data-cb-work-m2-scheduled="<?php esc_attr_e( 'Scheduled', 'core-blueprint-work' ); ?>" data-cb-work-m2-start="<?php esc_attr_e( 'Start date', 'core-blueprint-work' ); ?>" data-cb-work-m2-due="<?php esc_attr_e( 'Due', 'core-blueprint-work' ); ?>" data-cb-work-m2-no-project="<?php esc_attr_e( 'No Project', 'core-blueprint-work' ); ?>" data-cb-work-m2-no-service="<?php esc_attr_e( 'No service', 'core-blueprint-work' ); ?>" data-cb-work-m2-no-type="<?php esc_attr_e( 'No Work Type', 'core-blueprint-work' ); ?>" data-cb-work-m2-add="<?php esc_attr_e( 'Add', 'core-blueprint-work' ); ?>" data-cb-work-m2-edit="<?php esc_attr_e( 'Edit', 'core-blueprint-work' ); ?>" data-cb-work-m2-save="<?php esc_attr_e( 'Save', 'core-blueprint-work' ); ?>" data-cb-work-m2-cancel="<?php esc_attr_e( 'Cancel', 'core-blueprint-work' ); ?>" data-cb-work-m2-manage-types="<?php esc_attr_e( 'Work Types', 'core-blueprint-work' ); ?>" data-cb-work-m2-activate="<?php esc_attr_e( 'Activate', 'core-blueprint-work' ); ?>" data-cb-work-m2-deactivate="<?php esc_attr_e( 'Deactivate', 'core-blueprint-work' ); ?>" data-cb-work-m2-name="<?php esc_attr_e( 'Name', 'core-blueprint-work' ); ?>" data-cb-work-m2-code="<?php esc_attr_e( 'Code', 'core-blueprint-work' ); ?>" data-cb-work-m2-create="<?php esc_attr_e( 'Create', 'core-blueprint-work' ); ?>" data-cb-work-m2-close="<?php esc_attr_e( 'Close', 'core-blueprint-work' ); ?>" data-cb-work-m2-types-help="<?php esc_attr_e( 'Create Work Types or activate and deactivate existing types.', 'core-blueprint-work' ); ?>" data-cb-work-m2-pricing-model="<?php esc_attr_e( 'Pricing model', 'core-blueprint-work' ); ?>" data-cb-work-m2-hourly="<?php esc_attr_e( 'Hourly', 'core-blueprint-work' ); ?>" data-cb-work-m2-fixed="<?php esc_attr_e( 'Fixed price', 'core-blueprint-work' ); ?>" data-cb-work-m2-recurring="<?php esc_attr_e( 'Recurring', 'core-blueprint-work' ); ?>" data-cb-work-m2-recurring-period="<?php esc_attr_e( 'Recurring period', 'core-blueprint-work' ); ?>" data-cb-work-m2-weekly="<?php esc_attr_e( 'Weekly', 'core-blueprint-work' ); ?>" data-cb-work-m2-monthly="<?php esc_attr_e( 'Monthly', 'core-blueprint-work' ); ?>" data-cb-work-m2-quarterly="<?php esc_attr_e( 'Quarterly', 'core-blueprint-work' ); ?>" data-cb-work-m2-yearly="<?php esc_attr_e( 'Yearly', 'core-blueprint-work' ); ?>" data-cb-work-m2-price="<?php esc_attr_e( 'Standard price', 'core-blueprint-work' ); ?>" data-cb-work-m2-currency="<?php esc_attr_e( 'Currency', 'core-blueprint-work' ); ?>" data-cb-work-m2-tax-mode="<?php esc_attr_e( 'VAT', 'core-blueprint-work' ); ?>" data-cb-work-m2-exclusive="<?php esc_attr_e( 'Excluding VAT', 'core-blueprint-work' ); ?>" data-cb-work-m2-inclusive="<?php esc_attr_e( 'Including VAT', 'core-blueprint-work' ); ?>" data-cb-work-m2-exempt="<?php esc_attr_e( 'VAT exempt / no VAT', 'core-blueprint-work' ); ?>" data-cb-work-m2-tax-rate="<?php esc_attr_e( 'VAT rate', 'core-blueprint-work' ); ?>" data-cb-work-m2-no-rate="<?php esc_attr_e( 'No VAT rate', 'core-blueprint-work' ); ?>" data-cb-work-m2-billing="<?php esc_attr_e( 'Billing', 'core-blueprint-work' ); ?>" data-cb-work-m2-unclassified="<?php esc_attr_e( 'Not classified', 'core-blueprint-work' ); ?>" data-cb-work-m2-included="<?php esc_attr_e( 'Included', 'core-blueprint-work' ); ?>" data-cb-work-m2-non-billable="<?php esc_attr_e( 'Non-billable', 'core-blueprint-work' ); ?>" data-cb-work-m2-priority-low="<?php esc_attr_e( 'Low', 'core-blueprint-work' ); ?>" data-cb-work-m2-priority-normal="<?php esc_attr_e( 'Normal', 'core-blueprint-work' ); ?>" data-cb-work-m2-priority-high="<?php esc_attr_e( 'High', 'core-blueprint-work' ); ?>" data-cb-work-m2-priority-urgent="<?php esc_attr_e( 'Urgent', 'core-blueprint-work' ); ?>" data-cb-work-m2-context="<?php esc_attr_e( 'Work context', 'core-blueprint-work' ); ?>" data-cb-work-m2-internal="<?php esc_attr_e( 'Internal', 'core-blueprint-work' ); ?>" data-cb-work-m2-customer-work="<?php esc_attr_e( 'Customer', 'core-blueprint-work' ); ?>" data-cb-work-m2-customer="<?php esc_attr_e( 'Customer', 'core-blueprint-work' ); ?>" data-cb-work-m2-assignees="<?php esc_attr_e( 'Assignees', 'core-blueprint-work' ); ?>" data-cb-work-m2-customer-search="<?php esc_attr_e( 'Search contacts and organizations…', 'core-blueprint-work' ); ?>" data-cb-work-m2-customer-empty="<?php esc_attr_e( 'No matching CRM customers found.', 'core-blueprint-work' ); ?>" data-cb-work-m2-user-search="<?php esc_attr_e( 'Search WordPress users…', 'core-blueprint-work' ); ?>" data-cb-work-m2-user-empty="<?php esc_attr_e( 'No matching users found.', 'core-blueprint-work' ); ?>" data-cb-work-m2-denied="<?php esc_attr_e( 'You no longer have permission to edit this record. Close the editor.', 'core-blueprint-work' ); ?>" data-cb-work-m2-stale="<?php esc_attr_e( 'This record changed. Close and reopen the editor before saving.', 'core-blueprint-work' ); ?>" data-cb-work-m2-error="<?php esc_attr_e( 'Could not save. Review the fields and try again.', 'core-blueprint-work' ); ?>" data-cb-work-uwe-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>">
			<header class="cb-work-uwe__header">
				<h1><?php esc_html_e( 'Work', 'core-blueprint-work' ); ?></h1>
			<div class="cb-work-uwe__primary-navigation">
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
					><span class="dashicons dashicons-<?php echo esc_attr( $section_icons[ $key ] ); ?>" aria-hidden="true"></span><span><?php echo esc_html( $section['label'] ); ?></span></a>
				<?php endforeach; ?>
			</nav>
			</div>
			<?php WorkspaceOperationsPanel::render_navigation(); ?>
			</header>
			<?php WorkspaceOperationsPanel::render_panels(); ?>
			<p class="screen-reader-text" aria-live="polite" aria-atomic="true" data-cb-work-uwe-status></p>
			<?php foreach ( $sections as $key => $section ) : ?>
				<section
					id="cb-work-uwe-panel-<?php echo esc_attr( $key ); ?>"
					class="cb-work-uwe__panel"
					role="tabpanel"
					aria-labelledby="cb-work-uwe-tab-<?php echo esc_attr( $key ); ?>"
					tabindex="0"
					data-cb-work-uwe-panel="<?php echo esc_attr( $key ); ?>"
					<?php if ( 'recurring' === $key ) : ?>data-cb-work-b4-native="<?php echo esc_url( add_query_arg( [ 'page' => Menu::RECURRENCE_SLUG ], admin_url( 'admin.php' ) ) ); ?>" data-cb-work-b4-active="<?php esc_attr_e( 'Active', 'core-blueprint-work' ); ?>" data-cb-work-b4-inactive="<?php esc_attr_e( 'Inactive', 'core-blueprint-work' ); ?>"<?php endif; ?>
					<?php if ( 'work-items' === $key ) : ?>
						data-cb-work-uwe-task-singular="<?php esc_attr_e( 'task', 'core-blueprint-work' ); ?>"
						data-cb-work-uwe-task-plural="<?php esc_attr_e( 'tasks', 'core-blueprint-work' ); ?>"
						data-cb-work-uwe-search-label="<?php esc_attr_e( 'Search tasks', 'core-blueprint-work' ); ?>"
						data-cb-work-uwe-search-placeholder="<?php esc_attr_e( 'Search tasks…', 'core-blueprint-work' ); ?>"
						data-cb-work-uwe-add-task="<?php esc_attr_e( 'Add Task', 'core-blueprint-work' ); ?>"
						data-cb-work-uwe-no-tasks="<?php esc_attr_e( 'No tasks found.', 'core-blueprint-work' ); ?>"
						data-cb-work-uwe-drop-tasks="<?php esc_attr_e( 'Drop tasks here', 'core-blueprint-work' ); ?>"
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
					<div class="cb-work-uwe__context" data-cb-work-uwe-context>
						<div class="cb-work-uwe__context-label">
							<h2<?php echo 'work-items' === $key ? ' class="screen-reader-text"' : ''; ?>><?php echo esc_html( $section['label'] ); ?></h2>
							<span class="cb-work-uwe__context-count" data-cb-work-uwe-context-count hidden aria-live="polite"></span>
						</div>
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
						<nav class="cb-work-uwe__view-switcher" data-cb-work-uwe-view-switcher data-cb-work-uwe-default-view="<?php echo esc_attr( $preferred_view ); ?>" aria-label="<?php esc_attr_e( 'Task views', 'core-blueprint-work' ); ?>">
							<?php foreach ( $preferred_order as $view_id ) : ?>
								<?php if ( ! isset( $canonical_views[ $view_id ] ) ) { continue; } ?>
								<?php $view_label = $canonical_views[ $view_id ]; ?>
								<a
									class="cb-work-uwe__view-link<?php echo $view_id === $preferred_view ? ' is-active' : ''; ?>"
									href="<?php echo esc_url( add_query_arg( [ 'page' => Menu::UNIFIED_WORKSPACE_SLUG, 'section' => 'work-items', 'view' => $view_id ], admin_url( 'admin.php' ) ) ); ?>"
									data-cb-work-uwe-view="<?php echo esc_attr( $view_id ); ?>"
									<?php if ( $view_id === $preferred_view ) : ?>aria-current="page"<?php endif; ?>
								><?php echo esc_html( $view_label ); ?></a>
							<?php endforeach; ?>

						</nav>
					<?php endif; ?>
					<div class="cb-work-uwe__context-actions">
						<?php if ( 'work-items' === $key ) : ?>
							<button type="button" class="button cb-work-uwe__types-action" data-cb-work-m2-types><?php esc_html_e( 'Work Types', 'core-blueprint-work' ); ?></button>
							<a class="button button-primary" data-cb-work-m2-add="work-item" href="<?php echo esc_url( Menu::new_work_item_url() ); ?>"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span><span><?php esc_html_e( 'Add Task', 'core-blueprint-work' ); ?></span></a>
						<?php elseif ( 'projects' === $key || 'services' === $key ) : ?>
							<button type="button" class="button button-primary" data-cb-work-m2-add="<?php echo esc_attr( 'projects' === $key ? 'project' : 'service' ); ?>"><?php echo esc_html( 'projects' === $key ? __( 'Add Project', 'core-blueprint-work' ) : __( 'Add Service', 'core-blueprint-work' ) ); ?></button>
						<?php elseif ( 'recurring' === $key ) : ?>
							<a class="button button-primary" href="<?php echo esc_url( add_query_arg( [ 'page' => Menu::RECURRENCE_SLUG, 'mode' => 'add' ], admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Add Recurring Work Rule', 'core-blueprint-work' ); ?></a>
						<?php endif; ?>
					</div>
					</div>
					<form class="cb-work-uwe__controls" data-cb-work-uwe-controls hidden role="search">
						<label class="cb-work-uwe__field" for="cb-work-uwe-search-<?php echo esc_attr( $key ); ?>">
							<span><?php esc_html_e( 'Search', 'core-blueprint-work' ); ?></span>
							<input id="cb-work-uwe-search-<?php echo esc_attr( $key ); ?>" type="search" name="search" maxlength="160" autocomplete="off">
						</label>
						<label class="cb-work-uwe__field" for="cb-work-uwe-sort-<?php echo esc_attr( $key ); ?>">
							<span><?php esc_html_e( 'Sort by', 'core-blueprint-work' ); ?></span>
							<select id="cb-work-uwe-sort-<?php echo esc_attr( $key ); ?>" name="sort">
								<?php if ( 'recurring' === $key ) : ?>
									<option value="next_occurrence"><?php esc_html_e( 'Next occurrence', 'core-blueprint-work' ); ?></option>
									<option value="title"><?php esc_html_e( 'Title', 'core-blueprint-work' ); ?></option>
									<option value="status"><?php esc_html_e( 'Status', 'core-blueprint-work' ); ?></option>
								<?php else : ?>
									<option value="workload"><?php esc_html_e( 'Workload', 'core-blueprint-work' ); ?></option>
									<option value="title"><?php esc_html_e( 'Title', 'core-blueprint-work' ); ?></option>
									<option value="updated"><?php esc_html_e( 'Recently updated', 'core-blueprint-work' ); ?></option>
									<?php if ( 'work-items' === $key ) : ?>
										<option value="due"><?php esc_html_e( 'Due date', 'core-blueprint-work' ); ?></option>
									<?php endif; ?>
								<?php endif; ?>
							</select>
						</label>
						<?php if ( 'recurring' === $key ) : ?>
							<label class="cb-work-uwe__field" for="cb-work-uwe-status-recurring"><span><?php esc_html_e( 'Status', 'core-blueprint-work' ); ?></span><select id="cb-work-uwe-status-recurring" name="status"><option value=""><?php esc_html_e( 'All statuses', 'core-blueprint-work' ); ?></option><option value="active"><?php esc_html_e( 'Active', 'core-blueprint-work' ); ?></option><option value="inactive"><?php esc_html_e( 'Inactive', 'core-blueprint-work' ); ?></option></select></label>
						<?php endif; ?>
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
								<?php if ( 'recurring' === $key ) : ?>
									<a class="cb-work-uwe__record-title--button" href="<?php echo esc_url( add_query_arg( [ 'page' => Menu::RECURRENCE_SLUG, 'rule_id' => (int) ( $item['id'] ?? 0 ) ], admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( (string) ( $item['title'] ?? '' ) ); ?></a>
									<small class="cb-work-uwe__recurring-facts"><?php echo esc_html( ( ! empty( $item['is_active'] ) ? __( 'Active', 'core-blueprint-work' ) : __( 'Inactive', 'core-blueprint-work' ) ) . ' · ' . (string) ( $item['frequency'] ?? '' ) . ' · ' . (string) ( $item['next_occurrence_on'] ?? '' ) ); ?></small>
								<?php elseif ( in_array( $key, [ 'projects', 'services' ], true ) ) : ?><button class="cb-work-uwe__record-title--button" type="button" data-cb-work-m2-edit="<?php echo esc_attr( 'projects' === $key ? 'project' : 'service' ); ?>" data-cb-work-m2-id="<?php echo esc_attr( (string) ( $item['id'] ?? 0 ) ); ?>"><?php echo esc_html( (string) ( $item['title'] ?? '' ) ); ?></button><?php else : ?><span class="cb-work-uwe__record-title"><?php echo esc_html( (string) ( $item['title'] ?? '' ) ); ?></span><?php endif; ?>
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

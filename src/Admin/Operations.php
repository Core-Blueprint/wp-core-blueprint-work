<?php
declare(strict_types=1);

namespace CB\Work\Admin;

use CB\Work\Capabilities;
use CB\Work\Database\Schema;
use CB\Work\Domain\BillingDisposition;
use CB\Work\Domain\WorkContext;
use CB\Work\Domain\WorkItemPriority;
use CB\Work\Domain\WorkItemStatus;
use CB\Work\Integration\CRMCustomers;
use CB\Work\PublicApi\Services;
use CB\Work\Query\WorkItemQuery;
use CB\Work\Repository\Projects;
use CB\Work\Repository\WorkItems;
use CB\Work\Repository\WorkTypes;

defined( 'ABSPATH' ) || exit;

final class Operations {
	public static function render_overview(): void {
		self::guard();
		$counts = WorkItems::counts_by_status();
		?>
		<div class="wrap cb-work-overview-page">
			<h1><?php esc_html_e( 'Core Blueprint Work', 'core-blueprint-work' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Your operational workspace for actionable customer work.', 'core-blueprint-work' ); ?></p>
			<?php self::storage_notice(); ?>
			<div class="card"><h2><?php esc_html_e( 'In Progress', 'core-blueprint-work' ); ?></h2><p><strong><?php echo esc_html( (string) ( $counts[ WorkItemStatus::IN_PROGRESS ] ?? 0 ) ); ?></strong></p><p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=' . Menu::WORK_ITEMS_SLUG ) ); ?>"><?php esc_html_e( 'Open Work Items', 'core-blueprint-work' ); ?></a></p></div>
			<div class="card"><h2><?php esc_html_e( 'Planned', 'core-blueprint-work' ); ?></h2><p><strong><?php echo esc_html( (string) ( $counts[ WorkItemStatus::PLANNED ] ?? 0 ) ); ?></strong></p><p><?php esc_html_e( 'Planned work remains actionable until it is completed, skipped or cancelled.', 'core-blueprint-work' ); ?></p></div>
			<div class="card"><h2><?php esc_html_e( 'Projects', 'core-blueprint-work' ); ?></h2><p><strong><?php echo esc_html( (string) Projects::count() ); ?></strong></p><p><a class="button" href="<?php echo esc_url( Menu::projects_url() ); ?>"><?php esc_html_e( 'Manage Projects', 'core-blueprint-work' ); ?></a></p></div>
		</div>
		<?php
	}

	public static function render_work_items(): void {
		self::guard();
		if ( ! self::schema_ready() ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Work Items', 'core-blueprint-work' ) . '</h1>';
			self::storage_notice();
			echo '</div>';
			return;
		}

		$state = WorkItemViewState::from_request( $_GET );
		if ( ! in_array( (string) $state['view'], [ WorkItemViewState::VIEW_TABLE, WorkItemViewState::VIEW_LIST, WorkItemViewState::VIEW_KANBAN, WorkItemViewState::VIEW_CALENDAR ], true ) ) {
			$request         = $_GET;
			$request['view'] = WorkItemViewState::VIEW_TABLE;
			$state           = WorkItemViewState::from_request( $request );
		}

		$project_filter = (int) $state['project_id'];
		$result         = $state['customer_valid']
			? WorkItems::search( (array) $state['query'] )
			: [ 'items' => [], 'total' => 0, 'page' => 1, 'per_page' => 50, 'pages' => 0 ];
		$items          = (array) $result['items'];

		$projects    = Projects::all( 500 );
		$project_map = [];
		foreach ( $projects as $project ) {
			$project_map[ (int) $project['id'] ] = (string) $project['title'];
		}

		$services = Services::all( 500 );
		$types    = WorkTypes::all();
		$type_map = [];
		foreach ( $types as $type ) {
			$type_map[ (int) $type['id'] ] = (string) $type['label'];
		}

		$selected_customer = null;
		$customer_query    = $state['query']['customer'] ?? null;
		if ( true === $state['customer_valid'] && is_array( $customer_query ) ) {
			$selected_customer = CRMCustomers::selected(
				(string) ( $customer_query['provider'] ?? '' ),
				(string) ( $customer_query['type'] ?? '' ),
				(string) ( $customer_query['id'] ?? '' )
			);
		}
		?>
		<div class="wrap cb-work-items-page">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Work Items', 'core-blueprint-work' ); ?></h1>
			<a class="page-title-action" href="<?php echo esc_url( Menu::new_work_item_url( $project_filter ) ); ?>"><?php esc_html_e( 'Add Work Item', 'core-blueprint-work' ); ?></a>
			<hr class="wp-header-end">
			<p class="description"><?php esc_html_e( 'Manage actionable work across customers and Projects. Open a Work Item to edit it in Gutenberg.', 'core-blueprint-work' ); ?></p>
			<?php self::render_notice(); ?>

			<?php if ( false === $state['customer_valid'] ) : ?>
				<div class="notice notice-error inline"><p><?php esc_html_e( 'The customer filter is invalid or no longer resolvable. No Work Items are shown until the filter is cleared.', 'core-blueprint-work' ); ?></p></div>
			<?php endif; ?>

			<?php if ( $project_filter > 0 ) : ?>
				<?php $project_name = $project_map[ $project_filter ] ?? __( 'Unknown Project', 'core-blueprint-work' ); ?>
				<div class="notice notice-info inline"><p>
					<?php
					/* translators: %s: Project name. */
					echo esc_html( sprintf( __( 'Showing Work Items for Project: %s', 'core-blueprint-work' ), $project_name ) );
					?>
					<a href="<?php echo esc_url( self::work_items_url( $state, [ 'project_id' => 0, 'page' => 1 ] ) ); ?>"><?php esc_html_e( 'View all Work Items', 'core-blueprint-work' ); ?></a>
				</p></div>
			<?php endif; ?>

			<?php self::render_work_item_views( $state ); ?>
			<?php self::render_work_item_filters( $state, $projects, $services, $types, $selected_customer ); ?>

			<h2><?php echo esc_html( $project_filter > 0 ? __( 'Project Work Items', 'core-blueprint-work' ) : __( 'All Work Items', 'core-blueprint-work' ) ); ?></h2>
			<p class="description"><?php
			/* translators: %d: number of Work Items matching the current view. */
			echo esc_html( sprintf( _n( '%d Work Item matches the current view.', '%d Work Items match the current view.', (int) $result['total'], 'core-blueprint-work' ), (int) $result['total'] ) );
			?></p>
			<?php if ( WorkItemViewState::VIEW_CALENDAR === (string) $state['view'] ) : ?>
				<?php self::render_work_item_calendar( $items, $project_map, $state ); ?>
			<?php elseif ( [] === $items ) : ?>
				<p><?php esc_html_e( 'No Work Items found.', 'core-blueprint-work' ); ?></p>
			<?php elseif ( WorkItemViewState::VIEW_KANBAN === (string) $state['view'] ) : ?>
				<?php self::render_work_item_kanban( $items, $project_map, $type_map, $state ); ?>
			<?php elseif ( WorkItemViewState::VIEW_LIST === (string) $state['view'] ) : ?>
				<?php self::render_work_item_list( $items, $project_map, $type_map, $state ); ?>
			<?php else : ?>
				<?php self::render_work_item_table( $items, $project_map, $type_map, $state ); ?>
			<?php endif; ?>
			<?php self::render_work_item_pagination( $state, $result ); ?>
		</div>
		<?php
	}

	public static function render_work_types(): void {
		self::guard();
		$types = WorkTypes::all( true );
		?>
		<div class="wrap cb-work-types-page">
			<h1><?php esc_html_e( 'Work Types', 'core-blueprint-work' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Classify the nature of work independently from Services and pricing.', 'core-blueprint-work' ); ?></p>
			<?php self::render_notice(); ?>
			<?php if ( ! self::schema_ready() ) { self::storage_notice(); return; } ?>

			<div class="card">
				<h2><?php esc_html_e( 'Add Work Type', 'core-blueprint-work' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="cb_work_create_work_type">
					<?php wp_nonce_field( 'cb_work_create_work_type' ); ?>
					<p><label for="cb-work-type-label"><strong><?php esc_html_e( 'Name', 'core-blueprint-work' ); ?></strong></label></p>
					<p><input id="cb-work-type-label" class="regular-text" type="text" name="work_type[label]" required></p>
					<p><label for="cb-work-type-code"><strong><?php esc_html_e( 'Code', 'core-blueprint-work' ); ?></strong></label></p>
					<p><input id="cb-work-type-code" class="regular-text" type="text" name="work_type[code]"><span class="description"> <?php esc_html_e( 'Optional; generated from the name when empty.', 'core-blueprint-work' ); ?></span></p>
					<?php submit_button( __( 'Add Work Type', 'core-blueprint-work' ), 'primary', 'submit', false ); ?>
				</form>
			</div>

			<h2><?php esc_html_e( 'Configured Work Types', 'core-blueprint-work' ); ?></h2>
			<?php if ( [] === $types ) : ?>
				<p><?php esc_html_e( 'No Work Types configured.', 'core-blueprint-work' ); ?></p>
			<?php else : ?>
				<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Name', 'core-blueprint-work' ); ?></th><th><?php esc_html_e( 'Code', 'core-blueprint-work' ); ?></th><th><?php esc_html_e( 'Status', 'core-blueprint-work' ); ?></th><th><?php esc_html_e( 'Action', 'core-blueprint-work' ); ?></th></tr></thead><tbody>
				<?php foreach ( $types as $type ) : ?>
					<tr>
						<td><strong><?php echo esc_html( (string) $type['label'] ); ?></strong></td>
						<td><code><?php echo esc_html( (string) $type['code'] ); ?></code></td>
						<td><?php echo ! empty( $type['is_active'] ) ? esc_html__( 'Active', 'core-blueprint-work' ) : esc_html__( 'Inactive', 'core-blueprint-work' ); ?></td>
						<td>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="cb_work_toggle_work_type">
								<input type="hidden" name="work_type_id" value="<?php echo esc_attr( (string) $type['id'] ); ?>">
								<input type="hidden" name="active" value="<?php echo empty( $type['is_active'] ) ? '1' : '0'; ?>">
								<?php wp_nonce_field( 'cb_work_toggle_work_type_' . (int) $type['id'] ); ?>
								<button class="button button-small" type="submit"><?php echo empty( $type['is_active'] ) ? esc_html__( 'Activate', 'core-blueprint-work' ) : esc_html__( 'Deactivate', 'core-blueprint-work' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody></table>
			<?php endif; ?>
		</div>
		<?php
	}

	/** @param array<string,mixed> $state */
	private static function render_work_item_views( array $state ): void {
		$current = (string) ( $state['view'] ?? WorkItemViewState::VIEW_TABLE );
		$views = [
			WorkItemViewState::VIEW_TABLE    => __( 'Table', 'core-blueprint-work' ),
			WorkItemViewState::VIEW_LIST     => __( 'List', 'core-blueprint-work' ),
			WorkItemViewState::VIEW_KANBAN   => __( 'Kanban', 'core-blueprint-work' ),
			WorkItemViewState::VIEW_CALENDAR => __( 'Calendar', 'core-blueprint-work' ),
		];
		?>
		<h2 class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Work Item view', 'core-blueprint-work' ); ?>">
			<?php foreach ( $views as $view => $label ) : ?>
				<a class="nav-tab <?php echo $current === $view ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( self::work_items_url( $state, [ 'view' => $view, 'page' => 1 ] ) ); ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</h2>
		<?php
	}

	/**
	 * @param array<string,mixed> $state
	 * @param array<int,array<string,mixed>> $projects
	 * @param array<int,array<string,mixed>> $services
	 * @param array<int,array<string,mixed>> $types
	 * @param array{id:string,label:string,meta:string}|null $selected_customer
	 */
	private static function render_work_item_filters( array $state, array $projects, array $services, array $types, ?array $selected_customer ): void {
		$is_calendar = WorkItemViewState::VIEW_CALENDAR === (string) $state['view'];
		$clear_state = [ 'view' => (string) $state['view'] ];
		if ( $is_calendar ) {
			$clear_state['calendar_month'] = (string) $state['calendar_month'];
		}
		?>
		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="cb-work-items-filters">
			<input type="hidden" name="page" value="<?php echo esc_attr( Menu::WORK_ITEMS_SLUG ); ?>">
			<input type="hidden" name="view" value="<?php echo esc_attr( (string) $state['view'] ); ?>">
			<?php if ( $is_calendar ) : ?>
				<input type="hidden" name="calendar_month" value="<?php echo esc_attr( (string) $state['calendar_month'] ); ?>">
			<?php endif; ?>
			<div class="tablenav top">
				<div class="alignleft actions">
					<label class="screen-reader-text" for="cb-work-filter-search"><?php esc_html_e( 'Search Work Items', 'core-blueprint-work' ); ?></label>
					<input id="cb-work-filter-search" type="search" name="s" value="<?php echo esc_attr( (string) $state['search'] ); ?>" placeholder="<?php esc_attr_e( 'Search Work Items…', 'core-blueprint-work' ); ?>">
					<select name="status" aria-label="<?php esc_attr_e( 'Status', 'core-blueprint-work' ); ?>">
						<option value=""><?php esc_html_e( 'All statuses', 'core-blueprint-work' ); ?></option>
						<option value="active" <?php selected( 'active', (string) $state['status'] ); ?>><?php esc_html_e( 'Active', 'core-blueprint-work' ); ?></option>
						<?php foreach ( WorkItemStatus::all() as $status ) : ?>
							<option value="<?php echo esc_attr( $status ); ?>" <?php selected( $status, (string) $state['status'] ); ?>><?php echo esc_html( self::humanize( $status ) ); ?></option>
						<?php endforeach; ?>
					</select>
					<select name="priority" aria-label="<?php esc_attr_e( 'Priority', 'core-blueprint-work' ); ?>">
						<option value=""><?php esc_html_e( 'All priorities', 'core-blueprint-work' ); ?></option>
						<?php foreach ( WorkItemPriority::all() as $priority ) : ?>
							<option value="<?php echo esc_attr( $priority ); ?>" <?php selected( $priority, (string) $state['priority'] ); ?>><?php echo esc_html( self::humanize( $priority ) ); ?></option>
						<?php endforeach; ?>
					</select>
					<select name="project_id" aria-label="<?php esc_attr_e( 'Project', 'core-blueprint-work' ); ?>">
						<option value="0"><?php esc_html_e( 'All Projects', 'core-blueprint-work' ); ?></option>
						<?php foreach ( $projects as $project ) : ?>
							<option value="<?php echo esc_attr( (string) $project['id'] ); ?>" <?php selected( (int) $state['project_id'], (int) $project['id'] ); ?>><?php echo esc_html( (string) $project['title'] ); ?></option>
						<?php endforeach; ?>
					</select>
					<select name="service_id" aria-label="<?php esc_attr_e( 'Service', 'core-blueprint-work' ); ?>">
						<option value="0"><?php esc_html_e( 'All Services', 'core-blueprint-work' ); ?></option>
						<?php foreach ( $services as $service ) : ?>
							<option value="<?php echo esc_attr( (string) $service['id'] ); ?>" <?php selected( (int) $state['service_id'], (int) $service['id'] ); ?>><?php echo esc_html( (string) $service['title'] ); ?></option>
						<?php endforeach; ?>
					</select>
					<select name="work_type_id" aria-label="<?php esc_attr_e( 'Work Type', 'core-blueprint-work' ); ?>">
						<option value="0"><?php esc_html_e( 'All Work Types', 'core-blueprint-work' ); ?></option>
						<?php foreach ( $types as $type ) : ?>
							<option value="<?php echo esc_attr( (string) $type['id'] ); ?>" <?php selected( (int) $state['work_type_id'], (int) $type['id'] ); ?>><?php echo esc_html( (string) $type['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
					<select name="work_context" aria-label="<?php esc_attr_e( 'Work context', 'core-blueprint-work' ); ?>">
						<option value=""><?php esc_html_e( 'All contexts', 'core-blueprint-work' ); ?></option>
						<option value="<?php echo esc_attr( WorkContext::INTERNAL ); ?>" <?php selected( WorkContext::INTERNAL, (string) $state['work_context'] ); ?>><?php esc_html_e( 'Internal', 'core-blueprint-work' ); ?></option>
						<option value="<?php echo esc_attr( WorkContext::CUSTOMER ); ?>" <?php selected( WorkContext::CUSTOMER, (string) $state['work_context'] ); ?>><?php esc_html_e( 'Customer', 'core-blueprint-work' ); ?></option>
					</select>
					<select name="billing" aria-label="<?php esc_attr_e( 'Billing', 'core-blueprint-work' ); ?>">
						<option value=""><?php esc_html_e( 'All billing classes', 'core-blueprint-work' ); ?></option>
						<?php foreach ( BillingDisposition::all() as $billing ) : ?>
							<option value="<?php echo esc_attr( $billing ); ?>" <?php selected( $billing, (string) $state['billing'] ); ?>><?php echo esc_html( self::humanize( $billing ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>
			<p>
				<label for="cb-work-filter-customer"><strong><?php esc_html_e( 'Customer', 'core-blueprint-work' ); ?></strong></label><br>
				<?php Pickers::customer( 'customer', 'cb-work-filter-customer', $selected_customer ); ?>
			</p>
			<p>
				<label for="cb-work-filter-assignee"><strong><?php esc_html_e( 'Assignee', 'core-blueprint-work' ); ?></strong></label><br>
				<?php Pickers::assignee( 'assignee_id', 'cb-work-filter-assignee', (int) $state['assignee_id'] ); ?>
			</p>
			<p>
				<?php if ( ! $is_calendar ) : ?>
					<label for="cb-work-filter-scheduled-from"><?php esc_html_e( 'Scheduled from', 'core-blueprint-work' ); ?></label>
					<input id="cb-work-filter-scheduled-from" type="date" name="scheduled_from" value="<?php echo esc_attr( (string) $state['scheduled_from'] ); ?>">
					<label for="cb-work-filter-scheduled-to"><?php esc_html_e( 'to', 'core-blueprint-work' ); ?></label>
					<input id="cb-work-filter-scheduled-to" type="date" name="scheduled_to" value="<?php echo esc_attr( (string) $state['scheduled_to'] ); ?>">
				<?php endif; ?>
				<label for="cb-work-filter-due-from"><?php esc_html_e( 'Due from', 'core-blueprint-work' ); ?></label>
				<input id="cb-work-filter-due-from" type="date" name="due_from" value="<?php echo esc_attr( (string) $state['due_from'] ); ?>">
				<label for="cb-work-filter-due-to"><?php esc_html_e( 'to', 'core-blueprint-work' ); ?></label>
				<input id="cb-work-filter-due-to" type="date" name="due_to" value="<?php echo esc_attr( (string) $state['due_to'] ); ?>">
				<label for="cb-work-filter-sort"><?php esc_html_e( 'Sort', 'core-blueprint-work' ); ?></label>
				<select id="cb-work-filter-sort" name="sort">
					<option value="<?php echo esc_attr( WorkItemQuery::SORT_WORKLOAD ); ?>" <?php selected( WorkItemQuery::SORT_WORKLOAD, (string) $state['sort'] ); ?>><?php esc_html_e( 'Workload', 'core-blueprint-work' ); ?></option>
					<option value="<?php echo esc_attr( WorkItemQuery::SORT_DUE ); ?>" <?php selected( WorkItemQuery::SORT_DUE, (string) $state['sort'] ); ?>><?php esc_html_e( 'Due date', 'core-blueprint-work' ); ?></option>
					<option value="<?php echo esc_attr( WorkItemQuery::SORT_SCHEDULED ); ?>" <?php selected( WorkItemQuery::SORT_SCHEDULED, (string) $state['sort'] ); ?>><?php esc_html_e( 'Scheduled date', 'core-blueprint-work' ); ?></option>
					<option value="<?php echo esc_attr( WorkItemQuery::SORT_UPDATED ); ?>" <?php selected( WorkItemQuery::SORT_UPDATED, (string) $state['sort'] ); ?>><?php esc_html_e( 'Recently updated', 'core-blueprint-work' ); ?></option>
					<option value="<?php echo esc_attr( WorkItemQuery::SORT_TITLE ); ?>" <?php selected( WorkItemQuery::SORT_TITLE, (string) $state['sort'] ); ?>><?php esc_html_e( 'Title', 'core-blueprint-work' ); ?></option>
				</select>
				<button class="button" type="submit"><?php esc_html_e( 'Apply filters', 'core-blueprint-work' ); ?></button>
				<a class="button" href="<?php echo esc_url( self::work_items_url( $clear_state ) ); ?>"><?php esc_html_e( 'Clear filters', 'core-blueprint-work' ); ?></a>
			</p>
		</form>
		<?php
	}

	/**
	 * @param array<int,array<string,mixed>> $items
	 * @param array<int,string> $project_map
	 * @param array<int,string> $type_map
	 * @param array<string,mixed> $state
	 */
	private static function render_work_item_table( array $items, array $project_map, array $type_map, array $state ): void {
		$preferences = WorkItemTablePreferences::get( get_current_user_id() );
		$columns     = self::work_item_table_columns();
		$hidden      = array_fill_keys( $preferences['hidden'], true );
		$panel_id    = 'cb-work-table-columns-panel';
		?>
		<div
			class="cb-work-table-preferences"
			data-cb-work-table-preferences
			data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
			data-action="<?php echo esc_attr( WorkItemTablePreferences::ACTION ); ?>"
			data-nonce="<?php echo esc_attr( wp_create_nonce( WorkItemTablePreferences::NONCE_ACTION ) ); ?>"
			data-saving="<?php echo esc_attr__( 'Saving…', 'core-blueprint-work' ); ?>"
			data-saved="<?php echo esc_attr__( 'Saved', 'core-blueprint-work' ); ?>"
			data-error="<?php echo esc_attr__( 'Column preferences could not be saved.', 'core-blueprint-work' ); ?>"
		>
			<div class="cb-work-table-preferences__toolbar">
				<button
					type="button"
					class="button"
					data-cb-work-table-columns-toggle
					aria-controls="<?php echo esc_attr( $panel_id ); ?>"
					aria-expanded="false"
				><?php esc_html_e( 'Columns', 'core-blueprint-work' ); ?></button>
			</div>

			<div id="<?php echo esc_attr( $panel_id ); ?>" class="cb-work-table-preferences__panel" data-cb-work-table-columns-panel hidden>
				<div data-cb-core-reorder>
					<div
						class="cb-work-table-preferences__list"
						data-cb-core-reorder-list="columns"
						data-cb-core-reorder-list-label="<?php esc_attr_e( 'Work Item table columns', 'core-blueprint-work' ); ?>"
					>
						<?php foreach ( $preferences['order'] as $column_id ) :
							$label = $columns[ $column_id ] ?? $column_id;
							$is_hidden = isset( $hidden[ $column_id ] );
							$protected = 'work_item' === $column_id;
							/* translators: %s: Work Item table column label. */
							$reorder_label = sprintf( __( 'Reorder %s', 'core-blueprint-work' ), $label );
							?>
							<div
								class="cb-work-table-preferences__item"
								data-cb-core-reorder-item="<?php echo esc_attr( $column_id ); ?>"
								data-cb-core-reorder-label="<?php echo esc_attr( $label ); ?>"
							>
								<button
									type="button"
									class="button-link cb-core-icon-control cb-core-reorder-handle"
									data-cb-core-reorder-handle
									aria-label="<?php echo esc_attr( $reorder_label ); ?>"
									title="<?php esc_attr_e( 'Move', 'core-blueprint-work' ); ?>"
								><span class="dashicons dashicons-move" aria-hidden="true"></span></button>
								<label>
									<input
										type="checkbox"
										value="<?php echo esc_attr( $column_id ); ?>"
										data-cb-work-column-visible
										<?php checked( ! $is_hidden ); ?>
										<?php disabled( $protected ); ?>
									>
									<span><?php echo esc_html( $label ); ?></span>
								</label>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
				<div class="cb-work-table-preferences__footer">
					<button type="button" class="button-link" data-cb-work-table-columns-reset><?php esc_html_e( 'Reset to default', 'core-blueprint-work' ); ?></button>
					<span class="description" data-cb-work-table-preferences-status role="status" aria-live="polite"></span>
				</div>
			</div>

			<table class="widefat striped cb-work-items-table" data-cb-work-items-table>
				<thead><tr>
					<?php foreach ( $preferences['order'] as $column_id ) : ?>
						<th data-cb-work-column="<?php echo esc_attr( $column_id ); ?>" <?php if ( isset( $hidden[ $column_id ] ) ) : ?>hidden<?php endif; ?>><?php echo esc_html( $columns[ $column_id ] ?? $column_id ); ?></th>
					<?php endforeach; ?>
				</tr></thead>
				<tbody>
				<?php foreach ( $items as $item ) : ?>
					<tr>
						<?php foreach ( $preferences['order'] as $column_id ) : ?>
							<td data-cb-work-column="<?php echo esc_attr( $column_id ); ?>" <?php if ( isset( $hidden[ $column_id ] ) ) : ?>hidden<?php endif; ?>>
								<?php self::render_work_item_table_cell( $column_id, $item, $project_map, $type_map, $state ); ?>
							</td>
						<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/** @return array<string,string> */
	private static function work_item_table_columns(): array {
		return [
			'work_item' => __( 'Work Item', 'core-blueprint-work' ),
			'status'    => __( 'Status', 'core-blueprint-work' ),
			'priority'  => __( 'Priority', 'core-blueprint-work' ),
			'project'   => __( 'Project', 'core-blueprint-work' ),
			'due'       => __( 'Due', 'core-blueprint-work' ),
			'assigned'  => __( 'Assigned', 'core-blueprint-work' ),
			'actions'   => __( 'Actions', 'core-blueprint-work' ),
			'customer'  => __( 'Customer', 'core-blueprint-work' ),
			'type'      => __( 'Type', 'core-blueprint-work' ),
			'billing'   => __( 'Billing', 'core-blueprint-work' ),
		];
	}

	/**
	 * @param array<string,mixed> $item
	 * @param array<int,string> $project_map
	 * @param array<int,string> $type_map
	 * @param array<string,mixed> $state
	 */
	private static function render_work_item_table_cell( string $column_id, array $item, array $project_map, array $type_map, array $state ): void {
		switch ( $column_id ) {
			case 'work_item':
				?><strong><a href="<?php echo esc_url( Menu::edit_work_item_url( (int) $item['id'] ) ); ?>"><?php echo esc_html( (string) $item['title'] ); ?></a></strong><?php
				return;
			case 'status':
				echo esc_html( self::humanize( (string) $item['status'] ) );
				return;
			case 'priority':
				echo esc_html( self::humanize( (string) $item['priority'] ) );
				return;
			case 'project':
				echo esc_html( $project_map[ (int) ( $item['project_id'] ?? 0 ) ] ?? '—' );
				return;
			case 'due':
				echo esc_html( (string) ( $item['due_on'] ?: '—' ) );
				return;
			case 'assigned':
				echo esc_html( self::assignment_label( $item['assigned_user_ids'] ?? [] ) );
				return;
			case 'actions':
				self::transition_buttons( $item, $state );
				return;
			case 'customer':
				echo esc_html( self::customer_label( $item ) );
				return;
			case 'type':
				echo esc_html( $type_map[ (int) ( $item['work_type_id'] ?? 0 ) ] ?? '—' );
				return;
			case 'billing':
				echo esc_html( '' !== (string) $item['billing_disposition'] ? self::humanize( (string) $item['billing_disposition'] ) : '—' );
				return;
		}
	}


	/**
	 * @param array<int,array<string,mixed>> $items
	 * @param array<int,string> $project_map
	 * @param array<int,string> $type_map
	 * @param array<string,mixed> $state
	 */
	private static function render_work_item_list( array $items, array $project_map, array $type_map, array $state ): void {
		?>
		<div class="cb-work-items-list">
			<?php foreach ( $items as $item ) : ?>
				<div class="postbox">
					<div class="inside">
						<h3><a href="<?php echo esc_url( Menu::edit_work_item_url( (int) $item['id'] ) ); ?>"><?php echo esc_html( (string) $item['title'] ); ?></a></h3>
						<p>
							<strong><?php esc_html_e( 'Status:', 'core-blueprint-work' ); ?></strong> <?php echo esc_html( self::humanize( (string) $item['status'] ) ); ?>
							 · <strong><?php esc_html_e( 'Priority:', 'core-blueprint-work' ); ?></strong> <?php echo esc_html( self::humanize( (string) $item['priority'] ) ); ?>
							 · <strong><?php esc_html_e( 'Due:', 'core-blueprint-work' ); ?></strong> <?php echo esc_html( (string) ( $item['due_on'] ?: '—' ) ); ?>
						</p>
						<p>
							<strong><?php esc_html_e( 'Customer:', 'core-blueprint-work' ); ?></strong> <?php echo esc_html( self::customer_label( $item ) ); ?>
							 · <strong><?php esc_html_e( 'Project:', 'core-blueprint-work' ); ?></strong> <?php echo esc_html( $project_map[ (int) ( $item['project_id'] ?? 0 ) ] ?? '—' ); ?>
							 · <strong><?php esc_html_e( 'Type:', 'core-blueprint-work' ); ?></strong> <?php echo esc_html( $type_map[ (int) ( $item['work_type_id'] ?? 0 ) ] ?? '—' ); ?>
						</p>
						<p>
							<strong><?php esc_html_e( 'Billing:', 'core-blueprint-work' ); ?></strong> <?php echo esc_html( '' !== (string) $item['billing_disposition'] ? self::humanize( (string) $item['billing_disposition'] ) : '—' ); ?>
							 · <strong><?php esc_html_e( 'Assigned:', 'core-blueprint-work' ); ?></strong> <?php echo esc_html( self::assignment_label( $item['assigned_user_ids'] ?? [] ) ); ?>
						</p>
						<p><?php self::transition_buttons( $item, $state ); ?></p>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * @param array<int,array<string,mixed>> $items
	 * @param array<int,string> $project_map
	 * @param array<int,string> $type_map
	 * @param array<string,mixed> $state
	 */
	private static function render_work_item_kanban( array $items, array $project_map, array $type_map, array $state ): void {
		$lanes = array_fill_keys( WorkItemStatus::all(), [] );
		foreach ( $items as $item ) {
			$status = (string) ( $item['status'] ?? WorkItemStatus::PLANNED );
			if ( ! WorkItemStatus::is_valid( $status ) ) {
				$status = WorkItemStatus::PLANNED;
			}
			$lanes[ $status ][] = $item;
		}
		?>
		<div
			class="cb-work-items-kanban cb-work-board"
			data-cb-work-board-reorder
			data-cb-core-reorder
			data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
			data-action="<?php echo esc_attr( WorkItemBoardActions::ACTION ); ?>"
			data-nonce="<?php echo esc_attr( wp_create_nonce( WorkItemBoardActions::NONCE_ACTION ) ); ?>"
			data-error="<?php echo esc_attr__( 'The Work Item status could not be updated.', 'core-blueprint-work' ); ?>"
		>
			<?php foreach ( $lanes as $status => $lane_items ) :
				$lane_label = self::humanize( (string) $status );
				?>
				<section class="postbox cb-work-board__lane" data-cb-work-status-lane="<?php echo esc_attr( (string) $status ); ?>">
					<h2 class="hndle"><span><?php echo esc_html( $lane_label ); ?> <span class="count">(<?php echo esc_html( (string) count( $lane_items ) ); ?>)</span></span></h2>
					<div
						class="inside cb-work-board__list"
						data-cb-core-reorder-list="<?php echo esc_attr( (string) $status ); ?>"
						data-cb-core-reorder-list-label="<?php echo esc_attr( $lane_label ); ?>"
					>
						<p class="description" data-cb-work-board-empty <?php if ( [] !== $lane_items ) : ?>hidden<?php endif; ?>><?php esc_html_e( 'No Work Items in this status on the current page.', 'core-blueprint-work' ); ?></p>
						<?php foreach ( $lane_items as $item ) :
							$allowed = WorkItemStatus::transitions_from( (string) $status );
							$title   = (string) $item['title'];
							/* translators: %s: Work Item title. */
							$move_label = sprintf( __( 'Move %s to another status', 'core-blueprint-work' ), $title );
							?>
							<article
								class="card cb-work-board__card"
								data-cb-core-reorder-item="work-item:<?php echo esc_attr( (string) $item['id'] ); ?>"
								data-cb-core-reorder-label="<?php echo esc_attr( $title ); ?>"
								data-cb-work-item-id="<?php echo esc_attr( (string) $item['id'] ); ?>"
								data-cb-work-status="<?php echo esc_attr( (string) $status ); ?>"
								data-cb-work-allowed-statuses="<?php echo esc_attr( implode( ',', $allowed ) ); ?>"
							>
								<div class="cb-work-board__card-header">
									<h3><a href="<?php echo esc_url( Menu::edit_work_item_url( (int) $item['id'] ) ); ?>"><?php echo esc_html( $title ); ?></a></h3>
									<button
										type="button"
										class="button-link cb-core-icon-control cb-core-reorder-handle cb-work-board__drag-handle"
										data-cb-core-reorder-handle
										aria-label="<?php echo esc_attr( $move_label ); ?>"
										title="<?php esc_attr_e( 'Move to another status', 'core-blueprint-work' ); ?>"
									><span class="dashicons dashicons-move" aria-hidden="true"></span></button>
								</div>
								<p><strong><?php esc_html_e( 'Priority:', 'core-blueprint-work' ); ?></strong> <?php echo esc_html( self::humanize( (string) $item['priority'] ) ); ?><br>
								<strong><?php esc_html_e( 'Due:', 'core-blueprint-work' ); ?></strong> <?php echo esc_html( (string) ( $item['due_on'] ?: '—' ) ); ?></p>
								<p><strong><?php esc_html_e( 'Customer:', 'core-blueprint-work' ); ?></strong> <?php echo esc_html( self::customer_label( $item ) ); ?><br>
								<strong><?php esc_html_e( 'Project:', 'core-blueprint-work' ); ?></strong> <?php echo esc_html( $project_map[ (int) ( $item['project_id'] ?? 0 ) ] ?? '—' ); ?><br>
								<strong><?php esc_html_e( 'Type:', 'core-blueprint-work' ); ?></strong> <?php echo esc_html( $type_map[ (int) ( $item['work_type_id'] ?? 0 ) ] ?? '—' ); ?><br>
								<strong><?php esc_html_e( 'Assigned:', 'core-blueprint-work' ); ?></strong> <?php echo esc_html( self::assignment_label( $item['assigned_user_ids'] ?? [] ) ); ?></p>
								<?php self::transition_buttons( $item, $state ); ?>
							</article>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * @param array<int,array<string,mixed>> $items
	 * @param array<int,string> $project_map
	 * @param array<string,mixed> $state
	 */
	private static function render_work_item_calendar( array $items, array $project_map, array $state ): void {
		$month = (string) ( $state['calendar_month'] ?? '' );
		$first = \DateTimeImmutable::createFromFormat( '!Y-m-d', $month . '-01' );
		if ( ! $first ) {
			return;
		}

		$entries_by_date = [];
		foreach ( $items as $item ) {
			$scheduled_on = (string) ( $item['scheduled_on'] ?? '' );
			$due_on       = (string) ( $item['due_on'] ?? '' );
			$scheduled_in_month = str_starts_with( $scheduled_on, $month . '-' );
			$due_in_month       = str_starts_with( $due_on, $month . '-' );

			if ( $scheduled_in_month ) {
				$entries_by_date[ $scheduled_on ][] = [
					'kind'      => 'scheduled',
					'item'      => $item,
					'due_today' => '' !== $due_on && $due_on === $scheduled_on,
				];
			}
			if ( $due_in_month && ( ! $scheduled_in_month || $due_on !== $scheduled_on ) ) {
				$entries_by_date[ $due_on ][] = [
					'kind'      => 'due',
					'item'      => $item,
					'due_today' => true,
				];
			}
		}

		$previous_month = $first->modify( '-1 month' )->format( 'Y-m' );
		$next_month     = $first->modify( '+1 month' )->format( 'Y-m' );
		$current_month  = current_time( 'Y-m' );
		$days_in_month  = (int) $first->format( 't' );
		$leading_cells  = (int) $first->format( 'N' ) - 1;
		$weekdays       = [
			__( 'Monday', 'core-blueprint-work' ),
			__( 'Tuesday', 'core-blueprint-work' ),
			__( 'Wednesday', 'core-blueprint-work' ),
			__( 'Thursday', 'core-blueprint-work' ),
			__( 'Friday', 'core-blueprint-work' ),
			__( 'Saturday', 'core-blueprint-work' ),
			__( 'Sunday', 'core-blueprint-work' ),
		];
		?>
		<nav class="cb-work-calendar-navigation" aria-label="<?php esc_attr_e( 'Calendar navigation', 'core-blueprint-work' ); ?>">
			<div class="cb-work-calendar-navigation__controls">
				<a class="button" href="<?php echo esc_url( self::work_items_url( $state, [ 'calendar_month' => $previous_month, 'page' => 1 ] ) ); ?>"><?php esc_html_e( 'Previous month', 'core-blueprint-work' ); ?></a>
				<a class="button" href="<?php echo esc_url( self::work_items_url( $state, [ 'calendar_month' => $current_month, 'page' => 1 ] ) ); ?>"><?php esc_html_e( 'Today', 'core-blueprint-work' ); ?></a>
				<strong class="cb-work-calendar-navigation__month"><?php echo esc_html( wp_date( 'F Y', $first->getTimestamp() ) ); ?></strong>
				<a class="button" href="<?php echo esc_url( self::work_items_url( $state, [ 'calendar_month' => $next_month, 'page' => 1 ] ) ); ?>"><?php esc_html_e( 'Next month', 'core-blueprint-work' ); ?></a>
			</div>
		</nav>
		<div
			class="cb-work-calendar-reorder"
			data-cb-work-calendar-reorder
			data-cb-core-reorder
			data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
			data-action="<?php echo esc_attr( WorkItemCalendarActions::ACTION ); ?>"
			data-nonce="<?php echo esc_attr( wp_create_nonce( WorkItemCalendarActions::NONCE_ACTION ) ); ?>"
			data-error="<?php echo esc_attr__( 'The Work Item date could not be updated.', 'core-blueprint-work' ); ?>"
		>
		<table class="widefat cb-work-items-calendar">
			<thead><tr>
				<?php foreach ( $weekdays as $weekday ) : ?>
					<th scope="col"><?php echo esc_html( $weekday ); ?></th>
				<?php endforeach; ?>
			</tr></thead>
			<tbody>
			<tr>
			<?php $cell = 0; ?>
			<?php for ( $empty = 0; $empty < $leading_cells; $empty++ ) : ?>
				<td class="cb-work-calendar-empty" aria-hidden="true"></td>
				<?php $cell++; ?>
			<?php endfor; ?>
			<?php for ( $day = 1; $day <= $days_in_month; $day++ ) : ?>
				<?php
				$date        = $month . '-' . str_pad( (string) $day, 2, '0', STR_PAD_LEFT );
				$day_entries = $entries_by_date[ $date ] ?? [];
				?>
				<td class="cb-work-calendar-day">
					<strong class="cb-work-calendar-day__number"><?php echo esc_html( (string) $day ); ?></strong>
					<div
						class="cb-work-calendar-day__list"
						data-cb-core-reorder-list="<?php echo esc_attr( $date ); ?>"
						data-cb-core-reorder-list-label="<?php echo esc_attr( $date ); ?>"
					>
						<?php foreach ( $day_entries as $entry ) :
							$item    = $entry['item'];
							$kind    = (string) $entry['kind'];
							$is_due  = 'due' === $kind;
							$item_id = (int) $item['id'];
							$title   = (string) $item['title'];
							/* translators: 1: Work Item title, 2: date field label. */
							$move_label = sprintf(
								__( 'Move %1$s %2$s date', 'core-blueprint-work' ),
								$title,
								$is_due ? __( 'Due', 'core-blueprint-work' ) : __( 'Scheduled', 'core-blueprint-work' )
							);
							?>
							<article
								class="cb-work-calendar-entry cb-work-calendar-entry--<?php echo esc_attr( $kind ); ?>"
								data-cb-core-reorder-item="calendar:<?php echo esc_attr( $kind ); ?>:<?php echo esc_attr( (string) $item_id ); ?>"
								data-cb-core-reorder-label="<?php echo esc_attr( $title ); ?>"
								data-cb-work-item-id="<?php echo esc_attr( (string) $item_id ); ?>"
								data-cb-work-calendar-kind="<?php echo esc_attr( $kind ); ?>"
							>
								<div class="cb-work-calendar-entry__header">
									<div class="cb-work-calendar-entry__meta">
										<span class="cb-work-calendar-entry__kind"><?php echo esc_html( $is_due ? __( 'Due', 'core-blueprint-work' ) : __( 'Scheduled', 'core-blueprint-work' ) ); ?></span>
										<?php if ( ! $is_due && ! empty( $entry['due_today'] ) ) : ?>
											<span class="cb-work-calendar-entry__due"><?php esc_html_e( 'Due', 'core-blueprint-work' ); ?></span>
										<?php endif; ?>
									</div>
									<button
										type="button"
										class="button-link cb-core-icon-control cb-core-reorder-handle cb-work-calendar-entry__drag-handle"
										data-cb-core-reorder-handle
										aria-label="<?php echo esc_attr( $move_label ); ?>"
										title="<?php esc_attr_e( 'Move to another date', 'core-blueprint-work' ); ?>"
									><span class="dashicons dashicons-move" aria-hidden="true"></span></button>
								</div>
								<strong class="cb-work-calendar-entry__title"><a href="<?php echo esc_url( Menu::edit_work_item_url( $item_id ) ); ?>"><?php echo esc_html( $title ); ?></a></strong>
								<p class="cb-work-calendar-entry__details">
									<?php echo esc_html( self::humanize( (string) $item['status'] ) ); ?> · <?php echo esc_html( self::humanize( (string) $item['priority'] ) ); ?><br>
									<?php echo esc_html( $project_map[ (int) ( $item['project_id'] ?? 0 ) ] ?? '—' ); ?>
								</p>
							</article>
						<?php endforeach; ?>
					</div>
				</td>
				<?php $cell++; ?>
				<?php if ( 0 === $cell % 7 && $day < $days_in_month ) : ?>
					</tr><tr>
				<?php endif; ?>
			<?php endfor; ?>
			<?php while ( 0 !== $cell % 7 ) : ?>
				<td class="cb-work-calendar-empty" aria-hidden="true"></td>
				<?php $cell++; ?>
			<?php endwhile; ?>
			</tr>
			</tbody>
		</table>
		</div>
		<?php
	}

	/** @param array<string,mixed> $state @param array<string,mixed> $result */
	private static function render_work_item_pagination( array $state, array $result ): void {
		$page  = max( 1, (int) ( $result['page'] ?? 1 ) );
		$pages = max( 0, (int) ( $result['pages'] ?? 0 ) );
		if ( $pages <= 1 ) {
			return;
		}
		?>
		<div class="tablenav bottom"><div class="tablenav-pages">
			<span class="displaying-num"><?php
			/* translators: %d: total number of Work Items in the current result set. */
			echo esc_html( sprintf( _n( '%d item', '%d items', (int) $result['total'], 'core-blueprint-work' ), (int) $result['total'] ) );
			?></span>
			<?php if ( $page > 1 ) : ?>
				<a class="button" href="<?php echo esc_url( self::work_items_url( $state, [ 'page' => $page - 1 ] ) ); ?>"><?php esc_html_e( 'Previous', 'core-blueprint-work' ); ?></a>
			<?php endif; ?>
			<span class="paging-input"><?php
			/* translators: 1: current page number, 2: total number of pages. */
			echo esc_html( sprintf( __( 'Page %1$d of %2$d', 'core-blueprint-work' ), $page, $pages ) );
			?></span>
			<?php if ( $page < $pages ) : ?>
				<a class="button" href="<?php echo esc_url( self::work_items_url( $state, [ 'page' => $page + 1 ] ) ); ?>"><?php esc_html_e( 'Next', 'core-blueprint-work' ); ?></a>
			<?php endif; ?>
		</div></div>
		<?php
	}

	/** @param array<string,mixed> $state @param array<string,mixed> $overrides */
	private static function work_items_url( array $state, array $overrides = [] ): string {
		return add_query_arg( WorkItemViewState::query_args( $state, $overrides ), admin_url( 'admin.php' ) );
	}

	/** @param array<string,mixed> $item @param array<string,mixed> $state */
	private static function transition_buttons( array $item, array $state ): void {
		$from        = (string) ( $item['status'] ?? '' );
		$return_args = WorkItemViewState::query_args( $state );
		unset( $return_args['page'] );
		foreach ( WorkItemStatus::transitions_from( $from ) as $to ) {
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin:0 4px 4px 0">
				<input type="hidden" name="action" value="cb_work_transition_work_item">
				<input type="hidden" name="work_item_id" value="<?php echo esc_attr( (string) $item['id'] ); ?>">
				<input type="hidden" name="status" value="<?php echo esc_attr( $to ); ?>">
				<?php foreach ( $return_args as $key => $value ) : ?>
					<input type="hidden" name="return_state[<?php echo esc_attr( (string) $key ); ?>]" value="<?php echo esc_attr( (string) $value ); ?>">
				<?php endforeach; ?>
				<?php wp_nonce_field( 'cb_work_transition_work_item_' . (int) $item['id'] ); ?>
				<button class="button button-small" type="submit"><?php echo esc_html( self::transition_label( $to, $from ) ); ?></button>
			</form>
			<?php
		}
	}

	/** @param int[] $ids */
	private static function assignment_label( array $ids ): string {
		$labels = [];
		foreach ( $ids as $id ) {
			$user = get_userdata( (int) $id );
			if ( $user ) {
				$labels[] = (string) $user->display_name;
			}
		}
		return [] === $labels ? '—' : implode( ', ', $labels );
	}

	/** @param array<string,mixed> $item */
	private static function customer_label( array $item ): string {
		if ( WorkContext::INTERNAL === WorkContext::sanitize( $item['work_context'] ?? '' ) ) {
			return __( 'Internal', 'core-blueprint-work' );
		}
		$label = CRMCustomers::label(
			(string) ( $item['customer_provider'] ?? '' ),
			(string) ( $item['customer_type'] ?? '' ),
			(string) ( $item['customer_id'] ?? '' )
		);
		return '' !== $label ? $label : '—';
	}

	private static function humanize( string $value ): string {
		return ucwords( str_replace( '_', ' ', $value ) );
	}

	private static function transition_label( string $status, string $from = '' ): string {
		if ( WorkItemStatus::COMPLETED === $from ) {
			return match ( $status ) {
				WorkItemStatus::PLANNED     => __( 'Reopen as Planned', 'core-blueprint-work' ),
				WorkItemStatus::IN_PROGRESS => __( 'Reopen', 'core-blueprint-work' ),
				WorkItemStatus::BLOCKED     => __( 'Reopen as Blocked', 'core-blueprint-work' ),
				default                     => self::humanize( $status ),
			};
		}
		return match ( $status ) {
			WorkItemStatus::IN_PROGRESS => __( 'Start', 'core-blueprint-work' ),
			WorkItemStatus::COMPLETED   => __( 'Complete', 'core-blueprint-work' ),
			WorkItemStatus::SKIPPED     => __( 'Skip', 'core-blueprint-work' ),
			WorkItemStatus::CANCELLED   => __( 'Cancel', 'core-blueprint-work' ),
			default                     => self::humanize( $status ),
		};
	}

	private static function render_notice(): void {
		$notice = isset( $_GET['cb-work-notice'] ) ? sanitize_key( wp_unslash( (string) $_GET['cb-work-notice'] ) ) : '';
		$messages = [
			'work-item-transitioned'       => [ 'success', __( 'Work Item status updated.', 'core-blueprint-work' ) ],
			'work-item-transition-invalid' => [ 'error', __( 'That Work Item status transition is not allowed.', 'core-blueprint-work' ) ],
			'work-type-created'            => [ 'success', __( 'Work Type added.', 'core-blueprint-work' ) ],
			'work-type-invalid'            => [ 'error', __( 'Work Type could not be saved.', 'core-blueprint-work' ) ],
			'work-type-updated'            => [ 'success', __( 'Work Type status updated.', 'core-blueprint-work' ) ],
		];
		if ( ! isset( $messages[ $notice ] ) ) {
			return;
		}
		[ $class, $message ] = $messages[ $notice ];
		echo '<div class="notice notice-' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}

	private static function storage_notice(): void {
		if ( self::schema_ready() ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>' . esc_html__( 'Work storage upgrade is pending. Work Items remain unavailable until Base reconciles the Work schema.', 'core-blueprint-work' ) . '</p></div>';
	}

	private static function schema_ready(): bool {
		return CB_WORK_SCHEMA_VERSION === (string) get_option( Schema::OPTION, '0' );
	}

	private static function guard(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Work.', 'core-blueprint-work' ) );
		}
	}
}
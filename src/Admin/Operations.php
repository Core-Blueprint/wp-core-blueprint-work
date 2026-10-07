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
use CoreBlueprint\Core\UI\StateBadge;

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
		<div class="wrap cb-work-items-page cb-work-items-page--refined">
			<header class="cb-work-page-header">
				<div class="cb-work-page-header__copy">
					<h1><?php esc_html_e( 'Work Items', 'core-blueprint-work' ); ?></h1>
					<p class="description"><?php esc_html_e( 'Manage actionable work across customers and Projects. Open a Work Item to edit it in Gutenberg.', 'core-blueprint-work' ); ?></p>
				</div>
				<div class="cb-work-page-header__actions">
					<a class="button button-primary cb-work-page-header__primary" href="<?php echo esc_url( Menu::new_work_item_url( $project_filter ) ); ?>"><?php esc_html_e( 'Add Work Item', 'core-blueprint-work' ); ?></a>
				</div>
			</header>
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

			<?php self::render_work_item_focus_views( $state ); ?>
			<?php self::render_work_item_filters( $state, $projects, $services, $types, $selected_customer ); ?>

			<div class="cb-work-results-header">
				<div class="cb-work-results-header__copy">
					<h2><span class="cb-work-results-header__count"><?php echo esc_html( (string) (int) $result['total'] ); ?></span> <?php esc_html_e( 'Work Items', 'core-blueprint-work' ); ?></h2>
					<span class="cb-work-results-header__divider" aria-hidden="true"></span>
					<p class="description"><?php echo esc_html( $project_filter > 0 ? ( $project_map[ $project_filter ] ?? __( 'Unknown Project', 'core-blueprint-work' ) ) : __( 'All Projects', 'core-blueprint-work' ) ); ?></p>
				</div>
				<span class="cb-work-keyboard-hint"><?php esc_html_e( 'Shortcut: / search · Alt+N add Work Item', 'core-blueprint-work' ); ?></span>
			</div>
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
			WorkItemViewState::VIEW_TABLE    => [ 'label' => __( 'Table', 'core-blueprint-work' ), 'icon' => 'dashicons-editor-table' ],
			WorkItemViewState::VIEW_LIST     => [ 'label' => __( 'List', 'core-blueprint-work' ), 'icon' => 'dashicons-list-view' ],
			WorkItemViewState::VIEW_KANBAN   => [ 'label' => __( 'Board', 'core-blueprint-work' ), 'icon' => 'dashicons-screenoptions' ],
			WorkItemViewState::VIEW_CALENDAR => [ 'label' => __( 'Calendar', 'core-blueprint-work' ), 'icon' => 'dashicons-calendar-alt' ],
		];
		?>
		<nav class="cb-core-segmented-control cb-work-view-switcher" aria-label="<?php esc_attr_e( 'Work Item view', 'core-blueprint-work' ); ?>">
			<?php foreach ( $views as $view => $definition ) : ?>
				<a
					class="cb-core-segmented-control__option cb-work-view-switcher__option <?php echo $current === $view ? 'is-active' : ''; ?>"
					href="<?php echo esc_url( self::work_items_url( $state, [ 'view' => $view, 'page' => 1 ] ) ); ?>"
					<?php if ( $current === $view ) : ?>aria-current="page"<?php endif; ?>
				>
					<span class="dashicons <?php echo esc_attr( (string) $definition['icon'] ); ?>" aria-hidden="true"></span>
					<span><?php echo esc_html( (string) $definition['label'] ); ?></span>
				</a>
			<?php endforeach; ?>
		</nav>
		<?php
	}

	/** @param array<string,mixed> $state */
	private static function render_work_item_focus_views( array $state ): void {
		$user_id = get_current_user_id();
		$today   = current_time( 'Y-m-d' );
		$overdue = wp_date( 'Y-m-d', strtotime( $today . ' -1 day' ) );

		$has_other_filters = '' !== (string) $state['search']
			|| '' !== (string) $state['priority']
			|| (int) $state['project_id'] > 0
			|| (int) $state['service_id'] > 0
			|| (int) $state['work_type_id'] > 0
			|| '' !== (string) $state['work_context']
			|| '' !== (string) $state['billing']
			|| null !== ( $state['query']['customer'] ?? null )
			|| '' !== (string) $state['scheduled_from']
			|| '' !== (string) $state['scheduled_to']
			|| '' !== (string) $state['due_from']
			|| WorkItemQuery::SORT_WORKLOAD !== (string) $state['sort'];

		$status      = (string) $state['status'];
		$assignee_id = (int) $state['assignee_id'];
		$due_to      = (string) $state['due_to'];
		$base        = [
			'view' => (string) $state['view'],
		];
		if ( WorkItemViewState::VIEW_CALENDAR === (string) $state['view'] ) {
			$base['calendar_month'] = (string) $state['calendar_month'];
		}

		$links = [
			[
				'label'   => __( 'All', 'core-blueprint-work' ),
				'url'     => self::work_items_url( $base ),
				'current' => ! $has_other_filters && '' === $status && 0 === $assignee_id && '' === $due_to,
			],
		];
		if ( $user_id > 0 ) {
			$links[] = [
				'label'   => __( 'My work', 'core-blueprint-work' ),
				'url'     => self::work_items_url( $base + [ 'status' => 'active', 'assignee_id' => $user_id ] ),
				'current' => ! $has_other_filters && 'active' === $status && $assignee_id === $user_id,
			];
		}
		$links[] = [
			'label'   => __( 'Active', 'core-blueprint-work' ),
			'url'     => self::work_items_url( $base + [ 'status' => 'active' ] ),
			'current' => ! $has_other_filters && 'active' === $status && 0 === $assignee_id && '' === $due_to,
		];
		$links[] = [
			'label'   => __( 'Blocked', 'core-blueprint-work' ),
			'url'     => self::work_items_url( $base + [ 'status' => WorkItemStatus::BLOCKED ] ),
			'current' => ! $has_other_filters && WorkItemStatus::BLOCKED === $status,
		];
		$links[] = [
			'label'   => __( 'Overdue', 'core-blueprint-work' ),
			'url'     => self::work_items_url( $base + [ 'status' => 'active', 'due_to' => $overdue ] ),
			'current' => ! $has_other_filters && 'active' === $status && $overdue === $due_to,
		];
		?>
		<nav class="cb-work-fast-paths" aria-label="<?php esc_attr_e( 'Focus views', 'core-blueprint-work' ); ?>">
			<?php foreach ( $links as $link ) : ?>
				<a
					class="cb-work-fast-path <?php echo ! empty( $link['current'] ) ? 'is-current' : ''; ?>"
					href="<?php echo esc_url( (string) $link['url'] ); ?>"
					<?php if ( ! empty( $link['current'] ) ) : ?>aria-current="page"<?php endif; ?>
				><?php echo esc_html( (string) $link['label'] ); ?></a>
			<?php endforeach; ?>
		</nav>
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
		$is_table    = WorkItemViewState::VIEW_TABLE === (string) $state['view'];
		$clear_state = [ 'view' => (string) $state['view'] ];
		if ( $is_calendar ) {
			$clear_state['calendar_month'] = (string) $state['calendar_month'];
		}

		$advanced_count = 0;
		$advanced_count += (int) ( (int) $state['service_id'] > 0 );
		$advanced_count += (int) ( '' !== (string) $state['priority'] );
		$advanced_count += (int) ( (int) $state['work_type_id'] > 0 );
		$advanced_count += (int) ( '' !== (string) $state['work_context'] );
		$advanced_count += (int) ( '' !== (string) $state['billing'] );
		$advanced_count += (int) ( null !== ( $state['query']['customer'] ?? null ) );
		$advanced_count += (int) ( (int) $state['assignee_id'] > 0 );
		if ( ! $is_calendar ) {
			$advanced_count += (int) ( '' !== (string) $state['scheduled_from'] );
			$advanced_count += (int) ( '' !== (string) $state['scheduled_to'] );
		}
		$advanced_count += (int) ( '' !== (string) $state['due_from'] );
		$advanced_count += (int) ( '' !== (string) $state['due_to'] );
		$advanced_count += (int) ( WorkItemQuery::SORT_WORKLOAD !== (string) $state['sort'] );

		$has_any_filters = '' !== (string) $state['search']
			|| '' !== (string) $state['status']
			|| (int) $state['project_id'] > 0
			|| $advanced_count > 0;

		$summary = [];
		if ( '' !== (string) $state['status'] ) {
			$summary[] = [ __( 'Status', 'core-blueprint-work' ), 'active' === (string) $state['status'] ? __( 'Active', 'core-blueprint-work' ) : self::humanize( (string) $state['status'] ) ];
		}
		if ( (int) $state['project_id'] > 0 ) {
			foreach ( $projects as $project ) {
				if ( (int) $project['id'] === (int) $state['project_id'] ) {
					$summary[] = [ __( 'Project', 'core-blueprint-work' ), (string) $project['title'] ];
					break;
				}
			}
		}
		if ( '' !== (string) $state['search'] ) {
			$summary[] = [ __( 'Search', 'core-blueprint-work' ), (string) $state['search'] ];
		}
		if ( (int) $state['service_id'] > 0 ) {
			foreach ( $services as $service ) {
				if ( (int) $service['id'] === (int) $state['service_id'] ) {
					$summary[] = [ __( 'Service', 'core-blueprint-work' ), (string) $service['title'] ];
					break;
				}
			}
		}
		if ( '' !== (string) $state['priority'] ) {
			$summary[] = [ __( 'Priority', 'core-blueprint-work' ), self::humanize( (string) $state['priority'] ) ];
		}
		if ( (int) $state['work_type_id'] > 0 ) {
			foreach ( $types as $type ) {
				if ( (int) $type['id'] === (int) $state['work_type_id'] ) {
					$summary[] = [ __( 'Work Type', 'core-blueprint-work' ), (string) $type['label'] ];
					break;
				}
			}
		}
		if ( '' !== (string) $state['work_context'] ) {
			$summary[] = [ __( 'Work context', 'core-blueprint-work' ), self::humanize( (string) $state['work_context'] ) ];
		}
		if ( '' !== (string) $state['billing'] ) {
			$summary[] = [ __( 'Billing', 'core-blueprint-work' ), self::humanize( (string) $state['billing'] ) ];
		}
		if ( null !== $selected_customer ) {
			$summary[] = [ __( 'Customer', 'core-blueprint-work' ), (string) $selected_customer['label'] ];
		}
		if ( (int) $state['assignee_id'] > 0 ) {
			$user = get_userdata( (int) $state['assignee_id'] );
			if ( $user ) {
				$summary[] = [ __( 'Assignee', 'core-blueprint-work' ), (string) $user->display_name ];
			}
		}
		if ( ! $is_calendar && ( '' !== (string) $state['scheduled_from'] || '' !== (string) $state['scheduled_to'] ) ) {
			$summary[] = [
				__( 'Scheduled', 'core-blueprint-work' ),
				( '' !== (string) $state['scheduled_from'] ? (string) $state['scheduled_from'] : '…' )
					. ' ' . __( 'to', 'core-blueprint-work' ) . ' '
					. ( '' !== (string) $state['scheduled_to'] ? (string) $state['scheduled_to'] : '…' ),
			];
		}
		if ( '' !== (string) $state['due_from'] || '' !== (string) $state['due_to'] ) {
			$summary[] = [
				__( 'Due', 'core-blueprint-work' ),
				( '' !== (string) $state['due_from'] ? (string) $state['due_from'] : '…' )
					. ' ' . __( 'to', 'core-blueprint-work' ) . ' '
					. ( '' !== (string) $state['due_to'] ? (string) $state['due_to'] : '…' ),
			];
		}
		if ( WorkItemQuery::SORT_WORKLOAD !== (string) $state['sort'] ) {
			$summary[] = [ __( 'Sort', 'core-blueprint-work' ), self::humanize( (string) $state['sort'] ) ];
		}
		?>
		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="cb-work-items-filters">
			<input type="hidden" name="page" value="<?php echo esc_attr( Menu::WORK_ITEMS_SLUG ); ?>">
			<input type="hidden" name="view" value="<?php echo esc_attr( (string) $state['view'] ); ?>">
			<?php if ( $is_calendar ) : ?>
				<input type="hidden" name="calendar_month" value="<?php echo esc_attr( (string) $state['calendar_month'] ); ?>">
			<?php endif; ?>

			<div class="cb-work-toolbar">
				<div class="cb-work-toolbar__row">
					<div class="cb-work-toolbar__head">
						<?php self::render_work_item_views( $state ); ?>
					</div>

					<div class="cb-work-toolbar__primary">
						<label class="screen-reader-text" for="cb-work-filter-status"><?php esc_html_e( 'Status', 'core-blueprint-work' ); ?></label>
						<select id="cb-work-filter-status" name="status" data-cb-work-auto-submit aria-label="<?php esc_attr_e( 'Status', 'core-blueprint-work' ); ?>">
							<option value=""><?php esc_html_e( 'All statuses', 'core-blueprint-work' ); ?></option>
							<option value="active" <?php selected( 'active', (string) $state['status'] ); ?>><?php esc_html_e( 'Active', 'core-blueprint-work' ); ?></option>
							<?php foreach ( WorkItemStatus::all() as $status ) : ?>
								<option value="<?php echo esc_attr( $status ); ?>" <?php selected( $status, (string) $state['status'] ); ?>><?php echo esc_html( self::humanize( $status ) ); ?></option>
							<?php endforeach; ?>
						</select>

						<label class="screen-reader-text" for="cb-work-filter-project"><?php esc_html_e( 'Project', 'core-blueprint-work' ); ?></label>
						<select id="cb-work-filter-project" name="project_id" data-cb-work-auto-submit aria-label="<?php esc_attr_e( 'Project', 'core-blueprint-work' ); ?>">
							<option value="0"><?php esc_html_e( 'All Projects', 'core-blueprint-work' ); ?></option>
							<?php foreach ( $projects as $project ) : ?>
								<option value="<?php echo esc_attr( (string) $project['id'] ); ?>" <?php selected( (int) $state['project_id'], (int) $project['id'] ); ?>><?php echo esc_html( (string) $project['title'] ); ?></option>
							<?php endforeach; ?>
						</select>

						<button
							type="button"
							class="button cb-work-more-filters-toggle"
							aria-controls="cb-work-more-filters"
							aria-expanded="false"
						>
							<span class="dashicons dashicons-filter" aria-hidden="true"></span>
							<span>
							<?php
							echo esc_html(
								$advanced_count > 0
									? sprintf(
										'%1$s (%2$d)',
										__( 'More filters', 'core-blueprint-work' ),
										$advanced_count
									)
									: __( 'More filters', 'core-blueprint-work' )
							);
							?>
							</span>
						</button>
						<?php if ( $has_any_filters ) : ?>
							<a class="cb-work-clear-filters" href="<?php echo esc_url( self::work_items_url( $clear_state ) ); ?>"><?php esc_html_e( 'Clear filters', 'core-blueprint-work' ); ?></a>
						<?php endif; ?>
					</div>

					<div class="cb-work-toolbar__search">
						<label class="screen-reader-text" for="cb-work-filter-search"><?php esc_html_e( 'Search Work Items', 'core-blueprint-work' ); ?></label>
						<div class="cb-work-search-field">
							<span class="dashicons dashicons-search cb-work-search-field__icon" aria-hidden="true"></span>
							<input id="cb-work-filter-search" type="search" name="s" value="<?php echo esc_attr( (string) $state['search'] ); ?>" placeholder="<?php esc_attr_e( 'Search Work Items…', 'core-blueprint-work' ); ?>">
							<button class="screen-reader-text" type="submit"><?php esc_html_e( 'Search', 'core-blueprint-work' ); ?></button>
						</div>
						<?php if ( $is_table ) : ?>
							<button
								type="button"
								class="button cb-work-columns-toggle"
								data-cb-work-table-columns-toggle
								aria-controls="cb-work-table-columns-panel"
								aria-expanded="false"
							>
								<span class="dashicons dashicons-screenoptions" aria-hidden="true"></span>
								<span><?php esc_html_e( 'Columns', 'core-blueprint-work' ); ?></span>
							</button>
						<?php endif; ?>
					</div>
				</div>

				<?php if ( [] !== $summary ) : ?>
					<div class="cb-work-filter-summary" aria-label="<?php esc_attr_e( 'Active filters', 'core-blueprint-work' ); ?>">
						<span class="cb-work-filter-summary__label"><?php esc_html_e( 'Active filters', 'core-blueprint-work' ); ?></span>
						<?php foreach ( $summary as $entry ) : ?>
							<span class="cb-work-filter-chip"><?php echo esc_html( (string) $entry[0] . ': ' . (string) $entry[1] ); ?></span>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<div id="cb-work-more-filters" class="cb-work-toolbar__advanced" hidden>
					<div class="cb-work-toolbar__advanced-grid">
						<div class="cb-work-filter-field cb-work-filter-field--wide">
							<label class="cb-work-filter-label" for="cb-work-filter-customer"><?php esc_html_e( 'Customer', 'core-blueprint-work' ); ?></label>
							<?php Pickers::customer( 'customer', 'cb-work-filter-customer', $selected_customer ); ?>
						</div>
						<div class="cb-work-filter-field cb-work-filter-field--wide">
							<label class="cb-work-filter-label" for="cb-work-filter-assignee"><?php esc_html_e( 'Assignee', 'core-blueprint-work' ); ?></label>
							<?php Pickers::assignee( 'assignee_id', 'cb-work-filter-assignee', (int) $state['assignee_id'] ); ?>
						</div>
						<div class="cb-work-filter-field cb-work-filter-field--service">
							<label class="cb-work-filter-label" for="cb-work-filter-service"><?php esc_html_e( 'Service', 'core-blueprint-work' ); ?></label>
							<select id="cb-work-filter-service" name="service_id">
								<option value="0"><?php esc_html_e( 'All Services', 'core-blueprint-work' ); ?></option>
								<?php foreach ( $services as $service ) : ?>
									<option value="<?php echo esc_attr( (string) $service['id'] ); ?>" <?php selected( (int) $state['service_id'], (int) $service['id'] ); ?>><?php echo esc_html( (string) $service['title'] ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="cb-work-filter-field">
							<label class="cb-work-filter-label" for="cb-work-filter-priority"><?php esc_html_e( 'Priority', 'core-blueprint-work' ); ?></label>
							<select id="cb-work-filter-priority" name="priority">
								<option value=""><?php esc_html_e( 'All priorities', 'core-blueprint-work' ); ?></option>
								<?php foreach ( WorkItemPriority::all() as $priority ) : ?>
									<option value="<?php echo esc_attr( $priority ); ?>" <?php selected( $priority, (string) $state['priority'] ); ?>><?php echo esc_html( self::humanize( $priority ) ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="cb-work-filter-field">
							<label class="cb-work-filter-label" for="cb-work-filter-work-type"><?php esc_html_e( 'Work Type', 'core-blueprint-work' ); ?></label>
							<select id="cb-work-filter-work-type" name="work_type_id">
								<option value="0"><?php esc_html_e( 'All Work Types', 'core-blueprint-work' ); ?></option>
								<?php foreach ( $types as $type ) : ?>
									<option value="<?php echo esc_attr( (string) $type['id'] ); ?>" <?php selected( (int) $state['work_type_id'], (int) $type['id'] ); ?>><?php echo esc_html( (string) $type['label'] ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="cb-work-filter-field">
							<label class="cb-work-filter-label" for="cb-work-filter-work-context"><?php esc_html_e( 'Work context', 'core-blueprint-work' ); ?></label>
							<select id="cb-work-filter-work-context" name="work_context">
								<option value=""><?php esc_html_e( 'All contexts', 'core-blueprint-work' ); ?></option>
								<option value="<?php echo esc_attr( WorkContext::INTERNAL ); ?>" <?php selected( WorkContext::INTERNAL, (string) $state['work_context'] ); ?>><?php esc_html_e( 'Internal', 'core-blueprint-work' ); ?></option>
								<option value="<?php echo esc_attr( WorkContext::CUSTOMER ); ?>" <?php selected( WorkContext::CUSTOMER, (string) $state['work_context'] ); ?>><?php esc_html_e( 'Customer', 'core-blueprint-work' ); ?></option>
							</select>
						</div>
						<div class="cb-work-filter-field">
							<label class="cb-work-filter-label" for="cb-work-filter-billing"><?php esc_html_e( 'Billing', 'core-blueprint-work' ); ?></label>
							<select id="cb-work-filter-billing" name="billing">
								<option value=""><?php esc_html_e( 'All billing classes', 'core-blueprint-work' ); ?></option>
								<?php foreach ( BillingDisposition::all() as $billing ) : ?>
									<option value="<?php echo esc_attr( $billing ); ?>" <?php selected( $billing, (string) $state['billing'] ); ?>><?php echo esc_html( self::humanize( $billing ) ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="cb-work-filter-field">
							<label class="cb-work-filter-label" for="cb-work-filter-sort"><?php esc_html_e( 'Sort', 'core-blueprint-work' ); ?></label>
							<select id="cb-work-filter-sort" name="sort">
								<option value="<?php echo esc_attr( WorkItemQuery::SORT_WORKLOAD ); ?>" <?php selected( WorkItemQuery::SORT_WORKLOAD, (string) $state['sort'] ); ?>><?php esc_html_e( 'Workload', 'core-blueprint-work' ); ?></option>
								<option value="<?php echo esc_attr( WorkItemQuery::SORT_DUE ); ?>" <?php selected( WorkItemQuery::SORT_DUE, (string) $state['sort'] ); ?>><?php esc_html_e( 'Due date', 'core-blueprint-work' ); ?></option>
								<option value="<?php echo esc_attr( WorkItemQuery::SORT_SCHEDULED ); ?>" <?php selected( WorkItemQuery::SORT_SCHEDULED, (string) $state['sort'] ); ?>><?php esc_html_e( 'Scheduled date', 'core-blueprint-work' ); ?></option>
								<option value="<?php echo esc_attr( WorkItemQuery::SORT_UPDATED ); ?>" <?php selected( WorkItemQuery::SORT_UPDATED, (string) $state['sort'] ); ?>><?php esc_html_e( 'Recently updated', 'core-blueprint-work' ); ?></option>
								<option value="<?php echo esc_attr( WorkItemQuery::SORT_TITLE ); ?>" <?php selected( WorkItemQuery::SORT_TITLE, (string) $state['sort'] ); ?>><?php esc_html_e( 'Title', 'core-blueprint-work' ); ?></option>
							</select>
						</div>
						<?php if ( ! $is_calendar ) : ?>
							<div class="cb-work-filter-field cb-work-filter-field--range">
								<span class="cb-work-filter-label"><?php esc_html_e( 'Scheduled', 'core-blueprint-work' ); ?></span>
								<div class="cb-work-filter-range">
									<label class="screen-reader-text" for="cb-work-filter-scheduled-from"><?php esc_html_e( 'Scheduled from', 'core-blueprint-work' ); ?></label>
									<input id="cb-work-filter-scheduled-from" type="date" name="scheduled_from" value="<?php echo esc_attr( (string) $state['scheduled_from'] ); ?>">
									<span class="cb-work-filter-range__separator"><?php esc_html_e( 'to', 'core-blueprint-work' ); ?></span>
									<label class="screen-reader-text" for="cb-work-filter-scheduled-to"><?php echo esc_html( __( 'Scheduled', 'core-blueprint-work' ) . ' ' . __( 'to', 'core-blueprint-work' ) ); ?></label>
									<input id="cb-work-filter-scheduled-to" type="date" name="scheduled_to" value="<?php echo esc_attr( (string) $state['scheduled_to'] ); ?>">
								</div>
							</div>
						<?php endif; ?>
						<div class="cb-work-filter-field cb-work-filter-field--range">
							<span class="cb-work-filter-label"><?php esc_html_e( 'Due', 'core-blueprint-work' ); ?></span>
							<div class="cb-work-filter-range">
								<label class="screen-reader-text" for="cb-work-filter-due-from"><?php esc_html_e( 'Due from', 'core-blueprint-work' ); ?></label>
								<input id="cb-work-filter-due-from" type="date" name="due_from" value="<?php echo esc_attr( (string) $state['due_from'] ); ?>">
								<span class="cb-work-filter-range__separator"><?php esc_html_e( 'to', 'core-blueprint-work' ); ?></span>
								<label class="screen-reader-text" for="cb-work-filter-due-to"><?php echo esc_html( __( 'Due', 'core-blueprint-work' ) . ' ' . __( 'to', 'core-blueprint-work' ) ); ?></label>
								<input id="cb-work-filter-due-to" type="date" name="due_to" value="<?php echo esc_attr( (string) $state['due_to'] ); ?>">
							</div>
						</div>
					</div>
				</div>
			</div>
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

			<?php $return_args = WorkItemViewState::query_args( $state ); ?>
			<form
				id="cb-work-bulk-form"
				class="cb-work-table-bulk"
				method="post"
				action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
				data-cb-work-bulk-form
				hidden
			>
				<input type="hidden" name="action" value="cb_work_bulk_transition_work_items">
				<?php foreach ( $return_args as $key => $value ) : ?>
					<input type="hidden" name="return_state[<?php echo esc_attr( (string) $key ); ?>]" value="<?php echo esc_attr( (string) $value ); ?>">
				<?php endforeach; ?>
				<?php wp_nonce_field( 'cb_work_bulk_transition_work_items' ); ?>
				<span class="cb-work-table-bulk__count"><strong data-cb-work-selected-count>0</strong> <?php esc_html_e( 'Selected', 'core-blueprint-work' ); ?></span>
				<label class="screen-reader-text" for="cb-work-bulk-status"><?php esc_html_e( 'Status', 'core-blueprint-work' ); ?></label>
				<select id="cb-work-bulk-status" name="status" data-cb-work-bulk-status>
					<option value=""><?php esc_html_e( 'Actions', 'core-blueprint-work' ); ?></option>
					<option value="<?php echo esc_attr( WorkItemStatus::PLANNED ); ?>"><?php esc_html_e( 'Planned', 'core-blueprint-work' ); ?></option>
					<option value="<?php echo esc_attr( WorkItemStatus::IN_PROGRESS ); ?>"><?php esc_html_e( 'Start', 'core-blueprint-work' ); ?></option>
					<option value="<?php echo esc_attr( WorkItemStatus::BLOCKED ); ?>"><?php esc_html_e( 'Blocked', 'core-blueprint-work' ); ?></option>
					<option value="<?php echo esc_attr( WorkItemStatus::COMPLETED ); ?>"><?php esc_html_e( 'Complete', 'core-blueprint-work' ); ?></option>
					<option value="<?php echo esc_attr( WorkItemStatus::SKIPPED ); ?>"><?php esc_html_e( 'Skip', 'core-blueprint-work' ); ?></option>
					<option value="<?php echo esc_attr( WorkItemStatus::CANCELLED ); ?>"><?php esc_html_e( 'Cancel', 'core-blueprint-work' ); ?></option>
				</select>
				<button class="button" type="button" data-cb-work-bulk-edit-toggle><?php esc_html_e( 'Bulk Edit', 'core-blueprint-work' ); ?></button>
				<button class="button" type="submit" data-cb-work-bulk-submit disabled><?php esc_html_e( 'Move', 'core-blueprint-work' ); ?></button>
			</form>

			<form
				id="cb-work-bulk-edit-form"
				class="cb-work-inline-editor cb-work-bulk-editor"
				method="post"
				action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
				data-cb-work-bulk-edit-form
				hidden
			>
				<input type="hidden" name="action" value="cb_work_bulk_edit_work_items">
				<?php foreach ( $return_args as $key => $value ) : ?>
					<input type="hidden" name="return_state[<?php echo esc_attr( (string) $key ); ?>]" value="<?php echo esc_attr( (string) $value ); ?>">
				<?php endforeach; ?>
				<?php foreach ( $items as $item ) : ?>
					<input type="hidden" name="work_item_ids[]" value="<?php echo esc_attr( (string) $item['id'] ); ?>" data-cb-work-bulk-edit-id="<?php echo esc_attr( (string) $item['id'] ); ?>" disabled>
				<?php endforeach; ?>
				<?php wp_nonce_field( 'cb_work_bulk_edit_work_items' ); ?>
				<div class="cb-work-inline-editor__header">
					<div>
						<strong><?php esc_html_e( 'Bulk Edit', 'core-blueprint-work' ); ?></strong>
						<span class="description"><strong data-cb-work-bulk-edit-count>0</strong> <?php esc_html_e( 'Selected', 'core-blueprint-work' ); ?></span>
					</div>
					<button class="button-link" type="button" data-cb-work-bulk-edit-cancel><?php esc_html_e( 'Cancel', 'core-blueprint-work' ); ?></button>
				</div>
				<div class="cb-work-inline-editor__grid">
					<label class="cb-work-inline-editor__field">
						<span><?php esc_html_e( 'Priority', 'core-blueprint-work' ); ?></span>
						<select name="bulk_priority" data-cb-work-bulk-edit-control>
							<option value="__keep"><?php esc_html_e( 'No change', 'core-blueprint-work' ); ?></option>
							<?php foreach ( WorkItemPriority::all() as $priority ) : ?>
								<option value="<?php echo esc_attr( $priority ); ?>"><?php echo esc_html( self::humanize( $priority ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<div class="cb-work-inline-editor__field">
						<label><input type="checkbox" name="apply_due" value="1" data-cb-work-bulk-edit-control> <span><?php esc_html_e( 'Due', 'core-blueprint-work' ); ?></span></label>
						<input type="date" name="bulk_due_on">
					</div>
					<label class="cb-work-inline-editor__field">
						<span><?php esc_html_e( 'Work Type', 'core-blueprint-work' ); ?></span>
						<select name="bulk_work_type_id" data-cb-work-bulk-edit-control>
							<option value="__keep"><?php esc_html_e( 'No change', 'core-blueprint-work' ); ?></option>
							<option value="0">—</option>
							<?php foreach ( $type_map as $type_id => $type_label ) : ?>
								<option value="<?php echo esc_attr( (string) $type_id ); ?>"><?php echo esc_html( $type_label ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label class="cb-work-inline-editor__field">
						<span><?php esc_html_e( 'Billing classification', 'core-blueprint-work' ); ?></span>
						<select name="bulk_billing_disposition" data-cb-work-bulk-edit-control>
							<option value="__keep"><?php esc_html_e( 'No change', 'core-blueprint-work' ); ?></option>
							<option value="__clear"><?php esc_html_e( 'Not classified', 'core-blueprint-work' ); ?></option>
							<?php foreach ( BillingDisposition::all() as $billing ) : ?>
								<option value="<?php echo esc_attr( $billing ); ?>"><?php echo esc_html( self::humanize( $billing ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<div class="cb-work-inline-editor__field cb-work-inline-editor__field--wide">
						<label><input type="checkbox" name="apply_assignees" value="1" data-cb-work-bulk-edit-control> <span><?php esc_html_e( 'Update assignees', 'core-blueprint-work' ); ?></span></label>
						<?php Pickers::assignees( 'bulk_assigned_user_ids', 'cb-work-bulk-edit-assignees', [] ); ?>
					</div>
				</div>
				<div class="cb-work-inline-editor__actions">
					<button class="button button-primary" type="submit" data-cb-work-bulk-edit-submit disabled><?php esc_html_e( 'Update selected', 'core-blueprint-work' ); ?></button>
					<button class="button" type="button" data-cb-work-bulk-edit-cancel><?php esc_html_e( 'Cancel', 'core-blueprint-work' ); ?></button>
				</div>
			</form>

			<table class="widefat cb-work-items-table" data-cb-work-items-table>
				<thead><tr>
					<th class="cb-work-select-column">
						<input type="checkbox" data-cb-work-select-all aria-label="<?php esc_attr_e( 'Work Items', 'core-blueprint-work' ); ?>">
					</th>
					<?php foreach ( $preferences['order'] as $column_id ) :
						$label       = $columns[ $column_id ] ?? $column_id;
						$sort_key    = self::work_item_table_sort_key( $column_id );
						$sort_active = '' !== $sort_key && $sort_key === (string) $state['sort'];
						?>
						<th
							data-cb-work-column="<?php echo esc_attr( $column_id ); ?>"
							<?php if ( isset( $hidden[ $column_id ] ) ) : ?>hidden<?php endif; ?>
							<?php if ( '' !== $sort_key ) : ?>aria-sort="<?php echo esc_attr( $sort_active ? 'ascending' : 'none' ); ?>"<?php endif; ?>
						>
							<?php self::render_work_item_table_header( $column_id, $label, $state ); ?>
						</th>
					<?php endforeach; ?>
				</tr></thead>
				<tbody>
				<?php foreach ( $items as $item ) : ?>
					<tr data-cb-work-table-row>
						<td class="cb-work-select-column">
							<input
								type="checkbox"
								name="work_item_ids[]"
								value="<?php echo esc_attr( (string) $item['id'] ); ?>"
								form="cb-work-bulk-form"
								data-cb-work-select-item
								aria-label="<?php echo esc_attr( (string) $item['title'] ); ?>"
							>
						</td>
						<?php foreach ( $preferences['order'] as $column_id ) : ?>
							<td data-cb-work-column="<?php echo esc_attr( $column_id ); ?>" <?php if ( isset( $hidden[ $column_id ] ) ) : ?>hidden<?php endif; ?>>
								<?php self::render_work_item_table_cell( $column_id, $item, $project_map, $type_map, $state ); ?>
							</td>
						<?php endforeach; ?>
					</tr>
					<?php self::render_work_item_quick_edit_row( $item, $type_map, $state, count( $preferences['order'] ) + 1 ); ?>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}



	/**
	 * @param array<string,mixed> $item
	 * @param array<int,string> $type_map
	 * @param array<string,mixed> $state
	 */
	private static function render_work_item_quick_edit_row( array $item, array $type_map, array $state, int $colspan ): void {
		$id              = (int) $item['id'];
		$status          = (string) ( $item['status'] ?? WorkItemStatus::PLANNED );
		$status_list     = array_values( array_unique( [ $status, ...WorkItemStatus::transitions_from( $status ) ] ) );
		$return_args     = WorkItemViewState::query_args( $state );
		$current_type_id = (int) ( $item['work_type_id'] ?? 0 );
		$quick_type_map  = $type_map;
		if ( $current_type_id > 0 && ! isset( $quick_type_map[ $current_type_id ] ) ) {
			$current_type = WorkTypes::get( $current_type_id );
			if ( is_array( $current_type ) ) {
				$quick_type_map[ $current_type_id ] = (string) ( $current_type['label'] ?? $current_type_id );
			}
		}
		?>
		<tr class="cb-work-quick-edit-row" data-cb-work-quick-edit-row="<?php echo esc_attr( (string) $id ); ?>" hidden>
			<td colspan="<?php echo esc_attr( (string) $colspan ); ?>">
				<form class="cb-work-inline-editor cb-work-quick-editor" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="cb_work_quick_edit_work_item">
					<input type="hidden" name="work_item_id" value="<?php echo esc_attr( (string) $id ); ?>">
					<?php foreach ( $return_args as $key => $value ) : ?>
						<input type="hidden" name="return_state[<?php echo esc_attr( (string) $key ); ?>]" value="<?php echo esc_attr( (string) $value ); ?>">
					<?php endforeach; ?>
					<?php wp_nonce_field( 'cb_work_quick_edit_work_item_' . $id ); ?>
					<div class="cb-work-inline-editor__header">
						<strong><?php esc_html_e( 'Quick Edit', 'core-blueprint-work' ); ?></strong>
						<button class="button-link" type="button" data-cb-work-quick-edit-cancel><?php esc_html_e( 'Cancel', 'core-blueprint-work' ); ?></button>
					</div>
					<div class="cb-work-inline-editor__grid">
						<label class="cb-work-inline-editor__field cb-work-inline-editor__field--wide">
							<span><?php esc_html_e( 'Title', 'core-blueprint-work' ); ?></span>
							<input type="text" name="work_item[title]" value="<?php echo esc_attr( (string) $item['title'] ); ?>" required>
						</label>
						<label class="cb-work-inline-editor__field">
							<span><?php esc_html_e( 'Status', 'core-blueprint-work' ); ?></span>
							<select name="status">
								<?php foreach ( $status_list as $candidate_status ) : ?>
									<option value="<?php echo esc_attr( $candidate_status ); ?>" <?php selected( $status, $candidate_status ); ?>><?php echo esc_html( self::humanize( $candidate_status ) ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						<label class="cb-work-inline-editor__field">
							<span><?php esc_html_e( 'Priority', 'core-blueprint-work' ); ?></span>
							<select name="work_item[priority]">
								<?php foreach ( WorkItemPriority::all() as $priority ) : ?>
									<option value="<?php echo esc_attr( $priority ); ?>" <?php selected( (string) $item['priority'], $priority ); ?>><?php echo esc_html( self::humanize( $priority ) ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						<label class="cb-work-inline-editor__field">
							<span><?php esc_html_e( 'Due', 'core-blueprint-work' ); ?></span>
							<input type="date" name="work_item[due_on]" value="<?php echo esc_attr( (string) ( $item['due_on'] ?? '' ) ); ?>">
						</label>
						<label class="cb-work-inline-editor__field">
							<span><?php esc_html_e( 'Work Type', 'core-blueprint-work' ); ?></span>
							<select name="work_item[work_type_id]">
								<option value="0">—</option>
								<?php foreach ( $quick_type_map as $type_id => $type_label ) : ?>
									<option value="<?php echo esc_attr( (string) $type_id ); ?>" <?php selected( $current_type_id, (int) $type_id ); ?>><?php echo esc_html( $type_label ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						<label class="cb-work-inline-editor__field">
							<span><?php esc_html_e( 'Billing classification', 'core-blueprint-work' ); ?></span>
							<select name="work_item[billing_disposition]">
								<option value=""><?php esc_html_e( 'Not classified', 'core-blueprint-work' ); ?></option>
								<?php foreach ( BillingDisposition::all() as $billing ) : ?>
									<option value="<?php echo esc_attr( $billing ); ?>" <?php selected( (string) ( $item['billing_disposition'] ?? '' ), $billing ); ?>><?php echo esc_html( self::humanize( $billing ) ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						<div class="cb-work-inline-editor__field cb-work-inline-editor__field--wide">
							<span><?php esc_html_e( 'Assignees', 'core-blueprint-work' ); ?></span>
							<?php Pickers::assignees( 'work_item[assigned_user_ids]', 'cb-work-quick-assignees-' . $id, (array) ( $item['assigned_user_ids'] ?? [] ) ); ?>
						</div>
					</div>
					<div class="cb-work-inline-editor__actions">
						<button class="button button-primary" type="submit"><?php esc_html_e( 'Save changes', 'core-blueprint-work' ); ?></button>
						<button class="button" type="button" data-cb-work-quick-edit-cancel><?php esc_html_e( 'Cancel', 'core-blueprint-work' ); ?></button>
					</div>
				</form>
			</td>
		</tr>
		<?php
	}

	private static function work_item_table_sort_key( string $column_id ): string {
		return match ( $column_id ) {
			'work_item' => WorkItemQuery::SORT_TITLE,
			'due'       => WorkItemQuery::SORT_DUE,
			default     => '',
		};
	}

	/** @param array<string,mixed> $state */
	private static function render_work_item_table_header( string $column_id, string $label, array $state ): void {
		$sort_key = self::work_item_table_sort_key( $column_id );
		if ( '' === $sort_key ) {
			echo esc_html( $label );
			return;
		}
		$active = $sort_key === (string) $state['sort'];
		?>
		<a
			class="cb-work-table-sort<?php echo $active ? ' is-active' : ''; ?>"
			href="<?php echo esc_url( self::work_items_url( $state, [ 'sort' => $sort_key, 'page' => 1 ] ) ); ?>"
		>
			<span><?php echo esc_html( $label ); ?></span>
			<span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span>
		</a>
		<?php
	}

	/** @return array<string,string> */
	private static function work_item_table_columns(): array {
		return [
			'work_item' => __( 'Work Item', 'core-blueprint-work' ),
			'status'    => __( 'Status', 'core-blueprint-work' ),
			'priority'  => __( 'Priority', 'core-blueprint-work' ),
			'due'       => __( 'Due', 'core-blueprint-work' ),
			'assigned'  => __( 'Assignee', 'core-blueprint-work' ),
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
				$project = $project_map[ (int) ( $item['project_id'] ?? 0 ) ] ?? '—';
				?>
				<div class="cb-work-item-cell">
					<strong class="cb-work-item-cell__title"><a href="<?php echo esc_url( Menu::edit_work_item_url( (int) $item['id'] ) ); ?>"><?php echo esc_html( (string) $item['title'] ); ?></a></strong>
					<span class="cb-work-item-cell__context"><?php echo esc_html( $project ); ?></span>
				</div>
				<?php
				return;
			case 'status':
				$status = (string) ( $item['status'] ?? '' );
				echo StateBadge::render(
					self::humanize( $status ),
					[
						'variant' => self::work_item_status_badge_variant( $status ),
						'class'   => 'cb-work-status-badge',
					]
				);
				return;
			case 'priority':
				self::render_work_item_priority( (string) ( $item['priority'] ?? '' ) );
				return;
			case 'due':
				self::render_work_item_due( (string) ( $item['due_on'] ?? '' ) );
				return;
			case 'assigned':
				self::render_work_item_assignee( (array) ( $item['assigned_user_ids'] ?? [] ) );
				return;
			case 'actions':
				self::transition_icon_buttons( $item, $state );
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


	private static function work_item_status_badge_variant( string $status ): string {
		return match ( $status ) {
			WorkItemStatus::PLANNED     => StateBadge::NEUTRAL,
			WorkItemStatus::IN_PROGRESS => StateBadge::INFO,
			WorkItemStatus::BLOCKED     => StateBadge::WARNING,
			WorkItemStatus::COMPLETED   => StateBadge::SUCCESS,
			WorkItemStatus::CANCELLED   => StateBadge::DANGER,
			default                     => StateBadge::NEUTRAL,
		};
	}

	private static function render_work_item_priority( string $priority ): void {
		$icon = match ( $priority ) {
			WorkItemPriority::LOW    => 'dashicons-arrow-down-alt2',
			WorkItemPriority::HIGH,
			WorkItemPriority::URGENT => 'dashicons-arrow-up-alt2',
			default                  => 'dashicons-minus',
		};
		$priority = WorkItemPriority::is_valid( $priority ) ? $priority : WorkItemPriority::NORMAL;
		?>
		<span class="cb-work-priority cb-work-priority--<?php echo esc_attr( $priority ); ?>">
			<span class="dashicons <?php echo esc_attr( $icon ); ?>" aria-hidden="true"></span>
			<span><?php echo esc_html( self::humanize( $priority ) ); ?></span>
		</span>
		<?php
	}

	private static function render_work_item_due( string $due_on ): void {
		$due_on = trim( $due_on );
		if ( '' === $due_on ) {
			echo esc_html( '—' );
			return;
		}

		$timezone = wp_timezone();
		$due      = \DateTimeImmutable::createFromFormat( '!Y-m-d', $due_on, $timezone );
		$today    = \DateTimeImmutable::createFromFormat( '!Y-m-d', current_time( 'Y-m-d' ), $timezone );
		if ( false === $due || false === $today ) {
			echo esc_html( $due_on );
			return;
		}

		$delta = (int) $today->diff( $due )->format( '%r%a' );
		$class = $delta < 0 ? ' is-overdue' : ( 0 === $delta ? ' is-today' : '' );
		?>
		<span class="cb-work-due<?php echo esc_attr( $class ); ?>">
			<span class="cb-work-due__date"><?php echo esc_html( wp_date( 'M j, Y', $due->getTimestamp(), $timezone ) ); ?></span>
			<?php if ( 0 === $delta ) : ?>
				<span class="cb-work-due__relative"><?php esc_html_e( 'Today', 'core-blueprint-work' ); ?></span>
			<?php elseif ( $delta < 0 ) : ?>
				<span class="cb-work-due__relative"><?php esc_html_e( 'Overdue', 'core-blueprint-work' ); ?> · <?php echo esc_html( human_time_diff( $due->getTimestamp(), $today->getTimestamp() ) ); ?></span>
			<?php else : ?>
				<span class="cb-work-due__relative"><?php echo esc_html( human_time_diff( $today->getTimestamp(), $due->getTimestamp() ) ); ?></span>
			<?php endif; ?>
		</span>
		<?php
	}

	/** @param int[] $ids */
	private static function render_work_item_assignee( array $ids ): void {
		$users = [];
		foreach ( $ids as $id ) {
			$user = get_userdata( (int) $id );
			if ( $user ) {
				$users[] = $user;
			}
		}
		if ( [] === $users ) {
			echo esc_html( '—' );
			return;
		}

		$primary = $users[0];
		$name    = (string) $primary->display_name;
		?>
		<span class="cb-work-assignee">
			<span class="cb-work-assignee__avatar" aria-hidden="true"><?php echo esc_html( self::initials_for_name( $name ) ); ?></span>
			<span class="cb-work-assignee__name"><?php echo esc_html( $name ); ?></span>
			<?php if ( count( $users ) > 1 ) : ?>
				<span class="cb-work-assignee__more">+<?php echo esc_html( (string) ( count( $users ) - 1 ) ); ?></span>
			<?php endif; ?>
		</span>
		<?php
	}

	private static function initials_for_name( string $name ): string {
		$parts = preg_split( '/\s+/', trim( $name ) ) ?: [];
		$parts = array_values( array_filter( $parts, static fn ( string $part ): bool => '' !== $part ) );
		if ( [] === $parts ) {
			return '?';
		}
		$first = strtoupper( substr( $parts[0], 0, 1 ) );
		if ( count( $parts ) < 2 ) {
			return $first;
		}
		return $first . strtoupper( substr( $parts[ count( $parts ) - 1 ], 0, 1 ) );
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
	private static function transition_icon_buttons( array $item, array $state ): void {
		$from        = (string) ( $item['status'] ?? '' );
		$transitions = WorkItemStatus::transitions_from( $from );
		$direct      = array_values( array_filter( $transitions, static fn ( string $status ): bool => WorkItemStatus::CANCELLED !== $status ) );
		$overflow    = array_values( array_filter( $transitions, static fn ( string $status ): bool => WorkItemStatus::CANCELLED === $status ) );
		?>
		<div class="cb-work-row-actions">
			<?php foreach ( $direct as $to ) : ?>
				<?php self::transition_icon_form( $item, $state, $from, $to ); ?>
			<?php endforeach; ?>
			<details class="cb-work-row-actions__more">
					<summary class="button button-small cb-work-row-action cb-work-row-action--overflow" aria-label="<?php esc_attr_e( 'Actions', 'core-blueprint-work' ); ?>" data-cb-work-tooltip="<?php esc_attr_e( 'Actions', 'core-blueprint-work' ); ?>">
						<span class="dashicons dashicons-ellipsis" aria-hidden="true"></span>
					</summary>
					<div class="cb-work-row-actions__menu">
						<button
							type="button"
							class="button-link cb-work-row-actions__menu-item"
							data-cb-work-quick-edit-toggle="<?php echo esc_attr( (string) $item['id'] ); ?>"
						><?php esc_html_e( 'Quick Edit', 'core-blueprint-work' ); ?></button>
						<?php foreach ( $overflow as $to ) : ?>
							<?php self::transition_menu_form( $item, $state, $from, $to ); ?>
						<?php endforeach; ?>
					</div>
				</details>
		</div>
		<?php
	}

	/** @param array<string,mixed> $item @param array<string,mixed> $state */
	private static function transition_icon_form( array $item, array $state, string $from, string $to ): void {
		$label       = self::transition_label( $to, $from );
		$return_args = WorkItemViewState::query_args( $state );
		unset( $return_args['page'] );
		$icon = match ( $to ) {
			WorkItemStatus::IN_PROGRESS => 'dashicons-controls-play',
			WorkItemStatus::BLOCKED     => 'dashicons-no',
			WorkItemStatus::COMPLETED   => 'dashicons-yes-alt',
			WorkItemStatus::SKIPPED     => 'dashicons-controls-skipforward',
			WorkItemStatus::CANCELLED   => 'dashicons-dismiss',
			WorkItemStatus::PLANNED     => 'dashicons-controls-back',
			default                     => 'dashicons-admin-generic',
		};
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cb-work-transition-form">
			<input type="hidden" name="action" value="cb_work_transition_work_item">
			<input type="hidden" name="work_item_id" value="<?php echo esc_attr( (string) $item['id'] ); ?>">
			<input type="hidden" name="status" value="<?php echo esc_attr( $to ); ?>">
			<?php foreach ( $return_args as $key => $value ) : ?>
				<input type="hidden" name="return_state[<?php echo esc_attr( (string) $key ); ?>]" value="<?php echo esc_attr( (string) $value ); ?>">
			<?php endforeach; ?>
			<?php wp_nonce_field( 'cb_work_transition_work_item_' . (int) $item['id'] ); ?>
			<button class="button button-small cb-work-row-action cb-work-row-action--<?php echo esc_attr( $to ); ?>" type="submit" aria-label="<?php echo esc_attr( $label ); ?>" data-cb-work-tooltip="<?php echo esc_attr( $label ); ?>">
				<span class="dashicons <?php echo esc_attr( $icon ); ?>" aria-hidden="true"></span>
				<span class="screen-reader-text"><?php echo esc_html( $label ); ?></span>
			</button>
		</form>
		<?php
	}

	/** @param array<string,mixed> $item @param array<string,mixed> $state */
	private static function transition_menu_form( array $item, array $state, string $from, string $to ): void {
		$label       = self::transition_label( $to, $from );
		$return_args = WorkItemViewState::query_args( $state );
		unset( $return_args['page'] );
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cb-work-transition-form cb-work-transition-form--menu">
			<input type="hidden" name="action" value="cb_work_transition_work_item">
			<input type="hidden" name="work_item_id" value="<?php echo esc_attr( (string) $item['id'] ); ?>">
			<input type="hidden" name="status" value="<?php echo esc_attr( $to ); ?>">
			<?php foreach ( $return_args as $key => $value ) : ?>
				<input type="hidden" name="return_state[<?php echo esc_attr( (string) $key ); ?>]" value="<?php echo esc_attr( (string) $value ); ?>">
			<?php endforeach; ?>
			<?php wp_nonce_field( 'cb_work_transition_work_item_' . (int) $item['id'] ); ?>
			<button class="button-link cb-work-row-actions__menu-item" type="submit"><?php echo esc_html( $label ); ?></button>
		</form>
		<?php
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
			'work-item-updated'            => [ 'success', __( 'Work Item updated', 'core-blueprint-work' ) ],
			'work-item-update-invalid'     => [ 'error', __( 'Work Item could not be updated.', 'core-blueprint-work' ) ],
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
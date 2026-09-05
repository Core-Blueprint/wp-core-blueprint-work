<?php
declare(strict_types=1);

namespace CB\Work\Admin;

use CB\Work\Capabilities;
use CB\Work\Database\Schema;
use CB\Work\Domain\BillingDisposition;
use CB\Work\Domain\WorkItemPriority;
use CB\Work\Domain\WorkItemStatus;
use CB\Work\Integration\CRMCustomers;
use CB\Work\PublicApi\Services;
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

		$project_filter = isset( $_GET['project_id'] ) ? absint( $_GET['project_id'] ) : 0;
		$edit_id        = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
		$editing        = $edit_id > 0 ? WorkItems::get( $edit_id ) : null;
		$creating       = isset( $_GET['create'] ) && '1' === sanitize_text_field( wp_unslash( (string) $_GET['create'] ) );
		$items          = $project_filter > 0 ? WorkItems::for_project( $project_filter, 200 ) : WorkItems::all( 200 );
		$projects       = Projects::all();
		$project_map    = [];
		foreach ( $projects as $project ) {
			$project_map[ (int) $project['id'] ] = (string) $project['title'];
		}
		$types = WorkTypes::all();
		$type_map = [];
		foreach ( $types as $type ) {
			$type_map[ (int) $type['id'] ] = (string) $type['label'];
		}
		$services = Services::all( 250 );
		?>
		<div class="wrap cb-work-items-page">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Work Items', 'core-blueprint-work' ); ?></h1>
			<?php if ( ! $creating && null === $editing ) : ?>
				<a class="page-title-action" href="<?php echo esc_url( add_query_arg( [ 'page' => Menu::WORK_ITEMS_SLUG, 'create' => '1', 'project_id' => $project_filter ?: null ], admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Add Work Item', 'core-blueprint-work' ); ?></a>
			<?php endif; ?>
			<hr class="wp-header-end">
			<p class="description"><?php esc_html_e( 'Manage actionable work across customers and Projects.', 'core-blueprint-work' ); ?></p>
			<?php self::render_notice(); ?>

			<?php if ( $project_filter > 0 ) : ?>
				<?php $project_name = $project_map[ $project_filter ] ?? __( 'Unknown Project', 'core-blueprint-work' ); ?>
				<div class="notice notice-info inline"><p>
					<?php echo esc_html( sprintf( __( 'Showing Work Items for Project: %s', 'core-blueprint-work' ), $project_name ) ); ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Menu::WORK_ITEMS_SLUG ) ); ?>"><?php esc_html_e( 'View all Work Items', 'core-blueprint-work' ); ?></a>
				</p></div>
			<?php endif; ?>

			<?php if ( $creating || null !== $editing ) : ?>
				<?php self::render_work_item_form( $editing, $project_filter, $projects, $services, $types ); ?>
			<?php endif; ?>

			<h2><?php echo esc_html( $project_filter > 0 ? __( 'Project Work Items', 'core-blueprint-work' ) : __( 'All Work Items', 'core-blueprint-work' ) ); ?></h2>
			<?php if ( [] === $items ) : ?>
				<p><?php esc_html_e( 'No Work Items found.', 'core-blueprint-work' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead><tr>
						<th><?php esc_html_e( 'Work Item', 'core-blueprint-work' ); ?></th>
						<th><?php esc_html_e( 'Customer', 'core-blueprint-work' ); ?></th>
						<th><?php esc_html_e( 'Status', 'core-blueprint-work' ); ?></th>
						<th><?php esc_html_e( 'Priority', 'core-blueprint-work' ); ?></th>
						<th><?php esc_html_e( 'Project / Type', 'core-blueprint-work' ); ?></th>
						<th><?php esc_html_e( 'Due', 'core-blueprint-work' ); ?></th>
						<th><?php esc_html_e( 'Billing', 'core-blueprint-work' ); ?></th>
						<th><?php esc_html_e( 'Assigned', 'core-blueprint-work' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'core-blueprint-work' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $items as $item ) : ?>
						<?php $edit_url = add_query_arg( [ 'page' => Menu::WORK_ITEMS_SLUG, 'edit' => (int) $item['id'], 'project_id' => $project_filter ?: null ], admin_url( 'admin.php' ) ); ?>
						<tr>
							<td><strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( (string) $item['title'] ); ?></a></strong></td>
							<td><?php echo esc_html( self::customer_label( $item ) ); ?></td>
							<td><?php echo esc_html( self::humanize( (string) $item['status'] ) ); ?></td>
							<td><?php echo esc_html( self::humanize( (string) $item['priority'] ) ); ?></td>
							<td><?php echo esc_html( $project_map[ (int) ( $item['project_id'] ?? 0 ) ] ?? '—' ); ?><br><span class="description"><?php echo esc_html( $type_map[ (int) ( $item['work_type_id'] ?? 0 ) ] ?? '—' ); ?></span></td>
							<td><?php echo esc_html( (string) ( $item['due_on'] ?: '—' ) ); ?></td>
							<td><?php echo esc_html( '' !== (string) $item['billing_disposition'] ? self::humanize( (string) $item['billing_disposition'] ) : '—' ); ?></td>
							<td><?php echo esc_html( self::assignment_label( $item['assigned_user_ids'] ?? [] ) ); ?></td>
							<td><?php self::transition_buttons( $item, $project_filter ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
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

	/**
	 * @param array<string,mixed>|null $item
	 * @param array<int,array<string,mixed>> $projects
	 * @param array<int,array<string,mixed>> $services
	 * @param array<int,array<string,mixed>> $types
	 */
	private static function render_work_item_form( ?array $item, int $project_filter, array $projects, array $services, array $types ): void {
		$editing    = null !== $item;
		$project_id = $editing ? (int) ( $item['project_id'] ?? 0 ) : $project_filter;
		$selected_customer = $editing
			? CRMCustomers::selected( (string) $item['customer_provider'], (string) $item['customer_type'], (string) $item['customer_id'] )
			: null;
		$cancel_url = add_query_arg( [ 'page' => Menu::WORK_ITEMS_SLUG, 'project_id' => $project_filter ?: null ], admin_url( 'admin.php' ) );
		?>
		<div class="card" style="max-width:none">
			<h2><?php echo esc_html( $editing ? __( 'Edit Work Item', 'core-blueprint-work' ) : __( 'Add Work Item', 'core-blueprint-work' ) ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( $editing ? 'cb_work_update_work_item' : 'cb_work_create_work_item' ); ?>">
				<?php if ( $editing ) : ?><input type="hidden" name="work_item_id" value="<?php echo esc_attr( (string) $item['id'] ); ?>"><?php endif; ?>
				<input type="hidden" name="return_project_id" value="<?php echo esc_attr( (string) $project_filter ); ?>">
				<?php wp_nonce_field( $editing ? 'cb_work_update_work_item_' . (int) $item['id'] : 'cb_work_create_work_item' ); ?>

				<table class="form-table" role="presentation"><tbody>
					<tr><th><label for="cb-work-item-title"><?php esc_html_e( 'Title', 'core-blueprint-work' ); ?></label></th><td><input id="cb-work-item-title" class="regular-text" type="text" name="work_item[title]" value="<?php echo esc_attr( (string) ( $item['title'] ?? '' ) ); ?>" required></td></tr>
					<tr><th><?php esc_html_e( 'Customer', 'core-blueprint-work' ); ?></th><td>
						<?php if ( $editing && '' !== (string) ( $item['customer_provider'] ?? '' ) && null === $selected_customer ) : ?>
							<p class="description"><?php esc_html_e( 'This Work Item already has a customer link that cannot be resolved for the current user. The link is preserved.', 'core-blueprint-work' ); ?></p>
						<?php else : ?>
							<?php Pickers::customer( 'work_item[customer_object_id]', 'cb-work-item-customer', $selected_customer ); ?>
						<?php endif; ?>
						<?php if ( $project_id > 0 ) : ?><p class="description"><?php esc_html_e( 'Leave empty to use the Project customer when one is linked.', 'core-blueprint-work' ); ?></p><?php endif; ?>
					</td></tr>
					<tr><th><label for="cb-work-item-project"><?php esc_html_e( 'Project', 'core-blueprint-work' ); ?></label></th><td><select id="cb-work-item-project" name="work_item[project_id]"><option value="0"><?php esc_html_e( 'No project', 'core-blueprint-work' ); ?></option><?php foreach ( $projects as $project ) : ?><option value="<?php echo esc_attr( (string) $project['id'] ); ?>" <?php selected( $project_id, (int) $project['id'] ); ?>><?php echo esc_html( (string) $project['title'] ); ?></option><?php endforeach; ?></select></td></tr>
					<tr><th><label for="cb-work-item-service"><?php esc_html_e( 'Service', 'core-blueprint-work' ); ?></label></th><td><select id="cb-work-item-service" name="work_item[service_id]"><option value="0"><?php esc_html_e( 'No service', 'core-blueprint-work' ); ?></option><?php foreach ( $services as $service ) : ?><option value="<?php echo esc_attr( (string) $service['id'] ); ?>" <?php selected( (int) ( $item['service_id'] ?? 0 ), (int) $service['id'] ); ?>><?php echo esc_html( (string) $service['title'] ); ?></option><?php endforeach; ?></select></td></tr>
					<tr><th><label for="cb-work-item-type"><?php esc_html_e( 'Work Type', 'core-blueprint-work' ); ?></label></th><td><select id="cb-work-item-type" name="work_item[work_type_id]"><option value="0"><?php esc_html_e( 'No classification', 'core-blueprint-work' ); ?></option><?php foreach ( $types as $type ) : ?><option value="<?php echo esc_attr( (string) $type['id'] ); ?>" <?php selected( (int) ( $item['work_type_id'] ?? 0 ), (int) $type['id'] ); ?>><?php echo esc_html( (string) $type['label'] ); ?></option><?php endforeach; ?></select></td></tr>
					<tr><th><label for="cb-work-item-priority"><?php esc_html_e( 'Priority', 'core-blueprint-work' ); ?></label></th><td><select id="cb-work-item-priority" name="work_item[priority]"><?php foreach ( WorkItemPriority::all() as $priority ) : ?><option value="<?php echo esc_attr( $priority ); ?>" <?php selected( (string) ( $item['priority'] ?? WorkItemPriority::NORMAL ), $priority ); ?>><?php echo esc_html( self::humanize( $priority ) ); ?></option><?php endforeach; ?></select></td></tr>
					<tr><th><label for="cb-work-item-due"><?php esc_html_e( 'Due date', 'core-blueprint-work' ); ?></label></th><td><input id="cb-work-item-due" type="date" name="work_item[due_on]" value="<?php echo esc_attr( (string) ( $item['due_on'] ?? '' ) ); ?>"></td></tr>
					<tr><th><?php esc_html_e( 'Assignees', 'core-blueprint-work' ); ?></th><td><?php Pickers::assignees( 'work_item[assigned_user_ids]', 'cb-work-item-assignees', (array) ( $item['assigned_user_ids'] ?? [] ) ); ?></td></tr>
				</tbody></table>

				<details>
					<summary><strong><?php esc_html_e( 'More details', 'core-blueprint-work' ); ?></strong></summary>
					<table class="form-table" role="presentation"><tbody>
						<tr><th><label for="cb-work-item-description"><?php esc_html_e( 'Description / notes', 'core-blueprint-work' ); ?></label></th><td><textarea id="cb-work-item-description" class="large-text" rows="5" name="work_item[description]"><?php echo esc_textarea( (string) ( $item['description'] ?? '' ) ); ?></textarea></td></tr>
						<tr><th><label for="cb-work-item-scheduled"><?php esc_html_e( 'Scheduled date', 'core-blueprint-work' ); ?></label></th><td><input id="cb-work-item-scheduled" type="date" name="work_item[scheduled_on]" value="<?php echo esc_attr( (string) ( $item['scheduled_on'] ?? '' ) ); ?>"><p class="description"><?php esc_html_e( 'When the work is planned to be performed. The due date remains the deadline.', 'core-blueprint-work' ); ?></p></td></tr>
						<tr><th><label for="cb-work-item-billing"><?php esc_html_e( 'Billing classification', 'core-blueprint-work' ); ?></label></th><td><select id="cb-work-item-billing" name="work_item[billing_disposition]"><option value=""><?php esc_html_e( 'Not classified', 'core-blueprint-work' ); ?></option><?php foreach ( BillingDisposition::all() as $billing ) : ?><option value="<?php echo esc_attr( $billing ); ?>" <?php selected( (string) ( $item['billing_disposition'] ?? '' ), $billing ); ?>><?php echo esc_html( self::humanize( $billing ) ); ?></option><?php endforeach; ?></select></td></tr>
					</tbody></table>
				</details>

				<p>
					<?php submit_button( $editing ? __( 'Update Work Item', 'core-blueprint-work' ) : __( 'Add Work Item', 'core-blueprint-work' ), 'primary', 'submit', false ); ?>
					<a class="button" href="<?php echo esc_url( $cancel_url ); ?>"><?php esc_html_e( 'Cancel', 'core-blueprint-work' ); ?></a>
				</p>
			</form>
		</div>
		<?php
	}

	/** @param array<string,mixed> $item */
	private static function transition_buttons( array $item, int $project_filter = 0 ): void {
		$from = (string) ( $item['status'] ?? '' );
		foreach ( WorkItemStatus::transitions_from( $from ) as $to ) {
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin:0 4px 4px 0">
				<input type="hidden" name="action" value="cb_work_transition_work_item">
				<input type="hidden" name="work_item_id" value="<?php echo esc_attr( (string) $item['id'] ); ?>">
				<input type="hidden" name="status" value="<?php echo esc_attr( $to ); ?>">
				<input type="hidden" name="return_project_id" value="<?php echo esc_attr( (string) $project_filter ); ?>">
				<?php wp_nonce_field( 'cb_work_transition_work_item_' . (int) $item['id'] ); ?>
				<button class="button button-small" type="submit"><?php echo esc_html( self::transition_label( $to ) ); ?></button>
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

	private static function transition_label( string $status ): string {
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
			'work-item-created'            => [ 'success', __( 'Work Item created.', 'core-blueprint-work' ) ],
			'work-item-updated'            => [ 'success', __( 'Work Item updated.', 'core-blueprint-work' ) ],
			'work-item-invalid'            => [ 'error', __( 'Work Item could not be saved. Check its customer, Project, dates and classifications.', 'core-blueprint-work' ) ],
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

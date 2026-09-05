<?php
declare(strict_types=1);

namespace CB\Work\Admin;

use CB\Work\Capabilities;
use CB\Work\Database\Schema;
use CB\Work\Domain\BillingDisposition;
use CB\Work\Domain\WorkItemPriority;
use CB\Work\Domain\WorkItemStatus;
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
			<div class="card"><h2><?php esc_html_e( 'Projects', 'core-blueprint-work' ); ?></h2><p><strong><?php echo esc_html( (string) Projects::count() ); ?></strong></p><p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . Menu::PROJECTS_SLUG ) ); ?>"><?php esc_html_e( 'Manage Projects', 'core-blueprint-work' ); ?></a></p></div>
		</div>
		<?php
	}

	public static function render_projects(): void {
		self::guard();
		$projects = Projects::all();
		?>
		<div class="wrap cb-work-projects-page">
			<h1><?php esc_html_e( 'Projects', 'core-blueprint-work' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Projects are optional operational groupings. Work Items can exist without a project.', 'core-blueprint-work' ); ?></p>
			<?php self::render_notice(); ?>
			<?php if ( ! self::schema_ready() ) { self::storage_notice(); return; } ?>

			<details class="card"><summary><strong><?php esc_html_e( 'Add Project', 'core-blueprint-work' ); ?></strong></summary>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="cb_work_create_project">
					<?php wp_nonce_field( 'cb_work_create_project' ); ?>
					<table class="form-table" role="presentation"><tbody>
						<tr><th><label for="cb-work-project-title"><?php esc_html_e( 'Title', 'core-blueprint-work' ); ?></label></th><td><input id="cb-work-project-title" class="regular-text" type="text" name="project[title]" required></td></tr>
						<tr><th><label for="cb-work-project-description"><?php esc_html_e( 'Description', 'core-blueprint-work' ); ?></label></th><td><textarea id="cb-work-project-description" class="large-text" rows="4" name="project[description]"></textarea></td></tr>
						<tr><th><?php esc_html_e( 'Customer reference', 'core-blueprint-work' ); ?></th><td><?php self::reference_fields( 'project', 'customer', 'project' ); ?><p class="description"><?php esc_html_e( 'Optional soft reference. Example provider/type/id: crm / organization / 42. Work never reads CRM private tables.', 'core-blueprint-work' ); ?></p></td></tr>
						<tr><th><label for="cb-work-project-starts"><?php esc_html_e( 'Starts on', 'core-blueprint-work' ); ?></label></th><td><input id="cb-work-project-starts" type="date" name="project[starts_on]"></td></tr>
						<tr><th><label for="cb-work-project-due"><?php esc_html_e( 'Due on', 'core-blueprint-work' ); ?></label></th><td><input id="cb-work-project-due" type="date" name="project[due_on]"></td></tr>
					</tbody></table>
					<?php submit_button( __( 'Add Project', 'core-blueprint-work' ) ); ?>
				</form>
			</details>

			<h2><?php esc_html_e( 'Current Projects', 'core-blueprint-work' ); ?></h2>
			<?php if ( [] === $projects ) : ?><p><?php esc_html_e( 'No projects yet.', 'core-blueprint-work' ); ?></p><?php else : ?>
			<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Project', 'core-blueprint-work' ); ?></th><th><?php esc_html_e( 'Customer', 'core-blueprint-work' ); ?></th><th><?php esc_html_e( 'Dates', 'core-blueprint-work' ); ?></th><th><?php esc_html_e( 'Work Items', 'core-blueprint-work' ); ?></th></tr></thead><tbody>
			<?php foreach ( $projects as $project ) : ?>
				<tr><td><strong><?php echo esc_html( (string) $project['title'] ); ?></strong></td><td><?php echo esc_html( self::reference_label( $project, 'customer_' ) ); ?></td><td><?php echo esc_html( self::date_range( (string) ( $project['starts_on'] ?? '' ), (string) ( $project['due_on'] ?? '' ) ) ); ?></td><td><?php echo esc_html( (string) WorkItems::count_for_project( (int) $project['id'] ) ); ?></td></tr>
			<?php endforeach; ?>
			</tbody></table><?php endif; ?>
		</div>
		<?php
	}

	public static function render_work_items(): void {
		self::guard();
		$items = WorkItems::all( 200 );
		$projects = Projects::all();
		$project_map = [];
		foreach ( $projects as $project ) { $project_map[ (int) $project['id'] ] = (string) $project['title']; }
		$types = WorkTypes::all();
		$type_map = [];
		foreach ( $types as $type ) { $type_map[ (int) $type['id'] ] = (string) $type['label']; }
		$services = Services::all( 250 );
		?>
		<div class="wrap cb-work-items-page">
			<h1><?php esc_html_e( 'Work Items', 'core-blueprint-work' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Actionable work. Completion state and billing classification are intentionally separate.', 'core-blueprint-work' ); ?></p>
			<?php self::render_notice(); ?>
			<?php if ( ! self::schema_ready() ) { self::storage_notice(); return; } ?>

			<details class="card"><summary><strong><?php esc_html_e( 'Add Work Item', 'core-blueprint-work' ); ?></strong></summary>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="cb_work_create_work_item">
					<?php wp_nonce_field( 'cb_work_create_work_item' ); ?>
					<table class="form-table" role="presentation"><tbody>
						<tr><th><label for="cb-work-item-title"><?php esc_html_e( 'Title', 'core-blueprint-work' ); ?></label></th><td><input id="cb-work-item-title" class="regular-text" type="text" name="work_item[title]" required></td></tr>
						<tr><th><label for="cb-work-item-description"><?php esc_html_e( 'Description / notes', 'core-blueprint-work' ); ?></label></th><td><textarea id="cb-work-item-description" class="large-text" rows="4" name="work_item[description]"></textarea></td></tr>
						<tr><th><label for="cb-work-item-project"><?php esc_html_e( 'Project', 'core-blueprint-work' ); ?></label></th><td><select id="cb-work-item-project" name="work_item[project_id]"><option value="0"><?php esc_html_e( 'No project', 'core-blueprint-work' ); ?></option><?php foreach ( $projects as $project ) : ?><option value="<?php echo esc_attr( (string) $project['id'] ); ?>"><?php echo esc_html( (string) $project['title'] ); ?></option><?php endforeach; ?></select></td></tr>
						<tr><th><label for="cb-work-item-service"><?php esc_html_e( 'Service', 'core-blueprint-work' ); ?></label></th><td><select id="cb-work-item-service" name="work_item[service_id]"><option value="0"><?php esc_html_e( 'No service', 'core-blueprint-work' ); ?></option><?php foreach ( $services as $service ) : ?><option value="<?php echo esc_attr( (string) $service['id'] ); ?>"><?php echo esc_html( (string) $service['title'] ); ?></option><?php endforeach; ?></select></td></tr>
						<tr><th><label for="cb-work-item-type"><?php esc_html_e( 'Work Type', 'core-blueprint-work' ); ?></label></th><td><select id="cb-work-item-type" name="work_item[work_type_id]"><option value="0"><?php esc_html_e( 'No classification', 'core-blueprint-work' ); ?></option><?php foreach ( $types as $type ) : ?><option value="<?php echo esc_attr( (string) $type['id'] ); ?>"><?php echo esc_html( (string) $type['label'] ); ?></option><?php endforeach; ?></select></td></tr>
						<tr><th><label for="cb-work-item-priority"><?php esc_html_e( 'Priority', 'core-blueprint-work' ); ?></label></th><td><select id="cb-work-item-priority" name="work_item[priority]"><?php foreach ( WorkItemPriority::all() as $priority ) : ?><option value="<?php echo esc_attr( $priority ); ?>" <?php selected( WorkItemPriority::NORMAL, $priority ); ?>><?php echo esc_html( self::humanize( $priority ) ); ?></option><?php endforeach; ?></select></td></tr>
						<tr><th><?php esc_html_e( 'Schedule', 'core-blueprint-work' ); ?></th><td><label><?php esc_html_e( 'Scheduled', 'core-blueprint-work' ); ?> <input type="date" name="work_item[scheduled_on]"></label> &nbsp; <label><?php esc_html_e( 'Due', 'core-blueprint-work' ); ?> <input type="date" name="work_item[due_on]"></label></td></tr>
						<tr><th><label for="cb-work-item-billing"><?php esc_html_e( 'Billing classification', 'core-blueprint-work' ); ?></label></th><td><select id="cb-work-item-billing" name="work_item[billing_disposition]"><option value=""><?php esc_html_e( 'Not classified', 'core-blueprint-work' ); ?></option><?php foreach ( BillingDisposition::all() as $billing ) : ?><option value="<?php echo esc_attr( $billing ); ?>"><?php echo esc_html( self::humanize( $billing ) ); ?></option><?php endforeach; ?></select><p class="description"><?php esc_html_e( 'This does not mark the Work Item as invoiced or paid.', 'core-blueprint-work' ); ?></p></td></tr>
						<tr><th><?php esc_html_e( 'Assigned users', 'core-blueprint-work' ); ?></th><td><?php self::assignment_picker(); ?></td></tr>
						<tr><th><?php esc_html_e( 'Customer reference', 'core-blueprint-work' ); ?></th><td><?php self::reference_fields( 'work_item', 'customer', 'item' ); ?></td></tr>
						<tr><th><?php esc_html_e( 'External/source relation', 'core-blueprint-work' ); ?></th><td><?php self::reference_fields( 'work_item', 'source', 'source' ); ?><p class="description"><?php esc_html_e( 'Optional generic relation such as helpdesk / ticket / 123. This is a soft reference only.', 'core-blueprint-work' ); ?></p></td></tr>
					</tbody></table>
					<?php submit_button( __( 'Add Work Item', 'core-blueprint-work' ) ); ?>
				</form>
			</details>

			<h2><?php esc_html_e( 'All Work Items', 'core-blueprint-work' ); ?></h2>
			<?php if ( [] === $items ) : ?><p><?php esc_html_e( 'No Work Items yet.', 'core-blueprint-work' ); ?></p><?php else : ?>
			<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Work Item', 'core-blueprint-work' ); ?></th><th><?php esc_html_e( 'Status', 'core-blueprint-work' ); ?></th><th><?php esc_html_e( 'Priority', 'core-blueprint-work' ); ?></th><th><?php esc_html_e( 'Project / Type', 'core-blueprint-work' ); ?></th><th><?php esc_html_e( 'Due', 'core-blueprint-work' ); ?></th><th><?php esc_html_e( 'Billing', 'core-blueprint-work' ); ?></th><th><?php esc_html_e( 'Assigned', 'core-blueprint-work' ); ?></th><th><?php esc_html_e( 'Actions', 'core-blueprint-work' ); ?></th></tr></thead><tbody>
			<?php foreach ( $items as $item ) : ?>
				<tr>
					<td><strong><?php echo esc_html( (string) $item['title'] ); ?></strong><?php $ref = self::reference_label( $item, 'customer_' ); if ( '' !== $ref ) : ?><br><span class="description"><?php echo esc_html( $ref ); ?></span><?php endif; ?></td>
					<td><?php echo esc_html( self::humanize( (string) $item['status'] ) ); ?></td>
					<td><?php echo esc_html( self::humanize( (string) $item['priority'] ) ); ?></td>
					<td><?php echo esc_html( $project_map[ (int) ( $item['project_id'] ?? 0 ) ] ?? '—' ); ?><br><span class="description"><?php echo esc_html( $type_map[ (int) ( $item['work_type_id'] ?? 0 ) ] ?? '—' ); ?></span></td>
					<td><?php echo esc_html( (string) ( $item['due_on'] ?: '—' ) ); ?></td>
					<td><?php echo esc_html( '' !== (string) $item['billing_disposition'] ? self::humanize( (string) $item['billing_disposition'] ) : '—' ); ?></td>
					<td><?php echo esc_html( self::assignment_label( $item['assigned_user_ids'] ?? [] ) ); ?></td>
					<td><?php self::transition_buttons( $item ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody></table><?php endif; ?>
		</div>
		<?php
	}

	/** @param array<string,mixed> $item */
	private static function transition_buttons( array $item ): void {
		$from = (string) ( $item['status'] ?? '' );
		foreach ( WorkItemStatus::transitions_from( $from ) as $to ) {
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin:0 4px 4px 0">
				<input type="hidden" name="action" value="cb_work_transition_work_item">
				<input type="hidden" name="work_item_id" value="<?php echo esc_attr( (string) $item['id'] ); ?>">
				<input type="hidden" name="status" value="<?php echo esc_attr( $to ); ?>">
				<?php wp_nonce_field( 'cb_work_transition_work_item_' . (int) $item['id'] ); ?>
				<button class="button button-small" type="submit"><?php echo esc_html( self::transition_label( $to ) ); ?></button>
			</form>
			<?php
		}
	}

	private static function assignment_picker(): void {
		$users = get_users( [ 'fields' => [ 'ID', 'display_name' ], 'orderby' => 'display_name', 'order' => 'ASC' ] );
		if ( [] === $users ) {
			esc_html_e( 'No users available.', 'core-blueprint-work' );
			return;
		}
		echo '<select name="work_item[assigned_user_ids][]" multiple size="5" style="min-width:280px">';
		foreach ( $users as $user ) {
			echo '<option value="' . esc_attr( (string) $user->ID ) . '">' . esc_html( (string) $user->display_name ) . '</option>';
		}
		echo '</select>';
	}

	/** @param int[] $ids */
	private static function assignment_label( array $ids ): string {
		$labels = [];
		foreach ( $ids as $id ) {
			$user = get_userdata( (int) $id );
			if ( $user ) { $labels[] = (string) $user->display_name; }
		}
		return [] === $labels ? '—' : implode( ', ', $labels );
	}

	private static function reference_fields( string $root, string $prefix, string $id_prefix ): void {
		printf(
			'<input class="regular-text" type="text" name="%1$s[%2$s_provider]" placeholder="provider" aria-label="%3$s"> <input class="regular-text" type="text" name="%1$s[%2$s_type]" placeholder="type" aria-label="%4$s"> <input class="regular-text" type="text" name="%1$s[%2$s_id]" placeholder="id" aria-label="%5$s">',
			esc_attr( $root ),
			esc_attr( $prefix ),
			esc_attr( $id_prefix . ' provider' ),
			esc_attr( $id_prefix . ' type' ),
			esc_attr( $id_prefix . ' id' )
		);
	}

	/** @param array<string,mixed> $row */
	private static function reference_label( array $row, string $prefix ): string {
		$provider = (string) ( $row[ $prefix . 'provider' ] ?? '' );
		$type = (string) ( $row[ $prefix . 'type' ] ?? '' );
		$id = (string) ( $row[ $prefix . 'id' ] ?? '' );
		return '' === $provider ? '' : $provider . ' / ' . $type . ' / ' . $id;
	}

	private static function date_range( string $from, string $until ): string {
		if ( '' === $from && '' === $until ) { return '—'; }
		if ( '' !== $from && '' !== $until ) { return $from . ' → ' . $until; }
		return '' !== $from ? $from . ' →' : '→ ' . $until;
	}

	private static function humanize( string $value ): string {
		return ucwords( str_replace( '_', ' ', $value ) );
	}

	private static function transition_label( string $status ): string {
		return match ( $status ) {
			WorkItemStatus::IN_PROGRESS => __( 'Start', 'core-blueprint-work' ),
			WorkItemStatus::COMPLETED => __( 'Complete', 'core-blueprint-work' ),
			WorkItemStatus::SKIPPED => __( 'Skip', 'core-blueprint-work' ),
			WorkItemStatus::CANCELLED => __( 'Cancel', 'core-blueprint-work' ),
			default => self::humanize( $status ),
		};
	}

	private static function render_notice(): void {
		$notice = isset( $_GET['cb-work-notice'] ) ? sanitize_key( wp_unslash( (string) $_GET['cb-work-notice'] ) ) : '';
		$messages = [
			'project-created' => [ 'success', __( 'Project created.', 'core-blueprint-work' ) ],
			'project-invalid' => [ 'error', __( 'Project could not be created. Check the required fields and date/reference values.', 'core-blueprint-work' ) ],
			'work-item-created' => [ 'success', __( 'Work Item created.', 'core-blueprint-work' ) ],
			'work-item-invalid' => [ 'error', __( 'Work Item could not be created. Check its references, dates and classifications.', 'core-blueprint-work' ) ],
			'work-item-transitioned' => [ 'success', __( 'Work Item status updated.', 'core-blueprint-work' ) ],
			'work-item-transition-invalid' => [ 'error', __( 'That Work Item status transition is not allowed.', 'core-blueprint-work' ) ],
		];
		if ( ! isset( $messages[ $notice ] ) ) { return; }
		[ $class, $message ] = $messages[ $notice ];
		echo '<div class="notice notice-' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}

	private static function storage_notice(): void {
		if ( self::schema_ready() ) { return; }
		echo '<div class="notice notice-warning"><p>' . esc_html__( 'Work storage upgrade is pending. Projects and Work Items remain unavailable until Base reconciles the Work schema.', 'core-blueprint-work' ) . '</p></div>';
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

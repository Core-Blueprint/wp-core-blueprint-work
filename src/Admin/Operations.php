<?php
declare(strict_types=1);

namespace CB\Work\Admin;

use CB\Work\Capabilities;
use CB\Work\Database\Schema;
use CB\Work\Domain\WorkItemStatus;
use CB\Work\Integration\CRMCustomers;
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
		?>
		<div class="wrap cb-work-items-page">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Work Items', 'core-blueprint-work' ); ?></h1>
			<a class="page-title-action" href="<?php echo esc_url( Menu::new_work_item_url( $project_filter ) ); ?>"><?php esc_html_e( 'Add Work Item', 'core-blueprint-work' ); ?></a>
			<hr class="wp-header-end">
			<p class="description"><?php esc_html_e( 'Manage actionable work across customers and Projects. Open a Work Item to edit it in Gutenberg.', 'core-blueprint-work' ); ?></p>
			<?php self::render_notice(); ?>

			<?php if ( $project_filter > 0 ) : ?>
				<?php $project_name = $project_map[ $project_filter ] ?? __( 'Unknown Project', 'core-blueprint-work' ); ?>
				<div class="notice notice-info inline"><p>
					<?php echo esc_html( sprintf( __( 'Showing Work Items for Project: %s', 'core-blueprint-work' ), $project_name ) ); ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Menu::WORK_ITEMS_SLUG ) ); ?>"><?php esc_html_e( 'View all Work Items', 'core-blueprint-work' ); ?></a>
				</p></div>
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
						<tr>
							<td><strong><a href="<?php echo esc_url( Menu::edit_work_item_url( (int) $item['id'] ) ); ?>"><?php echo esc_html( (string) $item['title'] ); ?></a></strong></td>
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

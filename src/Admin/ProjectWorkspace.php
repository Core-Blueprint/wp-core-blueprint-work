<?php
declare(strict_types=1);

namespace CB\Work\Admin;

use CB\Work\Capabilities;
use CB\Work\Domain\WorkItemStatus;
use CB\Work\Integration\CRMCustomers;
use CB\Work\Repository\Projects;
use CB\Work\Repository\ProjectWorkSummary;
use CB\Work\Repository\WorkItems;

defined( 'ABSPATH' ) || exit;

/**
 * Read/operate-first Project workspace.
 *
 * The native Project editor remains authoritative for Project persistence.
 * This screen composes existing Project and Work Item truth into a faster
 * operational view without introducing parallel project semantics.
 */
final class ProjectWorkspace {
	public static function render(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to access this Project.', 'core-blueprint-work' ) );
		}

		$project_id = isset( $_GET['project_id'] ) ? absint( wp_unslash( $_GET['project_id'] ) ) : 0;
		$project    = Projects::get( $project_id );
		if ( null === $project ) {
			wp_die( esc_html__( 'Project not found.', 'core-blueprint-work' ) );
		}

		$counts       = ProjectWorkSummary::counts_by_status( $project_id );
		$active_count = array_sum( array_intersect_key( $counts, array_flip( WorkItemStatus::active() ) ) );
		$items        = WorkItems::for_project( $project_id, 12, WorkItemStatus::active() );
		$customer     = CRMCustomers::label(
			(string) $project['customer_provider'],
			(string) $project['customer_type'],
			(string) $project['customer_id']
		);
		$has_customer_reference = '' !== (string) $project['customer_provider'];
		$edit_url               = get_edit_post_link( $project_id, 'raw' );
		$all_items_url          = add_query_arg(
			[ 'page' => Menu::WORK_ITEMS_SLUG, 'project_id' => $project_id ],
			admin_url( 'admin.php' )
		);
		$description = wp_trim_words( wp_strip_all_tags( (string) $project['description'] ), 42 );
		?>
		<div class="wrap cb-work-project-workspace">
			<header class="cb-work-project-workspace__header">
				<div class="cb-work-project-workspace__heading">
					<p class="cb-work-project-workspace__eyebrow"><?php esc_html_e( 'Project workspace', 'core-blueprint-work' ); ?></p>
					<h1><?php echo esc_html( (string) $project['title'] ); ?></h1>
					<p class="description"><?php echo esc_html( '' !== $description ? $description : __( 'No project description yet.', 'core-blueprint-work' ) ); ?></p>
				</div>
				<div class="cb-work-project-workspace__actions">
					<a class="button button-primary" href="<?php echo esc_url( Menu::new_work_item_url( $project_id ) ); ?>"><?php esc_html_e( 'Add Work Item', 'core-blueprint-work' ); ?></a>
					<a class="button" href="<?php echo esc_url( $all_items_url ); ?>"><?php esc_html_e( 'View all Work Items', 'core-blueprint-work' ); ?></a>
					<?php if ( is_string( $edit_url ) && '' !== $edit_url ) : ?>
						<a class="button" href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit project details', 'core-blueprint-work' ); ?></a>
					<?php endif; ?>
				</div>
			</header>

			<div class="cb-work-project-workspace__metrics" aria-label="<?php esc_attr_e( 'Project work summary', 'core-blueprint-work' ); ?>">
				<?php self::metric( __( 'Active', 'core-blueprint-work' ), $active_count, __( 'Planned, in progress or blocked', 'core-blueprint-work' ) ); ?>
				<?php self::metric( __( 'In progress', 'core-blueprint-work' ), (int) ( $counts[ WorkItemStatus::IN_PROGRESS ] ?? 0 ), __( 'Currently being worked on', 'core-blueprint-work' ) ); ?>
				<?php self::metric( __( 'Blocked', 'core-blueprint-work' ), (int) ( $counts[ WorkItemStatus::BLOCKED ] ?? 0 ), __( 'Needs attention', 'core-blueprint-work' ) ); ?>
				<?php self::metric( __( 'Completed', 'core-blueprint-work' ), (int) ( $counts[ WorkItemStatus::COMPLETED ] ?? 0 ), __( 'Finished Work Items', 'core-blueprint-work' ) ); ?>
			</div>

			<div class="cb-work-project-workspace__layout">
				<main class="cb-work-project-workspace__main">
					<section class="cb-work-project-workspace__panel">
						<div class="cb-work-project-workspace__panel-header">
							<div>
								<h2><?php esc_html_e( 'Active work', 'core-blueprint-work' ); ?></h2>
								<p class="description"><?php echo esc_html( sprintf( _n( '%d active Work Item.', '%d active Work Items.', $active_count, 'core-blueprint-work' ), $active_count ) ); ?></p>
							</div>
							<a class="button button-small" href="<?php echo esc_url( Menu::new_work_item_url( $project_id ) ); ?>"><?php esc_html_e( 'Add Work Item', 'core-blueprint-work' ); ?></a>
						</div>

						<?php if ( [] === $items ) : ?>
							<div class="cb-work-project-workspace__empty">
								<h3><?php esc_html_e( 'No active work', 'core-blueprint-work' ); ?></h3>
								<p class="description"><?php esc_html_e( 'This Project has no planned, in-progress or blocked Work Items.', 'core-blueprint-work' ); ?></p>
								<a class="button button-primary" href="<?php echo esc_url( Menu::new_work_item_url( $project_id ) ); ?>"><?php esc_html_e( 'Create Work Item', 'core-blueprint-work' ); ?></a>
							</div>
						<?php else : ?>
							<div class="cb-work-project-workspace__table-wrap">
								<table class="widefat striped cb-work-project-workspace__work-table">
									<thead><tr>
										<th><?php esc_html_e( 'Work Item', 'core-blueprint-work' ); ?></th>
										<th><?php esc_html_e( 'Status', 'core-blueprint-work' ); ?></th>
										<th><?php esc_html_e( 'Priority', 'core-blueprint-work' ); ?></th>
										<th><?php esc_html_e( 'Due', 'core-blueprint-work' ); ?></th>
										<th><?php esc_html_e( 'Assignees', 'core-blueprint-work' ); ?></th>
									</tr></thead>
									<tbody>
									<?php foreach ( $items as $item ) : ?>
										<tr>
											<td><strong><a href="<?php echo esc_url( Menu::edit_work_item_url( (int) $item['id'] ) ); ?>"><?php echo esc_html( (string) $item['title'] ); ?></a></strong></td>
											<td><?php echo esc_html( self::humanize( (string) $item['status'] ) ); ?></td>
											<td><?php echo esc_html( self::humanize( (string) $item['priority'] ) ); ?></td>
											<td><?php echo esc_html( self::date_label( (string) ( $item['due_on'] ?? '' ) ) ); ?></td>
											<td><?php echo esc_html( self::assignment_label( (array) ( $item['assigned_user_ids'] ?? [] ) ) ); ?></td>
										</tr>
									<?php endforeach; ?>
									</tbody>
								</table>
							</div>
							<?php if ( $active_count > count( $items ) ) : ?>
								<p class="description cb-work-project-workspace__more"><a href="<?php echo esc_url( $all_items_url ); ?>"><?php echo esc_html( sprintf( __( 'View all %d active Work Items', 'core-blueprint-work' ), $active_count ) ); ?></a></p>
							<?php endif; ?>
						<?php endif; ?>
					</section>
				</main>

				<aside class="cb-work-project-workspace__aside">
					<section class="cb-work-project-workspace__panel">
						<h2><?php esc_html_e( 'Project context', 'core-blueprint-work' ); ?></h2>
						<dl class="cb-work-project-workspace__facts">
							<div><dt><?php esc_html_e( 'Customer', 'core-blueprint-work' ); ?></dt><dd><?php echo esc_html( '' !== $customer ? $customer : ( $has_customer_reference ? __( 'Linked customer unavailable', 'core-blueprint-work' ) : __( 'Not linked', 'core-blueprint-work' ) ) ); ?></dd></div>
							<div><dt><?php esc_html_e( 'Starts', 'core-blueprint-work' ); ?></dt><dd><?php echo esc_html( self::date_label( (string) $project['starts_on'] ) ); ?></dd></div>
							<div><dt><?php esc_html_e( 'Due', 'core-blueprint-work' ); ?></dt><dd><?php echo esc_html( self::date_label( (string) $project['due_on'] ) ); ?></dd></div>
							<div><dt><?php esc_html_e( 'Total Work Items', 'core-blueprint-work' ); ?></dt><dd><?php echo esc_html( (string) array_sum( $counts ) ); ?></dd></div>
						</dl>
						<?php if ( is_string( $edit_url ) && '' !== $edit_url ) : ?>
							<p class="cb-work-project-workspace__context-action"><a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit customer, dates and description', 'core-blueprint-work' ); ?></a></p>
						<?php endif; ?>
					</section>
				</aside>
			</div>
		</div>
		<?php
	}

	private static function metric( string $label, int $value, string $description ): void {
		?>
		<div class="cb-work-project-workspace__metric">
			<span class="cb-work-project-workspace__metric-value"><?php echo esc_html( (string) max( 0, $value ) ); ?></span>
			<strong><?php echo esc_html( $label ); ?></strong>
			<span class="description"><?php echo esc_html( $description ); ?></span>
		</div>
		<?php
	}

	private static function date_label( string $date ): string {
		return '' === $date
			? __( 'Not set', 'core-blueprint-work' )
			: mysql2date( (string) get_option( 'date_format' ), $date . ' 00:00:00' );
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

	private static function humanize( string $value ): string {
		return ucwords( str_replace( '_', ' ', $value ) );
	}
}

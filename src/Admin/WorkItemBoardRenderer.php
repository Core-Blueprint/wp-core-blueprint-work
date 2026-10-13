<?php
declare(strict_types=1);

namespace CB\Work\Admin;

use CB\Work\Domain\WorkItemStatus;

defined( 'ABSPATH' ) || exit;

/**
 * B1.3 Board renderer exact-source extraction.
 * Work-owned interim trait; shared presentation helpers remain with Operations.
 */
trait WorkItemBoardRenderer {
	/** @param array<string,mixed> $item @param array<string,mixed> $state */
	private static function render_work_item_board_actions( array $item, array $state ): void {
		$from        = (string) ( $item['status'] ?? '' );
		$transitions = WorkItemStatus::transitions_from( $from );
		if ( [] === $transitions ) {
			return;
		}
		?>
		<details class="cb-work-row-actions__more cb-work-board__more">
			<summary
				class="button-link cb-core-icon-control cb-work-board__more-toggle"
				aria-label="<?php esc_attr_e( 'Actions', 'core-blueprint-work' ); ?>"
				title="<?php esc_attr_e( 'Actions', 'core-blueprint-work' ); ?>"
			>
				<span class="dashicons dashicons-ellipsis" aria-hidden="true"></span>
			</summary>
			<div class="cb-work-row-actions__menu cb-work-board-actions__menu">
				<?php foreach ( $transitions as $to ) : ?>
					<?php self::transition_menu_form( $item, $state, $from, $to ); ?>
				<?php endforeach; ?>
			</div>
		</details>
		<?php
	}

	/**
	 * @param array<int,array<string,mixed>> $items
	 * @param array<int,string> $project_map
	 * @param array<int,string> $type_map
	 * @param array<string,mixed> $state
	 */
	private static function render_work_item_kanban( array $items, array $project_map, array $type_map, array $state ): void {
		$lanes           = array_fill_keys( WorkItemStatus::all(), [] );
		$lane_project_id = max( 0, (int) ( $state['project_id'] ?? 0 ) );
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
				// Only long, creatable lanes need a shortcut before their cards.
				$show_header_add = count( $lane_items ) >= 8
					&& in_array( (string) $status, WorkItemStatus::active(), true );
				?>
				<section class="postbox cb-work-board__lane" data-cb-work-status-lane="<?php echo esc_attr( (string) $status ); ?>">
					<h2 class="hndle">
						<span id="cb-work-board-lane-label-<?php echo esc_attr( (string) $status ); ?>"><?php echo esc_html( $lane_label ); ?> <span class="count">(<?php echo esc_html( (string) count( $lane_items ) ); ?>)</span></span>
						<?php if ( $show_header_add ) : ?>
							<a
								class="cb-work-board__header-add"
								href="<?php echo esc_url( Menu::new_work_item_url( $lane_project_id, (string) $status ) ); ?>"
								data-cb-work-quick-status-label="<?php echo esc_attr( $lane_label ); ?>"
								aria-label="<?php esc_attr_e( 'Add Work Item', 'core-blueprint-work' ); ?>"
								aria-describedby="cb-work-board-lane-label-<?php echo esc_attr( (string) $status ); ?>"
								title="<?php esc_attr_e( 'Add Work Item', 'core-blueprint-work' ); ?>"
							><span aria-hidden="true">+</span></a>
						<?php endif; ?>
					</h2>
					<div
						class="inside cb-work-board__list"
						data-cb-core-reorder-list="<?php echo esc_attr( (string) $status ); ?>"
						data-cb-core-reorder-list-label="<?php echo esc_attr( $lane_label ); ?>"
					>
						<div class="cb-work-board__empty" data-cb-work-board-empty <?php if ( [] !== $lane_items ) : ?>hidden<?php endif; ?>>
							<span class="cb-work-board__empty-title"><?php esc_html_e( 'No Work Items found.', 'core-blueprint-work' ); ?></span>
							<span class="cb-work-board__empty-hint"><?php esc_html_e( 'Drop Work Items here', 'core-blueprint-work' ); ?></span>
						</div>
						<?php foreach ( $lane_items as $item ) :
							$allowed = WorkItemStatus::transitions_from( (string) $status );
							$title   = (string) $item['title'];
							/* translators: %s: Work Item title. */
							$move_label = sprintf( __( 'Move %s to another status', 'core-blueprint-work' ), $title );
							?>
							<?php
							$project = $project_map[ (int) ( $item['project_id'] ?? 0 ) ] ?? __( 'No project', 'core-blueprint-work' );
							$type    = $type_map[ (int) ( $item['work_type_id'] ?? 0 ) ] ?? '';
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
									<div class="cb-work-board__card-heading">
										<h3 class="cb-work-board__title">
											<a href="<?php echo esc_url( Menu::edit_work_item_url( (int) $item['id'] ) ); ?>"><?php echo esc_html( $title ); ?></a>
										</h3>
										<div class="cb-work-board__context">
											<span class="dashicons dashicons-portfolio" aria-hidden="true"></span>
											<span class="cb-work-board__project"><?php echo esc_html( $project ); ?></span>
										</div>
										<?php if ( '' !== $type ) : ?>
											<span class="cb-work-board__type"><?php echo esc_html( $type ); ?></span>
										<?php endif; ?>
									</div>
									<div class="cb-work-board__card-controls">
										<button
											type="button"
											class="button-link cb-core-icon-control cb-core-reorder-handle cb-work-board__drag-handle"
											data-cb-core-reorder-handle
											aria-label="<?php echo esc_attr( $move_label ); ?>"
											title="<?php esc_attr_e( 'Move to another status', 'core-blueprint-work' ); ?>"
										><span class="dashicons dashicons-move" aria-hidden="true"></span></button>
										<?php self::render_work_item_board_actions( $item, $state ); ?>
									</div>
								</div>

								<div class="cb-work-board__signals">
									<span class="cb-work-board__priority"><?php self::render_work_item_priority( (string) ( $item['priority'] ?? '' ) ); ?></span>
									<span class="cb-work-board__due"><?php self::render_work_item_due( (string) ( $item['due_on'] ?? '' ) ); ?></span>
								</div>

								<div class="cb-work-board__footer">
									<span class="cb-work-board__assignee"><?php self::render_work_item_assignee( (array) ( $item['assigned_user_ids'] ?? [] ) ); ?></span>
								</div>

							</article>
						<?php endforeach; ?>
					</div>
					<?php if ( in_array( (string) $status, WorkItemStatus::active(), true ) ) : ?>
						<div class="cb-work-board__lane-footer">
							<a
								class="cb-work-board__add"
								href="<?php echo esc_url( Menu::new_work_item_url( $lane_project_id, (string) $status ) ); ?>"
								data-cb-work-quick-status-label="<?php echo esc_attr( $lane_label ); ?>"
							>
								<span class="cb-work-board__add-icon" aria-hidden="true">+</span>
								<span><?php esc_html_e( 'Add Work Item', 'core-blueprint-work' ); ?></span>
							</a>
						</div>
					<?php endif; ?>
				</section>
			<?php endforeach; ?>
		</div>
		<?php
	}

}

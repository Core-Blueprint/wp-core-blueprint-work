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

/** Exact-source Table presentation mixin; interim B1 boundary. */
trait WorkItemTableRenderer {
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
			data-display-panel-id="cb-work-table-display-panel"
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
					<button class="button button-small" type="button" data-cb-work-bulk-edit-cancel><?php esc_html_e( 'Cancel', 'core-blueprint-work' ); ?></button>
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
						<input type="date" name="bulk_due_on" data-cb-work-bulk-due disabled>
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
						<label><input type="checkbox" name="apply_assignees" value="1" data-cb-work-bulk-edit-control data-cb-work-bulk-assignees-toggle> <span><?php esc_html_e( 'Update assignees', 'core-blueprint-work' ); ?></span></label>
						<div class="cb-work-bulk-assignees is-disabled" data-cb-work-bulk-assignees-state aria-disabled="true" inert>
							<?php Pickers::assignees( 'bulk_assigned_user_ids', 'cb-work-bulk-edit-assignees', [], false ); ?>
						</div>
					</div>
				</div>
				<div class="cb-work-inline-editor__actions">
					<button class="button button-primary" type="submit" data-cb-work-bulk-edit-submit disabled><?php esc_html_e( 'Update selected', 'core-blueprint-work' ); ?></button>
					<button class="button" type="button" data-cb-work-bulk-edit-cancel><?php esc_html_e( 'Cancel', 'core-blueprint-work' ); ?></button>
				</div>
			</form>

			<table
				class="widefat cb-work-items-table"
				data-cb-work-items-table
				data-cb-work-density="<?php echo esc_attr( (string) $preferences['density'] ); ?>"
				data-cb-work-alternating="<?php echo ! empty( $preferences['alternating_rows'] ) ? '1' : '0'; ?>"
			>
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
				<?php $row_index = 0; ?>
				<?php foreach ( $items as $item ) :
					$is_alternate = 1 === ( $row_index % 2 );
					$row_index++;
					?>
					<tr data-cb-work-table-row class="<?php echo $is_alternate ? 'is-alternate' : ''; ?>">
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
					<input type="hidden" name="expected_status" value="<?php echo esc_attr( $status ); ?>">
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
							<?php Pickers::assignees( 'work_item[assigned_user_ids]', 'cb-work-quick-assignees-' . $id, (array) ( $item['assigned_user_ids'] ?? [] ), false ); ?>
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

}

<?php
declare(strict_types=1);

namespace CB\Work\Admin;

use CB\Work\Domain\WorkContext;
use CB\Work\Domain\WorkItemStatus;
use CoreBlueprint\Core\UI\StateBadge;

defined( 'ABSPATH' ) || exit;

/**
 * B1.2 List renderer exact-source extraction.
 * Kept as a Work-owned trait to preserve current private presentation helpers
 * without changing public Operations API, markup, actions or modal contracts.
 */
trait WorkItemListRenderer {
	/**
	 * @param array<int,array<string,mixed>> $items
	 * @param array<int,string> $project_map
	 * @param array<int,string> $service_map
	 * @param array<int,string> $type_map
	 * @param array<string,mixed> $state
	 */
	private static function render_work_item_list( array $items, array $project_map, array $service_map, array $type_map, array $state, array $preferences ): void {
		$preferences     = WorkItemListPreferences::normalize( $preferences );
		$project_filter = (int) ( $state['project_id'] ?? 0 );
		$grouped        = WorkItemListPreferences::GROUP_PROJECT === (string) $preferences['group_by'] && $project_filter <= 0;
		?>
		<div
			class="cb-work-items-list cb-work-items-list--golden <?php echo $grouped ? 'is-grouped' : 'is-ungrouped'; ?>"
			data-cb-work-items-list
			data-cb-work-list-grouping="<?php echo esc_attr( $grouped ? WorkItemListPreferences::GROUP_PROJECT : WorkItemListPreferences::GROUP_NONE ); ?>"
		>
			<?php if ( $grouped ) : ?>
				<?php foreach ( self::work_item_project_groups( $items, $project_map, (string) $preferences['project_order'] ) as $group ) : ?>
					<?php
					$group_token = (int) $group['id'] > 0 ? 'project-' . (int) $group['id'] : 'none';
					$group_items_id = 'cb-work-list-group-items-' . $group_token;
					?>
					<section class="cb-work-list-group" data-cb-work-list-group>
						<div class="cb-work-list-group__header">
							<h2 class="cb-work-list-group__title">
								<button
									type="button"
									class="cb-work-list-group__toggle"
									data-cb-work-list-group-toggle
									aria-expanded="true"
									aria-controls="<?php echo esc_attr( $group_items_id ); ?>"
								>
									<span class="dashicons dashicons-arrow-right-alt2 cb-work-list-group__chevron" aria-hidden="true"></span>
									<span><?php echo esc_html( (string) $group['label'] ); ?></span>
								</button>
							</h2>
							<span class="cb-work-list-group__count">
								<span class="screen-reader-text"><?php esc_html_e( 'Work Items', 'core-blueprint-work' ); ?>:</span>
								<?php echo esc_html( (string) count( $group['items'] ) ); ?>
							</span>
						</div>
						<div id="<?php echo esc_attr( $group_items_id ); ?>" class="cb-work-list-group__items" data-cb-work-list-group-items>
							<?php foreach ( $group['items'] as $item ) : ?>
								<?php self::render_work_item_list_item( $item, $project_map, $service_map, $type_map, $state, false ); ?>
							<?php endforeach; ?>
						</div>
					</section>
				<?php endforeach; ?>
			<?php else : ?>
				<?php foreach ( $items as $item ) : ?>
					<?php self::render_work_item_list_item( $item, $project_map, $service_map, $type_map, $state, $project_filter <= 0 ); ?>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * @param array<int,array<string,mixed>> $items
	 * @param array<int,string> $project_map
	 * @return list<array{id:int,label:string,items:list<array<string,mixed>>}>
	 */
	private static function work_item_project_groups( array $items, array $project_map, string $order ): array {
		$groups = [];
		foreach ( $items as $item ) {
			$project_id = (int) ( $item['project_id'] ?? 0 );
			$has_project = $project_id > 0 && isset( $project_map[ $project_id ] );
			$key   = $has_project ? 'project-' . $project_id : 'none';
			$label = $has_project ? (string) $project_map[ $project_id ] : __( 'No project', 'core-blueprint-work' );
			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = [
					'id'    => $has_project ? $project_id : 0,
					'label' => $label,
					'items' => [],
				];
			}
			$groups[ $key ]['items'][] = $item;
		}

		$no_project = $groups['none'] ?? null;
		unset( $groups['none'] );

		$direction = WorkItemListPreferences::ORDER_DESC === $order ? -1 : 1;
		uasort(
			$groups,
			static function ( array $left, array $right ) use ( $direction ): int {
				$comparison = strnatcasecmp( (string) $left['label'], (string) $right['label'] );
				if ( 0 === $comparison ) {
					$comparison = (int) $left['id'] <=> (int) $right['id'];
				}
				return $comparison * $direction;
			}
		);

		$ordered = array_values( $groups );
		if ( is_array( $no_project ) ) {
			$ordered[] = $no_project;
		}
		return $ordered;
	}

	/**
	 * @param array<string,mixed> $item
	 * @param array<int,string> $project_map
	 * @param array<int,string> $service_map
	 * @param array<int,string> $type_map
	 * @param array<string,mixed> $state
	 */
	private static function render_work_item_list_item( array $item, array $project_map, array $service_map, array $type_map, array $state, bool $show_project_context ): void {
		$item_id    = (int) $item['id'];
		$title      = (string) $item['title'];
		$project    = $project_map[ (int) ( $item['project_id'] ?? 0 ) ] ?? '';
		$type       = $type_map[ (int) ( $item['work_type_id'] ?? 0 ) ] ?? '';
		$status     = (string) ( $item['status'] ?? '' );
		$title_id   = 'cb-work-list-title-' . $item_id;
		$details_id = 'cb-work-list-details-' . $item_id;
		/* translators: %s: Work Item title. */
		$details_label = sprintf( __( 'Details for %s', 'core-blueprint-work' ), $title );
		?>
		<article
			class="cb-work-list-item"
			data-cb-work-list-item
			data-cb-work-item-id="<?php echo esc_attr( (string) $item_id ); ?>"
			aria-labelledby="<?php echo esc_attr( $title_id ); ?>"
		>
			<div class="cb-work-list-item__main">
				<button
					type="button"
					class="cb-work-list-item__toggle"
					data-cb-work-list-item-toggle
					aria-expanded="false"
					aria-controls="<?php echo esc_attr( $details_id ); ?>"
					aria-label="<?php echo esc_attr( $details_label ); ?>"
				>
					<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
				</button>
				<div class="cb-work-list-item__copy">
					<h3 id="<?php echo esc_attr( $title_id ); ?>" class="cb-work-list-item__title">
						<a href="<?php echo esc_url( Menu::edit_work_item_url( $item_id ) ); ?>"><?php echo esc_html( $title ); ?></a>
					</h3>
					<?php if ( ( $show_project_context && '' !== $project ) || '' !== $type ) : ?>
						<div class="cb-work-list-item__context">
							<?php if ( $show_project_context && '' !== $project ) : ?>
								<span><?php echo esc_html( $project ); ?></span>
							<?php endif; ?>
							<?php if ( $show_project_context && '' !== $project && '' !== $type ) : ?>
								<span class="cb-work-list-item__context-divider" aria-hidden="true">·</span>
							<?php endif; ?>
							<?php if ( '' !== $type ) : ?>
								<span class="cb-work-list-item__type"><?php echo esc_html( $type ); ?></span>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<div class="cb-work-list-item__meta">
				<span class="cb-work-list-item__status">
					<?php
					echo StateBadge::render(
						self::humanize( $status ),
						[
							'variant' => self::work_item_status_badge_variant( $status ),
							'class'   => 'cb-work-status-badge',
						]
					);
					?>
				</span>
				<span class="cb-work-list-item__priority"><?php self::render_work_item_priority( (string) ( $item['priority'] ?? '' ) ); ?></span>
				<span class="cb-work-list-item__due"><?php self::render_work_item_due( (string) ( $item['due_on'] ?? '' ) ); ?></span>
				<span class="cb-work-list-item__assignee"><?php self::render_work_item_assignee( (array) ( $item['assigned_user_ids'] ?? [] ) ); ?></span>
			</div>

			<?php self::render_work_item_list_actions( $item, $state ); ?>

			<div
				id="<?php echo esc_attr( $details_id ); ?>"
				class="cb-work-list-item__details"
				data-cb-work-list-item-details
				hidden
			>
				<?php self::render_work_item_list_details( $item, $service_map ); ?>
			</div>
		</article>
		<?php
	}

	/**
	 * @param array<string,mixed> $item
	 * @param array<int,string> $service_map
	 */
	private static function render_work_item_list_details( array $item, array $service_map ): void {
		$description = trim( (string) ( $item['description'] ?? '' ) );
		$service     = $service_map[ (int) ( $item['service_id'] ?? 0 ) ] ?? '—';
		$context     = WorkContext::sanitize( $item['work_context'] ?? '' );
		$billing     = sanitize_key( (string) ( $item['billing_disposition'] ?? '' ) );
		$estimate    = max( 0, (int) ( $item['estimated_minutes'] ?? 0 ) );
		?>
		<?php if ( '' !== $description ) : ?>
			<div class="cb-work-list-item__description">
				<span class="cb-work-list-item__detail-label"><?php esc_html_e( 'Description', 'core-blueprint-work' ); ?></span>
				<div class="cb-work-list-item__description-content"><?php echo wp_kses_post( $description ); ?></div>
			</div>
		<?php endif; ?>

		<div class="cb-work-list-item__facts">
			<div class="cb-work-list-item__fact">
				<span class="cb-work-list-item__detail-label"><?php esc_html_e( 'Customer', 'core-blueprint-work' ); ?></span>
				<span class="cb-work-list-item__detail-value"><?php echo esc_html( self::customer_label( $item ) ); ?></span>
			</div>
			<div class="cb-work-list-item__fact">
				<span class="cb-work-list-item__detail-label"><?php esc_html_e( 'Service', 'core-blueprint-work' ); ?></span>
				<span class="cb-work-list-item__detail-value"><?php echo esc_html( $service ); ?></span>
			</div>
			<div class="cb-work-list-item__fact">
				<span class="cb-work-list-item__detail-label"><?php esc_html_e( 'Work context', 'core-blueprint-work' ); ?></span>
				<span class="cb-work-list-item__detail-value"><?php echo esc_html( '' !== $context ? self::humanize( $context ) : '—' ); ?></span>
			</div>
			<div class="cb-work-list-item__fact">
				<span class="cb-work-list-item__detail-label"><?php esc_html_e( 'Billing', 'core-blueprint-work' ); ?></span>
				<span class="cb-work-list-item__detail-value"><?php echo esc_html( '' !== $billing ? self::humanize( $billing ) : '—' ); ?></span>
			</div>
			<div class="cb-work-list-item__fact">
				<span class="cb-work-list-item__detail-label"><?php esc_html_e( 'Scheduled', 'core-blueprint-work' ); ?></span>
				<span class="cb-work-list-item__detail-value"><?php echo esc_html( self::work_item_date_label( (string) ( $item['scheduled_on'] ?? '' ) ) ); ?></span>
			</div>
			<div class="cb-work-list-item__fact">
				<span class="cb-work-list-item__detail-label"><?php esc_html_e( 'Estimate', 'core-blueprint-work' ); ?></span>
				<span class="cb-work-list-item__detail-value">
					<?php echo $estimate > 0 ? esc_html( (string) $estimate . ' ' . __( 'min', 'core-blueprint-work' ) ) : esc_html( '—' ); ?>
				</span>
			</div>
			<div class="cb-work-list-item__fact">
				<span class="cb-work-list-item__detail-label"><?php esc_html_e( 'Assignees', 'core-blueprint-work' ); ?></span>
				<span class="cb-work-list-item__detail-value"><?php echo esc_html( self::assignment_label( (array) ( $item['assigned_user_ids'] ?? [] ) ) ); ?></span>
			</div>
		</div>

		<div class="cb-work-list-item__detail-actions">
			<a class="button" href="<?php echo esc_url( Menu::edit_work_item_url( (int) $item['id'] ) ); ?>"><?php esc_html_e( 'Edit Work Item', 'core-blueprint-work' ); ?></a>
		</div>
		<?php
	}

	/** @param array<string,mixed> $item @param array<string,mixed> $state */
	private static function render_work_item_list_actions( array $item, array $state ): void {
		$from        = (string) ( $item['status'] ?? '' );
		$transitions = WorkItemStatus::transitions_from( $from );
		$primary     = match ( $from ) {
			WorkItemStatus::PLANNED     => WorkItemStatus::IN_PROGRESS,
			WorkItemStatus::IN_PROGRESS => WorkItemStatus::COMPLETED,
			WorkItemStatus::BLOCKED     => WorkItemStatus::IN_PROGRESS,
			WorkItemStatus::COMPLETED   => WorkItemStatus::IN_PROGRESS,
			default                     => '',
		};
		if ( '' !== $primary && ! in_array( $primary, $transitions, true ) ) {
			$primary = '';
		}
		$overflow = array_values(
			array_filter(
				$transitions,
				static fn ( string $status ): bool => $status !== $primary
			)
		);
		if ( '' === $primary && [] === $overflow ) {
			return;
		}
		?>
		<div class="cb-work-row-actions cb-work-list-item__actions">
			<?php if ( '' !== $primary ) : ?>
				<?php self::transition_icon_form( $item, $state, $from, $primary ); ?>
			<?php endif; ?>
			<?php if ( [] !== $overflow ) : ?>
				<details class="cb-work-row-actions__more">
					<summary
						class="button button-small cb-work-row-action cb-work-row-action--overflow"
						aria-label="<?php esc_attr_e( 'Actions', 'core-blueprint-work' ); ?>"
						data-cb-work-tooltip="<?php esc_attr_e( 'Actions', 'core-blueprint-work' ); ?>"
					>
						<span class="dashicons dashicons-ellipsis" aria-hidden="true"></span>
					</summary>
					<div class="cb-work-row-actions__menu cb-work-list-actions__menu">
						<?php foreach ( $overflow as $to ) : ?>
							<?php self::transition_menu_form( $item, $state, $from, $to ); ?>
						<?php endforeach; ?>
					</div>
				</details>
			<?php endif; ?>
		</div>
		<?php
	}
}

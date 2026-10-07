<?php
declare(strict_types=1);

namespace CB\Work\Admin;

use CB\Work\Domain\WorkItemStatus;

defined( 'ABSPATH' ) || exit;

final class WorkItemCalendarView {
	/**
	 * @param array<int,array<string,mixed>> $items
	 * @param array<int,string> $project_map
	 * @param array<string,mixed> $state
	 */
	public static function render( array $items, array $project_map, array $state ): void {
		$month = (string) ( $state['calendar_month'] ?? '' );
		$first = \DateTimeImmutable::createFromFormat( '!Y-m-d', $month . '-01' );
		if ( ! $first ) {
			return;
		}

		$entries_by_date = self::entries_by_date( $items, $month );
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

		<div class="cb-work-calendar" data-cb-work-calendar data-close-label="<?php echo esc_attr__( 'Close', 'core-blueprint-work' ); ?>">
			<table class="widefat cb-work-items-calendar">
				<thead><tr>
					<?php foreach ( $weekdays as $weekday ) : ?>
						<th scope="col"><?php echo esc_html( $weekday ); ?></th>
					<?php endforeach; ?>
				</tr></thead>
				<tbody><tr>
				<?php $cell = 0; ?>
				<?php for ( $empty = 0; $empty < $leading_cells; $empty++ ) : ?>
					<td class="cb-work-calendar-empty" aria-hidden="true"></td>
					<?php $cell++; ?>
				<?php endfor; ?>

				<?php for ( $day = 1; $day <= $days_in_month; $day++ ) : ?>
					<?php
					$date        = $month . '-' . str_pad( (string) $day, 2, '0', STR_PAD_LEFT );
					$day_entries = $entries_by_date[ $date ] ?? [];
					$counts      = self::relationship_counts( $day_entries );
					?>
					<td class="cb-work-calendar-day">
						<strong class="cb-work-calendar-day__number"><?php echo esc_html( (string) $day ); ?></strong>
						<?php if ( [] !== $day_entries ) : ?>
							<button
								type="button"
								class="button-link cb-work-calendar-day__trigger"
								data-cb-work-calendar-day-open
								data-template-id="<?php echo esc_attr( self::template_id( $date ) ); ?>"
								data-modal-title="<?php echo esc_attr( self::day_label( $date ) ); ?>"
							>
								<span class="cb-work-calendar-day__count"><?php echo esc_html( (string) count( $day_entries ) ); ?> <?php esc_html_e( 'Work Items', 'core-blueprint-work' ); ?></span>
								<span class="cb-work-calendar-day__summary">
									<?php if ( $counts['scheduled'] > 0 ) : ?>
										<?php esc_html_e( 'Scheduled', 'core-blueprint-work' ); ?>: <?php echo esc_html( (string) $counts['scheduled'] ); ?>
									<?php endif; ?>
									<?php if ( $counts['scheduled'] > 0 && $counts['due'] > 0 ) : ?> · <?php endif; ?>
									<?php if ( $counts['due'] > 0 ) : ?>
										<?php esc_html_e( 'Due', 'core-blueprint-work' ); ?>: <?php echo esc_html( (string) $counts['due'] ); ?>
									<?php endif; ?>
								</span>
							</button>
						<?php endif; ?>
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
				</tr></tbody>
			</table>

			<?php foreach ( $entries_by_date as $date => $day_entries ) : ?>
				<?php self::render_day_template( (string) $date, $day_entries, $project_map ); ?>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/** @param array<int,array<string,mixed>> $items */
	private static function entries_by_date( array $items, string $month ): array {
		$entries = [];
		foreach ( $items as $item ) {
			$scheduled_on       = (string) ( $item['scheduled_on'] ?? '' );
			$due_on             = (string) ( $item['due_on'] ?? '' );
			$scheduled_in_month = str_starts_with( $scheduled_on, $month . '-' );
			$due_in_month       = str_starts_with( $due_on, $month . '-' );

			if ( $scheduled_in_month ) {
				$entries[ $scheduled_on ][] = [
					'kind'      => 'scheduled',
					'item'      => $item,
					'due_today' => '' !== $due_on && $due_on === $scheduled_on,
				];
			}
			if ( $due_in_month && ( ! $scheduled_in_month || $due_on !== $scheduled_on ) ) {
				$entries[ $due_on ][] = [
					'kind'      => 'due',
					'item'      => $item,
					'due_today' => true,
				];
			}
		}
		return $entries;
	}

	/** @param array<int,array<string,mixed>> $entries */
	private static function relationship_counts( array $entries ): array {
		$scheduled = 0;
		$due       = 0;
		foreach ( $entries as $entry ) {
			if ( 'scheduled' === (string) $entry['kind'] ) {
				$scheduled++;
			}
			if ( 'due' === (string) $entry['kind'] || ! empty( $entry['due_today'] ) ) {
				$due++;
			}
		}
		return [ 'scheduled' => $scheduled, 'due' => $due ];
	}

	/** @param array<int,array<string,mixed>> $day_entries @param array<int,string> $project_map */
	private static function render_day_template( string $date, array $day_entries, array $project_map ): void {
		$active_lanes   = array_fill_keys( WorkItemStatus::active(), [] );
		$closed_entries = [];

		foreach ( $day_entries as $entry ) {
			$status = (string) ( $entry['item']['status'] ?? WorkItemStatus::PLANNED );
			if ( ! WorkItemStatus::is_valid( $status ) ) {
				$status = WorkItemStatus::PLANNED;
			}
			if ( isset( $active_lanes[ $status ] ) ) {
				$active_lanes[ $status ][] = $entry;
			} else {
				$closed_entries[] = $entry;
			}
		}
		?>
		<template id="<?php echo esc_attr( self::template_id( $date ) ); ?>">
			<div class="cb-work-day-modal">
				<p class="cb-work-day-modal__summary"><?php echo esc_html( (string) count( $day_entries ) ); ?> <?php esc_html_e( 'Work Items', 'core-blueprint-work' ); ?></p>

				<div
					class="cb-work-day-board cb-work-board"
					data-cb-work-board-reorder
					data-cb-core-reorder
					data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
					data-action="<?php echo esc_attr( WorkItemBoardActions::ACTION ); ?>"
					data-nonce="<?php echo esc_attr( wp_create_nonce( WorkItemBoardActions::NONCE_ACTION ) ); ?>"
					data-error="<?php echo esc_attr__( 'The Work Item status could not be updated.', 'core-blueprint-work' ); ?>"
				>
					<?php foreach ( $active_lanes as $status => $lane_entries ) : ?>
						<?php self::render_active_lane( (string) $status, $lane_entries, $project_map ); ?>
					<?php endforeach; ?>
				</div>

				<?php if ( [] !== $closed_entries ) : ?>
					<details class="cb-work-day-modal__closed">
						<summary><?php esc_html_e( 'Show closed', 'core-blueprint-work' ); ?> (<?php echo esc_html( (string) count( $closed_entries ) ); ?>)</summary>
						<ul>
						<?php foreach ( $closed_entries as $entry ) :
							$item = $entry['item'];
							?>
							<li><a href="<?php echo esc_url( Menu::edit_work_item_url( (int) $item['id'] ) ); ?>"><?php echo esc_html( (string) $item['title'] ); ?></a> <span class="description">— <?php echo esc_html( self::humanize( (string) $item['status'] ) ); ?></span></li>
						<?php endforeach; ?>
						</ul>
					</details>
				<?php endif; ?>
			</div>
		</template>
		<?php
	}

	/** @param array<int,array<string,mixed>> $lane_entries @param array<int,string> $project_map */
	private static function render_active_lane( string $status, array $lane_entries, array $project_map ): void {
		$lane_label = self::humanize( $status );
		?>
		<section class="postbox cb-work-board__lane" data-cb-work-status-lane="<?php echo esc_attr( $status ); ?>">
			<h3 class="hndle"><span><?php echo esc_html( $lane_label ); ?> <span class="count">(<?php echo esc_html( (string) count( $lane_entries ) ); ?>)</span></span></h3>
			<div
				class="inside cb-work-board__list"
				data-cb-core-reorder-list="<?php echo esc_attr( $status ); ?>"
				data-cb-core-reorder-list-label="<?php echo esc_attr( $lane_label ); ?>"
			>
				<p class="description" data-cb-work-board-empty <?php if ( [] !== $lane_entries ) : ?>hidden<?php endif; ?>><?php esc_html_e( 'No Work Items', 'core-blueprint-work' ); ?></p>
				<?php foreach ( $lane_entries as $entry ) :
					$item       = $entry['item'];
					$title      = (string) $item['title'];
					$allowed    = WorkItemStatus::transitions_from( $status );
					$relation   = self::relationship_label( $entry );
					/* translators: %s: Work Item title. */
					$move_label = sprintf( __( 'Move %s to another status', 'core-blueprint-work' ), $title );
					?>
					<article
						class="card cb-work-day-card"
						data-cb-core-reorder-item="work-item:<?php echo esc_attr( (string) $item['id'] ); ?>"
						data-cb-core-reorder-label="<?php echo esc_attr( $title ); ?>"
						data-cb-work-item-id="<?php echo esc_attr( (string) $item['id'] ); ?>"
						data-cb-work-status="<?php echo esc_attr( $status ); ?>"
						data-cb-work-allowed-statuses="<?php echo esc_attr( implode( ',', $allowed ) ); ?>"
					>
						<div class="cb-work-board__card-header">
							<div>
								<span class="cb-work-day-card__relation"><?php echo esc_html( $relation ); ?></span>
								<h4><a href="<?php echo esc_url( Menu::edit_work_item_url( (int) $item['id'] ) ); ?>"><?php echo esc_html( $title ); ?></a></h4>
							</div>
							<button
								type="button"
								class="button-link cb-core-icon-control cb-core-reorder-handle cb-work-board__drag-handle"
								data-cb-core-reorder-handle
								aria-label="<?php echo esc_attr( $move_label ); ?>"
								title="<?php esc_attr_e( 'Move to another status', 'core-blueprint-work' ); ?>"
							><span class="dashicons dashicons-move" aria-hidden="true"></span></button>
						</div>
						<p class="cb-work-day-card__meta"><?php echo esc_html( self::humanize( (string) $item['priority'] ) ); ?> · <?php echo esc_html( $project_map[ (int) ( $item['project_id'] ?? 0 ) ] ?? '—' ); ?></p>
					</article>
				<?php endforeach; ?>
			</div>
		</section>
		<?php
	}

	/** @param array<string,mixed> $entry */
	private static function relationship_label( array $entry ): string {
		if ( 'due' === (string) $entry['kind'] ) {
			return __( 'Due', 'core-blueprint-work' );
		}
		if ( ! empty( $entry['due_today'] ) ) {
			return __( 'Scheduled', 'core-blueprint-work' ) . ' · ' . __( 'Due', 'core-blueprint-work' );
		}
		return __( 'Scheduled', 'core-blueprint-work' );
	}

	private static function template_id( string $date ): string {
		return 'cb-work-calendar-day-' . $date;
	}

	private static function day_label( string $date ): string {
		$value = \DateTimeImmutable::createFromFormat( '!Y-m-d', $date );
		return $value ? wp_date( 'l, j F Y', $value->getTimestamp() ) : $date;
	}

	/** @param array<string,mixed> $state @param array<string,mixed> $overrides */
	private static function work_items_url( array $state, array $overrides = [] ): string {
		return add_query_arg( WorkItemViewState::query_args( $state, $overrides ), admin_url( 'admin.php' ) );
	}


	private static function humanize( string $value ): string {
		return ucwords( str_replace( '_', ' ', $value ) );
	}

	private function __construct() {}
}

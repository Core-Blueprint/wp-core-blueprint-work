<?php
declare(strict_types=1);

namespace CB\Work\Admin;

use CB\Work\Domain\WorkItemPriority;
use CB\Work\Domain\WorkItemStatus;

defined( 'ABSPATH' ) || exit;

final class WorkItemCalendarView {
	/**
	 * @param array<int,array<string,mixed>> $items
	 * @param array<int,string> $project_map
	 * @param array<int,string> $type_map
	 * @param array<string,mixed> $state
	 */
	public static function render( array $items, array $project_map, array $type_map, array $state ): void {
		$month = (string) ( $state['calendar_month'] ?? '' );
		$first = \DateTimeImmutable::createFromFormat( '!Y-m-d', $month . '-01' );
		if ( ! $first ) {
			return;
		}

		$entries_by_date = self::entries_by_date( $items, $month );
		$previous_month = $first->modify( '-1 month' )->format( 'Y-m' );
		$next_month     = $first->modify( '+1 month' )->format( 'Y-m' );
		$current_month  = current_time( 'Y-m' );
		$today_date     = current_time( 'Y-m-d' );
		$month_label    = wp_date( 'F Y', $first->setTime( 12, 0 )->getTimestamp() );
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
				<strong class="cb-work-calendar-navigation__month"><?php echo esc_html( $month_label ); ?></strong>
				<div class="cb-work-calendar-navigation__actions">
					<a class="button" href="<?php echo esc_url( self::work_items_url( $state, [ 'calendar_month' => $previous_month, 'page' => 1 ] ) ); ?>"><?php esc_html_e( 'Previous month', 'core-blueprint-work' ); ?></a>
					<a class="button" href="<?php echo esc_url( self::work_items_url( $state, [ 'calendar_month' => $current_month, 'page' => 1 ] ) ); ?>"><?php esc_html_e( 'Today', 'core-blueprint-work' ); ?></a>
					<a class="button" href="<?php echo esc_url( self::work_items_url( $state, [ 'calendar_month' => $next_month, 'page' => 1 ] ) ); ?>"><?php esc_html_e( 'Next month', 'core-blueprint-work' ); ?></a>
				</div>
			</div>
		</nav>

		<div class="cb-work-calendar" data-cb-work-calendar data-close-label="<?php echo esc_attr__( 'Close', 'core-blueprint-work' ); ?>">
			<div class="cb-work-calendar__viewport cb-scrollbar" role="region" tabindex="0" aria-label="<?php echo esc_attr( $month_label ); ?>">
			<table class="widefat cb-work-items-calendar">
				<caption class="screen-reader-text"><?php echo esc_html( $month_label ); ?></caption>
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
					<td class="cb-work-calendar-day<?php echo $date === $today_date ? ' cb-work-calendar-day--today' : ''; ?>">
						<time class="cb-work-calendar-day__number" datetime="<?php echo esc_attr( $date ); ?>"<?php if ( $date === $today_date ) : ?> aria-current="date"<?php endif; ?>><?php echo esc_html( (string) $day ); ?></time>
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
			</div>

			<?php foreach ( $entries_by_date as $date => $day_entries ) : ?>
				<?php self::render_day_template( (string) $date, $day_entries, $project_map, $type_map ); ?>
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

	/** @param array<int,array<string,mixed>> $day_entries @param array<int,string> $project_map @param array<int,string> $type_map */
	private static function render_day_template( string $date, array $day_entries, array $project_map, array $type_map ): void {
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

				<div class="cb-work-day-board__viewport cb-scrollbar" role="region" tabindex="0" aria-label="<?php echo esc_attr( self::day_label( $date ) ); ?>">
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
						<?php self::render_active_lane( (string) $status, $lane_entries, $project_map, $type_map ); ?>
					<?php endforeach; ?>
					</div>
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

	/** @param array<int,array<string,mixed>> $lane_entries @param array<int,string> $project_map @param array<int,string> $type_map */
	private static function render_active_lane( string $status, array $lane_entries, array $project_map, array $type_map ): void {
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
					<?php
					$project = $project_map[ (int) ( $item['project_id'] ?? 0 ) ] ?? __( 'No project', 'core-blueprint-work' );
					$type    = $type_map[ (int) ( $item['work_type_id'] ?? 0 ) ] ?? '';
					?>
					<article
						class="card cb-work-board__card cb-work-day-card"
						data-cb-core-reorder-item="work-item:<?php echo esc_attr( (string) $item['id'] ); ?>"
						data-cb-core-reorder-label="<?php echo esc_attr( $title ); ?>"
						data-cb-work-item-id="<?php echo esc_attr( (string) $item['id'] ); ?>"
						data-cb-work-status="<?php echo esc_attr( $status ); ?>"
						data-cb-work-allowed-statuses="<?php echo esc_attr( implode( ',', $allowed ) ); ?>"
					>
						<div class="cb-work-board__card-header">
							<div class="cb-work-board__card-heading">
								<span class="cb-work-day-card__relation"><?php echo esc_html( $relation ); ?></span>
								<h4 class="cb-work-board__title">
									<a href="<?php echo esc_url( Menu::edit_work_item_url( (int) $item['id'] ) ); ?>"><?php echo esc_html( $title ); ?></a>
								</h4>
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
							</div>
						</div>
						<div class="cb-work-day-card__signals">
							<span><?php self::render_priority( (string) ( $item['priority'] ?? '' ) ); ?></span>
							<span><?php self::render_assignee( (array) ( $item['assigned_user_ids'] ?? [] ) ); ?></span>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		</section>
		<?php
	}

	/** Render the same semantic priority signal used by Board cards. */
	private static function render_priority( string $priority ): void {
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

	/** @param int[] $ids */
	private static function render_assignee( array $ids ): void {
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
		return $value ? wp_date( 'l, j F Y', $value->setTime( 12, 0 )->getTimestamp() ) : $date;
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

<?php
declare(strict_types=1);

namespace CB\Work\Admin;

use CB\Work\Capabilities;
use CB\Work\Repository\Projects;
use CB\Work\Repository\WorkItems;

defined( 'ABSPATH' ) || exit;

/** Daily operational cockpit for Work managers. */
final class Overview {
	public static function render(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Work.', 'core-blueprint-work' ) );
		}

		$snapshot = DailyOverview::snapshot( get_current_user_id() );
		$projects = [];
		foreach ( Projects::all( 500 ) as $project ) {
			$projects[ (int) $project['id'] ] = (string) $project['title'];
		}
		$today = current_time( 'Y-m-d' );
		$date  = \DateTimeImmutable::createFromFormat( '!Y-m-d', $today, wp_timezone() );
		if ( ! $date ) {
			$date = new \DateTimeImmutable( 'today', wp_timezone() );
		}
		$yesterday = $date->modify( '-1 day' )->format( 'Y-m-d' );
		$tomorrow  = $date->modify( '+1 day' )->format( 'Y-m-d' );
		$week_end  = $date->modify( '+7 days' )->format( 'Y-m-d' );
		?>
		<div class="wrap cb-work-daily-overview">
			<header class="cb-work-daily-overview__header">
				<div>
					<p class="cb-work-daily-overview__eyebrow"><?php esc_html_e( 'Daily overview', 'core-blueprint-work' ); ?></p>
					<h1><?php esc_html_e( 'Work', 'core-blueprint-work' ); ?></h1>
					<p class="description"><?php echo esc_html( wp_date( (string) get_option( 'date_format' ), $date->getTimestamp(), wp_timezone() ) ); ?> · <?php esc_html_e( 'What needs attention now.', 'core-blueprint-work' ); ?></p>
				</div>
				<div class="cb-work-daily-overview__actions">
					<a class="button button-primary" href="<?php echo esc_url( Menu::new_work_item_url() ); ?>"><?php esc_html_e( 'Add Work Item', 'core-blueprint-work' ); ?></a>
					<a class="button" href="<?php echo esc_url( Menu::time_url() ); ?>"><?php esc_html_e( 'Open Time', 'core-blueprint-work' ); ?></a>
				</div>
			</header>

			<div class="cb-work-daily-overview__focus">
				<?php self::focus_card( __( 'Today', 'core-blueprint-work' ), count( (array) $snapshot['today'] ), null ); ?>
				<?php self::focus_card( __( 'Overdue', 'core-blueprint-work' ), count( (array) $snapshot['overdue'] ), self::work_items_url( [ 'status' => 'active', 'due_to' => $yesterday ] ), true ); ?>
				<?php self::focus_card( __( 'Blocked', 'core-blueprint-work' ), count( (array) $snapshot['blocked'] ), self::work_items_url( [ 'status' => 'blocked' ] ), true ); ?>
				<?php self::focus_card( __( 'In progress', 'core-blueprint-work' ), count( (array) $snapshot['in_progress'] ), self::work_items_url( [ 'status' => 'in_progress' ] ) ); ?>
				<?php self::focus_card( __( 'Due soon', 'core-blueprint-work' ), count( (array) $snapshot['due_soon'] ), self::work_items_url( [ 'status' => 'active', 'due_from' => $tomorrow, 'due_to' => $week_end ] ) ); ?>
				<?php self::focus_card( __( 'Ready to bill', 'core-blueprint-work' ), count( (array) $snapshot['ready_to_bill'] ), null ); ?>
			</div>

			<?php self::render_timer( is_array( $snapshot['active_timer'] ) ? $snapshot['active_timer'] : null ); ?>

			<div class="cb-work-daily-overview__grid">
				<?php self::queue( __( 'Today', 'core-blueprint-work' ), __( 'Scheduled or due today.', 'core-blueprint-work' ), (array) $snapshot['today'], $projects ); ?>
				<?php self::queue( __( 'Needs attention', 'core-blueprint-work' ), __( 'Overdue and blocked work.', 'core-blueprint-work' ), self::merge_attention( (array) $snapshot['overdue'], (array) $snapshot['blocked'] ), $projects, true ); ?>
				<?php self::queue( __( 'In progress', 'core-blueprint-work' ), __( 'Work currently underway.', 'core-blueprint-work' ), (array) $snapshot['in_progress'], $projects ); ?>
				<?php self::queue( __( 'Coming up', 'core-blueprint-work' ), __( 'Due in the next seven days.', 'core-blueprint-work' ), (array) $snapshot['due_soon'], $projects ); ?>
			</div>

			<?php self::ready_to_bill( (array) $snapshot['ready_to_bill'] ); ?>
		</div>
		<?php
	}

	private static function focus_card( string $label, int $count, ?string $url, bool $attention = false ): void {
		$class = 'cb-work-daily-overview__focus-card' . ( $attention && $count > 0 ? ' is-attention' : '' );
		if ( null === $url ) {
			?>
			<div class="<?php echo esc_attr( $class . ' is-static' ); ?>">
				<span class="cb-work-daily-overview__focus-count"><?php echo esc_html( (string) $count ); ?></span>
				<span><?php echo esc_html( $label ); ?></span>
			</div>
			<?php
			return;
		}
		?>
		<a class="<?php echo esc_attr( $class ); ?>" href="<?php echo esc_url( $url ); ?>">
			<span class="cb-work-daily-overview__focus-count"><?php echo esc_html( (string) $count ); ?></span>
			<span><?php echo esc_html( $label ); ?></span>
		</a>
		<?php
	}

	/** @param array<string,mixed>|null $timer */
	private static function render_timer( ?array $timer ): void {
		if ( null === $timer ) {
			return;
		}
		$item = WorkItems::get( (int) ( $timer['work_item_id'] ?? 0 ) );
		?>
		<div class="notice notice-info inline cb-work-daily-overview__timer">
			<p><strong><?php esc_html_e( 'Timer running', 'core-blueprint-work' ); ?></strong> · <?php echo esc_html( (string) ( $item['title'] ?? __( 'Work Item', 'core-blueprint-work' ) ) ); ?> <a href="<?php echo esc_url( Menu::time_url() ); ?>"><?php esc_html_e( 'Open timer', 'core-blueprint-work' ); ?></a></p>
		</div>
		<?php
	}

	/** @param array<int,array<string,mixed>> $items @param array<int,string> $projects */
	private static function queue( string $title, string $description, array $items, array $projects, bool $attention = false ): void {
		?>
		<section class="cb-work-daily-overview__panel<?php echo $attention ? ' cb-work-daily-overview__panel--attention' : ''; ?>">
			<header>
				<div><h2><?php echo esc_html( $title ); ?></h2><p class="description"><?php echo esc_html( $description ); ?></p></div>
				<span class="cb-work-daily-overview__count"><?php echo esc_html( (string) count( $items ) ); ?></span>
			</header>
			<?php if ( [] === $items ) : ?>
				<p class="cb-work-daily-overview__empty"><?php esc_html_e( 'Nothing needs your attention here.', 'core-blueprint-work' ); ?></p>
			<?php else : ?>
				<ul class="cb-work-daily-overview__items">
					<?php foreach ( $items as $item ) : ?>
						<li>
							<a class="cb-work-daily-overview__item-title" href="<?php echo esc_url( Menu::edit_work_item_url( (int) $item['id'] ) ); ?>"><?php echo esc_html( (string) $item['title'] ); ?></a>
							<span class="cb-work-daily-overview__item-meta"><?php echo esc_html( self::meta( $item, $projects ) ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</section>
		<?php
	}

	/** @param array<int,array<string,mixed>> $items */
	private static function ready_to_bill( array $items ): void {
		?>
		<section class="cb-work-daily-overview__panel cb-work-daily-overview__ready">
			<header><div><h2><?php esc_html_e( 'Ready to bill', 'core-blueprint-work' ); ?></h2><p class="description"><?php esc_html_e( 'Operational work with a current billing-ready snapshot.', 'core-blueprint-work' ); ?></p></div><span class="cb-work-daily-overview__count"><?php echo esc_html( (string) count( $items ) ); ?></span></header>
			<?php if ( [] === $items ) : ?>
				<p class="cb-work-daily-overview__empty"><?php esc_html_e( 'Nothing is waiting for commercial handoff.', 'core-blueprint-work' ); ?></p>
			<?php else : ?>
				<ul class="cb-work-daily-overview__items">
					<?php foreach ( $items as $ready ) : ?>
						<?php $item = WorkItems::get( (int) ( $ready['work_item_id'] ?? 0 ) ); if ( null === $item ) { continue; } ?>
						<li><a class="cb-work-daily-overview__item-title" href="<?php echo esc_url( Menu::edit_work_item_url( (int) $item['id'] ) ); ?>"><?php echo esc_html( (string) $item['title'] ); ?></a><span class="cb-work-daily-overview__item-meta"><?php esc_html_e( 'Ready for handoff', 'core-blueprint-work' ); ?></span></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</section>
		<?php
	}

	/** @param array<string,mixed> $item @param array<int,string> $projects */
	private static function meta( array $item, array $projects ): string {
		$parts = [];
		$project_id = (int) ( $item['project_id'] ?? 0 );
		if ( $project_id > 0 && isset( $projects[ $project_id ] ) ) {
			$parts[] = $projects[ $project_id ];
		}
		if ( '' !== (string) ( $item['due_on'] ?? '' ) ) {
			$parts[] = sprintf( __( 'Due %s', 'core-blueprint-work' ), (string) $item['due_on'] );
		}
		$parts[] = ucwords( str_replace( '_', ' ', (string) ( $item['status'] ?? '' ) ) );
		return implode( ' · ', array_filter( $parts ) );
	}

	/** @param array<int,array<string,mixed>> $overdue @param array<int,array<string,mixed>> $blocked @return array<int,array<string,mixed>> */
	private static function merge_attention( array $overdue, array $blocked ): array {
		$out = [];
		foreach ( [ ...$overdue, ...$blocked ] as $item ) {
			$id = (int) ( $item['id'] ?? 0 );
			if ( $id > 0 ) {
				$out[ $id ] = $item;
			}
			if ( count( $out ) >= 8 ) {
				break;
			}
		}
		return array_values( $out );
	}

	/** @param array<string,string|int> $args */
	private static function work_items_url( array $args = [] ): string {
		return add_query_arg( [ 'page' => Menu::WORK_ITEMS_SLUG, ...$args ], admin_url( 'admin.php' ) );
	}
}

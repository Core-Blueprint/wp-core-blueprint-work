<?php
declare(strict_types=1);

namespace CB\Work\Frontend\Actions;

use CoreBlueprint\Core\Governance\Audit;
use CB\Work\Domain\WorkItemStatus;
use CB\Work\Frontend\Access;
use CB\Work\Frontend\Data\WorkItem;
use CB\Work\Governance\Events;
use CB\Work\Repository\WorkItems as WorkItemRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Builder-neutral governed Work Item mutations.
 *
 * This class is not an HTTP endpoint. Adapters own their transport and CSRF
 * protection; Work owns authorization, lifecycle validation and persistence.
 */
final class WorkItems {
	/** @return array<string,mixed>|\WP_Error */
	public static function transition_status( int $work_item_id, string $to ): array|\WP_Error {
		$to = sanitize_key( $to );
		if ( $work_item_id <= 0 || ! WorkItemStatus::is_valid( $to ) ) {
			return new \WP_Error( 'work_transition_invalid' );
		}

		$visible = WorkItem::get( $work_item_id );
		if ( is_wp_error( $visible ) ) {
			return $visible;
		}
		if ( ! Access::can_transition_work_item( $work_item_id, $to ) ) {
			return new \WP_Error( 'work_action_forbidden' );
		}

		$from = (string) ( $visible['status'] ?? '' );
		if ( ! WorkItemStatus::can_transition( $from, $to ) ) {
			return new \WP_Error( 'work_transition_invalid' );
		}
		if ( ! WorkItemRepository::transition_status( $work_item_id, $to, get_current_user_id() ) ) {
			return new \WP_Error( 'work_transition_conflict' );
		}

		Audit::record( Events::WORK_ITEM_STATUS_CHANGED, 'notice', [
			'work_item_id' => $work_item_id,
			'from'         => $from,
			'to'           => $to,
			'channel'      => 'frontend_contract',
		] );

		$updated = WorkItem::get( $work_item_id );
		return is_wp_error( $updated ) ? new \WP_Error( 'work_resource_unavailable' ) : $updated;
	}
}

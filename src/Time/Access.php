<?php
declare(strict_types=1);

namespace CB\Work\Time;

use CB\Work\Capabilities;
use CB\Work\Repository\WorkItems;

defined( 'ABSPATH' ) || exit;

final class Access {
	public static function can_manage(): bool {
		return current_user_can( Capabilities::MANAGE );
	}

	public static function can_track(): bool {
		return self::can_manage() || current_user_can( Capabilities::TRACK_TIME );
	}

	public static function can_track_work_item( int $work_item_id, int $user_id = 0 ): bool {
		$item = WorkItems::get( $work_item_id );
		if ( null === $item ) {
			return false;
		}

		$user_id = $user_id > 0 ? $user_id : get_current_user_id();
		if ( $user_id <= 0 || false === get_userdata( $user_id ) ) {
			return false;
		}
		if ( self::can_manage() ) {
			return true;
		}
		if ( ! current_user_can( Capabilities::TRACK_TIME ) || get_current_user_id() !== $user_id ) {
			return false;
		}
		return in_array( $user_id, (array) ( $item['assigned_user_ids'] ?? [] ), true );
	}

	/** @param array<string,mixed> $entry */
	public static function can_view_entry( array $entry ): bool {
		return self::can_manage()
			|| ( current_user_can( Capabilities::TRACK_TIME ) && get_current_user_id() === (int) ( $entry['user_id'] ?? 0 ) );
	}

	/**
	 * Trackers may correct only their own completed entries and only while they
	 * remain authorized for the target Work Item. Managers may correct any entry.
	 *
	 * @param array<string,mixed> $entry
	 */
	public static function can_edit_entry( array $entry, int $target_work_item_id ): bool {
		if ( self::can_manage() ) {
			return true;
		}
		$user_id = (int) ( $entry['user_id'] ?? 0 );
		return $user_id > 0
			&& get_current_user_id() === $user_id
			&& self::can_track_work_item( $target_work_item_id, $user_id );
	}

	/**
	 * A tracker may always stop their own already-running timer. Assignment can
	 * legitimately be removed after a timer started; that must not strand it.
	 */
	public static function can_stop_user_timer( int $user_id ): bool {
		return self::can_manage()
			|| ( current_user_can( Capabilities::TRACK_TIME ) && get_current_user_id() === $user_id );
	}
}

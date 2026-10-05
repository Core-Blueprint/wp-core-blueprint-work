<?php
declare(strict_types=1);

namespace CB\Work\Admin;

use CB\Work\Capabilities;
use CB\Work\Repository\WorkItems;

defined( 'ABSPATH' ) || exit;

final class WorkItemCalendarActions {
	public const ACTION = 'cb_work_calendar_move';
	public const NONCE_ACTION = 'cb_work_calendar_move';

	public static function init(): void {
		add_action( 'wp_ajax_' . self::ACTION, [ self::class, 'handle' ] );
	}

	public static function handle(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_send_json_error( [ 'message' => __( 'You are not allowed to move Work Items on the calendar.', 'core-blueprint-work' ) ], 403 );
		}

		$work_item_id = isset( $_POST['work_item_id'] ) ? absint( $_POST['work_item_id'] ) : 0;
		$kind         = isset( $_POST['kind'] ) ? sanitize_key( (string) wp_unslash( $_POST['kind'] ) ) : '';
		$date         = isset( $_POST['date'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['date'] ) ) : '';

		if ( ! in_array( $kind, [ 'scheduled', 'due' ], true ) || ! self::valid_date( $date ) ) {
			wp_send_json_error( [ 'message' => __( 'The calendar move is invalid.', 'core-blueprint-work' ) ], 400 );
		}

		$item = WorkItems::get( $work_item_id );
		if ( null === $item ) {
			wp_send_json_error( [ 'message' => __( 'The Work Item could not be found.', 'core-blueprint-work' ) ], 404 );
		}

		$field = 'scheduled' === $kind ? 'scheduled_on' : 'due_on';
		if ( (string) ( $item[ $field ] ?? '' ) === $date ) {
			wp_send_json_success( [
				'work_item_id' => $work_item_id,
				'kind'         => $kind,
				'date'         => $date,
			] );
		}

		if ( ! WorkItems::update( $work_item_id, [ $field => $date ] ) ) {
			wp_send_json_error( [ 'message' => __( 'The Work Item date could not be updated.', 'core-blueprint-work' ) ], 500 );
		}

		wp_send_json_success( [
			'work_item_id' => $work_item_id,
			'kind'         => $kind,
			'date'         => $date,
		] );
	}

	private static function valid_date( string $value ): bool {
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $value );
		return $date instanceof \DateTimeImmutable && $date->format( 'Y-m-d' ) === $value;
	}

	private function __construct() {}
}

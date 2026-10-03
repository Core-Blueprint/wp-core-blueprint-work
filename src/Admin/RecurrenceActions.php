<?php
declare(strict_types=1);

namespace CB\Work\Admin;

use CoreBlueprint\Core\Governance\Audit;
use CB\Work\Capabilities;
use CB\Work\Governance\Events;
use CB\Work\Integration\CRMCustomers;
use CB\Work\Recurrence\Scheduler;
use CB\Work\Repository\RecurrenceRules;

defined( 'ABSPATH' ) || exit;

final class RecurrenceActions {
	public static function init(): void {
		add_action( 'admin_post_cb_work_create_recurrence_rule', [ self::class, 'create' ] );
		add_action( 'admin_post_cb_work_update_recurrence_rule', [ self::class, 'update' ] );
		add_action( 'admin_post_cb_work_toggle_recurrence_rule', [ self::class, 'toggle' ] );
		add_action( 'admin_post_cb_work_run_recurrence_generator', [ self::class, 'run_generator' ] );
	}

	public static function create(): never {
		self::guard( 'cb_work_create_recurrence_rule' );
		$input = self::input();
		if ( null === $input ) {
			self::redirect( 'recurrence-invalid' );
		}
		$input['created_by'] = get_current_user_id();
		$input['updated_by'] = get_current_user_id();
		$rule_id = RecurrenceRules::create( $input );
		if ( $rule_id <= 0 ) {
			self::redirect( 'recurrence-invalid' );
		}
		Audit::record( Events::RECURRENCE_RULE_CREATED, 'notice', [ 'rule_id' => $rule_id ] );
		self::redirect( 'recurrence-created', [ 'rule_id' => $rule_id ] );
	}

	public static function update(): never {
		$rule_id = isset( $_POST['rule_id'] ) ? absint( $_POST['rule_id'] ) : 0;
		self::guard( 'cb_work_update_recurrence_rule_' . $rule_id );
		$current = RecurrenceRules::get( $rule_id );
		if ( null === $current ) {
			self::redirect( 'recurrence-invalid' );
		}
		$input = self::input( $current );
		if ( null === $input ) {
			self::redirect( 'recurrence-invalid', [ 'rule_id' => $rule_id ] );
		}
		$input['updated_by'] = get_current_user_id();
		if ( ! RecurrenceRules::update( $rule_id, $input ) ) {
			self::redirect( 'recurrence-invalid', [ 'rule_id' => $rule_id ] );
		}
		Audit::record( Events::RECURRENCE_RULE_UPDATED, 'notice', [ 'rule_id' => $rule_id ] );
		self::redirect( 'recurrence-updated', [ 'rule_id' => $rule_id ] );
	}

	public static function toggle(): never {
		$rule_id = isset( $_POST['rule_id'] ) ? absint( $_POST['rule_id'] ) : 0;
		self::guard( 'cb_work_toggle_recurrence_rule_' . $rule_id );
		$active = isset( $_POST['active'] ) && '1' === sanitize_text_field( wp_unslash( (string) $_POST['active'] ) );
		if ( $rule_id <= 0 || ! RecurrenceRules::update( $rule_id, [ 'is_active' => $active, 'updated_by' => get_current_user_id() ] ) ) {
			self::redirect( 'recurrence-invalid' );
		}
		Audit::record( Events::RECURRENCE_RULE_STATUS_CHANGED, 'notice', [ 'rule_id' => $rule_id, 'active' => $active ] );
		self::redirect( 'recurrence-status-updated' );
	}

	public static function run_generator(): never {
		self::guard( 'cb_work_run_recurrence_generator' );
		$stats = Scheduler::run( get_current_user_id(), 'manual' );
		self::redirect( 'recurrence-run', [
			'generated' => (int) $stats['generated'],
			'recovered' => (int) $stats['recovered'],
			'failed'    => (int) $stats['failed'],
		] );
	}

	/**
	 * @param array<string,mixed>|null $current
	 * @return array<string,mixed>|null
	 */
	private static function input( ?array $current = null ): ?array {
		$input = isset( $_POST['recurrence'] ) && is_array( $_POST['recurrence'] )
			? wp_unslash( $_POST['recurrence'] )
			: [];
		$input['is_active'] = isset( $input['is_active'] ) && '1' === sanitize_text_field( (string) $input['is_active'] );

		if ( array_key_exists( 'customer_object_id', $input ) ) {
			$identifier = is_scalar( $input['customer_object_id'] )
				? sanitize_text_field( (string) $input['customer_object_id'] )
				: '';
			$reference = CRMCustomers::reference( $identifier );
			if ( is_wp_error( $reference ) ) {
				return null;
			}
			$input['customer_provider'] = null === $reference ? '' : $reference['provider'];
			$input['customer_type']     = null === $reference ? '' : $reference['type'];
			$input['customer_id']       = null === $reference ? '' : $reference['id'];
			unset( $input['customer_object_id'] );
		} elseif ( null !== $current ) {
			$input['customer_provider'] = (string) ( $current['customer_provider'] ?? '' );
			$input['customer_type']     = (string) ( $current['customer_type'] ?? '' );
			$input['customer_id']       = (string) ( $current['customer_id'] ?? '' );
		} else {
			$input['customer_provider'] = '';
			$input['customer_type']     = '';
			$input['customer_id']       = '';
		}
		return $input;
	}

	private static function guard( string $nonce_action ): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Work.', 'core-blueprint-work' ) );
		}
		check_admin_referer( $nonce_action );
	}

	/** @param array<string,int|string> $extra */
	private static function redirect( string $notice, array $extra = [] ): never {
		wp_safe_redirect( add_query_arg(
			[ 'page' => Menu::RECURRENCE_SLUG, 'cb-work-notice' => sanitize_key( $notice ), ...$extra ],
			admin_url( 'admin.php' )
		) );
		exit;
	}
}

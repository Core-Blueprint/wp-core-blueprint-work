<?php
declare(strict_types=1);

namespace CB\Work\Admin;

use CB\Core\Governance\Audit;
use CB\Work\Capabilities;
use CB\Work\Governance\Events;
use CB\Work\Repository\TaxRates;
defined( 'ABSPATH' ) || exit;

final class TaxRateActions {
	public static function init(): void {
		add_action( 'admin_post_cb_work_add_tax_rate', [ self::class, 'add' ] );
		add_action( 'admin_post_cb_work_toggle_tax_rate', [ self::class, 'toggle' ] );
	}

	public static function add(): never {
		self::guard( 'cb_work_add_tax_rate' );
		$input = isset( $_POST['tax_rate'] ) && is_array( $_POST['tax_rate'] )
			? wp_unslash( $_POST['tax_rate'] )
			: [];
		$id = TaxRates::create( $input );
		if ( $id > 0 ) {
			Audit::record( Events::TAX_RATE_CREATED, 'notice', [ 'tax_rate_id' => $id ] );
			self::redirect( 'tax-created' );
		}
		self::redirect( 'tax-invalid' );
	}

	public static function toggle(): never {
		self::guard( 'cb_work_toggle_tax_rate' );
		$id     = isset( $_POST['tax_rate_id'] ) ? absint( $_POST['tax_rate_id'] ) : 0;
		$active = isset( $_POST['active'] ) && '1' === (string) $_POST['active'];
		if ( $id > 0 && TaxRates::set_active( $id, $active ) ) {
			Audit::record(
				$active ? Events::TAX_RATE_ACTIVATED : Events::TAX_RATE_DEACTIVATED,
				'notice',
				[ 'tax_rate_id' => $id ]
			);
			self::redirect( $active ? 'tax-activated' : 'tax-deactivated' );
		}
		self::redirect( 'tax-invalid' );
	}

	private static function guard( string $nonce_action ): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Work VAT rates.', 'core-blueprint-work' ) );
		}
		check_admin_referer( $nonce_action );
	}

	private static function redirect( string $notice ): never {
		wp_safe_redirect(
			add_query_arg(
				[
					'page'           => Page::SLUG,
					'view'           => Page::VIEW_SETTINGS,
					'cb-work-notice' => sanitize_key( $notice ),
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}

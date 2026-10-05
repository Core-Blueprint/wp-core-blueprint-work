<?php
declare(strict_types=1);

namespace CB\Work\Admin;

use CB\Work\Capabilities;
use CB\Work\Content\PostTypes;
use CB\Work\Content\ServicePricing;
use CB\Work\Domain\BillingDisposition;
use CB\Work\Domain\WorkContext;
use CB\Work\PublicApi\Services;
use CB\Work\Repository\Projects;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps Service pricing and Work Item billing classification understandable in
 * the native editor without changing the canonical persistence owner.
 *
 * A Service provides a sensible default only while the Work Item remains
 * unclassified. Explicit Work Item choices always win.
 */
final class BillingClassificationUx {
	private const SCRIPT_HANDLE = 'cb-work-billing-classification-ux';

	public static function init(): void {
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue' ] );
		add_action( 'save_post_' . PostTypes::WORK_ITEM, [ self::class, 'apply_service_default_before_save' ], 19, 3 );
	}

	public static function enqueue( string $hook_suffix = '' ): void {
		if ( ! in_array( $hook_suffix, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! is_object( $screen ) || PostTypes::WORK_ITEM !== (string) ( $screen->post_type ?? '' ) ) {
			return;
		}

		$file = CB_WORK_DIR . 'assets/work-item-billing.js';
		if ( ! is_file( $file ) ) {
			return;
		}

		$modified = filemtime( $file );
		$version  = false === $modified ? CB_WORK_VERSION : (string) $modified;

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			CB_WORK_URL . 'assets/work-item-billing.js',
			[],
			$version,
			true
		);

		$model_labels = ServicePricing::pricing_model_labels();
		$services     = [];
		foreach ( Services::all( 250 ) as $service ) {
			$billing = self::default_for_service( $service );
			if ( '' === $billing ) {
				continue;
			}
			$model = sanitize_key( (string) ( $service['pricing']['pricing_model'] ?? '' ) );
			$services[ (string) (int) ( $service['id'] ?? 0 ) ] = [
				'billing'       => $billing,
				'pricingLabel'  => $model_labels[ $model ] ?? ucfirst( str_replace( '_', ' ', $model ) ),
			];
		}

		wp_localize_script( self::SCRIPT_HANDLE, 'cbWorkBillingUx', [
			'services'       => $services,
			/* translators: %s: default billing classification from the selected Service. */
			'serviceDefault' => __( 'Selected Service default: %s. You can override it for this Work Item.', 'core-blueprint-work' ),
			'noService'      => __( 'Choose a Service to prefill billing classification, or classify this Work Item manually.', 'core-blueprint-work' ),
			'internal'       => __( 'Internal Work is always non-billable.', 'core-blueprint-work' ),
		] );
	}

	/**
	 * Fill only an empty editor classification before WorkItems::save() runs.
	 *
	 * This method intentionally does not persist anything itself. It only
	 * normalizes the canonical editor payload for the existing persistence owner.
	 */
	public static function apply_service_default_before_save( int $post_id, \WP_Post $post, bool $update ): void {
		unset( $update );
		if (
			PostTypes::WORK_ITEM !== $post->post_type
			|| wp_is_post_revision( $post_id )
			|| wp_is_post_autosave( $post_id )
			|| ! current_user_can( Capabilities::MANAGE )
			|| ! current_user_can( 'edit_post', $post_id )
			|| ! isset( $_POST['cb_work_work_item_nonce'] )
			|| ! isset( $_POST['cb_work_item'] )
			|| ! is_array( $_POST['cb_work_item'] )
		) {
			return;
		}

		$input      = wp_unslash( $_POST['cb_work_item'] );
		$context    = WorkContext::sanitize( $input['work_context'] ?? '' );
		$project_id = absint( $input['project_id'] ?? 0 );
		if ( $project_id > 0 ) {
			$project = Projects::get( $project_id );
			if ( is_array( $project ) ) {
				$context = WorkContext::sanitize( $project['work_context'] ?? '' );
			}
		}
		if ( WorkContext::INTERNAL === $context ) {
			$_POST['cb_work_item']['billing_disposition'] = BillingDisposition::NON_BILLABLE;
			return;
		}

		$billing = sanitize_key( (string) ( $input['billing_disposition'] ?? '' ) );
		if ( '' !== $billing ) {
			return;
		}

		$service_id = absint( $input['service_id'] ?? 0 );
		if ( $service_id <= 0 ) {
			return;
		}

		$service = Services::get( $service_id );
		if ( null === $service ) {
			return;
		}

		$default = self::default_for_service( $service );
		if ( '' !== $default ) {
			$_POST['cb_work_item']['billing_disposition'] = $default;
		}
	}

	/** @param array<string,mixed> $service */
	private static function default_for_service( array $service ): string {
		$model = sanitize_key( (string) ( $service['pricing']['pricing_model'] ?? '' ) );
		return match ( $model ) {
			ServicePricing::MODEL_HOURLY                         => BillingDisposition::HOURLY,
			ServicePricing::MODEL_FIXED, ServicePricing::MODEL_RECURRING => BillingDisposition::FIXED,
			default                                              => '',
		};
	}
}

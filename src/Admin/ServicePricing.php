<?php
declare(strict_types=1);

namespace CB\Work\Admin;

use CB\Core\Governance\Audit;
use CB\Work\Capabilities;
use CB\Work\Content\PostTypes;
use CB\Work\Content\ServicePricing as Pricing;
use CB\Work\Governance\Events;
use CB\Work\Repository\TaxRates;
defined( 'ABSPATH' ) || exit;

final class ServicePricing {
	public static function init(): void {
		add_action( 'add_meta_boxes', [ self::class, 'register_meta_box' ] );
		add_action( 'save_post_' . PostTypes::SERVICE, [ self::class, 'save' ], 20, 3 );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue' ] );
		add_filter( 'manage_' . PostTypes::SERVICE . '_posts_columns', [ self::class, 'columns' ] );
		add_action( 'manage_' . PostTypes::SERVICE . '_posts_custom_column', [ self::class, 'column' ], 10, 2 );
		add_filter( 'enter_title_here', [ self::class, 'title_placeholder' ], 10, 2 );
	}

	public static function register_meta_box(): void {
		add_meta_box(
			'cb-work-service-pricing',
			__( 'Service Pricing', 'core-blueprint-work' ),
			[ self::class, 'render' ],
			PostTypes::SERVICE,
			'normal',
			'high'
		);
	}

	public static function enqueue(): void {
		$screen = get_current_screen();
		if ( ! $screen || PostTypes::SERVICE !== (string) $screen->post_type ) {
			return;
		}
		wp_enqueue_script(
			'cb-work-service-pricing',
			CB_WORK_URL . 'assets/service-pricing.js',
			[],
			CB_WORK_VERSION,
			true
		);
	}

	public static function render( \WP_Post $post ): void {
		$pricing = Pricing::get( (int) $post->ID );
		$rates   = TaxRates::all( true );
		wp_nonce_field( 'cb_work_save_service_pricing', 'cb_work_service_pricing_nonce' );
		?>
		<table class="form-table cb-work-service-pricing" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><label for="cb-work-pricing-model"><?php esc_html_e( 'Pricing model', 'core-blueprint-work' ); ?></label></th>
					<td>
						<select id="cb-work-pricing-model" name="cb_work_service_pricing[pricing_model]" data-cb-work-pricing-model>
							<?php foreach ( Pricing::pricing_model_labels() as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $pricing['pricing_model'], $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Defines the standard commercial model for this service. Individual work can override it later.', 'core-blueprint-work' ); ?></p>
					</td>
				</tr>
				<tr data-cb-work-recurring-period <?php echo Pricing::MODEL_RECURRING === $pricing['pricing_model'] ? '' : 'hidden'; ?>>
					<th scope="row"><label for="cb-work-recurring-period"><?php esc_html_e( 'Recurring period', 'core-blueprint-work' ); ?></label></th>
					<td>
						<select id="cb-work-recurring-period" name="cb_work_service_pricing[recurring_period]">
							<?php foreach ( Pricing::recurring_period_labels() as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $pricing['recurring_period'], $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="cb-work-service-price"><?php esc_html_e( 'Standard price', 'core-blueprint-work' ); ?></label></th>
					<td><input id="cb-work-service-price" class="regular-text" type="text" inputmode="decimal" name="cb_work_service_pricing[amount]" value="<?php echo esc_attr( Pricing::amount_input( $pricing['amount_minor'] ) ); ?>" placeholder="95.00"><p class="description"><?php esc_html_e( 'Leave empty when the service has no standard price.', 'core-blueprint-work' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label for="cb-work-service-currency"><?php esc_html_e( 'Currency', 'core-blueprint-work' ); ?></label></th>
					<td><input id="cb-work-service-currency" class="small-text" type="text" maxlength="3" name="cb_work_service_pricing[currency]" value="<?php echo esc_attr( $pricing['currency'] ); ?>" placeholder="EUR"></td>
				</tr>
				<tr>
					<th scope="row"><label for="cb-work-tax-mode"><?php esc_html_e( 'Price is', 'core-blueprint-work' ); ?></label></th>
					<td>
						<select id="cb-work-tax-mode" name="cb_work_service_pricing[tax_mode]" data-cb-work-tax-mode>
							<?php foreach ( Pricing::tax_mode_labels() as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $pricing['tax_mode'], $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr data-cb-work-tax-rate <?php echo Pricing::TAX_EXEMPT === $pricing['tax_mode'] ? 'hidden' : ''; ?>>
					<th scope="row"><label for="cb-work-tax-rate"><?php esc_html_e( 'VAT rate', 'core-blueprint-work' ); ?></label></th>
					<td>
						<select id="cb-work-tax-rate" name="cb_work_service_pricing[tax_rate_id]">
							<option value="0"><?php esc_html_e( 'No VAT rate selected', 'core-blueprint-work' ); ?></option>
							<?php foreach ( $rates as $rate ) : ?>
								<?php $available = TaxRates::is_available( $rate ); ?>
								<option value="<?php echo esc_attr( (string) $rate['id'] ); ?>" <?php selected( $pricing['tax_rate_id'], (int) $rate['id'] ); ?> <?php disabled( ! $available && $pricing['tax_rate_id'] !== (int) $rate['id'] ); ?>><?php echo esc_html( TaxRates::display_label( $rate ) . ( $available ? '' : ' · ' . __( 'inactive/historical', 'core-blueprint-work' ) ) ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><a href="<?php echo esc_url( Page::settings_url() . '#vat-rates' ); ?>"><?php esc_html_e( 'Manage VAT rates in Work Settings', 'core-blueprint-work' ); ?></a></p>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	public static function save( int $post_id, \WP_Post $post, bool $update ): void {
		unset( $update );
		if ( PostTypes::SERVICE !== $post->post_type || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) || 'trash' === $post->post_status ) {
			return;
		}
		if ( ! isset( $_POST['cb_work_service_pricing_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( (string) wp_unslash( $_POST['cb_work_service_pricing_nonce'] ) ), 'cb_work_save_service_pricing' ) ) {
			return;
		}
		if ( ! current_user_can( Capabilities::MANAGE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$input = isset( $_POST['cb_work_service_pricing'] ) && is_array( $_POST['cb_work_service_pricing'] )
			? wp_unslash( $_POST['cb_work_service_pricing'] )
			: [];
		if ( Pricing::save( $post_id, $input ) ) {
			Audit::record( Events::SERVICE_PRICING_UPDATED, 'notice', [ 'service_id' => $post_id ] );
		}
	}

	/** @param array<string,string> $columns @return array<string,string> */
	public static function columns( array $columns ): array {
		$columns['cb_work_pricing'] = __( 'Pricing', 'core-blueprint-work' );
		$columns['cb_work_vat']     = __( 'VAT', 'core-blueprint-work' );
		return $columns;
	}

	public static function column( string $column, int $post_id ): void {
		if ( 'cb_work_pricing' === $column ) {
			$pricing = Pricing::get( $post_id );
			$model   = Pricing::pricing_model_labels()[ $pricing['pricing_model'] ] ?? $pricing['pricing_model'];
			$amount  = null === $pricing['amount_minor'] ? __( 'No standard price', 'core-blueprint-work' ) : $pricing['currency'] . ' ' . Pricing::amount_input( $pricing['amount_minor'] );
			echo esc_html( $model . ' · ' . $amount );
			return;
		}
		if ( 'cb_work_vat' === $column ) {
			$pricing = Pricing::get( $post_id );
			if ( Pricing::TAX_EXEMPT === $pricing['tax_mode'] ) {
				esc_html_e( 'Exempt', 'core-blueprint-work' );
				return;
			}
			$rate = TaxRates::get( $pricing['tax_rate_id'] );
			echo esc_html( $rate ? TaxRates::display_label( $rate ) : __( 'No VAT rate', 'core-blueprint-work' ) );
		}
	}

	public static function title_placeholder( string $title, \WP_Post $post ): string {
		return PostTypes::SERVICE === $post->post_type ? __( 'Service name', 'core-blueprint-work' ) : $title;
	}
}

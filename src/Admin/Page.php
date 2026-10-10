<?php
declare(strict_types=1);

namespace CB\Work\Admin;

use CoreBlueprint\Core\Admin\SettingsRegistry;
use CoreBlueprint\Core\UI\Notice;
use CB\Work\Capabilities;
use CB\Work\Database\Schema;
use CB\Work\Integration\Suite;
use CB\Work\Repository\TaxRates;
defined( 'ABSPATH' ) || exit;

final class Page {
	private static bool $initialized = false;

	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;
		TaxRateDataExchange::init();
		add_action( 'core_blueprint_register_settings', [ self::class, 'register' ] );
	}

	public static function register(): void {
		SettingsRegistry::register(
			Suite::EXTENSION_ID,
			[
				'label'       => __( 'Work', 'core-blueprint-work' ),
				'description' => __( 'Configure Work-wide settings. Day-to-day operational work is managed from the separate Work menu.', 'core-blueprint-work' ),
				'group'       => SettingsRegistry::GROUP_BUSINESS,
				'capability'  => Capabilities::MANAGE,
				'renderer'    => [ self::class, 'render' ],
				'requirements' => [
					'components' => [ 'panels', 'notices', 'fields', 'form-controls', 'buttons', 'badges', 'empty-state' ],
				],
			]
		);
	}

	/** @param array<string,int|string> $query */
	public static function settings_url( array $query = [] ): string {
		return SettingsRegistry::url( Suite::EXTENSION_ID, $query );
	}

	public static function render(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to access Work settings.', 'core-blueprint-work' ) );
		}

		$notice       = isset( $_GET['cb-work-notice'] ) ? sanitize_key( wp_unslash( (string) $_GET['cb-work-notice'] ) ) : '';
		$view         = isset( $_GET['cb-work-view'] ) ? sanitize_key( wp_unslash( (string) $_GET['cb-work-view'] ) ) : '';
		$schema_ready = CB_WORK_SCHEMA_VERSION === (string) get_option( Schema::OPTION, '0' );
		$rates        = $schema_ready ? TaxRates::all( true ) : [];

		self::render_notice( $notice );
		if ( ! $schema_ready ) {
			echo Notice::render( [
				'variant' => Notice::ERROR,
				'title'   => __( 'Work storage unavailable', 'core-blueprint-work' ),
				'message' => __( 'The Work database schema is not ready. Work settings remain read-only until Base reconciles the registered schema.', 'core-blueprint-work' ),
			] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Base renderer returns escaped component HTML.
		echo '</div>';
			return;
		}

		if ( 'vat-import' === $view ) {
			TaxRateDataExchange::render_import();
			return;
		}

		self::render_settings( $rates );
	}

	/** @param array<int,array<string,mixed>> $rates */
	private static function render_settings( array $rates ): void {
		?>
		<section class="cb-core-panel" id="vat-rates">
			<h2><?php esc_html_e( 'VAT rates', 'core-blueprint-work' ); ?></h2>
			<p><?php esc_html_e( 'Configure reusable VAT rates for Work service pricing. Deactivate old rates instead of changing or deleting them so historical references remain reliable.', 'core-blueprint-work' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="cb_work_add_tax_rate">
				<?php wp_nonce_field( 'cb_work_add_tax_rate' ); ?>
				<table class="form-table" role="presentation"><tbody>
					<tr><th><label for="cb-work-tax-label"><?php esc_html_e( 'Label', 'core-blueprint-work' ); ?></label></th><td><input id="cb-work-tax-label" class="regular-text" type="text" name="tax_rate[label]" required placeholder="NL Standard"><p class="description"><?php esc_html_e( 'Example: NL Standard or NL Reduced.', 'core-blueprint-work' ); ?></p></td></tr>
					<tr><th><label for="cb-work-tax-code"><?php esc_html_e( 'Code', 'core-blueprint-work' ); ?></label></th><td><input id="cb-work-tax-code" class="regular-text" type="text" name="tax_rate[code]" placeholder="nl-standard"><p class="description"><?php esc_html_e( 'Optional stable internal code. When empty it is generated from the label.', 'core-blueprint-work' ); ?></p></td></tr>
					<tr><th><label for="cb-work-tax-country"><?php esc_html_e( 'Country code', 'core-blueprint-work' ); ?></label></th><td><input id="cb-work-tax-country" class="small-text" type="text" name="tax_rate[country_code]" maxlength="2" placeholder="NL"></td></tr>
					<tr><th><label for="cb-work-tax-rate"><?php esc_html_e( 'Rate', 'core-blueprint-work' ); ?></label></th><td><input id="cb-work-tax-rate" class="small-text" type="text" inputmode="decimal" name="tax_rate[rate]" required placeholder="21"> <span class="description">%</span></td></tr>
					<tr><th><label for="cb-work-tax-from"><?php esc_html_e( 'Valid from', 'core-blueprint-work' ); ?></label></th><td><input id="cb-work-tax-from" type="date" name="tax_rate[valid_from]"></td></tr>
					<tr><th><label for="cb-work-tax-until"><?php esc_html_e( 'Valid until', 'core-blueprint-work' ); ?></label></th><td><input id="cb-work-tax-until" type="date" name="tax_rate[valid_until]"></td></tr>
				</tbody></table>
				<?php submit_button( __( 'Add VAT Rate', 'core-blueprint-work' ) ); ?>
			</form>
		</section>

		<section class="cb-core-panel" id="configured-vat-rates">
			<h2><?php esc_html_e( 'Configured VAT rates', 'core-blueprint-work' ); ?></h2>
			<?php if ( TaxRateDataExchange::available() ) : ?>
				<p class="submit">
					<a class="button button-secondary" href="<?php echo esc_url( TaxRateDataExchange::import_url() ); ?>"><?php esc_html_e( 'Import VAT rates', 'core-blueprint-work' ); ?></a>
					<a class="button button-secondary" href="<?php echo esc_url( TaxRateDataExchange::export_url() ); ?>"><?php esc_html_e( 'Export VAT rates', 'core-blueprint-work' ); ?></a>
				</p>
			<?php endif; ?>
			<?php if ( [] === $rates ) : ?>
				<p><?php esc_html_e( 'No VAT rates configured yet.', 'core-blueprint-work' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead><tr><th><?php esc_html_e( 'Code', 'core-blueprint-work' ); ?></th><th><?php esc_html_e( 'Label', 'core-blueprint-work' ); ?></th><th><?php esc_html_e( 'Country', 'core-blueprint-work' ); ?></th><th><?php esc_html_e( 'Rate', 'core-blueprint-work' ); ?></th><th><?php esc_html_e( 'Validity', 'core-blueprint-work' ); ?></th><th><?php esc_html_e( 'Status', 'core-blueprint-work' ); ?></th><th><?php esc_html_e( 'Action', 'core-blueprint-work' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( $rates as $rate ) : ?>
						<tr>
							<td><code><?php echo esc_html( (string) $rate['code'] ); ?></code></td>
							<td><?php echo esc_html( (string) $rate['label'] ); ?></td>
							<td><?php echo esc_html( (string) $rate['country_code'] ); ?></td>
							<td><?php echo esc_html( TaxRates::format_rate_bp( (int) $rate['rate_bp'] ) . '%' ); ?></td>
							<td><?php echo esc_html( TaxRates::validity_label( $rate ) ); ?></td>
							<td><?php echo ! empty( $rate['is_active'] ) ? esc_html__( 'Active', 'core-blueprint-work' ) : esc_html__( 'Inactive', 'core-blueprint-work' ); ?></td>
							<td>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<input type="hidden" name="action" value="cb_work_toggle_tax_rate">
									<input type="hidden" name="tax_rate_id" value="<?php echo esc_attr( (string) $rate['id'] ); ?>">
									<input type="hidden" name="active" value="<?php echo empty( $rate['is_active'] ) ? '1' : '0'; ?>">
									<?php wp_nonce_field( 'cb_work_toggle_tax_rate' ); ?>
									<button class="button button-small" type="submit"><?php echo empty( $rate['is_active'] ) ? esc_html__( 'Activate', 'core-blueprint-work' ) : esc_html__( 'Deactivate', 'core-blueprint-work' ); ?></button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</section>
		<?php
	}

	private static function render_notice( string $notice ): void {
		$messages = [
			'tax-created'     => [ Notice::SUCCESS, __( 'VAT rate added.', 'core-blueprint-work' ) ],
			'tax-activated'   => [ Notice::SUCCESS, __( 'VAT rate activated.', 'core-blueprint-work' ) ],
			'tax-deactivated' => [ Notice::INFO, __( 'VAT rate deactivated. Existing references remain intact.', 'core-blueprint-work' ) ],
			'tax-invalid'     => [ Notice::ERROR, __( 'The VAT rate could not be saved. Check its code, percentage and validity dates.', 'core-blueprint-work' ) ],
		];
		if ( ! isset( $messages[ $notice ] ) ) {
			return;
		}
		[ $variant, $message ] = $messages[ $notice ];
		echo '<div data-cb-work-toast="' . esc_attr( $variant ) . '">';
		echo Notice::render( [
			'variant' => $variant,
			'title'   => __( 'VAT Rates', 'core-blueprint-work' ),
			'message' => $message,
		] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Base renderer returns escaped component HTML.
		echo '</div>';
	}
}

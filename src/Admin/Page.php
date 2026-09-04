<?php
declare(strict_types=1);

namespace CB\Work\Admin;

use CB\Core\Admin\Page as PageContract;
use CB\Core\Admin\PageRegistry;
use CB\Core\UI\Notice;
use CB\Work\Capabilities;
use CB\Work\Content\PostTypes;
use CB\Work\Content\ServicePricing;
use CB\Work\Database\Schema;
use CB\Work\PublicApi\Services;
use CB\Work\Repository\TaxRates;
defined( 'ABSPATH' ) || exit;

final class Page implements PageContract {
	public const SLUG = 'core-blueprint-work';
	public const VIEW_SERVICES = 'services';
	public const VIEW_SETTINGS = 'settings';

	private static bool $initialized = false;

	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;
		add_action( 'cb_core_register_pages', [ self::class, 'register' ] );
	}

	public static function register(): void {
		PageRegistry::register(
			new self(),
			[
				'components' => [ 'panels', 'notices', 'fields', 'form-controls', 'actions', 'badges', 'empty-state', 'nav-tabs' ],
			]
		);
	}

	public function slug(): string {
		return self::SLUG;
	}

	public function title(): string {
		return __( 'Work', 'core-blueprint-work' );
	}

	public function menu_title(): string {
		return __( 'Work', 'core-blueprint-work' );
	}

	public function capability(): string {
		return Capabilities::MANAGE;
	}

	public function position(): ?int {
		return null;
	}

	public static function view_url( string $view = self::VIEW_SERVICES ): string {
		if ( ! in_array( $view, [ self::VIEW_SERVICES, self::VIEW_SETTINGS ], true ) ) {
			$view = self::VIEW_SERVICES;
		}
		return admin_url( 'admin.php?page=' . self::SLUG . '&view=' . $view );
	}

	public static function settings_url(): string {
		return self::view_url( self::VIEW_SETTINGS );
	}

	public function render(): void {
		if ( ! current_user_can( $this->capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to access Work.', 'core-blueprint-work' ) );
		}

		$view         = self::current_view();
		$notice       = isset( $_GET['cb-work-notice'] ) ? sanitize_key( wp_unslash( (string) $_GET['cb-work-notice'] ) ) : '';
		$schema_ready = CB_WORK_SCHEMA_VERSION === (string) get_option( Schema::OPTION, '0' );
		$services     = $schema_ready && self::VIEW_SERVICES === $view ? Services::all( 20 ) : [];
		$rates        = $schema_ready && self::VIEW_SETTINGS === $view ? TaxRates::all( true ) : [];
		?>
		<div class="wrap cb-core-wrap cb-work-wrap">
			<h1 class="cb-core-title"><?php esc_html_e( 'Core Blueprint Work', 'core-blueprint-work' ); ?></h1>
			<p class="cb-core-intro"><?php esc_html_e( 'Manage the service catalog and settings that Work will use for projects, time and billing-ready records.', 'core-blueprint-work' ); ?></p>

			<nav class="nav-tab-wrapper cb-core-tab-wrapper" aria-label="<?php esc_attr_e( 'Work sections', 'core-blueprint-work' ); ?>">
				<a class="nav-tab <?php echo self::VIEW_SERVICES === $view ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( self::view_url( self::VIEW_SERVICES ) ); ?>" <?php echo self::VIEW_SERVICES === $view ? 'aria-current="page"' : ''; ?>><?php esc_html_e( 'Services', 'core-blueprint-work' ); ?></a>
				<a class="nav-tab <?php echo self::VIEW_SETTINGS === $view ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( self::view_url( self::VIEW_SETTINGS ) ); ?>" <?php echo self::VIEW_SETTINGS === $view ? 'aria-current="page"' : ''; ?>><?php esc_html_e( 'Settings', 'core-blueprint-work' ); ?></a>
			</nav>

			<?php self::render_notice( $notice ); ?>
			<?php if ( ! $schema_ready ) : ?>
				<?php echo Notice::render( [
					'variant' => Notice::ERROR,
					'title'   => __( 'Work storage unavailable', 'core-blueprint-work' ),
					'message' => __( 'The Work database schema is not ready. Service and VAT management stays read-only until Base reconciles the registered schema.', 'core-blueprint-work' ),
				] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Base renderer returns escaped component HTML. ?>
			<?php return; endif; ?>

			<?php if ( self::VIEW_SETTINGS === $view ) : ?>
				<?php self::render_settings( $rates ); ?>
			<?php else : ?>
				<?php self::render_services( $services ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function current_view(): string {
		$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( (string) $_GET['view'] ) ) : self::VIEW_SERVICES;
		return in_array( $view, [ self::VIEW_SERVICES, self::VIEW_SETTINGS ], true ) ? $view : self::VIEW_SERVICES;
	}

	/** @param array<int,array<string,mixed>> $services */
	private static function render_services( array $services ): void {
		?>
		<section class="cb-core-panel" id="services">
			<div class="cb-core-actions">
				<div>
					<h2><?php esc_html_e( 'Services', 'core-blueprint-work' ); ?></h2>
					<p><?php esc_html_e( 'Define the standard commercial model, price and VAT treatment for each service.', 'core-blueprint-work' ); ?></p>
				</div>
				<p>
					<a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . PostTypes::SERVICE ) ); ?>"><?php esc_html_e( 'Add Service', 'core-blueprint-work' ); ?></a>
					<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . PostTypes::SERVICE ) ); ?>"><?php esc_html_e( 'Manage Services', 'core-blueprint-work' ); ?></a>
				</p>
			</div>

			<?php if ( [] === $services ) : ?>
				<p><?php esc_html_e( 'No Work services exist yet. Add your first service to define its default pricing and VAT behavior.', 'core-blueprint-work' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead><tr><th><?php esc_html_e( 'Service', 'core-blueprint-work' ); ?></th><th><?php esc_html_e( 'Default pricing', 'core-blueprint-work' ); ?></th><th><?php esc_html_e( 'Status', 'core-blueprint-work' ); ?></th><th><?php esc_html_e( 'Action', 'core-blueprint-work' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( $services as $service ) : ?>
						<tr>
							<td><strong><?php echo esc_html( (string) $service['title'] ); ?></strong></td>
							<td><?php echo esc_html( ServicePricing::summary( $service['pricing'] ) ); ?></td>
							<td><?php echo esc_html( (string) $service['status'] ); ?></td>
							<td><a class="button button-small" href="<?php echo esc_url( admin_url( 'post.php?post=' . (int) $service['id'] . '&action=edit' ) ); ?>"><?php esc_html_e( 'Edit', 'core-blueprint-work' ); ?></a></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</section>
		<?php
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
		echo Notice::render( [
			'variant' => $variant,
			'title'   => __( 'VAT Rates', 'core-blueprint-work' ),
			'message' => $message,
		] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Base renderer returns escaped component HTML.
	}
}

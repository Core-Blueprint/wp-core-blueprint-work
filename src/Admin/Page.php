<?php
declare(strict_types=1);

namespace CB\Work\Admin;

use CB\Core\Admin\Page as PageContract;
use CB\Core\Admin\PageRegistry;
use CB\Work\Capabilities;

defined( 'ABSPATH' ) || exit;

final class Page implements PageContract {
	public const SLUG = 'core-blueprint-work';

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
				'components' => [ 'panels', 'notices', 'fields', 'form-controls' ],
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

	public function render(): void {
		if ( ! current_user_can( $this->capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to access Work.', 'core-blueprint-work' ) );
		}
		?>
		<div class="wrap cb-core-wrap cb-work-wrap">
			<h1 class="cb-core-title"><?php esc_html_e( 'Core Blueprint Work', 'core-blueprint-work' ); ?></h1>
			<p class="cb-core-intro"><?php esc_html_e( 'Manage service delivery from catalog and projects through work items, time and billing-ready output.', 'core-blueprint-work' ); ?></p>

			<section class="cb-core-panel">
				<h2><?php esc_html_e( 'Work foundation ready', 'core-blueprint-work' ); ?></h2>
				<p><?php esc_html_e( 'The first-party integration shell is active. Work domains are introduced in separate reviewed phases so CRM and Helpdesk boundaries remain explicit.', 'core-blueprint-work' ); ?></p>
			</section>
		</div>
		<?php
	}
}

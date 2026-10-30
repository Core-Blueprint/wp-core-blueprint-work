<?php
declare(strict_types=1);

namespace CB\Work\Admin;

use CB\Work\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * M3-B1: hidden until JS confirms its in-place transport. WordPress service
 * operations remain linked for recurrence, billing and no-JS parity.
 */
final class WorkspaceEngagementsPanel {
	public static function render(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			return;
		}
		?>
		<section class="cb-work-m3-engagements" data-cb-work-m3-engagements hidden
			data-cb-work-m3-endpoint="<?php echo esc_url( rest_url( 'cb-work/v1/workspace/engagements' ) ); ?>"
			data-cb-work-m3-rule-endpoint="<?php echo esc_url( rest_url( 'cb-work/v1/workspace/engagements/__id__/rules' ) ); ?>"
			data-cb-work-m3-projects="<?php echo esc_url( rest_url( 'cb-work/v1/workspace/projects' ) ); ?>"
			data-cb-work-m3-services="<?php echo esc_url( rest_url( 'cb-work/v1/workspace/services' ) ); ?>"
			data-cb-work-m3-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
			data-cb-work-m3-error="<?php esc_attr_e( 'The change could not be saved.', 'core-blueprint-work' ); ?>"
			data-cb-work-m3-no-projects="<?php esc_attr_e( 'No projects found.', 'core-blueprint-work' ); ?>"
			data-cb-work-m3-no-services="<?php esc_attr_e( 'No services found.', 'core-blueprint-work' ); ?>"
			data-cb-work-m3-project="<?php esc_attr_e( 'Project', 'core-blueprint-work' ); ?>"
			data-cb-work-m3-service="<?php esc_attr_e( 'Service', 'core-blueprint-work' ); ?>"
			data-cb-work-m3-start="<?php esc_attr_e( 'Start date', 'core-blueprint-work' ); ?>"
			data-cb-work-m3-label="<?php esc_attr_e( 'Label', 'core-blueprint-work' ); ?>"
			data-cb-work-m3-add="<?php esc_attr_e( 'Add service', 'core-blueprint-work' ); ?>"
			data-cb-work-m3-create="<?php esc_attr_e( 'Create', 'core-blueprint-work' ); ?>"
			data-cb-work-m3-cancel="<?php esc_attr_e( 'Cancel', 'core-blueprint-work' ); ?>"
			data-cb-work-m3-context-internal="<?php esc_attr_e( 'Internal', 'core-blueprint-work' ); ?>"
			data-cb-work-m3-context-customer="<?php esc_attr_e( 'Customer', 'core-blueprint-work' ); ?>"
			data-cb-work-m3-rule-action="<?php esc_attr_e( 'Link recurring work', 'core-blueprint-work' ); ?>"
			data-cb-work-m3-rule-month="<?php esc_attr_e( 'Period', 'core-blueprint-work' ); ?>"
			data-cb-work-m3-rule-status="<?php esc_attr_e( 'Execution status', 'core-blueprint-work' ); ?>"
			data-cb-work-m3-rule-scheduled="<?php esc_attr_e( 'Scheduled', 'core-blueprint-work' ); ?>"
			data-cb-work-m3-rule-completed="<?php esc_attr_e( 'Completed', 'core-blueprint-work' ); ?>"
			data-cb-work-m3-rule-overdue="<?php esc_attr_e( 'Overdue', 'core-blueprint-work' ); ?>"
			data-cb-work-m3-rule-search="<?php esc_attr_e( 'Search', 'core-blueprint-work' ); ?>"
			data-cb-work-m3-rule-empty="<?php esc_attr_e( 'No recurring Work rules configured.', 'core-blueprint-work' ); ?>"
			data-cb-work-m3-rule-from="<?php esc_attr_e( 'Effective from', 'core-blueprint-work' ); ?>"
			data-cb-work-m3-rule-save="<?php esc_attr_e( 'Save', 'core-blueprint-work' ); ?>"
			data-cb-work-m3-rule-prev="<?php esc_attr_e( 'Previous', 'core-blueprint-work' ); ?>"
			data-cb-work-m3-rule-next="<?php esc_attr_e( 'Next', 'core-blueprint-work' ); ?>"
			data-cb-work-m3-rule-statuses="<?php echo esc_attr( wp_json_encode( ServiceOperations::statuses() ) ); ?>"
			data-cb-work-m3-billing-endpoint="<?php echo esc_url( rest_url( 'cb-work/v1/workspace/engagements/__id__/billing' ) ); ?>"
			data-cb-work-m3-billing-terms-endpoint="<?php echo esc_url( rest_url( 'cb-work/v1/workspace/engagements/__id__/billing/terms' ) ); ?>"
			data-cb-work-m3-billing-action="<?php esc_attr_e( 'Billing registration', 'core-blueprint-work' ); ?>"
			data-cb-work-m3-billing-edit="<?php esc_attr_e( 'Edit billing registration', 'core-blueprint-work' ); ?>"
			data-cb-work-m3-billing-na="<?php esc_attr_e( 'Not applicable', 'core-blueprint-work' ); ?>"
			data-cb-work-m3-billing-period="<?php esc_attr_e( 'Period', 'core-blueprint-work' ); ?>"
			data-cb-work-m3-billing-state="<?php esc_attr_e( 'Registration', 'core-blueprint-work' ); ?>"
			data-cb-work-m3-billing-date="<?php esc_attr_e( 'Invoice date', 'core-blueprint-work' ); ?>"
			data-cb-work-m3-billing-ref="<?php esc_attr_e( 'External reference', 'core-blueprint-work' ); ?>"
			data-cb-work-m3-billing-save="<?php esc_attr_e( 'Save', 'core-blueprint-work' ); ?>"
			data-cb-work-m3-billing-setup="<?php esc_attr_e( 'Track external invoicing', 'core-blueprint-work' ); ?>"
			data-cb-work-m3-billing-frequency="<?php esc_attr_e( 'Billing frequency', 'core-blueprint-work' ); ?>"
			data-cb-work-m3-billing-timing="<?php esc_attr_e( 'Invoice timing', 'core-blueprint-work' ); ?>"
			data-cb-work-m3-billing-start="<?php esc_attr_e( 'Start date', 'core-blueprint-work' ); ?>"
			data-cb-work-m3-billing-statuses="<?php echo esc_attr( wp_json_encode( ServiceOperations::billing_statuses() ) ); ?>"
			data-cb-work-m3-billing-frequencies="<?php echo esc_attr( wp_json_encode( [
				'monthly' => __( 'Monthly', 'core-blueprint-work' ),
				'weekly' => __( 'Weekly', 'core-blueprint-work' ),
				'quarterly' => __( 'Quarterly', 'core-blueprint-work' ),
				'yearly' => __( 'Yearly', 'core-blueprint-work' ),
			] ) ); ?>"
			data-cb-work-m3-billing-timings="<?php echo esc_attr( wp_json_encode( [
				'arrears' => __( 'After service', 'core-blueprint-work' ),
				'advance' => __( 'In advance', 'core-blueprint-work' ),
			] ) ); ?>">
			<header class="cb-work-m3-engagements__header">
				<h2><?php esc_html_e( 'Service Operations', 'core-blueprint-work' ); ?></h2>
				<button type="button" class="button" data-cb-work-m3-engagements-close><?php esc_html_e( 'Close', 'core-blueprint-work' ); ?></button>
			</header>
			<form data-cb-work-m3-engagements-filter role="search">
				<label><?php esc_html_e( 'Search', 'core-blueprint-work' ); ?>
					<input type="search" name="search" maxlength="160" autocomplete="off">
				</label>
				<button type="submit" class="button"><?php esc_html_e( 'Search', 'core-blueprint-work' ); ?></button>
				<button type="button" class="button button-primary" data-cb-work-m3-engagements-add hidden><?php esc_html_e( 'Add service', 'core-blueprint-work' ); ?></button>
			</form>
			<p role="alert" data-cb-work-m3-engagements-error hidden><?php esc_html_e( 'Work storage unavailable', 'core-blueprint-work' ); ?></p>
			<p data-cb-work-m3-engagements-empty hidden><?php esc_html_e( 'No matching services. Adjust your filters or add a service.', 'core-blueprint-work' ); ?></p>
			<ul class="cb-work-m3-engagements__list" data-cb-work-m3-engagements-list></ul>
			<div class="cb-work-m3-engagements__paging" data-cb-work-m3-engagements-paging hidden>
				<button type="button" class="button" data-cb-work-m3-engagements-prev><?php esc_html_e( 'Previous', 'core-blueprint-work' ); ?></button>
				<output data-cb-work-m3-engagements-page aria-live="polite"></output>
				<button type="button" class="button" data-cb-work-m3-engagements-next><?php esc_html_e( 'Next', 'core-blueprint-work' ); ?></button>
			</div>
			<a class="button" href="<?php echo esc_url( add_query_arg( [ 'page' => Menu::SERVICE_OPERATIONS_SLUG ], admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Service Operations', 'core-blueprint-work' ); ?></a>
		</section>
		<?php
	}

	private function __construct() {}
}

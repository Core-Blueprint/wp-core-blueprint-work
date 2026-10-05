<?php
declare(strict_types=1);

namespace CB\Work\Admin;

use CB\Work\Capabilities;
use CB\Work\Database\Schema;
use CB\Work\Domain\BillingDisposition;
use CB\Work\Domain\WorkContext;
use CB\Work\Domain\RecurrenceSchedule;
use CB\Work\Domain\WorkItemPriority;
use CB\Work\Integration\CRMCustomers;
use CB\Work\PublicApi\Services;
use CB\Work\Recurrence\Scheduler;
use CB\Work\Repository\Projects;
use CB\Work\Repository\RecurrenceOccurrences;
use CB\Work\Repository\RecurrenceRules;
use CB\Work\Repository\WorkTypes;

defined( 'ABSPATH' ) || exit;

final class Recurrence {
	public static function render(): void {
		self::guard();
		$edit_id = isset( $_GET['rule_id'] ) ? absint( $_GET['rule_id'] ) : 0;
		$rule    = $edit_id > 0 ? RecurrenceRules::get( $edit_id ) : null;
		$editing = is_array( $rule );
		$locked  = $editing && RecurrenceOccurrences::count_for_rule( $edit_id ) > 0;

		$projects = Projects::all( 500 );
		$services = Services::all( 250 );
		$types    = WorkTypes::all();
		$rules    = RecurrenceRules::all( false, 500 );

		$selected_customer = null;
		if ( $editing ) {
			$selected_customer = CRMCustomers::selected(
				(string) ( $rule['customer_provider'] ?? '' ),
				(string) ( $rule['customer_type'] ?? '' ),
				(string) ( $rule['customer_id'] ?? '' )
			);
		}

		$title             = $editing ? (string) $rule['title'] : '';
		$description       = $editing ? (string) $rule['description'] : '';
		$context           = $editing ? (string) ( $rule['work_context'] ?? '' ) : WorkContext::INTERNAL;
		$project_id        = $editing ? (int) ( $rule['project_id'] ?? 0 ) : 0;
		$service_id        = $editing ? (int) ( $rule['service_id'] ?? 0 ) : 0;
		$work_type_id      = $editing ? (int) ( $rule['work_type_id'] ?? 0 ) : 0;
		$priority          = $editing ? (string) $rule['priority'] : WorkItemPriority::NORMAL;
		$estimated_minutes = $editing ? max( 0, (int) ( $rule['estimated_minutes'] ?? 0 ) ) : 0;
		$billing           = $editing ? (string) $rule['billing_disposition'] : '';
		$frequency         = $editing ? (string) $rule['frequency'] : RecurrenceSchedule::WEEKLY;
		$interval          = $editing ? (int) $rule['interval_count'] : 1;
		$start_on          = $editing ? (string) $rule['start_on'] : current_time( 'Y-m-d' );
		$end_on            = $editing && null !== $rule['end_on'] ? (string) $rule['end_on'] : '';
		$create_ahead      = $editing ? (int) $rule['create_ahead_days'] : 14;
		$due_offset        = $editing ? (int) $rule['due_offset_days'] : 0;
		$active            = ! $editing || ! empty( $rule['is_active'] );
		$assignees         = $editing ? (array) $rule['assigned_user_ids'] : [];
		?>
		<div class="wrap cb-work-recurrence-page">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Recurring Work', 'core-blueprint-work' ); ?></h1>
			<?php if ( $editing ) : ?>
				<a class="page-title-action" href="<?php echo esc_url( self::url() ); ?>"><?php esc_html_e( 'Add New Rule', 'core-blueprint-work' ); ?></a>
			<?php endif; ?>
			<hr class="wp-header-end">
			<p class="description"><?php esc_html_e( 'Create real Work Items from reusable schedules. Commercial recurring service pricing is separate from Work Item recurrence.', 'core-blueprint-work' ); ?></p>
			<?php self::render_notice(); ?>
			<?php if ( ! self::schema_ready() ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'Work storage is not ready yet. Recurring Work becomes available after the Work schema is installed.', 'core-blueprint-work' ); ?></p></div>
			</div>
			<?php return; ?>
			<?php endif; ?>

			<div class="card">
				<h2><?php echo esc_html( $editing ? __( 'Edit Recurring Work Rule', 'core-blueprint-work' ) : __( 'Add Recurring Work Rule', 'core-blueprint-work' ) ); ?></h2>
				<?php if ( $locked ) : ?>
					<div class="notice notice-info inline"><p><?php esc_html_e( 'This rule already has occurrence history. Frequency, interval, start date and end date are locked so generated history cannot be rewound. Template fields and planning offsets remain editable.', 'core-blueprint-work' ); ?></p></div>
				<?php endif; ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo $editing ? 'cb_work_update_recurrence_rule' : 'cb_work_create_recurrence_rule'; ?>">
					<?php if ( $editing ) : ?><input type="hidden" name="rule_id" value="<?php echo esc_attr( (string) $edit_id ); ?>"><?php endif; ?>
					<?php wp_nonce_field( $editing ? 'cb_work_update_recurrence_rule_' . $edit_id : 'cb_work_create_recurrence_rule' ); ?>
					<table class="form-table" role="presentation"><tbody>
					<tr><th scope="row"><label for="cb-work-recurrence-title"><?php esc_html_e( 'Title', 'core-blueprint-work' ); ?></label></th><td><input id="cb-work-recurrence-title" class="regular-text" type="text" name="recurrence[title]" value="<?php echo esc_attr( $title ); ?>" required></td></tr>
					<tr><th scope="row"><label for="cb-work-recurrence-description"><?php esc_html_e( 'Description', 'core-blueprint-work' ); ?></label></th><td><textarea id="cb-work-recurrence-description" class="large-text" rows="4" name="recurrence[description]"><?php echo esc_textarea( $description ); ?></textarea></td></tr>
					<tr data-cb-work-context-row><th scope="row"><label for="cb-work-recurrence-context"><?php esc_html_e( 'Work context', 'core-blueprint-work' ); ?></label></th><td><select id="cb-work-recurrence-context" name="recurrence[work_context]" data-cb-work-context-select required><?php if ( '' === $context ) : ?><option value="" selected><?php esc_html_e( 'Needs classification', 'core-blueprint-work' ); ?></option><?php endif; ?><option value="<?php echo esc_attr( WorkContext::INTERNAL ); ?>" <?php selected( $context, WorkContext::INTERNAL ); ?>><?php esc_html_e( 'Internal', 'core-blueprint-work' ); ?></option><option value="<?php echo esc_attr( WorkContext::CUSTOMER ); ?>" <?php selected( $context, WorkContext::CUSTOMER ); ?>><?php esc_html_e( 'Customer', 'core-blueprint-work' ); ?></option></select><p class="description"><?php esc_html_e( 'When a Project is selected, the Project context and customer are authoritative.', 'core-blueprint-work' ); ?></p></td></tr>
					<tr data-cb-work-customer-row><th scope="row"><?php esc_html_e( 'Customer', 'core-blueprint-work' ); ?></th><td><?php Pickers::customer( 'recurrence[customer_object_id]', 'cb-work-recurrence-customer', $selected_customer ); ?></td></tr>
					<tr><th scope="row"><label for="cb-work-recurrence-project"><?php esc_html_e( 'Project', 'core-blueprint-work' ); ?></label></th><td><select id="cb-work-recurrence-project" name="recurrence[project_id]" data-cb-work-project-select><option value="0"><?php esc_html_e( 'No Project', 'core-blueprint-work' ); ?></option><?php foreach ( $projects as $project ) : ?><option value="<?php echo esc_attr( (string) $project['id'] ); ?>" data-cb-work-context="<?php echo esc_attr( (string) ( $project['work_context'] ?? '' ) ); ?>" <?php selected( $project_id, (int) $project['id'] ); ?>><?php echo esc_html( (string) $project['title'] ); ?></option><?php endforeach; ?></select></td></tr>
					<tr><th scope="row"><label for="cb-work-recurrence-service"><?php esc_html_e( 'Service', 'core-blueprint-work' ); ?></label></th><td><select id="cb-work-recurrence-service" name="recurrence[service_id]"><option value="0"><?php esc_html_e( 'No Service', 'core-blueprint-work' ); ?></option><?php foreach ( $services as $service ) : ?><option value="<?php echo esc_attr( (string) $service['id'] ); ?>" <?php selected( $service_id, (int) $service['id'] ); ?>><?php echo esc_html( (string) $service['title'] ); ?></option><?php endforeach; ?></select></td></tr>
					<tr><th scope="row"><label for="cb-work-recurrence-type"><?php esc_html_e( 'Work Type', 'core-blueprint-work' ); ?></label></th><td><select id="cb-work-recurrence-type" name="recurrence[work_type_id]"><option value="0"><?php esc_html_e( 'No Work Type', 'core-blueprint-work' ); ?></option><?php foreach ( $types as $type ) : ?><option value="<?php echo esc_attr( (string) $type['id'] ); ?>" <?php selected( $work_type_id, (int) $type['id'] ); ?>><?php echo esc_html( (string) $type['label'] ); ?></option><?php endforeach; ?></select></td></tr>
					<tr><th scope="row"><label for="cb-work-recurrence-priority"><?php esc_html_e( 'Priority', 'core-blueprint-work' ); ?></label></th><td><select id="cb-work-recurrence-priority" name="recurrence[priority]"><?php foreach ( WorkItemPriority::all() as $value ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $priority, $value ); ?>><?php echo esc_html( ucfirst( str_replace( '_', ' ', $value ) ) ); ?></option><?php endforeach; ?></select></td></tr>
					<tr><th scope="row"><label for="cb-work-recurrence-estimate"><?php esc_html_e( 'Estimated time (minutes)', 'core-blueprint-work' ); ?></label></th><td><input id="cb-work-recurrence-estimate" class="small-text" type="number" min="0" step="5" name="recurrence[estimated_minutes]" value="<?php echo esc_attr( (string) $estimated_minutes ); ?>"><p class="description"><?php esc_html_e( 'Planning estimate inherited by future generated Work Items. Registered time remains separate.', 'core-blueprint-work' ); ?></p></td></tr>
					<tr><th scope="row"><label for="cb-work-recurrence-billing"><?php esc_html_e( 'Billing classification', 'core-blueprint-work' ); ?></label></th><td><select id="cb-work-recurrence-billing" name="recurrence[billing_disposition]" data-cb-work-billing-select><option value=""><?php esc_html_e( 'Not classified', 'core-blueprint-work' ); ?></option><?php foreach ( BillingDisposition::all() as $value ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $billing, $value ); ?>><?php echo esc_html( ucfirst( str_replace( '_', ' ', $value ) ) ); ?></option><?php endforeach; ?></select></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Assignees', 'core-blueprint-work' ); ?></th><td><?php Pickers::assignees( 'recurrence[assigned_user_ids]', 'cb-work-recurrence-assignees', $assignees ); ?></td></tr>
					<?php if ( $locked ) : ?>
						<?php
						$end_label = '';
						if ( '' !== $end_on ) {
							/* translators: %s: recurrence end date. */
							$end_label = sprintf( __( ', end %s', 'core-blueprint-work' ), $end_on );
						}
						?>
						<tr><th scope="row"><?php esc_html_e( 'Schedule', 'core-blueprint-work' ); ?></th><td><strong><?php echo esc_html( self::schedule_label( $rule ) ); ?></strong><p class="description"><?php
						/* translators: 1: recurrence start date, 2: optional translated end-date suffix. */
						echo esc_html( sprintf( __( 'Start %1$s%2$s.', 'core-blueprint-work' ), $start_on, $end_label ) );
						?></p></td></tr>
					<?php else : ?>
						<tr><th scope="row"><label for="cb-work-recurrence-frequency"><?php esc_html_e( 'Frequency', 'core-blueprint-work' ); ?></label></th><td><select id="cb-work-recurrence-frequency" name="recurrence[frequency]"><?php foreach ( RecurrenceSchedule::frequencies() as $value ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $frequency, $value ); ?>><?php echo esc_html( ucfirst( $value ) ); ?></option><?php endforeach; ?></select> <label><?php esc_html_e( 'Every', 'core-blueprint-work' ); ?> <input class="small-text" type="number" min="1" max="999" name="recurrence[interval_count]" value="<?php echo esc_attr( (string) $interval ); ?>"></label></td></tr>
						<tr><th scope="row"><label for="cb-work-recurrence-start"><?php esc_html_e( 'Start date', 'core-blueprint-work' ); ?></label></th><td><input id="cb-work-recurrence-start" type="date" name="recurrence[start_on]" value="<?php echo esc_attr( $start_on ); ?>" required></td></tr>
						<tr><th scope="row"><label for="cb-work-recurrence-end"><?php esc_html_e( 'End date', 'core-blueprint-work' ); ?></label></th><td><input id="cb-work-recurrence-end" type="date" name="recurrence[end_on]" value="<?php echo esc_attr( $end_on ); ?>"><p class="description"><?php esc_html_e( 'Optional. Leave empty for an open-ended rule.', 'core-blueprint-work' ); ?></p></td></tr>
					<?php endif; ?>
					<tr><th scope="row"><label for="cb-work-recurrence-ahead"><?php esc_html_e( 'Create ahead', 'core-blueprint-work' ); ?></label></th><td><input id="cb-work-recurrence-ahead" class="small-text" type="number" min="0" max="3650" name="recurrence[create_ahead_days]" value="<?php echo esc_attr( (string) $create_ahead ); ?>"> <?php esc_html_e( 'days', 'core-blueprint-work' ); ?><p class="description"><?php esc_html_e( 'Work Items are generated when an occurrence enters this planning horizon.', 'core-blueprint-work' ); ?></p></td></tr>
					<tr><th scope="row"><label for="cb-work-recurrence-due"><?php esc_html_e( 'Due offset', 'core-blueprint-work' ); ?></label></th><td><input id="cb-work-recurrence-due" class="small-text" type="number" min="0" max="3650" name="recurrence[due_offset_days]" value="<?php echo esc_attr( (string) $due_offset ); ?>"> <?php esc_html_e( 'days after scheduled date', 'core-blueprint-work' ); ?></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Status', 'core-blueprint-work' ); ?></th><td><label><input type="checkbox" name="recurrence[is_active]" value="1" <?php checked( $active ); ?>> <?php esc_html_e( 'Active', 'core-blueprint-work' ); ?></label></td></tr>
					</tbody></table>
					<?php submit_button( $editing ? __( 'Save Recurring Work Rule', 'core-blueprint-work' ) : __( 'Add Recurring Work Rule', 'core-blueprint-work' ) ); ?>
				</form>
			</div>

			<h2><?php esc_html_e( 'Recurring Work Rules', 'core-blueprint-work' ); ?></h2>
			<p class="description"><?php
			/* translators: %s: next WordPress cron timestamp, or a not-scheduled label. */
			echo esc_html( sprintf( __( 'Generator hook: hourly. Next WordPress cron timestamp: %s', 'core-blueprint-work' ), self::next_cron_label() ) );
			?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:12px 0;">
				<input type="hidden" name="action" value="cb_work_run_recurrence_generator">
				<?php wp_nonce_field( 'cb_work_run_recurrence_generator' ); ?>
				<?php submit_button( __( 'Run Generator Now', 'core-blueprint-work' ), 'secondary', 'submit', false ); ?>
			</form>

			<?php if ( [] === $rules ) : ?>
				<p><?php esc_html_e( 'No recurring Work rules configured.', 'core-blueprint-work' ); ?></p>
			<?php else : ?>
				<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Rule', 'core-blueprint-work' ); ?></th><th><?php esc_html_e( 'Schedule', 'core-blueprint-work' ); ?></th><th><?php esc_html_e( 'Next occurrence', 'core-blueprint-work' ); ?></th><th><?php esc_html_e( 'Generated', 'core-blueprint-work' ); ?></th><th><?php esc_html_e( 'Status', 'core-blueprint-work' ); ?></th><th><?php esc_html_e( 'Actions', 'core-blueprint-work' ); ?></th></tr></thead><tbody>
				<?php foreach ( $rules as $configured ) : ?>
					<?php $configured_id = (int) $configured['id']; $count = RecurrenceOccurrences::count_for_rule( $configured_id ); ?>
					<tr>
						<td><strong><?php echo esc_html( (string) $configured['title'] ); ?></strong></td>
						<td><?php echo esc_html( self::schedule_label( $configured ) ); ?></td>
						<td><?php echo esc_html( null === $configured['next_occurrence_on'] ? __( 'Complete', 'core-blueprint-work' ) : (string) $configured['next_occurrence_on'] ); ?></td>
						<td><?php echo esc_html( (string) $count ); ?></td>
						<td><?php echo ! empty( $configured['is_active'] ) ? esc_html__( 'Active', 'core-blueprint-work' ) : esc_html__( 'Inactive', 'core-blueprint-work' ); ?></td>
						<td><a class="button button-small" href="<?php echo esc_url( self::url( [ 'rule_id' => $configured_id ] ) ); ?>"><?php esc_html_e( 'Edit', 'core-blueprint-work' ); ?></a> <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block"><input type="hidden" name="action" value="cb_work_toggle_recurrence_rule"><input type="hidden" name="rule_id" value="<?php echo esc_attr( (string) $configured_id ); ?>"><input type="hidden" name="active" value="<?php echo empty( $configured['is_active'] ) ? '1' : '0'; ?>"><?php wp_nonce_field( 'cb_work_toggle_recurrence_rule_' . $configured_id ); ?><button class="button button-small" type="submit"><?php echo empty( $configured['is_active'] ) ? esc_html__( 'Activate', 'core-blueprint-work' ) : esc_html__( 'Deactivate', 'core-blueprint-work' ); ?></button></form></td>
					</tr>
				<?php endforeach; ?>
				</tbody></table>
			<?php endif; ?>
		</div>
		<?php
	}

	/** @param array<string,mixed> $rule */
	private static function schedule_label( array $rule ): string {
		$interval  = max( 1, (int) ( $rule['interval_count'] ?? 1 ) );
		$frequency = (string) ( $rule['frequency'] ?? '' );
		$unit = match ( $frequency ) {
			RecurrenceSchedule::DAILY   => 1 === $interval ? __( 'day', 'core-blueprint-work' ) : __( 'days', 'core-blueprint-work' ),
			RecurrenceSchedule::WEEKLY  => 1 === $interval ? __( 'week', 'core-blueprint-work' ) : __( 'weeks', 'core-blueprint-work' ),
			RecurrenceSchedule::MONTHLY => 1 === $interval ? __( 'month', 'core-blueprint-work' ) : __( 'months', 'core-blueprint-work' ),
			RecurrenceSchedule::YEARLY  => 1 === $interval ? __( 'year', 'core-blueprint-work' ) : __( 'years', 'core-blueprint-work' ),
			default                     => __( 'interval', 'core-blueprint-work' ),
		};
		/* translators: 1: recurrence interval count, 2: translated recurrence unit. */
		return sprintf( __( 'Every %1$d %2$s', 'core-blueprint-work' ), $interval, $unit );
	}

	/** @param array<string,mixed> $args */
	private static function url( array $args = [] ): string {
		return add_query_arg( [ 'page' => Menu::RECURRENCE_SLUG, ...$args ], admin_url( 'admin.php' ) );
	}

	private static function next_cron_label(): string {
		$timestamp = wp_next_scheduled( Scheduler::HOOK );
		return false === $timestamp ? __( 'not scheduled yet', 'core-blueprint-work' ) : wp_date( 'Y-m-d H:i', $timestamp );
	}

	private static function render_notice(): void {
		$notice = isset( $_GET['cb-work-notice'] ) ? sanitize_key( (string) wp_unslash( $_GET['cb-work-notice'] ) ) : '';
		$messages = [
			'recurrence-created'        => [ 'success', __( 'Recurring Work rule created.', 'core-blueprint-work' ) ],
			'recurrence-updated'        => [ 'success', __( 'Recurring Work rule updated.', 'core-blueprint-work' ) ],
			'recurrence-status-updated' => [ 'success', __( 'Recurring Work rule status updated.', 'core-blueprint-work' ) ],
			'recurrence-invalid'        => [ 'error', __( 'The Recurring Work rule could not be saved. Check the supplied values and whether its schedule is already locked by history.', 'core-blueprint-work' ) ],
			'recurrence-run'            => [ 'success', __( 'Recurring Work generator finished.', 'core-blueprint-work' ) ],
		];
		if ( ! isset( $messages[ $notice ] ) ) {
			return;
		}
		[ $type, $message ] = $messages[ $notice ];
		if ( 'recurrence-run' === $notice ) {
			$generated = isset( $_GET['generated'] ) ? absint( $_GET['generated'] ) : 0;
			$recovered = isset( $_GET['recovered'] ) ? absint( $_GET['recovered'] ) : 0;
			$failed    = isset( $_GET['failed'] ) ? absint( $_GET['failed'] ) : 0;
			/* translators: 1: generated count, 2: recovered count, 3: failed count. */
			$message .= ' ' . sprintf( __( 'Generated: %1$d · recovered: %2$d · failed: %3$d.', 'core-blueprint-work' ), $generated, $recovered, $failed );
		}
		printf( '<div class="notice notice-%1$s inline"><p>%2$s</p></div>', esc_attr( $type ), esc_html( $message ) );
	}

	private static function guard(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Work.', 'core-blueprint-work' ) );
		}
	}

	private static function schema_ready(): bool {
		return defined( 'CB_WORK_SCHEMA_VERSION' ) && CB_WORK_SCHEMA_VERSION === (string) get_option( Schema::OPTION, '0' );
	}
}

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
	/** Load the workspace styling in wp-admin head, before page rendering. */
	public static function enqueue_assets(): void {
		$page = isset( $_GET['page'] ) ? sanitize_key( (string) wp_unslash( $_GET['page'] ) ) : '';
		if ( Menu::RECURRENCE_SLUG !== $page ) {
			return;
		}

		$stylesheet = CB_WORK_DIR . 'assets/css/recurrence-workspace.css';
		$hash       = is_readable( $stylesheet ) ? hash_file( 'sha256', $stylesheet ) : false;
		$version    = false !== $hash ? substr( $hash, 0, 12 ) : CB_WORK_VERSION;
		wp_enqueue_style( 'cb-work-recurrence-workspace', CB_WORK_URL . 'assets/css/recurrence-workspace.css', [], $version );
		$script = CB_WORK_DIR . 'assets/recurrence-workspace.js';
		if ( is_readable( $script ) ) {
			$script_hash = hash_file( 'sha256', $script );
			wp_enqueue_script( 'cb-work-recurrence-ui', CB_WORK_URL . 'assets/recurrence-workspace.js', [], false !== $script_hash ? substr( $script_hash, 0, 12 ) : CB_WORK_VERSION, true );
			wp_localize_script( 'cb-work-recurrence-ui', 'cbWorkRecurrenceUi', [
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'previewNonce' => wp_create_nonce( 'cb_work_recurrence_preview' ),
				'error' => __( 'The Recurring Work rule could not be saved. Check the supplied values and whether its schedule is already locked by history.', 'core-blueprint-work' ),
				'loading' => __( 'Building server preview…', 'core-blueprint-work' ),
				'saveFirst' => __( 'Save changes', 'core-blueprint-work' ),
			] );
		}

	}

	public static function render(): void {
		self::guard();
		$edit_id = isset( $_GET['rule_id'] ) ? absint( $_GET['rule_id'] ) : 0;
		$rule    = $edit_id > 0 ? RecurrenceRules::get( $edit_id ) : null;
		$editing = is_array( $rule );
		$show_editor = $editing || ( isset( $_GET['mode'] ) && 'add' === sanitize_key( (string) wp_unslash( $_GET['mode'] ) ) );
		$locked  = $editing && RecurrenceOccurrences::count_for_rule( $edit_id ) > 0;

		$projects = Projects::all( 500 );
		$services = Services::all( 250 );
		$types    = WorkTypes::all();
		$filter_search = isset( $_GET['cb_search'] ) && is_scalar( $_GET['cb_search'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['cb_search'] ) ) : '';
		$filter_status = isset( $_GET['cb_status'] ) ? sanitize_key( (string) wp_unslash( $_GET['cb_status'] ) ) : '';
		$filter_context = isset( $_GET['cb_context'] ) ? sanitize_key( (string) wp_unslash( $_GET['cb_context'] ) ) : '';
		$filter_project = isset( $_GET['cb_project'] ) ? absint( $_GET['cb_project'] ) : 0;
		$filter_sort = isset( $_GET['cb_sort'] ) ? sanitize_key( (string) wp_unslash( $_GET['cb_sort'] ) ) : 'next_occurrence';
		$filter_direction = isset( $_GET['cb_dir'] ) && 'desc' === sanitize_key( (string) wp_unslash( $_GET['cb_dir'] ) ) ? 'desc' : 'asc';
		$filter_page = isset( $_GET['cb_page'] ) ? max( 1, absint( $_GET['cb_page'] ) ) : 1;
		$list_args = array_filter( [
			'cb_search' => $filter_search,
			'cb_status' => $filter_status,
			'cb_context' => $filter_context,
			'cb_project' => $filter_project,
			'cb_sort' => $filter_sort,
			'cb_dir' => $filter_direction,
		], static fn( mixed $value ): bool => '' !== $value && 0 !== $value );
		$listing = $show_editor ? [ 'rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'per_page' => 25 ] : RecurrenceRules::search_page( [
			'search' => $filter_search, 'status' => $filter_status, 'context' => $filter_context,
			'project_id' => $filter_project, 'sort' => $filter_sort,
			'direction' => $filter_direction, 'page' => $filter_page,
		] );
		$rules = $listing['rows'];
		$project_titles = [];
		foreach ( $projects as $project ) {
			$project_titles[ (int) $project['id'] ] = (string) $project['title'];
		}

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
		$active            = $editing && ! empty( $rule['is_active'] );
		$assignees         = $editing ? (array) $rule['assigned_user_ids'] : [];
		?>
		<div class="wrap cb-work-recurrence-page">
			<div class="cb-work-recurrence-header"><div><h1><?php esc_html_e( 'Recurring Work', 'core-blueprint-work' ); ?></h1><p class="description"><?php esc_html_e( 'Create real Work Items from reusable schedules. Commercial recurring service pricing is separate from Work Item recurrence.', 'core-blueprint-work' ); ?></p></div><div class="cb-work-recurrence-header-actions"><a class="button <?php echo $show_editor ? 'button-secondary' : 'button-primary'; ?>" href="<?php echo esc_url( $show_editor ? self::url() : self::url( [ 'mode' => 'add' ] ) ); ?>"><?php echo $show_editor ? esc_html__( 'Recurring Work Rules', 'core-blueprint-work' ) : esc_html__( 'Add Recurring Work Rule', 'core-blueprint-work' ); ?></a></div></div>
			<hr class="wp-header-end">
			<?php self::render_notice(); ?>
			<?php if ( ! self::schema_ready() ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'Work storage is not ready yet. Recurring Work becomes available after the Work schema is installed.', 'core-blueprint-work' ); ?></p></div>
			</div>
			<?php return; ?>
			<?php endif; ?>

			<?php if ( $show_editor ) : ?>
			<div class="cb-work-recurrence-editor">
				<h2><?php echo esc_html( $editing ? __( 'Edit Recurring Work Rule', 'core-blueprint-work' ) : __( 'Add Recurring Work Rule', 'core-blueprint-work' ) ); ?></h2>
				<?php if ( $locked ) : ?>
					<div class="notice notice-info inline"><p><?php esc_html_e( 'This rule already has occurrence history. Frequency, interval, start date and end date are locked so generated history cannot be rewound. Template fields and planning offsets remain editable.', 'core-blueprint-work' ); ?></p></div>
				<?php endif; ?>
				<div class="cb-work-recurrence-builder">
				<form class="cb-work-recurrence-builder-form" data-cb-recurrence-editor data-rule-id="<?php echo esc_attr( (string) $edit_id ); ?>" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo $editing ? 'cb_work_update_recurrence_rule' : 'cb_work_create_recurrence_rule'; ?>">
					<?php if ( $editing ) : ?><input type="hidden" name="rule_id" value="<?php echo esc_attr( (string) $edit_id ); ?>"><?php endif; ?>
					<?php wp_nonce_field( $editing ? 'cb_work_update_recurrence_rule_' . $edit_id : 'cb_work_create_recurrence_rule' ); ?>
					<section class="cb-work-recurrence-section cb-work-recurrence-section--rule"><h3><?php esc_html_e( 'Rule', 'core-blueprint-work' ); ?></h3><table class="form-table cb-work-recurrence-fields" role="presentation"><tbody>
					<tr class="cb-work-recurrence-field--rule"><th scope="row"><label for="cb-work-recurrence-title"><?php esc_html_e( 'Title', 'core-blueprint-work' ); ?></label></th><td><input id="cb-work-recurrence-title" class="regular-text" type="text" name="recurrence[title]" value="<?php echo esc_attr( $title ); ?>" required></td></tr>
					<tr class="cb-work-recurrence-field--rule"><th scope="row"><label for="cb-work-recurrence-description"><?php esc_html_e( 'Description', 'core-blueprint-work' ); ?></label></th><td><textarea id="cb-work-recurrence-description" class="large-text" rows="4" name="recurrence[description]"><?php echo esc_textarea( $description ); ?></textarea></td></tr>
					</tbody></table></section><section class="cb-work-recurrence-section cb-work-recurrence-section--defaults"><h3><?php esc_html_e( 'Work Items', 'core-blueprint-work' ); ?></h3><button class="button button-secondary cb-work-recurrence-advanced-toggle" type="button" aria-expanded="<?php echo $editing ? 'true' : 'false'; ?>" data-cb-work-advanced-toggle><?php esc_html_e( 'Work Item Details', 'core-blueprint-work' ); ?> <span aria-hidden="true">▾</span></button><table class="form-table cb-work-recurrence-fields" role="presentation"><tbody><tr class="cb-work-recurrence-field--wide"><th scope="row"><label for="cb-work-recurrence-project"><?php esc_html_e( 'Project', 'core-blueprint-work' ); ?></label></th><td><select id="cb-work-recurrence-project" name="recurrence[project_id]" data-cb-work-project-select><option value="0"><?php esc_html_e( 'No Project', 'core-blueprint-work' ); ?></option><?php foreach ( $projects as $project ) : ?><option value="<?php echo esc_attr( (string) $project['id'] ); ?>" data-cb-work-context="<?php echo esc_attr( (string) ( $project['work_context'] ?? '' ) ); ?>" <?php selected( $project_id, (int) $project['id'] ); ?>><?php echo esc_html( (string) $project['title'] ); ?></option><?php endforeach; ?></select></td></tr>
					<tr data-cb-work-context-row><th scope="row"><label for="cb-work-recurrence-context"><?php esc_html_e( 'Work context', 'core-blueprint-work' ); ?></label></th><td><select id="cb-work-recurrence-context" name="recurrence[work_context]" data-cb-work-context-select required><?php if ( '' === $context ) : ?><option value="" selected><?php esc_html_e( 'Needs classification', 'core-blueprint-work' ); ?></option><?php endif; ?><option value="<?php echo esc_attr( WorkContext::INTERNAL ); ?>" <?php selected( $context, WorkContext::INTERNAL ); ?>><?php esc_html_e( 'Internal', 'core-blueprint-work' ); ?></option><option value="<?php echo esc_attr( WorkContext::CUSTOMER ); ?>" <?php selected( $context, WorkContext::CUSTOMER ); ?>><?php esc_html_e( 'Customer', 'core-blueprint-work' ); ?></option></select><p class="description"><?php esc_html_e( 'When a Project is selected, the Project context and customer are authoritative.', 'core-blueprint-work' ); ?></p></td></tr>
					<tr data-cb-work-customer-row><th scope="row"><?php esc_html_e( 'Customer', 'core-blueprint-work' ); ?></th><td><?php Pickers::customer( 'recurrence[customer_object_id]', 'cb-work-recurrence-customer', $selected_customer ); ?></td></tr>
					
					<tr><th scope="row"><label for="cb-work-recurrence-service"><?php esc_html_e( 'Service', 'core-blueprint-work' ); ?></label></th><td><select id="cb-work-recurrence-service" name="recurrence[service_id]"><option value="0"><?php esc_html_e( 'No Service', 'core-blueprint-work' ); ?></option><?php foreach ( $services as $service ) : ?><option value="<?php echo esc_attr( (string) $service['id'] ); ?>" <?php selected( $service_id, (int) $service['id'] ); ?>><?php echo esc_html( (string) $service['title'] ); ?></option><?php endforeach; ?></select></td></tr>
					<tr><th scope="row"><label for="cb-work-recurrence-type"><?php esc_html_e( 'Work Type', 'core-blueprint-work' ); ?></label></th><td><select id="cb-work-recurrence-type" name="recurrence[work_type_id]"><option value="0"><?php esc_html_e( 'No Work Type', 'core-blueprint-work' ); ?></option><?php foreach ( $types as $type ) : ?><option value="<?php echo esc_attr( (string) $type['id'] ); ?>" <?php selected( $work_type_id, (int) $type['id'] ); ?>><?php echo esc_html( (string) $type['label'] ); ?></option><?php endforeach; ?></select></td></tr>
					<tr data-cb-work-advanced><th scope="row"><label for="cb-work-recurrence-priority"><?php esc_html_e( 'Priority', 'core-blueprint-work' ); ?></label></th><td><select id="cb-work-recurrence-priority" name="recurrence[priority]"><?php foreach ( WorkItemPriority::all() as $value ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $priority, $value ); ?>><?php echo esc_html( ucfirst( str_replace( '_', ' ', $value ) ) ); ?></option><?php endforeach; ?></select></td></tr>
					<tr data-cb-work-advanced><th scope="row"><label for="cb-work-recurrence-estimate"><?php esc_html_e( 'Estimated time (minutes)', 'core-blueprint-work' ); ?></label></th><td><input id="cb-work-recurrence-estimate" class="small-text" type="number" min="0" step="5" name="recurrence[estimated_minutes]" value="<?php echo esc_attr( (string) $estimated_minutes ); ?>"><p class="description"><?php esc_html_e( 'Planning estimate inherited by future generated Work Items. Registered time remains separate.', 'core-blueprint-work' ); ?></p></td></tr>
					<tr class="cb-work-recurrence-field--wide" data-cb-work-advanced><th scope="row"><label for="cb-work-recurrence-billing"><?php esc_html_e( 'Billing classification', 'core-blueprint-work' ); ?></label></th><td><select id="cb-work-recurrence-billing" name="recurrence[billing_disposition]" data-cb-work-billing-select><option value=""><?php esc_html_e( 'Not classified', 'core-blueprint-work' ); ?></option><?php foreach ( BillingDisposition::all() as $value ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $billing, $value ); ?>><?php echo esc_html( ucfirst( str_replace( '_', ' ', $value ) ) ); ?></option><?php endforeach; ?></select></td></tr>
					<tr class="cb-work-recurrence-field--wide"><th scope="row"><?php esc_html_e( 'Assignees', 'core-blueprint-work' ); ?></th><td><?php Pickers::assignees( 'recurrence[assigned_user_ids]', 'cb-work-recurrence-assignees', $assignees, false ); ?></td></tr>
					</tbody></table></section><section class="cb-work-recurrence-section cb-work-recurrence-section--schedule"><h3><?php esc_html_e( 'Schedule', 'core-blueprint-work' ); ?></h3><table class="form-table cb-work-recurrence-fields" role="presentation"><tbody>
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
						<tr class="cb-work-recurrence-field--frequency"><th scope="row"><label for="cb-work-recurrence-frequency"><?php esc_html_e( 'Frequency', 'core-blueprint-work' ); ?></label></th><td>
							<label for="cb-work-recurrence-interval"><?php esc_html_e( 'Every', 'core-blueprint-work' ); ?></label>
							<input id="cb-work-recurrence-interval" class="small-text" type="number" min="1" max="999" name="recurrence[interval_count]" value="<?php echo esc_attr( (string) $interval ); ?>">
							<select id="cb-work-recurrence-frequency" name="recurrence[frequency]"><?php foreach ( RecurrenceSchedule::frequencies() as $value ) : ?>
								<?php $unit = match ( $value ) {
									RecurrenceSchedule::DAILY => [ __( 'day', 'core-blueprint-work' ), __( 'days', 'core-blueprint-work' ) ],
									RecurrenceSchedule::WEEKLY => [ __( 'week', 'core-blueprint-work' ), __( 'weeks', 'core-blueprint-work' ) ],
									RecurrenceSchedule::MONTHLY => [ __( 'month', 'core-blueprint-work' ), __( 'months', 'core-blueprint-work' ) ],
									default => [ __( 'year', 'core-blueprint-work' ), __( 'years', 'core-blueprint-work' ) ],
								}; ?>
								<option value="<?php echo esc_attr( $value ); ?>" data-cb-unit-singular="<?php echo esc_attr( $unit[0] ); ?>" data-cb-unit-plural="<?php echo esc_attr( $unit[1] ); ?>" <?php selected( $frequency, $value ); ?>><?php echo esc_html( 1 === $interval ? $unit[0] : $unit[1] ); ?></option>
							<?php endforeach; ?></select></td></tr>
						<tr><th scope="row"><label for="cb-work-recurrence-start"><?php esc_html_e( 'Start date', 'core-blueprint-work' ); ?></label></th><td><input id="cb-work-recurrence-start" type="date" name="recurrence[start_on]" value="<?php echo esc_attr( $start_on ); ?>" required></td></tr>
						<tr><th scope="row"><label for="cb-work-recurrence-end"><?php esc_html_e( 'End date', 'core-blueprint-work' ); ?></label></th><td><input id="cb-work-recurrence-end" type="date" name="recurrence[end_on]" value="<?php echo esc_attr( $end_on ); ?>"><p class="description"><?php esc_html_e( 'Optional. Leave empty for an open-ended rule.', 'core-blueprint-work' ); ?></p></td></tr>
					<?php endif; ?>
					<tr class="cb-work-recurrence-field--wide"><th scope="row"><label for="cb-work-recurrence-ahead"><?php esc_html_e( 'Create ahead', 'core-blueprint-work' ); ?></label></th><td><input id="cb-work-recurrence-ahead" class="small-text" type="number" min="0" max="3650" name="recurrence[create_ahead_days]" value="<?php echo esc_attr( (string) $create_ahead ); ?>"> <?php esc_html_e( 'days', 'core-blueprint-work' ); ?><p class="description"><?php esc_html_e( 'Work Items are generated when an occurrence enters this planning horizon.', 'core-blueprint-work' ); ?></p></td></tr>
					<tr class="cb-work-recurrence-field--wide"><th scope="row"><label for="cb-work-recurrence-due"><?php esc_html_e( 'Due offset', 'core-blueprint-work' ); ?></label></th><td><input id="cb-work-recurrence-due" class="small-text" type="number" min="0" max="3650" name="recurrence[due_offset_days]" value="<?php echo esc_attr( (string) $due_offset ); ?>"> <?php esc_html_e( 'days after scheduled date', 'core-blueprint-work' ); ?></td></tr>
					
					</tbody></table></section>
					<div class="cb-work-recurrence-actions"><a class="button button-secondary" href="<?php echo esc_url( self::url() ); ?>"><?php esc_html_e( 'Cancel', 'core-blueprint-work' ); ?></a><?php submit_button( $editing ? __( 'Save Recurring Work Rule', 'core-blueprint-work' ) : __( 'Add Recurring Work Rule', 'core-blueprint-work' ), 'primary', 'submit', false ); ?></div>
				</form>
				<aside class="cb-work-recurrence-preview" aria-labelledby="cb-work-recurrence-preview-heading" data-cb-recurrence-preview>
					<div class="cb-work-recurrence-preview-top">
						<h3 id="cb-work-recurrence-preview-heading"><?php esc_html_e( 'Work Item Details', 'core-blueprint-work' ); ?></h3>
						<span class="cb-work-recurrence-status <?php echo $active ? 'cb-work-recurrence-status--active' : 'cb-work-recurrence-status--inactive'; ?>"><?php echo $active ? esc_html__( 'Active', 'core-blueprint-work' ) : esc_html__( 'Inactive', 'core-blueprint-work' ); ?></span>
					</div>
					<div class="cb-work-recurrence-preview-fact"><span><?php esc_html_e( 'Title', 'core-blueprint-work' ); ?></span><strong data-cb-preview-title><?php echo esc_html( $title ); ?></strong></div>
					<div class="cb-work-recurrence-preview-fact"><span><?php esc_html_e( 'Project', 'core-blueprint-work' ); ?></span><strong data-cb-preview-project><?php echo esc_html( $project_titles[ $project_id ] ?? __( 'No Project', 'core-blueprint-work' ) ); ?></strong></div>
					<div class="cb-work-recurrence-preview-fact"><span><?php esc_html_e( 'Estimated time (minutes)', 'core-blueprint-work' ); ?></span><strong data-cb-preview-estimate><?php echo esc_html( (string) $estimated_minutes ); ?></strong></div>
					<hr>
					<h4><?php esc_html_e( 'Next occurrence', 'core-blueprint-work' ); ?></h4>
					<ol data-cb-preview-dates aria-live="polite" aria-atomic="true">
					<?php $forecast = self::preview_dates( $frequency, $interval, $start_on, $end_on, $locked ? $rule['next_occurrence_on'] : null, $locked ); ?>
					<?php foreach ( $forecast ?? [] as $forecast_date ) : ?>
					<li><?php echo esc_html( mysql2date( get_option( 'date_format' ), $forecast_date . ' 12:00:00' ) ); ?></li>
					<?php endforeach; ?>
					</ol>
				</aside>
				</div>
				<div class="cb-work-recurrence-activation">
					<strong><?php esc_html_e( 'Status', 'core-blueprint-work' ); ?></strong>
					<span class="cb-work-recurrence-status <?php echo $active ? 'cb-work-recurrence-status--active' : 'cb-work-recurrence-status--inactive'; ?>"><?php echo $active ? esc_html__( 'Active', 'core-blueprint-work' ) : esc_html__( 'Inactive', 'core-blueprint-work' ); ?></span>
					<?php if ( $editing ) : ?>
					<form data-cb-editor-toggle method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="cb_work_toggle_recurrence_rule">
						<input type="hidden" name="rule_id" value="<?php echo esc_attr( (string) $edit_id ); ?>">
						<input type="hidden" name="active" value="<?php echo $active ? '0' : '1'; ?>">
						<?php wp_nonce_field( 'cb_work_toggle_recurrence_rule_' . $edit_id ); ?>
						<button class="button button-secondary" type="submit" <?php if ( ! $active ) : ?>data-cb-work-confirm<?php endif; ?>><?php echo $active ? esc_html__( 'Deactivate', 'core-blueprint-work' ) : esc_html__( 'Activate', 'core-blueprint-work' ); ?></button>
					</form>
					<?php endif; ?>
				</div>
			</div>
			<?php else : ?>
			<div class="cb-work-recurrence-list">
			<h2><?php esc_html_e( 'Recurring Work Rules', 'core-blueprint-work' ); ?></h2>
			<form class="cb-work-recurrence-filterbar" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" role="search">
				<input type="hidden" name="page" value="<?php echo esc_attr( Menu::RECURRENCE_SLUG ); ?>">
				<label><span><?php esc_html_e( 'Search', 'core-blueprint-work' ); ?></span><input type="search" name="cb_search" value="<?php echo esc_attr( $filter_search ); ?>" placeholder="<?php esc_attr_e( 'Search', 'core-blueprint-work' ); ?>"></label>
				<label><span><?php esc_html_e( 'Status', 'core-blueprint-work' ); ?></span><select name="cb_status"><option value=""><?php esc_html_e( 'All statuses', 'core-blueprint-work' ); ?></option><option value="active" <?php selected( $filter_status, 'active' ); ?>><?php esc_html_e( 'Active', 'core-blueprint-work' ); ?></option><option value="inactive" <?php selected( $filter_status, 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'core-blueprint-work' ); ?></option></select></label>
				<label><span><?php esc_html_e( 'Work context', 'core-blueprint-work' ); ?></span><select name="cb_context"><option value=""><?php esc_html_e( 'All contexts', 'core-blueprint-work' ); ?></option><option value="internal" <?php selected( $filter_context, 'internal' ); ?>><?php esc_html_e( 'Internal', 'core-blueprint-work' ); ?></option><option value="customer" <?php selected( $filter_context, 'customer' ); ?>><?php esc_html_e( 'Customer', 'core-blueprint-work' ); ?></option></select></label>
				<label><span><?php esc_html_e( 'Project', 'core-blueprint-work' ); ?></span><select name="cb_project"><option value="0"><?php esc_html_e( 'All Projects', 'core-blueprint-work' ); ?></option><?php foreach ( $projects as $project ) : ?><option value="<?php echo esc_attr( (string) $project['id'] ); ?>" <?php selected( $filter_project, (int) $project['id'] ); ?>><?php echo esc_html( (string) $project['title'] ); ?></option><?php endforeach; ?></select></label>
				<div class="cb-work-recurrence-filter-actions"><button class="button button-primary" type="submit"><?php esc_html_e( 'Filter', 'core-blueprint-work' ); ?></button><a class="button button-secondary" href="<?php echo esc_url( self::url() ); ?>"><?php esc_html_e( 'Clear filters', 'core-blueprint-work' ); ?></a></div>
			</form>


			<?php if ( [] === $rules ) : ?>
				<div class="cb-work-recurrence-empty"><h3><?php esc_html_e( 'No recurring Work rules configured.', 'core-blueprint-work' ); ?></h3>
				<?php if ( '' !== $filter_search || '' !== $filter_status || '' !== $filter_context || 0 !== $filter_project ) : ?>
				<p><?php esc_html_e( 'Adjust or clear the current filters to broaden this view.', 'core-blueprint-work' ); ?></p><a class="button button-secondary" href="<?php echo esc_url( self::url() ); ?>"><?php esc_html_e( 'Clear filters', 'core-blueprint-work' ); ?></a>
				<?php else : ?><a class="button button-primary" href="<?php echo esc_url( self::url( [ 'mode' => 'add' ] ) ); ?>"><?php esc_html_e( 'Add Recurring Work Rule', 'core-blueprint-work' ); ?></a><?php endif; ?></div>
			<?php else : ?>
				<div class="cb-work-recurrence-table-scroll"><table class="widefat striped"><thead><tr><th scope="col"><a href="<?php echo esc_url( self::url( [ ...$list_args, 'cb_sort' => 'title', 'cb_dir' => 'title' === $filter_sort && 'asc' === $filter_direction ? 'desc' : 'asc' ] ) ); ?>"><?php esc_html_e( 'Rule', 'core-blueprint-work' ); ?></a></th><th scope="col"><?php esc_html_e( 'Work context', 'core-blueprint-work' ); ?></th><th scope="col"><?php esc_html_e( 'Schedule', 'core-blueprint-work' ); ?></th><th><a href="<?php echo esc_url( self::url( [ ...$list_args, 'cb_sort' => 'next_occurrence', 'cb_dir' => 'next_occurrence' === $filter_sort && 'asc' === $filter_direction ? 'desc' : 'asc' ] ) ); ?>"><?php esc_html_e( 'Next occurrence', 'core-blueprint-work' ); ?></a></th><th><?php esc_html_e( 'Generated', 'core-blueprint-work' ); ?></th><th><a href="<?php echo esc_url( self::url( [ ...$list_args, 'cb_sort' => 'status', 'cb_dir' => 'status' === $filter_sort && 'asc' === $filter_direction ? 'desc' : 'asc' ] ) ); ?>"><?php esc_html_e( 'Status', 'core-blueprint-work' ); ?></a></th><th><?php esc_html_e( 'Actions', 'core-blueprint-work' ); ?></th></tr></thead><tbody>
				<?php foreach ( $rules as $configured ) : ?>
					<?php
						$configured_id = (int) $configured['id'];
						$count         = RecurrenceOccurrences::count_for_rule( $configured_id );
						$project_title = $project_titles[ (int) ( $configured['project_id'] ?? 0 ) ] ?? '';
						$is_customer   = WorkContext::CUSTOMER === (string) ( $configured['work_context'] ?? '' );
					?>
					<tr data-cb-rule-row="<?php echo esc_attr( (string) $configured_id ); ?>">
						<td><strong data-cb-rule-title><?php echo esc_html( (string) $configured['title'] ); ?></strong><?php if ( ! empty( $configured['description'] ) ) : ?><div class="cb-work-recurrence-rule-description"><?php echo esc_html( (string) $configured['description'] ); ?></div><?php endif; ?></td>
						<td><?php if ( '' !== $project_title ) : ?><strong><?php echo esc_html( $project_title ); ?></strong><span class="cb-work-recurrence-context-kind"><?php echo $is_customer ? esc_html__( 'Customer', 'core-blueprint-work' ) : esc_html__( 'Internal', 'core-blueprint-work' ); ?></span><?php else : ?><?php echo $is_customer ? esc_html__( 'Customer', 'core-blueprint-work' ) : esc_html__( 'Internal', 'core-blueprint-work' ); ?><?php endif; ?></td>
						<td><?php echo esc_html( self::schedule_label( $configured ) ); ?></td>
						<td><?php echo esc_html( null === $configured['next_occurrence_on'] ? __( 'Complete', 'core-blueprint-work' ) : wp_date( get_option( 'date_format' ), strtotime( (string) $configured['next_occurrence_on'] ) ) ); ?></td>
						<td><?php echo esc_html( (string) $count ); ?></td>
						<td><span data-cb-rule-status class="cb-work-recurrence-status <?php echo ! empty( $configured['is_active'] ) ? 'cb-work-recurrence-status--active' : 'cb-work-recurrence-status--inactive'; ?>"><?php echo ! empty( $configured['is_active'] ) ? esc_html__( 'Active', 'core-blueprint-work' ) : esc_html__( 'Inactive', 'core-blueprint-work' ); ?></span></td>
						<td><a class="button button-small" href="<?php echo esc_url( self::url( [ 'rule_id' => $configured_id ] ) ); ?>"><?php esc_html_e( 'Edit', 'core-blueprint-work' ); ?></a> <button type="button" class="button button-small" data-cb-quick-edit-trigger aria-expanded="false" aria-controls="cb-work-recurrence-quick-<?php echo esc_attr( (string) $configured_id ); ?>"><?php esc_html_e( 'Quick Edit', 'core-blueprint-work' ); ?></button> <form data-cb-inline-toggle method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block"><input type="hidden" name="action" value="cb_work_toggle_recurrence_rule"><input type="hidden" name="rule_id" value="<?php echo esc_attr( (string) $configured_id ); ?>"><input type="hidden" name="active" value="<?php echo empty( $configured['is_active'] ) ? '1' : '0'; ?>"><?php wp_nonce_field( 'cb_work_toggle_recurrence_rule_' . $configured_id ); ?><button class="button button-small" type="submit" <?php if ( empty( $configured['is_active'] ) ) : ?>data-cb-work-confirm<?php endif; ?>><?php echo empty( $configured['is_active'] ) ? esc_html__( 'Activate', 'core-blueprint-work' ) : esc_html__( 'Deactivate', 'core-blueprint-work' ); ?></button></form></td>
					</tr>
					<tr id="cb-work-recurrence-quick-<?php echo esc_attr( (string) $configured_id ); ?>" class="cb-work-recurrence-quick-row" hidden data-cb-quick-edit-row>
						<td colspan="7">
							<form data-cb-quick-edit-form>
								<input type="hidden" name="action" value="cb_work_quick_edit_recurrence_rule">
								<input type="hidden" name="rule_id" value="<?php echo esc_attr( (string) $configured_id ); ?>">
								<?php wp_nonce_field( 'cb_work_quick_edit_recurrence_rule_' . $configured_id ); ?>
								<label><?php esc_html_e( 'Title', 'core-blueprint-work' ); ?><input type="text" name="title" value="<?php echo esc_attr( (string) $configured['title'] ); ?>" required></label>
								<label><?php esc_html_e( 'Priority', 'core-blueprint-work' ); ?><select name="priority"><?php foreach ( WorkItemPriority::all() as $priority_option ) : ?><option value="<?php echo esc_attr( $priority_option ); ?>" <?php selected( (string) $configured['priority'], $priority_option ); ?>><?php echo esc_html( ucfirst( str_replace( '_', ' ', $priority_option ) ) ); ?></option><?php endforeach; ?></select></label>
								<button class="button button-primary" type="submit"><?php esc_html_e( 'Save changes', 'core-blueprint-work' ); ?></button>
								<button class="button button-secondary" type="button" data-cb-quick-edit-cancel><?php esc_html_e( 'Cancel', 'core-blueprint-work' ); ?></button>
								<span role="status" data-cb-quick-edit-status></span>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody></table></div>
			<?php endif; ?>
			<details class="cb-work-recurrence-diagnostics"><summary><?php esc_html_e( 'Run Generator Now', 'core-blueprint-work' ); ?></summary><p class="description"><?php
			/* translators: %s: next WordPress cron timestamp, or a not-scheduled label. */
			echo esc_html( sprintf( __( 'Generator hook: hourly. Next WordPress cron timestamp: %s', 'core-blueprint-work' ), self::next_cron_label() ) );
			?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:12px 0;">
				<input type="hidden" name="action" value="cb_work_run_recurrence_generator">
				<?php wp_nonce_field( 'cb_work_run_recurrence_generator' ); ?>
				<?php submit_button( __( 'Run Generator Now', 'core-blueprint-work' ), 'secondary', 'submit', false ); ?>
			</form></details>
			<div class="cb-work-recurrence-pagination">
				<span><?php echo esc_html( (string) $listing['total'] ); ?> <?php esc_html_e( 'Recurring Work Rules', 'core-blueprint-work' ); ?> · <?php echo esc_html( sprintf( __( 'Page %1$d of %2$d', 'core-blueprint-work' ), $listing['page'], $listing['pages'] ) ); ?></span>
				<nav aria-label="<?php esc_attr_e( 'Recurring Work Rules', 'core-blueprint-work' ); ?>">
					<?php if ( $listing['page'] > 1 ) : ?><a class="button" href="<?php echo esc_url( self::url( [ ...$list_args, 'cb_page' => $listing['page'] - 1 ] ) ); ?>"><?php esc_html_e( 'Previous', 'core-blueprint-work' ); ?></a><?php endif; ?>
					<?php if ( $listing['page'] < $listing['pages'] ) : ?><a class="button" href="<?php echo esc_url( self::url( [ ...$list_args, 'cb_page' => $listing['page'] + 1 ] ) ); ?>"><?php esc_html_e( 'Next', 'core-blueprint-work' ); ?></a><?php endif; ?>
				</nav>
			</div>
			</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Read-only occurrence projection using the canonical recurrence math.
	 * Locked rules use the persisted next-occurrence cursor, never rewind history.
	 *
	 * @return string[]|null
	 */
	public static function preview_dates( string $frequency, int $interval, string $start_on, ?string $end_on, ?string $cursor = null, bool $locked = false ): ?array {
		$schedule = RecurrenceSchedule::normalize( $frequency, $interval, $start_on, $end_on );
		if ( null === $schedule ) {
			return null;
		}
		$date = $locked ? $cursor : $schedule['start_on'];
		$dates = [];
		for ( $i = 0; $i < 3 && null !== $date; $i++ ) {
			if ( null !== $schedule['end_on'] && $date > $schedule['end_on'] ) {
				break;
			}
			$dates[] = $date;
			$date = RecurrenceSchedule::next_after( $schedule['start_on'], $date, $schedule['frequency'], $schedule['interval_count'], $schedule['end_on'] );
		}
		return $dates;
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

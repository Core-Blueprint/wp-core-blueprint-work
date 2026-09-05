<?php
declare(strict_types=1);

namespace CB\Work\Admin;

use CB\Core\Governance\Audit;
use CB\Work\Capabilities;
use CB\Work\Content\PostTypes;
use CB\Work\Content\WorkItemMeta;
use CB\Work\Domain\BillingDisposition;
use CB\Work\Domain\WorkItemPriority;
use CB\Work\Domain\WorkItemStatus;
use CB\Work\Governance\Events;
use CB\Work\Integration\CRMCustomers;
use CB\Work\Repository\Projects;
use CB\Work\Repository\WorkItems as WorkItemRepository;
use CB\Work\Repository\WorkTypes;
use CB\Work\PublicApi\Services;

defined( 'ABSPATH' ) || exit;

final class WorkItems {
	private const NONCE_ACTION = 'cb_work_save_work_item';
	private const NONCE_NAME   = 'cb_work_work_item_nonce';

	public static function init(): void {
		add_action( 'add_meta_boxes_' . PostTypes::WORK_ITEM, [ self::class, 'register_meta_boxes' ] );
		add_action( 'save_post_' . PostTypes::WORK_ITEM, [ self::class, 'save' ], 20, 3 );
		add_filter( 'enter_title_here', [ self::class, 'title_placeholder' ], 10, 2 );
	}

	public static function register_meta_boxes( \WP_Post $post ): void {
		add_meta_box(
			'cb-work-item-details',
			__( 'Work Item Details', 'core-blueprint-work' ),
			[ self::class, 'render_details' ],
			PostTypes::WORK_ITEM,
			'normal',
			'high'
		);
	}

	public static function render_details( \WP_Post $post ): void {
		$item = WorkItemRepository::get( (int) $post->ID );
		$project_id = isset( $_GET['project_id'] ) ? absint( wp_unslash( $_GET['project_id'] ) ) : 0;
		if ( is_array( $item ) && ! empty( $item['project_id'] ) ) {
			$project_id = (int) $item['project_id'];
		}

		$customer = [ 'provider' => '', 'type' => '', 'id' => '' ];
		if ( is_array( $item ) ) {
			$customer = [
				'provider' => (string) ( $item['customer_provider'] ?? '' ),
				'type'     => (string) ( $item['customer_type'] ?? '' ),
				'id'       => (string) ( $item['customer_id'] ?? '' ),
			];
		} elseif ( $project_id > 0 ) {
			$project = Projects::get( $project_id );
			if ( is_array( $project ) ) {
				$customer = [
					'provider' => (string) ( $project['customer_provider'] ?? '' ),
					'type'     => (string) ( $project['customer_type'] ?? '' ),
					'id'       => (string) ( $project['customer_id'] ?? '' ),
				];
			}
		}

		$projects = Projects::all( 500 );
		$services = Services::all( 500 );
		$work_types = WorkTypes::all( false );
		$priority = is_array( $item ) ? (string) ( $item['priority'] ?? WorkItemPriority::NORMAL ) : WorkItemPriority::NORMAL;
		$status = is_array( $item ) ? (string) ( $item['status'] ?? WorkItemStatus::PLANNED ) : WorkItemStatus::PLANNED;
		$billing = is_array( $item ) ? (string) ( $item['billing_disposition'] ?? '' ) : '';
		$scheduled_on = is_array( $item ) ? (string) ( $item['scheduled_on'] ?? '' ) : '';
		$due_on = is_array( $item ) ? (string) ( $item['due_on'] ?? '' ) : '';
		$assignments = is_array( $item ) ? (array) ( $item['assigned_user_ids'] ?? [] ) : [];

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		?>
		<table class="form-table" role="presentation">
			<tbody>
			<tr>
				<th scope="row"><?php esc_html_e( 'Customer', 'core-blueprint-work' ); ?></th>
				<td>
					<?php Pickers::customer( 'cb_work_item_customer', $customer ); ?>
					<p class="description"><?php esc_html_e( 'Optional. Uses CRM Contacts and Organizations when CRM is available.', 'core-blueprint-work' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cb-work-item-project"><?php esc_html_e( 'Project', 'core-blueprint-work' ); ?></label></th>
				<td><select id="cb-work-item-project" name="cb_work_item[project_id]">
					<option value="0"><?php esc_html_e( 'No project', 'core-blueprint-work' ); ?></option>
					<?php foreach ( $projects as $project ) : ?>
						<option value="<?php echo esc_attr( (string) $project['id'] ); ?>" <?php selected( $project_id, (int) $project['id'] ); ?>><?php echo esc_html( (string) $project['title'] ); ?></option>
					<?php endforeach; ?>
				</select></td>
			</tr>
			<tr>
				<th scope="row"><label for="cb-work-item-service"><?php esc_html_e( 'Service', 'core-blueprint-work' ); ?></label></th>
				<td><select id="cb-work-item-service" name="cb_work_item[service_id]">
					<option value="0"><?php esc_html_e( 'No service', 'core-blueprint-work' ); ?></option>
					<?php foreach ( $services as $service ) : ?>
						<option value="<?php echo esc_attr( (string) $service['id'] ); ?>" <?php selected( (int) ( $item['service_id'] ?? 0 ), (int) $service['id'] ); ?>><?php echo esc_html( (string) $service['title'] ); ?></option>
					<?php endforeach; ?>
				</select></td>
			</tr>
			<tr>
				<th scope="row"><label for="cb-work-item-type"><?php esc_html_e( 'Work Type', 'core-blueprint-work' ); ?></label></th>
				<td><select id="cb-work-item-type" name="cb_work_item[work_type_id]">
					<option value="0"><?php esc_html_e( 'No Work Type', 'core-blueprint-work' ); ?></option>
					<?php foreach ( $work_types as $type ) : ?>
						<option value="<?php echo esc_attr( (string) $type['id'] ); ?>" <?php selected( (int) ( $item['work_type_id'] ?? 0 ), (int) $type['id'] ); ?>><?php echo esc_html( (string) $type['label'] ); ?></option>
					<?php endforeach; ?>
				</select></td>
			</tr>
			<tr>
				<th scope="row"><label for="cb-work-item-priority"><?php esc_html_e( 'Priority', 'core-blueprint-work' ); ?></label></th>
				<td><select id="cb-work-item-priority" name="cb_work_item[priority]">
					<?php foreach ( WorkItemPriority::all() as $value ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $priority, $value ); ?>><?php echo esc_html( ucfirst( str_replace( '_', ' ', $value ) ) ); ?></option>
					<?php endforeach; ?>
				</select></td>
			</tr>
			<tr>
				<th scope="row"><label for="cb-work-item-status"><?php esc_html_e( 'Operational status', 'core-blueprint-work' ); ?></label></th>
				<td>
					<select id="cb-work-item-status" name="cb_work_item_status">
						<option value="<?php echo esc_attr( $status ); ?>"><?php echo esc_html( ucfirst( str_replace( '_', ' ', $status ) ) ); ?></option>
						<?php foreach ( WorkItemStatus::transitions_from( $status ) as $value ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( ucfirst( str_replace( '_', ' ', $value ) ) ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Work status is separate from WordPress publishing state and only valid lifecycle transitions are offered.', 'core-blueprint-work' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cb-work-item-due"><?php esc_html_e( 'Due date', 'core-blueprint-work' ); ?></label></th>
				<td><input id="cb-work-item-due" type="date" name="cb_work_item[due_on]" value="<?php echo esc_attr( $due_on ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Assignees', 'core-blueprint-work' ); ?></th>
				<td><?php Pickers::assignees( 'cb_work_item_assignees', $assignments ); ?></td>
			</tr>
			<tr>
				<th scope="row"><label for="cb-work-item-scheduled"><?php esc_html_e( 'Scheduled date', 'core-blueprint-work' ); ?></label></th>
				<td><input id="cb-work-item-scheduled" type="date" name="cb_work_item[scheduled_on]" value="<?php echo esc_attr( $scheduled_on ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="cb-work-item-billing"><?php esc_html_e( 'Billing classification', 'core-blueprint-work' ); ?></label></th>
				<td><select id="cb-work-item-billing" name="cb_work_item[billing_disposition]">
					<option value=""><?php esc_html_e( 'Not classified', 'core-blueprint-work' ); ?></option>
					<?php foreach ( BillingDisposition::all() as $value ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $billing, $value ); ?>><?php echo esc_html( ucfirst( str_replace( '_', ' ', $value ) ) ); ?></option>
					<?php endforeach; ?>
				</select></td>
			</tr>
			</tbody>
		</table>
		<?php
	}

	public static function save( int $post_id, \WP_Post $post, bool $update ): void {
		unset( $update );
		if (
			PostTypes::WORK_ITEM !== $post->post_type
			|| wp_is_post_revision( $post_id )
			|| wp_is_post_autosave( $post_id )
			|| ! current_user_can( Capabilities::MANAGE )
			|| ! current_user_can( 'edit_post', $post_id )
		) {
			return;
		}
		$nonce = isset( $_POST[ self::NONCE_NAME ] ) ? sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE_NAME ] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return;
		}

		$input = isset( $_POST['cb_work_item'] ) && is_array( $_POST['cb_work_item'] ) ? wp_unslash( $_POST['cb_work_item'] ) : [];
		$customer = Pickers::submitted_customer( 'cb_work_item_customer' );
		$current = WorkItemRepository::get( $post_id );
		if ( false === $customer && is_array( $current ) ) {
			$customer = [
				'provider' => (string) ( $current['customer_provider'] ?? '' ),
				'type'     => (string) ( $current['customer_type'] ?? '' ),
				'id'       => (string) ( $current['customer_id'] ?? '' ),
			];
		}
		if ( is_array( $customer ) ) {
			$input['customer_provider'] = $customer['provider'];
			$input['customer_type']     = $customer['type'];
			$input['customer_id']       = $customer['id'];
		}
		$input['assigned_user_ids'] = Pickers::submitted_assignees( 'cb_work_item_assignees' );

		$status = isset( $_POST['cb_work_item_status'] ) ? sanitize_key( wp_unslash( (string) $_POST['cb_work_item_status'] ) ) : '';
		$from = is_array( $current ) ? (string) ( $current['status'] ?? WorkItemStatus::PLANNED ) : WorkItemStatus::PLANNED;

		if ( ! WorkItemRepository::save_editor( $post_id, $input ) ) {
			return;
		}
		if ( '' !== $status && $status !== $from && WorkItemRepository::transition_status( $post_id, $status, get_current_user_id() ) ) {
			Audit::record( Events::WORK_ITEM_STATUS_CHANGED, 'notice', [ 'work_item_id' => $post_id, 'from' => $from, 'to' => $status ] );
		}
		Audit::record( Events::WORK_ITEM_UPDATED, 'notice', [ 'work_item_id' => $post_id ] );
	}

	public static function title_placeholder( string $title, \WP_Post $post ): string {
		return PostTypes::WORK_ITEM === $post->post_type ? __( 'Work Item title', 'core-blueprint-work' ) : $title;
	}
}

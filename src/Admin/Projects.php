<?php
declare(strict_types=1);

namespace CB\Work\Admin;

use CB\Core\Governance\Audit;
use CB\Work\Capabilities;
use CB\Work\Content\PostTypes;
use CB\Work\Content\ProjectMeta;
use CB\Work\Domain\WorkItemStatus;
use CB\Work\Governance\Events;
use CB\Work\Integration\CRMCustomers;
use CB\Work\Repository\Projects as ProjectRepository;
use CB\Work\Repository\WorkItems;

defined( 'ABSPATH' ) || exit;

final class Projects {
	private const NONCE_ACTION = 'cb_work_save_project_details';
	private const NONCE_FIELD  = 'cb_work_project_details_nonce';

	public static function init(): void {
		add_action( 'add_meta_boxes_' . PostTypes::PROJECT, [ self::class, 'register_meta_boxes' ] );
		add_action( 'save_post_' . PostTypes::PROJECT, [ self::class, 'save' ], 20, 3 );
		add_filter( 'manage_' . PostTypes::PROJECT . '_posts_columns', [ self::class, 'columns' ] );
		add_action( 'manage_' . PostTypes::PROJECT . '_posts_custom_column', [ self::class, 'column' ], 10, 2 );
		add_filter( 'enter_title_here', [ self::class, 'title_placeholder' ], 10, 2 );
	}

	public static function register_meta_boxes(): void {
		add_meta_box(
			'cb-work-project-details',
			__( 'Project Details', 'core-blueprint-work' ),
			[ self::class, 'render_details' ],
			PostTypes::PROJECT,
			'normal',
			'high'
		);
		add_meta_box(
			'cb-work-project-items',
			__( 'Work Items', 'core-blueprint-work' ),
			[ self::class, 'render_work_items' ],
			PostTypes::PROJECT,
			'normal',
			'default'
		);
	}

	public static function render_details( \WP_Post $post ): void {
		$meta     = ProjectMeta::get( (int) $post->ID );
		$selected = CRMCustomers::selected( $meta['customer_provider'], $meta['customer_type'], $meta['customer_id'] );

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
		?>
		<table class="form-table" role="presentation"><tbody>
			<tr>
				<th scope="row"><?php esc_html_e( 'Customer', 'core-blueprint-work' ); ?></th>
				<td>
					<?php if ( '' !== $meta['customer_provider'] && null === $selected ) : ?>
						<p class="description"><?php esc_html_e( 'This Project already has a customer link that cannot be resolved for the current user. The link is preserved.', 'core-blueprint-work' ); ?></p>
					<?php else : ?>
						<?php Pickers::customer( 'cb_work_project[customer_object_id]', 'cb-work-project-customer', $selected ); ?>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cb-work-project-starts"><?php esc_html_e( 'Start date', 'core-blueprint-work' ); ?></label></th>
				<td><input id="cb-work-project-starts" type="date" name="cb_work_project[starts_on]" value="<?php echo esc_attr( $meta['starts_on'] ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="cb-work-project-due"><?php esc_html_e( 'Due date', 'core-blueprint-work' ); ?></label></th>
				<td><input id="cb-work-project-due" type="date" name="cb_work_project[due_on]" value="<?php echo esc_attr( $meta['due_on'] ); ?>"></td>
			</tr>
		</tbody></table>
		<?php
	}

	public static function render_work_items( \WP_Post $post ): void {
		$project_id = (int) $post->ID;
		if ( $project_id <= 0 || 'auto-draft' === $post->post_status ) {
			echo '<p>' . esc_html__( 'Save the Project before adding Work Items.', 'core-blueprint-work' ) . '</p>';
			return;
		}

		$items = WorkItems::for_project( $project_id, 50, WorkItemStatus::active() );
		$add_url = add_query_arg(
			[ 'page' => Menu::WORK_ITEMS_SLUG, 'create' => '1', 'project_id' => $project_id ],
			admin_url( 'admin.php' )
		);
		$all_url = add_query_arg(
			[ 'page' => Menu::WORK_ITEMS_SLUG, 'project_id' => $project_id ],
			admin_url( 'admin.php' )
		);
		?>
		<p>
			<a class="button button-primary" href="<?php echo esc_url( $add_url ); ?>"><?php esc_html_e( 'Add Work Item', 'core-blueprint-work' ); ?></a>
			<a class="button" href="<?php echo esc_url( $all_url ); ?>"><?php esc_html_e( 'View all Work Items', 'core-blueprint-work' ); ?></a>
		</p>
		<?php if ( [] === $items ) : ?>
			<p><?php esc_html_e( 'No active Work Items for this Project.', 'core-blueprint-work' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Work Item', 'core-blueprint-work' ); ?></th>
					<th><?php esc_html_e( 'Status', 'core-blueprint-work' ); ?></th>
					<th><?php esc_html_e( 'Priority', 'core-blueprint-work' ); ?></th>
					<th><?php esc_html_e( 'Due', 'core-blueprint-work' ); ?></th>
					<th><?php esc_html_e( 'Assignees', 'core-blueprint-work' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $items as $item ) : ?>
					<?php $edit_url = add_query_arg( [ 'page' => Menu::WORK_ITEMS_SLUG, 'edit' => (int) $item['id'] ], admin_url( 'admin.php' ) ); ?>
					<tr>
						<td><strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( (string) $item['title'] ); ?></a></strong></td>
						<td><?php echo esc_html( self::humanize( (string) $item['status'] ) ); ?></td>
						<td><?php echo esc_html( self::humanize( (string) $item['priority'] ) ); ?></td>
						<td><?php echo esc_html( (string) ( $item['due_on'] ?: '—' ) ); ?></td>
						<td><?php echo esc_html( self::assignment_label( $item['assigned_user_ids'] ?? [] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif;
	}

	public static function save( int $post_id, \WP_Post $post, bool $update ): void {
		if (
			PostTypes::PROJECT !== $post->post_type
			|| wp_is_post_autosave( $post_id )
			|| wp_is_post_revision( $post_id )
			|| 'trash' === $post->post_status
		) {
			return;
		}
		if (
			! isset( $_POST[ self::NONCE_FIELD ] )
			|| ! wp_verify_nonce( sanitize_text_field( (string) wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION )
			|| ! current_user_can( Capabilities::MANAGE )
			|| ! current_user_can( 'edit_post', $post_id )
		) {
			return;
		}

		$input = isset( $_POST['cb_work_project'] ) && is_array( $_POST['cb_work_project'] )
			? wp_unslash( $_POST['cb_work_project'] )
			: [];
		$current = ProjectMeta::get( $post_id );

		if ( array_key_exists( 'customer_object_id', $input ) ) {
			$customer_id = absint( $input['customer_object_id'] );
			$reference   = CRMCustomers::reference( $customer_id );
			if ( is_wp_error( $reference ) ) {
				return;
			}
			$current['customer_provider'] = null === $reference ? '' : $reference['provider'];
			$current['customer_type']     = null === $reference ? '' : $reference['type'];
			$current['customer_id']       = null === $reference ? '' : $reference['id'];
		}

		$save = [
			'customer_provider' => $current['customer_provider'],
			'customer_type'     => $current['customer_type'],
			'customer_id'       => $current['customer_id'],
			'starts_on'         => $input['starts_on'] ?? $current['starts_on'],
			'due_on'            => $input['due_on'] ?? $current['due_on'],
		];
		if ( ProjectMeta::save( $post_id, $save ) ) {
			if ( ! ProjectMeta::is_initialized( $post_id ) ) {
				ProjectMeta::mark_initialized( $post_id );
				do_action( 'cb_work_project_created', $post_id, ProjectRepository::get( $post_id ) );
				Audit::record( Events::PROJECT_CREATED, 'notice', [ 'project_id' => $post_id ] );
				return;
			}
			Audit::record( Events::PROJECT_UPDATED, 'notice', [ 'project_id' => $post_id ] );
		}
	}

	/** @param array<string,string> $columns @return array<string,string> */
	public static function columns( array $columns ): array {
		$out = [];
		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'title' === $key ) {
				$out['cb_work_customer']   = __( 'Customer', 'core-blueprint-work' );
				$out['cb_work_due']        = __( 'Due', 'core-blueprint-work' );
				$out['cb_work_item_count'] = __( 'Work Items', 'core-blueprint-work' );
			}
		}
		return $out;
	}

	public static function column( string $column, int $post_id ): void {
		$meta = ProjectMeta::get( $post_id );
		if ( 'cb_work_customer' === $column ) {
			$label = CRMCustomers::label( $meta['customer_provider'], $meta['customer_type'], $meta['customer_id'] );
			echo esc_html( '' !== $label ? $label : '—' );
			return;
		}
		if ( 'cb_work_due' === $column ) {
			echo esc_html( '' !== $meta['due_on'] ? $meta['due_on'] : '—' );
			return;
		}
		if ( 'cb_work_item_count' === $column ) {
			echo esc_html( (string) WorkItems::count_for_project( $post_id ) );
		}
	}

	public static function title_placeholder( string $title, \WP_Post $post ): string {
		return PostTypes::PROJECT === $post->post_type ? __( 'Project name', 'core-blueprint-work' ) : $title;
	}

	/** @param int[] $ids */
	private static function assignment_label( array $ids ): string {
		$labels = [];
		foreach ( $ids as $id ) {
			$user = get_userdata( (int) $id );
			if ( $user ) {
				$labels[] = (string) $user->display_name;
			}
		}
		return [] === $labels ? '—' : implode( ', ', $labels );
	}

	private static function humanize( string $value ): string {
		return ucwords( str_replace( '_', ' ', $value ) );
	}
}

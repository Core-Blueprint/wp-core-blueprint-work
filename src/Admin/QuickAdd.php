<?php
declare(strict_types=1);

namespace CB\Work\Admin;

use CB\Work\Capabilities;
use CB\Work\PublicApi\WorkItemActions;
use CB\Work\Repository\Projects;

defined( 'ABSPATH' ) || exit;

/** Progressive-enhancement quick capture for canonical Work Items. */
final class QuickAdd {
	private const ACTION = 'cb_work_quick_add';

	public static function init(): void {
		add_action( 'admin_post_' . self::ACTION, [ self::class, 'handle' ] );
		add_action( 'admin_footer', [ self::class, 'render' ] );
		add_action( 'admin_notices', [ self::class, 'notice' ] );
	}

	public static function render(): void {
		if ( '' === Menu::screen_context() || ! current_user_can( Capabilities::MANAGE ) ) {
			return;
		}
		$projects = Projects::all( 500 );
		?>
		<div class="cb-work-quick-add" hidden data-cb-work-quick-add-panel>
			<div class="cb-work-quick-add__backdrop" data-cb-work-quick-add-close></div>
			<section class="cb-work-quick-add__panel" role="dialog" aria-modal="true" aria-labelledby="cb-work-quick-add-title">
				<header class="cb-work-quick-add__header">
					<div><p class="cb-work-quick-add__eyebrow"><?php esc_html_e( 'Quick capture', 'core-blueprint-work' ); ?></p><h2 id="cb-work-quick-add-title"><?php esc_html_e( 'Add Work Item', 'core-blueprint-work' ); ?></h2></div>
					<button type="button" class="button-link cb-work-quick-add__close" data-cb-work-quick-add-close aria-label="<?php esc_attr_e( 'Close', 'core-blueprint-work' ); ?>">×</button>
				</header>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
					<?php wp_nonce_field( self::ACTION ); ?>
					<p><label for="cb-work-quick-title"><strong><?php esc_html_e( 'What needs to be done?', 'core-blueprint-work' ); ?></strong></label><br><input id="cb-work-quick-title" class="large-text" type="text" name="work_item[title]" maxlength="200" required autocomplete="off"></p>
					<div class="cb-work-quick-add__fields">
						<p><label for="cb-work-quick-project"><strong><?php esc_html_e( 'Project', 'core-blueprint-work' ); ?></strong></label><br><select id="cb-work-quick-project" name="work_item[project_id]"><option value="0"><?php esc_html_e( 'No Project', 'core-blueprint-work' ); ?></option><?php foreach ( $projects as $project ) : ?><option value="<?php echo esc_attr( (string) $project['id'] ); ?>"><?php echo esc_html( (string) $project['title'] ); ?></option><?php endforeach; ?></select></p>
						<p><label for="cb-work-quick-due"><strong><?php esc_html_e( 'Due', 'core-blueprint-work' ); ?></strong></label><br><input id="cb-work-quick-due" type="date" name="work_item[due_on]"></p>
					</div>
					<div class="cb-work-quick-add__actions">
						<button class="button button-primary" type="submit"><?php esc_html_e( 'Add Work Item', 'core-blueprint-work' ); ?></button>
						<a class="button" data-cb-work-quick-add-full href="<?php echo esc_url( Menu::new_work_item_url() ); ?>"><?php esc_html_e( 'Open full editor', 'core-blueprint-work' ); ?></a>
						<button class="button-link" type="button" data-cb-work-quick-add-close><?php esc_html_e( 'Cancel', 'core-blueprint-work' ); ?></button>
					</div>
				</form>
			</section>
		</div>
		<?php
	}

	public static function handle(): never {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Work.', 'core-blueprint-work' ) );
		}
		check_admin_referer( self::ACTION );
		$input = isset( $_POST['work_item'] ) && is_array( $_POST['work_item'] ) ? wp_unslash( $_POST['work_item'] ) : [];
		$title = isset( $input['title'] ) && is_scalar( $input['title'] ) ? sanitize_text_field( (string) $input['title'] ) : '';
		$project_id = isset( $input['project_id'] ) ? absint( $input['project_id'] ) : 0;
		$due_on = isset( $input['due_on'] ) && is_scalar( $input['due_on'] ) ? sanitize_text_field( (string) $input['due_on'] ) : '';

		$result = '' === $title ? new \WP_Error( 'work_quick_add_title_required' ) : WorkItemActions::create( [
			'title'      => $title,
			'project_id' => $project_id,
			'due_on'     => $due_on,
		] );

		$referer = wp_get_referer();
		$target  = is_string( $referer ) && '' !== $referer
			? $referer
			: add_query_arg( 'page', Menu::WORK_ITEMS_SLUG, admin_url( 'admin.php' ) );
		$target = remove_query_arg( [ 'cb-work-quick-notice', 'cb-work-created' ], $target );
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg( 'cb-work-quick-notice', 'error', $target ) );
			exit;
		}
		wp_safe_redirect( add_query_arg( [
			'cb-work-quick-notice' => 'created',
			'cb-work-created'      => (int) ( $result['id'] ?? 0 ),
		], $target ) );
		exit;
	}

	public static function notice(): void {
		if ( '' === Menu::screen_context() ) {
			return;
		}
		$notice = isset( $_GET['cb-work-quick-notice'] ) ? sanitize_key( (string) wp_unslash( $_GET['cb-work-quick-notice'] ) ) : '';
		if ( 'created' === $notice ) {
			$id = isset( $_GET['cb-work-created'] ) ? absint( $_GET['cb-work-created'] ) : 0;
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Work Item added.', 'core-blueprint-work' );
			if ( $id > 0 ) {
				echo ' <a href="' . esc_url( Menu::edit_work_item_url( $id ) ) . '">' . esc_html__( 'Open it', 'core-blueprint-work' ) . '</a>';
			}
			echo '</p></div>';
			return;
		}
		if ( 'error' === $notice ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'The Work Item could not be added. Open the full editor to review all required details.', 'core-blueprint-work' ) . '</p></div>';
		}
	}
}

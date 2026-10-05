<?php
declare(strict_types=1);

namespace CB\Work\Admin;

use CoreBlueprint\Core\DataExchange\Engine;
use CoreBlueprint\Core\DataExchange\EntityInterface;
use CoreBlueprint\Core\DataExchange\Foundation;
use CB\Work\Capabilities;
use CB\Work\Integration\DataExchange as DataExchangeIntegration;
use CB\Work\Integration\Suite;
use CB\Work\Repository\PortableIdentities;
use CB\Work\Repository\Projects;
use JsonException;
use WP_Error;

defined( 'ABSPATH' ) || exit;

final class ProjectDataExchange {
	private const NONCE_ACTION = 'cb_work_project_data_exchange';
	private const SCRIPT_HANDLE = 'cb-work-project-data-exchange';

	public static function init(): void {
		add_action( 'admin_post_cb_work_export_project_bundle', [ self::class, 'export' ] );
		add_action( 'wp_ajax_cb_work_project_bundle_preview', [ self::class, 'preview' ] );
		add_action( 'wp_ajax_cb_work_project_bundle_apply', [ self::class, 'apply' ] );
	}

	public static function available(): bool {
		return class_exists( Engine::class )
			&& class_exists( Foundation::class )
			&& interface_exists( EntityInterface::class );
	}

	public static function import_url(): string {
		return Menu::project_import_url();
	}

	public static function export_url( int $project_id ): string {
		return wp_nonce_url(
			add_query_arg(
				[
					'action'     => 'cb_work_export_project_bundle',
					'project_id' => max( 0, $project_id ),
				],
				admin_url( 'admin-post.php' )
			),
			self::NONCE_ACTION
		);
	}

	public static function render_import(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to import Work Projects.', 'core-blueprint-work' ) );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Import Work Project', 'core-blueprint-work' ); ?></h1>
			<?php if ( ! self::available() ) : ?>
				<div class="notice notice-error"><p><?php esc_html_e( 'Project Data Exchange is unavailable in the installed Core Blueprint Base build.', 'core-blueprint-work' ); ?></p></div>
			<?php else : ?>
				<p><?php esc_html_e( 'Import a portable Project planning bundle containing one internal Project and its active Work Items.', 'core-blueprint-work' ); ?></p>
				<div class="notice notice-info inline"><p><?php esc_html_e( 'Schema v1 does not import customer links, assignments, Services, Work Types, recurrence, Time or terminal Work history.', 'core-blueprint-work' ); ?></p></div>
				<form data-cb-work-project-import>
					<table class="form-table" role="presentation"><tbody>
						<tr>
							<th scope="row"><label for="cb-work-project-import-file"><?php esc_html_e( 'JSON file', 'core-blueprint-work' ); ?></label></th>
							<td><input id="cb-work-project-import-file" name="file" type="file" accept=".json,.cb-work.json,application/json" required></td>
						</tr>
						<tr>
							<th scope="row"><label for="cb-work-project-import-mode"><?php esc_html_e( 'Import mode', 'core-blueprint-work' ); ?></label></th>
							<td>
								<select id="cb-work-project-import-mode" name="mode">
									<option value="<?php echo esc_attr( Foundation::MODE_CREATE_ONLY ); ?>"><?php esc_html_e( 'Create only', 'core-blueprint-work' ); ?></option>
									<option value="<?php echo esc_attr( Foundation::MODE_CREATE_UPDATE ); ?>"><?php esc_html_e( 'Create or update', 'core-blueprint-work' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'Create only is safest for new plans. Create or update uses immutable portable identities and never matches by title or database ID.', 'core-blueprint-work' ); ?></p>
							</td>
						</tr>
					</tbody></table>
					<p class="submit">
						<button class="button button-primary" type="submit" data-cb-work-project-preview><?php esc_html_e( 'Validate import', 'core-blueprint-work' ); ?></button>
						<button class="button" type="button" data-cb-work-project-apply disabled><?php esc_html_e( 'Apply import', 'core-blueprint-work' ); ?></button>
						<a class="button" href="<?php echo esc_url( Menu::projects_url() ); ?>"><?php esc_html_e( 'Back to Projects', 'core-blueprint-work' ); ?></a>
					</p>
				</form>
				<div data-cb-work-project-import-result aria-live="polite"></div>
				<?php self::enqueue_script(); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function export(): never {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to export Work Projects.', 'core-blueprint-work' ), '', [ 'response' => 403 ] );
		}
		check_admin_referer( self::NONCE_ACTION );

		$project_id = isset( $_GET['project_id'] ) ? absint( wp_unslash( $_GET['project_id'] ) ) : 0;
		$project = Projects::get( $project_id );
		if ( null === $project || ! self::available() ) {
			wp_die( esc_html__( 'The Project export is unavailable.', 'core-blueprint-work' ), '', [ 'response' => 400 ] );
		}

		$json = Engine::export_json(
			Suite::EXTENSION_ID,
			DataExchangeIntegration::PROJECT_BUNDLE_ENTITY,
			[ 'project_id' => $project_id ]
		);
		if ( is_wp_error( $json ) ) {
			wp_die( esc_html( self::error_message( $json ) ), '', [ 'response' => 400 ] );
		}

		$slug = sanitize_title( (string) $project['title'] );
		$filename = sanitize_file_name( 'core-blueprint-work-' . ( '' !== $slug ? $slug : 'project' ) . '.cb-work.json' );
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- intentional bounded JSON download from Base Data Exchange.
		exit;
	}

	public static function preview(): never {
		self::guard_ajax();
		$input = self::uploaded_json();
		if ( is_wp_error( $input ) ) {
			self::send_error( $input );
		}
		$mode = self::mode();
		if ( is_wp_error( $mode ) ) {
			self::send_error( $mode );
		}

		$preview = Engine::preview_json( $input, $mode );
		if ( is_wp_error( $preview ) ) {
			self::send_error( $preview );
		}
		$summary = self::envelope_summary( $input );

		wp_send_json_success( [
			'validation' => [
				'valid'       => true === ( $preview['valid'] ?? false ),
				'message'     => true === ( $preview['valid'] ?? false )
					? __( 'Preview ready. Review the plan, then apply the import.', 'core-blueprint-work' )
					: __( 'The import preview contains errors and cannot be applied.', 'core-blueprint-work' ),
				'counts'      => [
					'projects_create' => (int) ( $preview['counts'][ Foundation::OP_CREATE ] ?? 0 ),
					'projects_update' => (int) ( $preview['counts'][ Foundation::OP_UPDATE ] ?? 0 ),
					'projects_skip'   => (int) ( $preview['counts'][ Foundation::OP_SKIP ] ?? 0 ),
					'work_items'      => (int) ( $summary['work_items'] ?? 0 ),
				],
				'errors'      => isset( $preview['errors'] ) && is_array( $preview['errors'] ) ? $preview['errors'] : [],
				'warnings'    => isset( $preview['items'][0]['warnings'] ) && is_array( $preview['items'][0]['warnings'] ) ? $preview['items'][0]['warnings'] : [],
				'fingerprint' => is_string( $preview['fingerprint'] ?? null ) ? $preview['fingerprint'] : '',
			],
		] );
	}

	public static function apply(): never {
		self::guard_ajax();

		$fingerprint = isset( $_POST['fingerprint'] )
			? strtolower( trim( wp_unslash( (string) $_POST['fingerprint'] ) ) )
			: '';
		if ( 1 !== preg_match( '/^[a-f0-9]{64}$/D', $fingerprint ) ) {
			self::send_error( new WP_Error( 'work_project_bundle_invalid_fingerprint', __( 'Preview the import again before applying it.', 'core-blueprint-work' ) ), 409 );
		}

		$input = self::uploaded_json();
		if ( is_wp_error( $input ) ) {
			self::send_error( $input );
		}
		$mode = self::mode();
		if ( is_wp_error( $mode ) ) {
			self::send_error( $mode );
		}

		$result = Engine::apply_json( $input, $mode, $fingerprint );
		if ( is_wp_error( $result ) ) {
			self::send_error( $result, 409 );
		}
		if ( 'complete' !== (string) ( $result['status'] ?? '' ) ) {
			self::send_error( new WP_Error( 'work_project_bundle_apply_failed', __( 'The Project import stopped before it could complete.', 'core-blueprint-work' ) ), 409 );
		}

		$project_id = self::project_id_from_envelope( $input );
		wp_send_json_success( [
			'validation' => [
				'valid'   => true,
				'message' => __( 'Project import completed.', 'core-blueprint-work' ),
				'url'     => $project_id > 0 ? Menu::project_workspace_url( $project_id ) : Menu::projects_url(),
			],
		] );
	}

	private static function guard_ajax(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			self::send_error( new WP_Error( 'work_project_bundle_forbidden', __( 'You do not have permission to import Work Projects.', 'core-blueprint-work' ) ), 403 );
		}
		if ( false === check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			self::send_error( new WP_Error( 'work_project_bundle_invalid_nonce', __( 'The import request expired. Reload the page and try again.', 'core-blueprint-work' ) ), 403 );
		}
		if ( ! self::available() ) {
			self::send_error( new WP_Error( 'work_project_bundle_unavailable', __( 'Project Data Exchange is unavailable.', 'core-blueprint-work' ) ), 409 );
		}
	}

	private static function enqueue_script(): void {
		$file = CB_WORK_DIR . 'assets/project-data-exchange.js';
		if ( ! is_file( $file ) ) {
			return;
		}
		$modified = filemtime( $file );
		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			CB_WORK_URL . 'assets/project-data-exchange.js',
			[],
			false === $modified ? CB_WORK_VERSION : (string) $modified,
			true
		);
		wp_localize_script( self::SCRIPT_HANDLE, 'cbWorkProjectDataExchange', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
			'actions' => [
				'preview' => 'cb_work_project_bundle_preview',
				'apply'   => 'cb_work_project_bundle_apply',
			],
			'labels' => [
				'validating'    => __( 'Validating Project import…', 'core-blueprint-work' ),
				'applying'      => __( 'Applying Project import…', 'core-blueprint-work' ),
				'failed'        => __( 'The Project import request could not be completed.', 'core-blueprint-work' ),
				'projectsCreate'=> __( 'Projects to create', 'core-blueprint-work' ),
				'projectsUpdate'=> __( 'Projects to update', 'core-blueprint-work' ),
				'projectsSkip'  => __( 'Projects skipped', 'core-blueprint-work' ),
				'workItems'     => __( 'Work Items', 'core-blueprint-work' ),
			],
		] );
	}

	private static function uploaded_json(): string|WP_Error {
		$file = $_FILES['file'] ?? null;
		if ( ! is_array( $file ) ) {
			return new WP_Error( 'work_project_bundle_missing_file', __( 'Choose a JSON file to continue.', 'core-blueprint-work' ) );
		}
		$error = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
		$size  = isset( $file['size'] ) ? (int) $file['size'] : 0;
		$name  = isset( $file['name'] ) && is_string( $file['name'] ) ? sanitize_file_name( $file['name'] ) : '';
		$tmp   = isset( $file['tmp_name'] ) && is_string( $file['tmp_name'] ) ? $file['tmp_name'] : '';
		if ( UPLOAD_ERR_OK !== $error || '' === $tmp || ! is_uploaded_file( $tmp ) ) {
			return new WP_Error( 'work_project_bundle_invalid_upload', __( 'The JSON upload could not be verified.', 'core-blueprint-work' ) );
		}
		if ( 'json' !== strtolower( (string) pathinfo( $name, PATHINFO_EXTENSION ) ) ) {
			return new WP_Error( 'work_project_bundle_invalid_file_type', __( 'Project imports accept JSON files only.', 'core-blueprint-work' ) );
		}
		if ( $size <= 0 || $size > Foundation::MAX_INPUT_BYTES ) {
			return new WP_Error( 'work_project_bundle_invalid_file_size', __( 'The JSON file is empty or exceeds the Data Exchange size limit.', 'core-blueprint-work' ) );
		}
		$contents = file_get_contents( $tmp );
		if ( false === $contents || strlen( $contents ) !== $size || strlen( $contents ) > Foundation::MAX_INPUT_BYTES ) {
			return new WP_Error( 'work_project_bundle_unreadable_file', __( 'The JSON file could not be read safely.', 'core-blueprint-work' ) );
		}
		return $contents;
	}

	private static function mode(): string|WP_Error {
		$mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( (string) $_POST['mode'] ) ) : '';
		return in_array( $mode, [ Foundation::MODE_CREATE_ONLY, Foundation::MODE_CREATE_UPDATE ], true )
			? $mode
			: new WP_Error( 'work_project_bundle_invalid_mode', __( 'Choose a supported Project import mode.', 'core-blueprint-work' ) );
	}

	/** @return array{work_items:int} */
	private static function envelope_summary( string $input ): array {
		try {
			$decoded = json_decode( $input, true, 16, JSON_THROW_ON_ERROR );
		} catch ( JsonException $exception ) {
			unset( $exception );
			return [ 'work_items' => 0 ];
		}
		$records = is_array( $decoded ) && isset( $decoded['records'] ) && is_array( $decoded['records'] ) ? $decoded['records'] : [];
		$record  = isset( $records[0] ) && is_array( $records[0] ) ? $records[0] : [];
		$items   = isset( $record['work_items'] ) && is_array( $record['work_items'] ) ? $record['work_items'] : [];
		return [ 'work_items' => count( $items ) ];
	}

	private static function project_id_from_envelope( string $input ): int {
		try {
			$decoded = json_decode( $input, true, 16, JSON_THROW_ON_ERROR );
		} catch ( JsonException $exception ) {
			unset( $exception );
			return 0;
		}
		$key = $decoded['records'][0]['portable_key'] ?? '';
		return PortableIdentities::local_id( PortableIdentities::PROJECT, is_string( $key ) ? $key : '' );
	}

	private static function send_error( WP_Error $error, int $status = 400 ): never {
		wp_send_json_error( [
			'validation' => [
				'valid'   => false,
				'message' => self::error_message( $error ),
				'errors'  => [
					[
						'code'    => sanitize_key( (string) $error->get_error_code() ),
						'message' => self::error_message( $error ),
					],
				],
			],
		], $status );
	}

	private static function error_message( WP_Error $error ): string {
		$message = sanitize_text_field( (string) $error->get_error_message() );
		return '' !== $message ? $message : __( 'The Project Data Exchange request failed validation.', 'core-blueprint-work' );
	}

	private function __construct() {}
}

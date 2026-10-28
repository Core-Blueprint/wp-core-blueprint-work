<?php
declare(strict_types=1);

namespace {
	define( 'ABSPATH', '/tmp/wp/' );

	final class WP_Post {
		public function __construct( public int $ID, public string $post_status ) {}
	}

	function __( string $text, string $domain = 'default' ): string { return $text; }
	function esc_html_e( string $text, string $domain = 'default' ): void { echo htmlspecialchars( $text, ENT_QUOTES ); }
	function esc_attr( mixed $value ): string { return htmlspecialchars( (string) $value, ENT_QUOTES ); }
	function esc_url( string $value ): string { return htmlspecialchars( $value, ENT_QUOTES ); }
	function selected( mixed $value, mixed $selected, bool $echo = true ): string {
		$out = $value === $selected ? 'selected="selected"' : '';
		if ( $echo ) { echo $out; }
		return $out;
	}
	function wp_nonce_field( string $action, string $field ): void {
		echo '<input type="hidden" name="' . esc_attr( $field ) . '">';
	}
	function check_ux02c( bool $ok, string $message ): void {
		if ( ! $ok ) {
			fwrite( STDERR, "UX-02C Project Experience FAIL: {$message}\n" );
			exit( 1 );
		}
	}
}

namespace CB\Work\Content {
	final class PostTypes { public const PROJECT = 'cb_work_project'; }
	final class ProjectMeta {
		public static array $current = [];
		public static function get( int $id ): array { return self::$current; }
	}
}

namespace CB\Work\Domain {
	final class WorkContext {
		public const INTERNAL = 'internal';
		public const CUSTOMER = 'customer';
	}
}

namespace CB\Work\Integration {
	final class CRMCustomers {
		public static function selected( string $provider, string $type, string $id ): ?array {
			return '' === $provider ? null : [ 'id' => $provider . ':' . $type . ':' . $id ];
		}
	}
}

namespace CB\Work\Admin {
	final class Menu {
		public static function project_workspace_url( int $id ): string {
			return '/wp-admin/admin.php?page=project&project_id=' . $id;
		}
	}
	final class Pickers {
		public static function customer( string $name, string $id, ?array $selected ): void {
			echo '<input id="' . \esc_attr( $id ) . '" name="' . \esc_attr( $name ) . '">';
		}
	}
}

namespace {
	require dirname( __DIR__ ) . '/src/Admin/Projects.php';

	use CB\Work\Admin\Projects;
	use CB\Work\Content\ProjectMeta;

	ProjectMeta::$current = [
		'work_context' => '', 'customer_provider' => '', 'customer_type' => '',
		'customer_id' => '', 'starts_on' => '', 'due_on' => '',
	];
	ob_start();
	Projects::render_details( new WP_Post( 100, 'auto-draft' ) );
	$first = (string) ob_get_clean();
	check_ux02c( str_contains( $first, 'cb-work-project-editor__section' ), 'Editor sections rendered.' );
	check_ux02c( str_contains( $first, 'aria-labelledby="cb-work-project-editor-context-title"' ), 'Context section accessible heading.' );
	check_ux02c( str_contains( $first, 'aria-labelledby="cb-work-project-editor-schedule-title"' ), 'Schedule section accessible heading.' );
	check_ux02c( str_contains( $first, 'data-cb-work-context-select required' ), 'Native authoritative context field preserved.' );
	check_ux02c( str_contains( $first, 'selected="selected">Internal' ), 'New Project defaults to Internal context.' );
	check_ux02c( str_contains( $first, 'data-cb-work-customer-row' ), 'Customer row uses established context JS.' );
	check_ux02c( str_contains( $first, 'cb_work_project[customer_object_id]' ), 'Canonical CRM picker transport preserved.' );
	check_ux02c( str_contains( $first, 'name="cb_work_project[starts_on]"' ), 'Existing start date field preserved.' );
	check_ux02c( str_contains( $first, 'name="cb_work_project[due_on]"' ), 'Existing due date field preserved.' );
	check_ux02c( ! str_contains( $first, 'Open Project Workspace' ), 'Unsaved Project cannot open workspace.' );

	ProjectMeta::$current = [
		'work_context' => 'customer', 'customer_provider' => 'crm', 'customer_type' => 'organization',
		'customer_id' => '91', 'starts_on' => '2026-10-01', 'due_on' => '2026-11-30',
	];
	ob_start();
	Projects::render_details( new WP_Post( 91, 'publish' ) );
	$existing = (string) ob_get_clean();
	check_ux02c( str_contains( $existing, 'Open Project Workspace' ), 'Saved Project links to workspace.' );
	check_ux02c( str_contains( $existing, 'selected="selected">Customer' ), 'Existing customer context selected.' );
	check_ux02c( str_contains( $existing, 'value="2026-10-01"' ) && str_contains( $existing, 'value="2026-11-30"' ), 'Dates retain their values.' );
	check_ux02c( str_contains( $existing, 'name="cb_work_project[customer_object_id]"' ), 'Customer picker retains field name.' );
	check_ux02c( str_contains( $existing, 'cb_work_project_details_nonce' ), 'Native nonce preserved.' );

	$workspace = file_get_contents( dirname( __DIR__ ) . '/src/Admin/ProjectWorkspace.php' );
	$assets = file_get_contents( dirname( __DIR__ ) . '/src/Admin/Assets.php' );
	$css = file_get_contents( dirname( __DIR__ ) . '/assets/project-editor.css' );
	check_ux02c( is_string( $workspace ) && is_string( $assets ) && is_string( $css ), 'Presentation files readable.' );
	check_ux02c( substr_count( $workspace, "esc_html_e( 'Add Work Item'" ) === 1, 'Exactly one primary Add action in workspace.' );
	check_ux02c( str_contains( $workspace, 'cb-work-project-workspace__secondary' ), 'Secondary links grouped under header.' );
	check_ux02c( str_contains( $workspace, 'ProjectDataExchange::available()' ), 'Optional Project JSON export gate preserved.' );
	check_ux02c( str_contains( $workspace, 'WorkContext::CUSTOMER ===' ), 'Customer fact conditional on project context.' );
	check_ux02c( str_contains( $workspace, 'ProjectWorkSummary::counts_by_status' ), 'Workspace retains canonical summary read model.' );
	check_ux02c( ! preg_match( '/\b(?:update_post_meta|delete_post_meta|wp_update_post|wpdb->)\b/', $workspace ), 'Workspace does not own persistence.' );
	check_ux02c( str_contains( $assets, 'enqueue_project_editor_style' ) && str_contains( $assets, "'post' !== (string) \$screen->base" ), 'Editor CSS scoped to native Project post screens.' );
	check_ux02c( str_contains( $css, '#cb-work-project-details' ) && str_contains( $css, '[hidden]' ), 'CSS scoped and obeys hidden customer row.' );

	echo "Work UX-02C Project experience smoke: PASS (24 assertions).\n";
}

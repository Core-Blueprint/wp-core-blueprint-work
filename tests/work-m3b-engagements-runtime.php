<?php
declare(strict_types=1);

namespace {
	define( 'ABSPATH', '/tmp/work-m3b/' );
	$GLOBALS['m3b_manager'] = true;
	$GLOBALS['m3b_actor'] = 17;
	$GLOBALS['m3b_ready'] = true;
	$GLOBALS['m3b_error'] = false;
	$GLOBALS['m3b_read_calls'] = [];
	$GLOBALS['m3b_writes'] = [];
	$GLOBALS['m3b_routes'] = [];

	class WP_Error {
		public function __construct( private string $code, public string $message = '', public array $data = [] ) {}
		public function get_error_code(): string { return $this->code; }
	}
	class WP_REST_Request {
		public function __construct( private array $params = [] ) {}
		public function get_param( string $key ): mixed { return $this->params[$key] ?? null; }
	}
	class WP_REST_Response {
		public function __construct( public array $data, public int $status = 200 ) {}
	}
	class WP_REST_Server { public const READABLE = 'GET'; public const CREATABLE = 'POST'; }

	function current_user_can( string $cap ): bool { return 'cb_manage_work' === $cap && $GLOBALS['m3b_manager']; }
	function get_current_user_id(): int { return $GLOBALS['m3b_actor']; }
	function cb_work_runtime_ready(): bool { return $GLOBALS['m3b_ready']; }
	function rest_authorization_required_code(): int { return 403; }
	function __( string $value, string $domain ): string { return $value; }
	function sanitize_text_field( string $value ): string { return trim( strip_tags( $value ) ); }
	function sanitize_key( string $value ): string { return strtolower( preg_replace( '/[^a-z0-9_-]/', '', $value ) ); }
	function is_wp_error( mixed $value ): bool { return $value instanceof WP_Error; }
	function rest_ensure_response( array $data ): WP_REST_Response { return new WP_REST_Response( $data ); }
	function add_action( string $hook, mixed $handler ): void { $GLOBALS['m3b_hook'] = [ $hook, $handler ]; }
	function register_rest_route( string $namespace, string $route, array $args ): void {
		$GLOBALS['m3b_routes'][] = [ $namespace, $route, $args ];
	}
}
namespace CB\Work {
	final class Capabilities { public const MANAGE = 'cb_manage_work'; }
}
namespace CB\Work\Content {
	final class ProjectMeta {
		public static function sanitize_date( string $raw ): string {
			$d = \DateTimeImmutable::createFromFormat( '!Y-m-d', $raw );
			return $d && $d->format( 'Y-m-d' ) === $raw ? $raw : '';
		}
	}
}
namespace CB\Work\PublicApi {
	final class ServiceOperations {
		public static function search( array $filters ): array|\WP_Error {
			$GLOBALS['m3b_read_calls'][] = $filters;
			if ( $GLOBALS['m3b_error'] ) return new \WP_Error( 'db_fault' );
			return [
				'items' => [
					[ 'id' => 19, 'project_id' => 3, 'service_id' => 5,
						'label' => 'Maintenance', 'work_context' => 'customer',
						'starts_on' => '2026-10-31', 'is_active' => true,
						'customer_id' => 'not-for-client', 'customer_provider' => 'secret' ],
				],
				'total' => 34, 'page' => 2, 'pages' => 2,
			];
		}
	}
	final class Projects {
		public static function get( int $id ): ?array { return $id === 3 ? [ 'title' => 'Acme' ] : null; }
	}
	final class Services {
		public static function get( int $id ): ?array { return $id === 5 ? [ 'title' => 'Maintenance Plan' ] : null; }
	}
	final class ServiceOperationsActions {
		public static function create( array $input ): int|\WP_Error {
			$GLOBALS['m3b_writes'][] = $input;
			return $GLOBALS['m3b_error'] ? new \WP_Error( 'work_service_context_mismatch' ) : 77;
		}
	}
}
namespace {
	require dirname( __DIR__ ) . '/src/Transport/Rest/WorkspaceEngagementsController.php';
	$api = \CB\Work\Transport\Rest\WorkspaceEngagementsController::class;
	$assertions = 0;
	$check = static function ( bool $ok, string $label ) use ( &$assertions ): void {
		++$assertions;
		if ( ! $ok ) { fwrite( STDERR, "Work M3-B1 Engagements API FAIL: {$label}\n" ); exit( 1 ); }
	};
	$api::init();
	$check( $GLOBALS['m3b_hook'][0] === 'rest_api_init', 'route registration only during REST init' );
	$api::register_routes();
	$route = $GLOBALS['m3b_routes'][0] ?? [];
	$check( $route[0] === 'cb-work/v1' && $route[1] === '/workspace/engagements', 'isolated Work REST route' );
	$check( count( $route[2] ) === 2 && $route[2][0]['methods'] === 'GET' && $route[2][1]['methods'] === 'POST',
		'explicit read and create route methods, no update/delete' );
	$check( $route[2][0]['args']['search']['maxLength'] === 160
		&& $route[2][1]['args']['project_id']['minimum'] === 1
		&& $route[2][1]['args']['service_id']['required'] === true, 'bounded input schema' );
	$valid = new WP_REST_Request( [ 'page' => 2, 'search' => 'Maintenance', 'work_context' => 'customer' ] );
	$GLOBALS['m3b_manager'] = false;
	$check( is_wp_error( $api::list_engagements( $valid ) ) && is_wp_error( $api::create_engagement( $valid ) ),
		'tracker denied for both callbacks without permission middleware' );
	$check( $GLOBALS['m3b_read_calls'] === [] && $GLOBALS['m3b_writes'] === [],
		'permission rejection occurs before domain access' );
	$GLOBALS['m3b_manager'] = true;
	$GLOBALS['m3b_actor'] = 0;
	$check( is_wp_error( $api::list_engagements( $valid ) ), 'anonymous manager-like actor denied' );
	$GLOBALS['m3b_actor'] = 17;
	$GLOBALS['m3b_ready'] = false;
	$check( is_wp_error( $api::list_engagements( $valid ) ), 'Base/runtime missing fails closed' );
	$GLOBALS['m3b_ready'] = true;

	$list = $api::list_engagements( $valid );
	$check( $list instanceof WP_REST_Response && $list->status === 200, 'authorized list' );
	$check( $list->data['total'] === 34 && $list->data['page'] === 2 && $list->data['pages'] === 2, 'canonical paging metadata' );
	$check( $list->data['items'][0] === [
		'id' => 19, 'project' => 'Acme', 'service' => 'Maintenance Plan',
		'label' => 'Maintenance', 'context' => 'customer', 'starts_on' => '2026-10-31', 'is_active' => true,
	], 'minimal safe projection, no customer identity exposed' );
	$check( $GLOBALS['m3b_read_calls'][0] === [
		'page' => 2, 'per_page' => 25, 'search' => 'Maintenance', 'work_context' => 'customer',
	], 'server enforced 25-row canonical paging' );
	$badContext = $api::list_engagements( new WP_REST_Request( [ 'work_context' => 'admin' ] ) );
	$check( is_wp_error( $badContext ), 'unknown context rejected' );
	$check( count( $GLOBALS['m3b_read_calls'] ) === 1, 'invalid context no query' );
	$check( is_wp_error( $api::list_engagements( new WP_REST_Request( [ 'page' => [] ] ) ) ),
		'direct callback rejects malformed pagination without coercion' );
	$check( is_wp_error( $api::list_engagements( new WP_REST_Request( [ 'search' => [] ] ) ) ),
		'direct callback rejects malformed search without PHP warnings' );
	$check( is_wp_error( $api::create_engagement( new WP_REST_Request( [
		'project_id' => [ 3 ], 'service_id' => 5, 'starts_on' => '2026-11-01',
	] ) ) ), 'direct callback rejects array IDs' );
	$GLOBALS['m3b_error'] = true;
	$check( is_wp_error( $api::list_engagements( $valid ) ), 'DB failure is an error, not empty list' );
	$GLOBALS['m3b_error'] = false;
	$input = new WP_REST_Request( [
		'project_id' => 3, 'service_id' => 5, 'starts_on' => '2026-11-01',
		'label' => '<b>Support</b>', 'customer_id' => 'spoof', 'billing_enabled' => 1,
		'work_context' => 'internal', 'rule_id' => 9000,
	] );
	$created = $api::create_engagement( $input );
	$check( $created instanceof WP_REST_Response && $created->status === 201 && $created->data['id'] === 77,
		'returns created ID only after canonical writer succeeds' );
	$check( $GLOBALS['m3b_writes'][0] === [
		'project_id' => 3, 'service_id' => 5, 'starts_on' => '2026-11-01', 'label' => 'Support',
	], 'server trusts canonical Project context, blocks client identity, billing and recurrence spoofing' );
	$check( is_wp_error( $api::create_engagement( new WP_REST_Request( [
		'project_id' => 3, 'service_id' => 5, 'starts_on' => '2026-02-30',
	] ) ) ), 'calendar-invalid date rejected' );
	$check( count( $GLOBALS['m3b_writes'] ) === 1, 'invalid create never reaches canonical writer' );
	$GLOBALS['m3b_error'] = true;
	$failed = $api::create_engagement( $input );
	$check( is_wp_error( $failed ) && $failed->get_error_code() === 'work_service_context_mismatch',
		'canonical domain rejection code preserved' );
	$src = (string) file_get_contents( dirname( __DIR__ ) . '/src/Transport/Rest/WorkspaceEngagementsController.php' );
	// Inspect executable PHP, not prose: the controller documents the
	// canonical Engagements::create() boundary in a comment. Raw substring
	// searches incorrectly report that comment as a second domain writer.
	$executable = '';
	foreach ( token_get_all( $src ) as $token ) {
		if ( is_array( $token ) && in_array( $token[0], [ T_COMMENT, T_DOC_COMMENT, T_CONSTANT_ENCAPSED_STRING ], true ) ) {
			continue;
		}
		$executable .= is_array( $token ) ? $token[1] : $token;
	}
	$check( ! str_contains( $executable, 'Engagements::create(' ) &&
		str_contains( $executable, 'ServiceOperationsActions::create(' ) &&
		! str_contains( $executable, 'Scheduler::' ) && ! str_contains( $executable, 'ServiceBilling::' ),
		'no duplicate domain create, scheduler or billing engine' );
	echo "Work M3-B1 Engagements API: PASS ({$assertions} assertions; role, bounded list, canonical create, safe identity).\n";
}

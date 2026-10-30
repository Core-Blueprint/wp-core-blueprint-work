<?php
declare(strict_types=1);

/** M3-A isolated source/runtime contract: no WordPress database and no writes. */
namespace {
	define( 'ABSPATH', '/tmp/work-m3a/' );
	$GLOBALS['m3a_manager'] = true;
	$GLOBALS['m3a_user'] = 7;
	$GLOBALS['m3a_ready'] = true;
	$GLOBALS['m3a_fail'] = false;
	$GLOBALS['m3a_service_calls'] = [];
	$GLOBALS['m3a_timer_calls'] = [];
	$GLOBALS['m3a_rest_routes'] = [];

	class WP_Error {
		public function __construct( public string $code, public string $message = '', public array $data = [] ) {}
		public function get_error_code(): string { return $this->code; }
	}
	class WP_REST_Request {}
	class WP_REST_Response {
		public function __construct( public array $data ) {}
	}
	class WP_REST_Server { public const READABLE = 'GET'; }

	function current_user_can( string $cap ): bool { return 'cb_manage_work' === $cap && $GLOBALS['m3a_manager']; }
	function get_current_user_id(): int { return $GLOBALS['m3a_user']; }
	function rest_authorization_required_code(): int { return 403; }
	function is_wp_error( mixed $value ): bool { return $value instanceof WP_Error; }
	function cb_work_runtime_ready(): bool { return $GLOBALS['m3a_ready']; }
	function __( string $str, string $domain ): string { return $str; }
	function add_action( string $hook, mixed $action ): void { $GLOBALS['m3a_hook'] = [ $hook, $action ]; }
	function register_rest_route( string $namespace, string $route, array $args ): void {
		$GLOBALS['m3a_rest_routes'][] = [ $namespace, $route, $args ];
	}
	function rest_ensure_response( array $data ): WP_REST_Response { return new WP_REST_Response( $data ); }
}
namespace CB\Work {
	final class Capabilities { public const MANAGE = 'cb_manage_work'; }
}
namespace CB\Work\PublicApi {
	final class ServiceOperations {
		public static function search( array $args ): array|\WP_Error {
			$GLOBALS['m3a_service_calls'][] = $args;
			if ( $GLOBALS['m3a_fail'] ) { return new \WP_Error( 'db_fail' ); }
			return [ 'items' => [], 'total' => isset( $args['active'] ) ? 3 : 7 ];
		}
	}
}
namespace CB\Work\ServiceOperations {
	final class Engagements {
		public static function ready(): bool { return $GLOBALS['m3a_ready']; }
	}
}
namespace CB\Work\Repository {
	final class RecurrenceRules {
		public static array $calls = [];
		public static function search_page( array $filters = [] ): array {
			self::$calls[] = $filters;
			return [ 'rows' => [], 'total' => isset( $filters['status'] ) ? 2 : 5 ];
		}
	}
	final class Timers {
		public static function active_for_user( int $actor ): ?array {
			$GLOBALS['m3a_timer_calls'][] = $actor;
			return [ 'work_item_id' => 42, 'user_id' => $actor ];
		}
	}
}
namespace {
	require dirname( __DIR__ ) . '/src/Operations/WorkspaceSnapshot.php';
	require dirname( __DIR__ ) . '/src/Transport/Rest/WorkspaceOperationsController.php';

	use CB\Work\Operations\WorkspaceSnapshot;
	use CB\Work\Transport\Rest\WorkspaceOperationsController;
	use CB\Work\Repository\RecurrenceRules;

	$checks = 0;
	$check = static function ( bool $ok, string $label ) use ( &$checks ): void {
		++$checks;
		if ( ! $ok ) { fwrite( STDERR, "M3-A operational snapshot FAIL: {$label}\n" ); exit( 1 ); }
	};

	$request = new WP_REST_Request();
	WorkspaceOperationsController::init();
	$check( $GLOBALS['m3a_hook'][0] === 'rest_api_init', 'WordPress registers only on rest_api_init' );
	WorkspaceOperationsController::register_routes();
	$registered = $GLOBALS['m3a_rest_routes'][0] ?? [];
	$check( $registered[0] === 'cb-work/v1' && $registered[1] === '/workspace/operations', 'separate REST route does not extend M1 route regex' );
	$check( $registered[2]['methods'] === WP_REST_Server::READABLE, 'GET-only, not writable' );
	$check( is_callable( $registered[2]['permission_callback'] ), 'explicit WordPress permission callback' );

	$GLOBALS['m3a_manager'] = false;
	$no = WorkspaceOperationsController::read( $request );
	$check( is_wp_error( $no ) && $no->get_error_code() === 'rest_forbidden', 'tracker is denied in callback itself' );
	$check( $GLOBALS['m3a_service_calls'] === [], 'permission denied before database reads' );
	$GLOBALS['m3a_manager'] = true;
	$GLOBALS['m3a_ready'] = false;
	$no = WorkspaceOperationsController::can_read( $request );
	$check( is_wp_error( $no ), 'Base/runtime dependency loss fails closed' );
	$GLOBALS['m3a_ready'] = true;
	$GLOBALS['m3a_user'] = 0;
	$check( is_wp_error( WorkspaceOperationsController::read( $request ) ), 'anonymous actor denied' );
	$GLOBALS['m3a_user'] = 7;
	$check( is_wp_error( WorkspaceSnapshot::read( 8 ) ), 'cannot request another actor timer' );

	$ok = WorkspaceOperationsController::read( $request );
	$check( $ok instanceof WP_REST_Response, 'authorized snapshot REST response' );
	$check( $ok->data['services'] === [ 'total' => 7, 'active' => 3 ], 'Service Operations counts from canonical authorized PublicApi' );
	$check( $ok->data['recurrence'] === [ 'total' => 5, 'active' => 2 ], 'Recurring Work counts from canonical search' );
	$check( $ok->data['time'] === [ 'running' => true, 'work_item_id' => 42 ], 'active timer scoped to actor' );
	$check( $GLOBALS['m3a_timer_calls'] === [ 7 ], 'never queries another timer user' );
	$check( $GLOBALS['m3a_service_calls'] === [
		[ 'page' => 1, 'per_page' => 1 ],
		[ 'page' => 1, 'per_page' => 1, 'active' => 1 ],
	], 'service counts require at most one hydrated row per existing canonical search' );
	$check( RecurrenceRules::$calls === [ [], [ 'status' => 'active' ] ], 'no scheduler or recurrence write path' );

	$GLOBALS['m3a_fail'] = true;
	$check( is_wp_error( WorkspaceSnapshot::read( 7 ) ), 'canonical storage failure does not invent zero counts' );
	$GLOBALS['m3a_fail'] = false;
	$GLOBALS['m3a_ready'] = false;
	$check( is_wp_error( WorkspaceSnapshot::read( 7 ) ), 'schema unready returns error rather than stale data' );

	$php = (string) file_get_contents( dirname( __DIR__ ) . '/src/Operations/WorkspaceSnapshot.php' );
	$check( ! str_contains( $php, '::start(' ) && ! str_contains( $php, '::stop(' ) &&
		! str_contains( $php, 'Scheduler::' ) && ! str_contains( $php, '::create(' ) &&
		! str_contains( $php, '::update(' ), 'read projection does not invoke any writer' );
	echo "Work M3-A operations snapshot runtime: PASS ({$checks} assertions; access, schema, counts, actor isolation, failure).\n";
}

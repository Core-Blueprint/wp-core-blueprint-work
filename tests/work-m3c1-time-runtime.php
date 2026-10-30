<?php
declare(strict_types=1);

namespace {
	define( 'ABSPATH', '/tmp/m3c1/' );
	define( 'CB_WORK_SCHEMA_VERSION', '2' );
	$GLOBALS['m3c_manager'] = true;
	$GLOBALS['m3c_user'] = 7;
	$GLOBALS['m3c_ready'] = true;
	$GLOBALS['m3c_schema'] = '2';
	$GLOBALS['m3c_entries'] = [];
	$GLOBALS['m3c_search'] = [];
	$GLOBALS['m3c_timer_start'] = [];
	$GLOBALS['m3c_timer_stop'] = [];
	$GLOBALS['m3c_events'] = [];
	$GLOBALS['m3c_routes'] = [];
	$GLOBALS['m3c_fail'] = false;

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
	function add_action( string $hook, mixed $callback ): void { $GLOBALS['m3c_hook'] = $hook; }
	function register_rest_route( string $ns, string $route, array $args ): void {
		$GLOBALS['m3c_routes'][] = [ $ns, $route, $args ];
	}
	function is_wp_error( mixed $x ): bool { return $x instanceof WP_Error; }
	function rest_ensure_response( array $x ): WP_REST_Response { return new WP_REST_Response( $x ); }
	function rest_authorization_required_code(): int { return 403; }
	function __( string $s, string $domain ): string { return $s; }
	function current_user_can( string $cap ): bool { return 'cb_manage_work' === $cap && $GLOBALS['m3c_manager']; }
	function get_current_user_id(): int { return $GLOBALS['m3c_user']; }
	function cb_work_runtime_ready(): bool { return $GLOBALS['m3c_ready']; }
	function get_option( string $name, string $default ): string { return $GLOBALS['m3c_schema']; }
	function sanitize_text_field( string $s ): string { return strip_tags( $s ); }
}
namespace CB\Work {
	final class Capabilities { public const MANAGE = 'cb_manage_work'; }
}
namespace CB\Work\Database {
	final class Schema { public const OPTION = 'cb_work_schema'; }
}
namespace CB\Work\Governance {
	final class Events {
		public const TIMER_STARTED = 'timer_started';
		public const TIMER_STOPPED = 'timer_stopped';
	}
}
namespace CoreBlueprint\Core\Governance {
	final class Audit {
		public static function record( string $name, string $severity, array $data ): void {
			$GLOBALS['m3c_events'][] = [ $name, $data ];
		}
	}
}
namespace CB\Work\Repository {
	final class WorkItems {
		public static function get( int $id ): ?array { return $id === 15 ? [ 'title' => '<unsafe>Website' ] : null; }
	}
	final class Timers {
		public static function active_for_user( int $actor ): ?array {
			return $actor === 7 ? [ 'user_id'=>7, 'time_entry_id'=>91,
				'work_item_id'=>15, 'started_at'=>'2026-10-30 08:00:00', 'note'=>'Private note' ] : null;
		}
		public static function start( int $item, int $user, string $note, int $actor ): int {
			$GLOBALS['m3c_timer_start'][] = [ $item, $user, $note, $actor ];
			return $GLOBALS['m3c_fail'] ? 0 : 92;
		}
		public static function stop( int $user, int $actor ): ?array {
			$GLOBALS['m3c_timer_stop'][] = [ $user, $actor ];
			return $GLOBALS['m3c_fail'] ? null : [
				'id'=>91, 'work_item_id'=>15, 'user_id'=>7, 'duration_seconds'=>3600,
			];
		}
	}
	final class TimeEntries {
		public static function query_completed( array $criteria ): array {
			$GLOBALS['m3c_entries'][] = $criteria;
			return [
				'items'=>[
					[ 'id'=>80, 'work_item_id'=>15, 'user_id'=>7, 'started_at'=>'2026-10-29 09:00:00',
						'ended_at'=>'2026-10-29 10:00:00', 'duration_seconds'=>3600, 'revision'=>2,
						'entry_source'=>'timer', 'note'=>'Never return this note' ],
				],
				'total'=>38, 'page'=>1, 'pages'=>4,
			];
		}
	}
}
namespace CB\Work\Time {
	final class Access {
		public static function can_view_entry( array $entry ): bool { return (int) $entry['user_id'] === 7; }
		public static function can_stop_user_timer( int $actor ): bool { return $actor === 7; }
		public static function can_track_work_item( int $id, int $actor ): bool { return $id === 15 && $actor === 7; }
	}
	final class WorkItemPickerSearch {
		public static function results( string $term, int $actor, bool $manager ): array {
			$GLOBALS['m3c_search'][] = [ $term, $actor, $manager ];
			return [ [ 'id'=>15, 'label'=>'Website', 'meta'=>'#15' ] ];
		}
	}
}
namespace {
	require dirname( __DIR__ ) . '/src/Transport/Rest/WorkspaceTimeController.php';
	$api = \CB\Work\Transport\Rest\WorkspaceTimeController::class;
	$count = 0;
	$check = static function ( bool $yes, string $name ) use ( &$count ): void {
		$count++;
		if ( ! $yes ) { fwrite( STDERR, "M3-C1 Time FAIL: {$name}\n" ); exit( 1 ); }
	};
	$api::init();
	$check( 'rest_api_init' === $GLOBALS['m3c_hook'], 'native REST action registration' );
	$api::register_routes();
	$check( count( $GLOBALS['m3c_routes'] ) === 5, 'M3-C1 routes retained and M3-C2 entry create/detail added' );
	$check( $GLOBALS['m3c_routes'][0][2]['methods'] === 'GET'
		&& $GLOBALS['m3c_routes'][1][2]['methods'] === 'GET'
		&& $GLOBALS['m3c_routes'][2][2]['methods'] === 'POST' &&
		$GLOBALS['m3c_routes'][3][2]['methods'] === 'POST' &&
		$GLOBALS['m3c_routes'][4][2]['methods'] === 'GET', 'M3-C1 timer route contracts unchanged, M3-C2 added' );
	$empty = new WP_REST_Request();
	$GLOBALS['m3c_manager'] = false;
	$check( is_wp_error( $api::read( $empty ) ) && is_wp_error( $api::search_items( $empty ) )
		&& is_wp_error( $api::timer( $empty ) ), 'tracker-only actor denied for manager shell route' );
	$check( $GLOBALS['m3c_entries'] === [] && $GLOBALS['m3c_timer_start'] === [],
		'denied actor never reaches data stores' );
	$GLOBALS['m3c_manager'] = true;
	$GLOBALS['m3c_ready'] = false;
	$check( is_wp_error( $api::read( $empty ) ), 'runtime dependency loss denied' );
	$GLOBALS['m3c_ready'] = true;
	$GLOBALS['m3c_schema'] = '1';
	$check( is_wp_error( $api::read( $empty ) ), 'schema mismatch fails closed' );
	$GLOBALS['m3c_schema'] = '2';
	$GLOBALS['m3c_user'] = 0;
	$check( is_wp_error( $api::read( $empty ) ), 'anonymous current actor denied' );
	$GLOBALS['m3c_user'] = 7;
	$read = $api::read( new WP_REST_Request( [ 'page'=>1 ] ) );
	$check( $read instanceof WP_REST_Response && $read->data['actor_id'] === 7
		&& $read->data['timer']['entry_id'] === 91, 'own timer read' );
	$check( $read->data['entries'][0]['title'] === '<unsafe>Website'
		&& ! str_contains( json_encode( $read->data['entries'] ), 'Never return this note' ),
		'bounded safe display projection excludes entry note and unknown private fields' );
	$check( $GLOBALS['m3c_entries'] === [ [
		'user_id'=>7, 'page'=>1, 'per_page'=>10, 'sort'=>'newest',
	] ], 'canonical Time Entries query always own actor, bounded paging' );
	$check( $read->data['total'] === 38 && $read->data['pages'] === 4, 'source paging maintained' );
	$check( is_wp_error( $api::read( new WP_REST_Request( [ 'page'=>[] ] ) ) ),
		'malformed direct callback page fails closed' );
	$result = $api::search_items( new WP_REST_Request( [ 'search'=>'Web' ] ) );
	$check( $result instanceof WP_REST_Response && $result->data['items'][0]['id'] === 15,
		'canonical bounded Work Item search' );
	$check( $GLOBALS['m3c_search'][0] === [ 'Web', 7, true ], 'actor-aware canonical picker' );
	$check( is_wp_error( $api::search_items( new WP_REST_Request( [ 'search'=>[] ] ) ) ),
		'malformed picker never hits database' );
	$badStart = new WP_REST_Request( [ 'operation'=>'start', 'work_item_id'=>18 ] );
	$check( is_wp_error( $api::timer( $badStart ) ), 'cannot start unauthorized Work Item' );
	$check( $GLOBALS['m3c_timer_start'] === [], 'unauthorized start has no write side effect' );
	$start = $api::timer( new WP_REST_Request( [ 'operation'=>'start',
		'work_item_id'=>15, 'note'=>'Work for customer' ] ) );
	$check( $start instanceof WP_REST_Response && $start->data === [ 'started'=>true, 'entry_id'=>92 ],
		'canonical transaction returns true start receipt' );
	$check( $GLOBALS['m3c_timer_start'] === [ [ 15,7,'Work for customer',7 ] ],
		'only current actor timer started' );
	$stop = $api::timer( new WP_REST_Request( [ 'operation'=>'stop', 'user_id'=>999 ] ) );
	$check( $stop instanceof WP_REST_Response && $stop->data === [ 'stopped'=>true, 'entry_id'=>91 ],
		'own timer completed, ignores forged user ID' );
	$check( $GLOBALS['m3c_timer_stop'] === [ [7,7] ], 'cannot stop arbitrary users timer' );
	$check( count( $GLOBALS['m3c_events'] ) === 2
		&& $GLOBALS['m3c_events'][0][0] === 'timer_started'
		&& $GLOBALS['m3c_events'][1][0] === 'timer_stopped',
		'existing governance audit events preserved' );
	$GLOBALS['m3c_fail'] = true;
	$check( is_wp_error( $api::timer( new WP_REST_Request( [ 'operation'=>'stop' ] ) ) ),
		'domain stop failure not reported successful' );
	$check( is_wp_error( $api::timer( new WP_REST_Request( [ 'operation'=>'start',
		'work_item_id'=>15 ] ) ) ), 'domain start conflict not reported successful' );
	$check( count( $GLOBALS['m3c_events'] ) === 2, 'failed timer mutations do not audit false success' );
	$src = (string) file_get_contents( dirname( __DIR__ ) . '/src/Transport/Rest/WorkspaceTimeController.php' );
	// M3-C2 legitimately delegates completed-entry corrections to the canonical
	// TimeEntries::update_completed() API. Enforce the M3-C1 invariant on the
	// actual timer method, not unrelated controller operations.
	$timer_method = new \ReflectionMethod( $api, 'timer' );
	$timer_lines = explode( "\n", $src );
	$timer_source = implode( "\n", array_slice( $timer_lines,
		$timer_method->getStartLine() - 1,
		$timer_method->getEndLine() - $timer_method->getStartLine() + 1
	) );
	$check( ! str_contains( $timer_source, 'WorkSqlTransaction::' )
		&& ! str_contains( $timer_source, 'TimeEntries::update_completed(' )
		&& str_contains( $timer_source, 'Timers::start(' )
		&& str_contains( $timer_source, 'Timers::stop(' ),
		'canonical timer transactions not duplicated' );
	echo "Work M3-C1 Time PHP: PASS ({$count} assertions; roles, actor, bounded reads, timer audit, failures).\n";
}

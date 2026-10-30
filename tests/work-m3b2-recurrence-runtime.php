<?php
declare(strict_types=1);

namespace {
	define( 'ABSPATH', '/tmp/cb-work-m3b2/' );
	$GLOBALS['actor'] = 42;
	$GLOBALS['authorized'] = true;
	$GLOBALS['engagement_reads'] = 0;
	$GLOBALS['period_reads'] = 0;
	$GLOBALS['binds'] = [];
	$GLOBALS['rule_filters'] = [];
	$GLOBALS['bind_fail'] = false;
	$GLOBALS['rest_routes'] = [];
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
	function is_wp_error( mixed $v ): bool { return $v instanceof WP_Error; }
	function rest_ensure_response( array $value ): WP_REST_Response { return new WP_REST_Response( $value ); }
	function __( string $s, string $domain ): string { return $s; }
	function sanitize_text_field( string $s ): string { return trim( strip_tags( $s ) ); }
	function wp_date( string $format ): string { return '2026-10'; }
	function add_action( string $hook, mixed $handler ): void { $GLOBALS['rest_hook'] = $hook; }
	function register_rest_route( string $namespace, string $path, array $options ): void {
		$GLOBALS['rest_routes'][] = [ $namespace, $path, $options ];
	}
}
namespace CB\Work\Content {
	final class ProjectMeta {
		public static function sanitize_date( string $value ): string {
			$d = \DateTimeImmutable::createFromFormat( '!Y-m-d', $value );
			return $d && $d->format('Y-m-d') === $value ? $value : '';
		}
	}
}
namespace CB\Work\Transport\Rest {
	final class WorkspaceEngagementsController {
		public static function can_manage( \WP_REST_Request $request ): bool|\WP_Error {
			return $GLOBALS['authorized'] ? true : new \WP_Error( 'rest_forbidden', '', [ 'status' => 403 ] );
		}
	}
}
namespace CB\Work\ServiceOperations {
	final class Periods {
		public static function bounds( string $month ): ?array {
			return preg_match( '/^[0-9]{4}-(0[1-9]|1[0-2])$/D', $month ) ? [ $month . '-01', $month . '-31' ] : null;
		}
	}
	final class Engagements {
		public static function rules( int $engagement_id ): array|\WP_Error {
			return [ [ 'rule_id' => 105, 'effective_from' => '2026-10-15' ] ];
		}
	}
}
namespace CB\Work\PublicApi {
	final class ServiceOperations {
		public static function engagement( int $id ): array|\WP_Error {
			$GLOBALS['engagement_reads']++;
			if ( 9 !== $id ) return new \WP_Error( 'work_service_not_found' );
			return [ 'id'=>9, 'project_id'=>3, 'service_id'=>5,
				'starts_on'=>'2026-10-01', 'work_context'=>'customer',
				'customer_provider'=>'crm', 'customer_type'=>'company', 'customer_id'=>'private-123' ];
		}
		public static function period( int $id, string $month ): array|\WP_Error {
			$GLOBALS['period_reads']++;
			return [ 'status'=>'not_scheduled', 'scheduled'=>0, 'completed'=>0,
				'overdue'=>0, 'missing'=>0, 'next_execution'=>null, 'last_execution'=>null ];
		}
	}
	final class ServiceOperationsActions {
		public static function bind_rule( int $id, int $rule_id, string $from ): bool|\WP_Error {
			$GLOBALS['binds'][] = [ $id, $rule_id, $from ];
			return $GLOBALS['bind_fail'] ? new \WP_Error( 'work_service_rule_context_mismatch' ) : true;
		}
	}
}
namespace CB\Work\Repository {
	final class RecurrenceRules {
		public static function get( int $id ): ?array {
			$good = [ 'id'=>$id, 'project_id'=>3, 'service_id'=>5,
				'work_context'=>'customer', 'customer_provider'=>'crm',
				'customer_type'=>'company', 'customer_id'=>'private-123',
				'is_active'=>true, 'is_trashed'=>false ];
			if ( 102 === $id ) $good['service_id'] = 999;
			if ( 104 === $id ) $good['is_active'] = false;
			return $id > 0 ? $good : null;
		}
		public static function search_page( array $filters ): array {
			$GLOBALS['rule_filters'][] = $filters;
			$good = [ 'id'=>101, 'title'=>'Monthly maintenance', 'frequency'=>'monthly',
				'project_id'=>3, 'service_id'=>5, 'work_context'=>'customer',
				'customer_provider'=>'crm', 'customer_type'=>'company', 'customer_id'=>'private-123',
				'is_active'=>true, 'is_trashed'=>false, 'next_occurrence_on'=>'2026-11-01' ];
			return [ 'rows'=>[
				$good,
				[ ...$good, 'id'=>102, 'service_id'=>999 ],
				[ ...$good, 'id'=>103, 'customer_id'=>'other-private-customer' ],
				[ ...$good, 'id'=>104, 'is_active'=>false ],
				[ ...$good, 'id'=>105 ],
			], 'page'=>(int)$filters['page'], 'pages'=>2, 'total'=>50 ];
		}
	}
}
namespace {
	require dirname( __DIR__ ) . '/src/Transport/Rest/WorkspaceEngagementRulesController.php';
	$api = \CB\Work\Transport\Rest\WorkspaceEngagementRulesController::class;
	$n = 0;
	$check = static function ( bool $result, string $message ) use ( &$n ): void {
		$n++;
		if ( ! $result ) { fwrite( STDERR, "M3-B2 Recurrence FAIL: {$message}\n" ); exit( 1 ); }
	};
	$api::init();
	$check( 'rest_api_init' === $GLOBALS['rest_hook'], 'registered after REST boot' );
	$api::register_routes();
	$route = $GLOBALS['rest_routes'][0];
	$check( $route[0] === 'cb-work/v1' && str_contains( $route[1], '/rules' ), 'existing Work REST namespace and nested rule URL' );
	$check( $route[2][0]['methods'] === 'GET' && $route[2][1]['methods'] === 'POST',
		'only GET and POST with existing manager authorization' );
	$check( $route[2][1]['permission_callback'] === [\CB\Work\Transport\Rest\WorkspaceEngagementsController::class,'can_manage'], 'reuses Work manager permission' );
	$request = new WP_REST_Request( [ 'engagement_id'=>9, 'month'=>'2026-10',
		'page'=>1, 'search'=>'maintenance' ] );
	$GLOBALS['authorized'] = false;
	$check( is_wp_error( $api::read( $request ) ) && is_wp_error( $api::bind( $request ) ), 'denied inside both callbacks' );
	$check( $GLOBALS['engagement_reads'] === 0 && $GLOBALS['binds'] === [], 'denied without any domain touch' );
	$GLOBALS['authorized'] = true;
	$read = $api::read( $request );
	$check( $read instanceof WP_REST_Response, 'authorized GET returns canonical month result' );
	$check( $read->data['period']['status'] === 'not_scheduled' && $read->data['linked_total'] === 1,
		'does not manufacture scheduled status for linked historic rules' );
	$check( $read->data['rules'] === [ [
		'id'=>101, 'title'=>'Monthly maintenance','frequency'=>'monthly','next_on'=>'2026-11-01'
	] ], 'eligible Project, Service, customer provenance, active and not already linked' );
	$check( ! str_contains( json_encode( $read->data ), 'private-123' ) &&
		! str_contains( json_encode( $read->data ), 'other-private-customer' ), 'no customer IDs disclosed' );
	$check( $read->data['pages'] === 2, 'source pagination maintained even after eligibility filtering' );
	$check( $GLOBALS['rule_filters'][0] === [
		'page'=>1, 'search'=>'maintenance', 'status'=>'active', 'project_id'=>3, 'context'=>'customer'
	], 'canonical indexed and bounded recurrence search' );
	$invalid = $api::read( new WP_REST_Request( [ 'engagement_id'=>9, 'month'=>'2026-13' ] ) );
	$check( is_wp_error( $invalid ) && $GLOBALS['period_reads'] === 1, 'invalid month fails before period query' );
	$check( is_wp_error( $api::read( new WP_REST_Request( [ 'engagement_id'=>9, 'search'=>[] ] ) ) ),
		'direct callback invalid arrays rejected' );
	$check( is_wp_error( $api::read( new WP_REST_Request( [ 'engagement_id'=>0 ] ) ) ),
		'unknown engagement rejected before state read' );
	$bind = $api::bind( new WP_REST_Request( [ 'engagement_id'=>9, 'rule_id'=>101, 'effective_from'=>'2026-10-20' ] ) );
	$check( $bind instanceof WP_REST_Response && $bind->data === [ 'linked'=>true ], 'bind receipt only after canonical API success' );
	$check( $GLOBALS['binds'] === [ [ 9, 101, '2026-10-20' ] ], 'canonical audited binder invoked once' );
	$check( is_wp_error( $api::bind( new WP_REST_Request( [
		'engagement_id'=>9, 'rule_id'=>[], 'effective_from'=>'2026-10-20'
	] ) ) ), 'invalid rule ID array fails closed' );
	$check( is_wp_error( $api::bind( new WP_REST_Request( [
		'engagement_id'=>9, 'rule_id'=>101, 'effective_from'=>'2026-02-30'
	] ) ) ), 'calendar-invalid effective date rejected before domain' );
	$check( is_wp_error( $api::bind( new WP_REST_Request( [
		'engagement_id'=>9, 'rule_id'=>102, 'effective_from'=>'2026-10-20'
	] ) ) ), 'forged rule with different Service rejected before canonical writer' );
	$check( is_wp_error( $api::bind( new WP_REST_Request( [
		'engagement_id'=>9, 'rule_id'=>104, 'effective_from'=>'2026-10-20'
	] ) ) ), 'inactive rule rejected by Workspace chooser transport' );
	$check( count( $GLOBALS['binds'] ) === 1, 'bad binds do not dispatch' );
	$GLOBALS['bind_fail'] = true;
	$bad = $api::bind( new WP_REST_Request( [ 'engagement_id'=>9, 'rule_id'=>103, 'effective_from'=>'2026-10-20' ] ) );
	$check( is_wp_error( $bad ) && $bad->get_error_code() === 'work_service_rule_context_mismatch',
		'canonical context refusal preserved, not a false success' );
	$source = (string) file_get_contents( dirname( __DIR__ ) . '/src/Transport/Rest/WorkspaceEngagementRulesController.php' );
	$check( ! str_contains( $source, 'Engagements::bind_rule(' ) &&
		! str_contains( $source, 'Scheduler::' ) &&
		str_contains( $source, 'ServiceOperationsActions::bind_rule(' ),
		'no duplicated binding/scheduler engine' );
	echo "Work M3-B2 Recurrence runtime: PASS ({$n} assertions; eligibility, period, roles, canonical binding).\n";
}

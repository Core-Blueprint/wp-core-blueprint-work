<?php
declare(strict_types=1);

namespace {
	define( 'ABSPATH', '/tmp/cb-work-m3b3/' );
	$GLOBALS['m3b3_manager'] = true;
	$GLOBALS['m3b3_context'] = 'customer';
	$GLOBALS['m3b3_state'] = 'not_recorded';
	$GLOBALS['m3b3_fail'] = false;
	$GLOBALS['m3b3_reads'] = [];
	$GLOBALS['m3b3_writes'] = [];
	$GLOBALS['m3b3_terms'] = [];
	$GLOBALS['m3b3_routes'] = [];

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
	function add_action( string $hook, mixed $callback ): void { $GLOBALS['m3b3_hook'] = $hook; }
	function register_rest_route( string $ns, string $route, array $args ): void {
		$GLOBALS['m3b3_routes'][] = [ $ns, $route, $args ];
	}
	function is_wp_error( mixed $x ): bool { return $x instanceof WP_Error; }
	function __( string $s, string $domain ): string { return $s; }
	function wp_date( string $format ): string { return '2026-10'; }
	function rest_ensure_response( array $data ): WP_REST_Response { return new WP_REST_Response( $data ); }
}
namespace CB\Work\Transport\Rest {
	final class WorkspaceEngagementsController {
		public static function can_manage( \WP_REST_Request $request ): true|\WP_Error {
			return $GLOBALS['m3b3_manager'] ? true : new \WP_Error( 'rest_forbidden', '', [ 'status' => 403 ] );
		}
	}
}
namespace CB\Work\Content {
	final class ProjectMeta {
		public static function sanitize_date( string $date ): string {
			$d = \DateTimeImmutable::createFromFormat( '!Y-m-d', $date );
			return $d && $d->format( 'Y-m-d' ) === $date ? $date : '';
		}
	}
}
namespace CB\Work\ServiceOperations {
	final class Periods {
		public static function bounds( string $month ): ?array {
			if ( ! preg_match( '/^\d{4}-(0[1-9]|1[0-2])$/D', $month ) ) return null;
			return [ $month . '-01', $month . '-30' ];
		}
	}
}
namespace CB\Work\PublicApi {
	final class ServiceOperations {
		public static function engagement( int $id ): array|\WP_Error {
			return $id === 9 ? [
				'id' => 9, 'work_context' => $GLOBALS['m3b3_context'], 'starts_on' => '2026-09-01',
				'customer_id' => 'TOP-SECRET', 'customer_provider' => 'secret-crm',
			] : new \WP_Error( 'work_service_not_found' );
		}
	}
	final class ServiceBilling {
		public static function coverage( int $id, string $month ): array|\WP_Error {
			$GLOBALS['m3b3_reads'][] = [ $id, $month ];
			if ( $GLOBALS['m3b3_fail'] ) return new \WP_Error( 'db_query_failed' );
			if ( 'internal' === $GLOBALS['m3b3_context'] || 'not_applicable' === $GLOBALS['m3b3_state'] ) {
				return [ 'state' => 'not_applicable', 'registration' => null ];
			}
			return [
				'state' => $GLOBALS['m3b3_state'],
				'period_start' => '2026-10-01', 'period_end_exclusive' => '2026-11-01',
				'registration' => [
					'revision' => 4, 'invoiced_on' => null, 'external_reference' => 'EXT-12',
					'recorded_by' => 991, 'billing_terms_id' => 32,
				],
			];
		}
	}
	final class ServiceBillingActions {
		public static function record( int $id, string $month, string $state, string $date,
			string $reference, int $revision ): array|\WP_Error {
			$GLOBALS['m3b3_writes'][] = [ $id, $month, $state, $date, $reference, $revision ];
			return $GLOBALS['m3b3_fail']
				? new \WP_Error( 'work_service_registration_stale' )
				: [ 'outcome' => 'saved', 'registration' => [ 'revision' => $revision + 1 ] ];
		}
		public static function create_terms( int $id, string $frequency, string $anchor, string $timing ): int|\WP_Error {
			$GLOBALS['m3b3_terms'][] = [ $id, $frequency, $anchor, $timing ];
			return $GLOBALS['m3b3_fail'] ? new \WP_Error( 'work_service_terms_already_set' ) : 33;
		}
	}
}
namespace {
	require dirname( __DIR__ ) . '/src/Transport/Rest/WorkspaceEngagementBillingController.php';
	$api = \CB\Work\Transport\Rest\WorkspaceEngagementBillingController::class;
	$count = 0;
	$check = static function ( bool $ok, string $reason ) use ( &$count ): void {
		$count++;
		if ( ! $ok ) { fwrite( STDERR, "M3-B3 billing FAIL: {$reason}\n" ); exit( 1 ); }
	};
	$api::init();
	$check( 'rest_api_init' === $GLOBALS['m3b3_hook'], 'REST registration native boot' );
	$api::register_routes();
	$check( count( $GLOBALS['m3b3_routes'] ) === 2, 'two registered paths' );
	$check( $GLOBALS['m3b3_routes'][0][2][0]['methods'] === 'GET'
		&& $GLOBALS['m3b3_routes'][0][2][1]['methods'] === 'POST'
		&& $GLOBALS['m3b3_routes'][1][2]['methods'] === 'POST', 'GET+POST record and POST terms only' );
	$check( $GLOBALS['m3b3_routes'][1][2]['permission_callback']
		=== [ \CB\Work\Transport\Rest\WorkspaceEngagementsController::class, 'can_manage' ],
		'existing manager authorization reused' );
	$read = new WP_REST_Request( [ 'engagement_id' => 9, 'month' => '2026-10' ] );
	$write = new WP_REST_Request( [
		'engagement_id' => 9, 'month' => '2026-10', 'state' => 'invoiced',
		'invoiced_on' => '2026-10-29', 'reference' => 'INV-123',
		'expected_revision' => 4, 'customer_id' => 'forged', 'billing_terms_id' => 99,
	] );
	$terms = new WP_REST_Request( [
		'engagement_id' => 9, 'frequency' => 'monthly', 'anchor_on' => '2026-09-01',
		'timing' => 'arrears', 'customer_provider' => 'forged',
	] );
	$GLOBALS['m3b3_manager'] = false;
	$check( is_wp_error( $api::read( $read ) )
		&& is_wp_error( $api::record( $write ) )
		&& is_wp_error( $api::create_terms( $terms ) ), 'all callbacks independently deny non-manager' );
	$check( !$GLOBALS['m3b3_reads'] && !$GLOBALS['m3b3_writes'] && !$GLOBALS['m3b3_terms'],
		'no read/write side effects on denied request' );
	$GLOBALS['m3b3_manager'] = true;
	$result = $api::read( $read );
	$check( $result instanceof WP_REST_Response && $result->data['state'] === 'not_recorded',
		'canonical coverage state' );
	$check( $result->data['registration']['revision'] === 4
		&& $result->data['registration']['external_reference'] === 'EXT-12',
		'CAS revision and external reference read from canonical registration' );
	$check( ! str_contains( json_encode( $result->data ), 'TOP-SECRET' )
		&& ! str_contains( json_encode( $result->data ), 'secret-crm' )
		&& ! isset( $result->data['registration']['recorded_by'] ),
		'customer identity and internal audit actors not exposed' );
	$check( is_wp_error( $api::read( new WP_REST_Request( [
		'engagement_id' => 9, 'month' => [ '2026-10' ],
	] ) ) ), 'direct callback invalid month type' );
	$check( is_wp_error( $api::read( new WP_REST_Request( [
		'engagement_id' => 9, 'month' => '2026-13',
	] ) ) ), 'invalid calendar month' );
	$ok = $api::record( $write );
	$check( $ok instanceof WP_REST_Response
		&& $ok->data === [ 'outcome' => 'saved', 'revision' => 5 ],
		'canonical CAS write with explicit success receipt' );
	$check( $GLOBALS['m3b3_writes'] === [
		[ 9, '2026-10', 'invoiced', '2026-10-29', 'INV-123', 4 ],
	], 'exact canonical arguments; ignores forged fields' );
	$check( is_wp_error( $api::record( new WP_REST_Request( [
		'engagement_id'=>9, 'month'=>'2026-10', 'state'=>'invoiced',
		'expected_revision'=>[ 0 ],
	] ) ) ), 'malformed revision fails before canonical writer' );
	$check( is_wp_error( $api::record( new WP_REST_Request( [
		'engagement_id'=>9, 'month'=>'2026-10', 'state'=>'draft', 'expected_revision'=>4,
	] ) ) ), 'draft is not a valid external registration state' );
	$check( is_wp_error( $api::record( new WP_REST_Request( [
		'engagement_id'=>9, 'month'=>'2026-10', 'state'=>'invoiced',
		'invoiced_on'=>'2026-02-30', 'expected_revision'=>4,
	] ) ) ), 'invalid invoice date rejected' );
	$check( count( $GLOBALS['m3b3_writes'] ) === 1, 'invalid writes do not dispatch' );
	$GLOBALS['m3b3_fail'] = true;
	$conflict = $api::record( $write );
	$check( is_wp_error( $conflict ) && 'work_service_registration_stale' === $conflict->get_error_code()
		&& $conflict->data['status'] === 409, 'canonical revision conflict preserved as 409' );
	$GLOBALS['m3b3_fail'] = false;
	$GLOBALS['m3b3_context'] = 'internal';
	$internal = $api::read( $read );
	$check( $internal instanceof WP_REST_Response
		&& $internal->data['state'] === 'not_applicable'
		&& $internal->data['registration'] === null, 'internal service never shown as billable' );
	$before = count( $GLOBALS['m3b3_writes'] );
	$check( is_wp_error( $api::record( $write ) )
		&& is_wp_error( $api::create_terms( $terms ) ), 'internal cannot record or establish billing terms' );
	$check( count( $GLOBALS['m3b3_writes'] ) === $before && !$GLOBALS['m3b3_terms'],
		'internal requests never reach canonical billing writer' );
	$GLOBALS['m3b3_context'] = 'customer';
	$GLOBALS['m3b3_state'] = 'not_applicable';
	$check( $api::read( $read )->data['state'] === 'not_applicable', 'customer may have no billing terms' );
	$created = $api::create_terms( $terms );
	$check( $created instanceof WP_REST_Response && 201 === $created->status
		&& $created->data === [ 'terms_id' => 33 ], 'terms written only by existing domain API' );
	$check( $GLOBALS['m3b3_terms'] === [ [9,'monthly','2026-09-01','arrears'] ],
		'canonical terms API receives no client-supplied customer context' );
	$check( is_wp_error( $api::create_terms( new WP_REST_Request( [
		'engagement_id'=>9, 'frequency'=>'monthly', 'anchor_on'=>'2026-02-30', 'timing'=>'arrears'
	] ) ) ), 'invalid anchor cannot reach terms writer' );
	$GLOBALS['m3b3_fail'] = true;
	$badTerms = $api::create_terms( $terms );
	$check( is_wp_error( $badTerms )
		&& 'work_service_terms_already_set' === $badTerms->get_error_code(), 'canonical duplicate terms preserved' );
	$code = (string) file_get_contents( dirname( __DIR__ ) . '/src/Transport/Rest/WorkspaceEngagementBillingController.php' );
	$check( ! str_contains( $code, 'BillingRepository::record(' )
		&& ! str_contains( $code, 'Scheduler::' )
		&& str_contains( $code, 'ServiceBillingActions::record(' )
		&& str_contains( $code, 'ServiceBillingActions::create_terms(' ),
		'no new invoice engine, scheduler or duplicated billing repository write' );
	echo "Work M3-B3 billing runtime: PASS ({$count} assertions; CAS, internal segregation, terms, audit API).\n";
}

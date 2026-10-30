<?php
declare(strict_types=1);
namespace {
	define( 'ABSPATH', '/tmp/cb-work-m3b4a/' );
	$GLOBALS['b4_authorized'] = true;
	$GLOBALS['b4_ready'] = true;
	$GLOBALS['b4_filters'] = [];
	class WP_Error {
		public function __construct( private string $code, public string $message = '', public array $data = [] ) {}
		public function get_error_code(): string { return $this->code; }
	}
	class WP_REST_Request {
		public function __construct( private array $params ) {}
		public function get_param( string $key ): mixed { return $this->params[$key] ?? null; }
	}
	class WP_REST_Response {
		public function __construct( public array $data ) {}
	}
	function current_user_can( string $cap ): bool { return $GLOBALS['b4_authorized']; }
	function cb_work_runtime_ready(): bool { return $GLOBALS['b4_ready']; }
	function rest_authorization_required_code(): int { return 403; }
	function __( string $text, string $domain ): string { return $text; }
	function rest_ensure_response( array $result ): WP_REST_Response { return new WP_REST_Response($result); }
	function sanitize_text_field( string $value ): string { return trim( strip_tags( $value ) ); }
}
namespace CB\Work {
	final class Capabilities { public const MANAGE = 'manage_work'; }
}
namespace CB\Work\Repository {
	final class RecurrenceRules {
		public static function search_page( array $filters ): array {
			$GLOBALS['b4_filters'][] = $filters;
			return [ 'rows' => [[
				'id' => 18, 'title' => 'Monthly maintenance', 'frequency' => 'monthly',
				'interval_count' => 1, 'next_occurrence_on' => '2026-11-01',
				'is_active' => true, 'customer_id' => 'private-secret',
				'customer_provider' => 'private', 'assigned_user_ids' => [5],
				'description' => 'private notes',
			]], 'total' => 1, 'page' => 1, 'pages' => 1, 'per_page' => 25 ];
		}
	}
}
namespace {
	require_once dirname(__DIR__) . '/src/Transport/Rest/WorkspaceReadController.php';
	$controller = \CB\Work\Transport\Rest\WorkspaceReadController::class;
	$check = static function( bool $ok, string $label ): void {
		if (!$ok) { fwrite(STDERR, "Work M3-B4-A REST FAIL: {$label}\n"); exit(1); }
	};
	$request = new WP_REST_Request([
		'section' => 'recurring', 'page' => 2, 'per_page' => 100, 'sort' => 'status',
		'search' => '<b>maintenance</b>', 'status' => 'active'
	]);
	$result = $controller::read($request);
	$check($result instanceof WP_REST_Response, 'manager read succeeded');
	$check($result->data['section'] === 'recurring', 'section is recurring');
	$check($result->data['page'] === 1 && $result->data['per_page'] === 25, 'bounded authoritative paging');
	$check(count($result->data['items']) === 1, 'single list item');
	$check(array_keys($result->data['items'][0]) === [
		'id', 'title', 'frequency', 'interval_count', 'next_occurrence_on', 'is_active'
	], 'no customer identity, assignments or description in list');
	$check($GLOBALS['b4_filters'][0]['page'] === 2
		&& $GLOBALS['b4_filters'][0]['status'] === 'active'
		&& $GLOBALS['b4_filters'][0]['search'] === 'maintenance'
		&& $GLOBALS['b4_filters'][0]['sort'] === 'status', 'canonical filter mapping');
	$bad = $controller::read(new WP_REST_Request([
		'section'=>'recurring', 'page'=>1, 'sort'=>'invalid', 'status'=>'invalid', 'search'=>''
	]));
	$check($bad instanceof WP_REST_Response
		&& $GLOBALS['b4_filters'][1]['status'] === ''
		&& $GLOBALS['b4_filters'][1]['sort'] === 'next_occurrence',
		'unknown filters fail to canonical defaults');
	$GLOBALS['b4_authorized'] = false;
	$denied = $controller::read($request);
	$check($denied instanceof WP_Error && $denied->data['status'] === 403
		&& count($GLOBALS['b4_filters']) === 2, '403 before any repository read');
	$GLOBALS['b4_authorized'] = true;
	$GLOBALS['b4_ready'] = false;
	$check($controller::read($request) instanceof WP_Error, 'runtime loss fail closed');
	echo "Work M3-B4-A REST: PASS (manager, canonical filters, bounded paging, privacy, 403).\n";
}

<?php
declare(strict_types=1);
namespace {
	define('ABSPATH', '/tmp/work-b4b/');
	define('CB_WORK_SCHEMA_VERSION', '2.3');
	$GLOBALS['b4b_allow'] = true;
	$GLOBALS['b4b_ready'] = true;
	$GLOBALS['b4b_lock'] = true;
	$GLOBALS['b4b_calls'] = [];
	class WP_Error {
		public function __construct(public string $code, public string $message = '', public array $data = []) {}
	}
	class WP_REST_Request {
		public function __construct(private array $params = [], private ?array $json = null) {}
		public function get_param(string $key): mixed { return $this->params[$key] ?? null; }
		public function get_json_params(): ?array { return $this->json; }
	}
	class WP_REST_Response { public function __construct(public array $data) {} }
	function current_user_can(string $cap): bool { return $GLOBALS['b4b_allow']; }
	function get_current_user_id(): int { return 8; }
	function cb_work_runtime_ready(): bool { return $GLOBALS['b4b_ready']; }
	function get_option(string $key, string $fallback = ''): string { return 'schema' === $key ? '2.3' : 'd/m/Y'; }
	function rest_authorization_required_code(): int { return 403; }
	function __(string $text, string $domain): string { return $text; }
	function rest_ensure_response(array $data): WP_REST_Response { return new WP_REST_Response($data); }
	function current_time(string $format): string { return 'Y-m-d' === $format ? '2026-10-31' : ''; }
	function mysql2date(string $format, string $raw): string { return substr($raw, 0, 10); }
}
namespace CB\Work {
	final class Capabilities { public const MANAGE = 'manage_work'; }
}
namespace CB\Work\Database {
	final class Schema { public const OPTION = 'schema'; }
}
namespace CB\Work\Domain {
	final class RecurrenceSchedule {
		public static function normalize(string $f, int $i, string $s, ?string $e): ?array {
			if (!in_array($f, ['daily','weekly','monthly','yearly'], true) || $i < 1 || $i > 999 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return null;
			return ['frequency'=>$f,'interval_count'=>$i,'start_on'=>$s,'end_on'=>null === $e || '' === $e ? null : $e];
		}
	}
}
namespace CB\Work\Admin {
	final class Recurrence {
		public static function preview_dates(string $f, int $i, string $s, ?string $e, ?string $cursor, bool $use_cursor, ?string $from): array {
			$GLOBALS['b4b_calls'][] = compact('f','i','s','e','cursor','use_cursor','from');
			return [$use_cursor ? ($cursor ?? $s) : $s];
		}
	}
}
namespace CB\Work\Integration {
	final class CRMCustomers {
		public static function selected(string $provider, string $type, string $id): ?array {
			return ['id'=>'crm:contact:12', 'label'=>'Example', 'meta'=>'Contact'];
		}
	}
}
namespace CB\Work\Repository {
	final class RecurrenceOccurrences {
		public static function count_for_rule(int $id): int { return $GLOBALS['b4b_lock'] ? 1 : 0; }
	}
	final class RecurrenceRules {
		public static function get(int $id): ?array {
			if (99 === $id) return null;
			return [
				'id'=>17, 'title'=>'Maintenance', 'description'=>'Private',
				'work_context'=>'customer','customer_provider'=>'crm','customer_type'=>'contact','customer_id'=>'12',
				'project_id'=>2,'service_id'=>3,'work_type_id'=>4,'priority'=>'normal',
				'estimated_minutes'=>45, 'billing_disposition'=>'billable','assigned_user_ids'=>[8],
				'frequency'=>'monthly','interval_count'=>1,'start_on'=>'2026-10-01','end_on'=>null,
				'next_occurrence_on'=>'2026-11-01','create_ahead_days'=>14,'due_offset_days'=>2,
				'is_active'=>true,'updated_at'=>'2026-10-31 10:00:00','is_trashed'=>false,
			];
		}
	}
}
namespace {
	require_once dirname(__DIR__) . '/src/Transport/Rest/WorkspaceRecurrenceEditorController.php';
	$c = \CB\Work\Transport\Rest\WorkspaceRecurrenceEditorController::class;
	$check = static function(bool $value, string $label): void {
		if (!$value) { fwrite(STDERR, "M3-B4-B transport FAIL: {$label}\n"); exit(1); }
	};
	$r = $c::detail(new WP_REST_Request(['id'=>17]));
	$check($r instanceof WP_REST_Response && $r->data['schedule_locked'] === true && $r->data['customer']['id'] === 'crm:contact:12', 'privileged editor detail');
	$check(!array_key_exists('customer_id', $r->data) && !array_key_exists('customer_provider', $r->data), 'no raw customer reference');
	$p = $c::preview(new WP_REST_Request([], [
		'rule_id'=>17,'frequency'=>'monthly','interval_count'=>1,'start_on'=>'2026-10-01','end_on'=>null
	]));
	$check($p instanceof WP_REST_Response && $p->data['dates'][0]['iso']==='2026-11-01', 'locked unchanged cursor');
	$blocked = $c::preview(new WP_REST_Request([], [
		'rule_id'=>17,'frequency'=>'daily','interval_count'=>1,'start_on'=>'2026-10-01'
	]));
	$check($blocked instanceof WP_Error && $blocked->data['status'] === 409 && count($GLOBALS['b4b_calls']) === 1, 'locked changed schedule fails closed');
	$GLOBALS['b4b_lock'] = false;
	$draft = $c::preview(new WP_REST_Request([], [
		'rule_id'=>17,'frequency'=>'daily','interval_count'=>1,'start_on'=>'2026-10-31'
	]));
	$check($draft instanceof WP_REST_Response && $draft->data['dates'][0]['iso'] === '2026-10-31', 'unlocked draft starts new cursor');
	$check($c::detail(new WP_REST_Request(['id'=>99])) instanceof WP_Error, 'missing rule fails closed');
	$GLOBALS['b4b_allow'] = false;
	$check($c::detail(new WP_REST_Request(['id'=>17])) instanceof WP_Error, 'permission denied');
	$check($c::preview(new WP_REST_Request([], ['frequency'=>'monthly','interval_count'=>1,'start_on'=>'2026-10-31'])) instanceof WP_Error, 'preview permission denied');
	$GLOBALS['b4b_allow'] = true;
	$GLOBALS['b4b_ready'] = false;
	$check($c::detail(new WP_REST_Request(['id'=>17])) instanceof WP_Error, 'runtime lost');
	echo "Work M3-B4-B editor detail/preview transport: PASS\n";
}

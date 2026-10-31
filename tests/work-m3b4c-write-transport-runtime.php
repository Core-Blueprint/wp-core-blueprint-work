<?php
declare(strict_types=1);
namespace {
	define('ABSPATH', '/tmp/recurrence-b3/');
	define('CB_WORK_SCHEMA_VERSION', '2.5');
	$GLOBALS['allow'] = true; $GLOBALS['events'] = []; $GLOBALS['writes'] = [];
	class WP_Error { public function __construct(public string $code, public string $message = '', public array $data = []) {} }
	class WP_REST_Request {
		public function __construct(private array $params = [], private ?array $json = null) {}
		public function get_param(string $key): mixed { return $this->params[$key] ?? null; }
		public function get_json_params(): ?array { return $this->json; }
	}
	class WP_REST_Response {
		public int $status = 200;
		public function __construct(public array $data) {}
		public function set_status(int $status): void { $this->status = $status; }
	}
	class WP_REST_Server { public const CREATABLE = 'POST'; public const EDITABLE = 'PATCH'; public const READABLE = 'GET'; }
	function add_action(string $name, array $handler): void {}
	function register_rest_route(string $namespace, string $route, array $options): void {}
	function current_user_can(string $cap): bool { return $GLOBALS['allow']; }
	function get_current_user_id(): int { return 8; }
	function cb_work_runtime_ready(): bool { return true; }
	function get_option(string $key, string $default = ''): string { return '2.5'; }
	function rest_authorization_required_code(): int { return 403; }
	function __(string $text, string $domain): string { return $text; }
	function rest_ensure_response(array $data): WP_REST_Response { return new WP_REST_Response($data); }
	function is_wp_error(mixed $value): bool { return $value instanceof WP_Error; }
	function sanitize_text_field(string $value): string { return trim(strip_tags($value)); }
	function wp_kses_post(string $value): string { return $value; }
	function current_time(string $format): string { return '2026-10-31'; }
	function mysql2date(string $format, string $value): string { return $value; }
}
namespace CB\Work {
	final class Capabilities { public const MANAGE = 'manage_work'; }
}
namespace CB\Work\Database {
	final class Schema { public const OPTION = 'schema'; }
}
namespace CB\Work\Domain {
	final class WorkContext { public const INTERNAL = 'internal'; public const CUSTOMER = 'customer'; }
	final class WorkItemPriority { public static function is_valid(string $v): bool { return in_array($v, ['normal','high'], true); } }
	final class BillingDisposition { public static function is_valid(string $v): bool { return in_array($v, ['billable','non_billable'], true); } }
	final class RecurrenceSchedule {
		public static function frequencies(): array { return ['daily','weekly','monthly']; }
		public static function normalize(string $f, int $i, string $s, ?string $e): ?array { return compact('f','i','s','e'); }
	}
}
namespace CB\Work\Governance {
	final class Events {
		public const RECURRENCE_RULE_CREATED = 'created', RECURRENCE_RULE_UPDATED = 'updated';
		public const RECURRENCE_RULE_STATUS_CHANGED = 'status', RECURRENCE_RULE_TRASHED = 'trash';
		public const RECURRENCE_RULE_RESTORED = 'restore', RECURRENCE_RULE_DELETED = 'deleted';
	}
}
namespace CoreBlueprint\Core\Governance {
	final class Audit {
		public static function record(string $event, string $severity, array $payload): void { $GLOBALS['events'][] = $event; }
	}
}
namespace CB\Work\Integration {
	final class CRMCustomers {
		public static function reference(string $id): array|null|\WP_Error {
			if ('' === $id) return null;
			return 'crm:contact:9' === $id ? ['provider'=>'crm','type'=>'contact','id'=>'9'] : new \WP_Error('invalid');
		}
		public static function selected(string $a, string $b, string $c): ?array { return null; }
	}
}
namespace CB\Work\Admin {
	final class Recurrence { public static function preview_dates(...$args): ?array { return []; } }
}
namespace CB\Work\Repository {
	final class RecurrenceOccurrences { public static function count_for_rule(int $id): int { return 0; } }
	final class RecurrenceRules {
		public static array $rule = [
			'id'=>17,'title'=>'Original','description'=>'','work_context'=>'internal',
			'customer_provider'=>'','customer_type'=>'','customer_id'=>'',
			'project_id'=>0,'service_id'=>0,'work_type_id'=>0,'priority'=>'normal',
			'estimated_minutes'=>0,'billing_disposition'=>'non_billable','assigned_user_ids'=>[],
			'frequency'=>'weekly','interval_count'=>1,'start_on'=>'2026-10-31','end_on'=>null,
			'next_occurrence_on'=>'2026-11-07','create_ahead_days'=>14,'due_offset_days'=>0,
			'is_active'=>false,'revision'=>3,'updated_at'=>'','is_trashed'=>false,
		];
		public static function get(int $id): ?array { return in_array($id, [17,18], true) ? self::$rule : null; }
		public static function create(array $fields): int {
			$GLOBALS['writes'][] = $fields;
			self::$rule = array_merge(self::$rule, $fields, ['id'=>18,'revision'=>1]); return 18;
		}
		public static function update_if_revision(int $id, array $fields, int $revision): true|\WP_Error {
			if (self::$rule['revision'] !== $revision) return new \WP_Error('conflict', '', ['status'=>409]);
			$GLOBALS['writes'][] = $fields; self::$rule = array_merge(self::$rule, $fields, ['revision'=>$revision+1]); return true;
		}
		public static function trash(int $id, int $actor, ?int $expected = null): bool { return false; }
		public static function restore(int $id, int $actor, ?int $expected = null): bool { return false; }
		public static function delete_permanently(int $id, ?int $expected = null): bool { return false; }
	}
}
namespace {
	require_once dirname(__DIR__) . '/src/Transport/Rest/WorkspaceRecurrenceEditorController.php';
	$c = \CB\Work\Transport\Rest\WorkspaceRecurrenceEditorController::class;
	$assert = static function(bool $ok, string $label): void {
		if (!$ok) { fwrite(STDERR, "M3-B4-B3 transport FAIL: {$label}\n"); exit(1); }
	};
	$fields = ['title'=>'A','work_context'=>'internal','frequency'=>'weekly','interval_count'=>1,'start_on'=>'2026-10-31'];
	$bad = $c::create(new WP_REST_Request([], ['fields'=>$fields+['is_active'=>true]]));
	$assert($bad instanceof WP_Error && $bad->data['status']===400 && count($GLOBALS['writes'])===0, 'cannot forge activation');
	$bad = $c::create(new WP_REST_Request([], ['fields'=>$fields+['customer_object_id'=>'forged']]));
	$assert($bad instanceof WP_Error && count($GLOBALS['writes'])===0, 'cannot forge customer');
	$created = $c::create(new WP_REST_Request([], ['fields'=>$fields]));
	$assert($created instanceof WP_REST_Response && $created->status===201 && $GLOBALS['writes'][0]['is_active']===false, 'new rule is inactive');
	$conflict = $c::update(new WP_REST_Request(['id'=>18], ['revision'=>2,'fields'=>['title'=>'Stale']]));
	$assert($conflict instanceof WP_Error && $conflict->data['status']===409, 'stale edit rejected');
	$saved = $c::update(new WP_REST_Request(['id'=>18], ['revision'=>1,'fields'=>['title'=>'New']]));
	$assert($saved instanceof WP_REST_Response && $saved->data['revision']===2, 'partial update');
	$active = $c::lifecycle(new WP_REST_Request(['id'=>18], ['action'=>'activate','revision'=>2]));
	$assert($active instanceof WP_REST_Response && $active->data['is_active']===true, 'versioned activation');
	$stale = $c::lifecycle(new WP_REST_Request(['id'=>18], ['action'=>'trash','revision'=>2]));
	$assert($stale instanceof WP_Error && $stale->data['status']===409, 'revision guarded trash');
	$GLOBALS['allow'] = false;
	$denied = $c::create(new WP_REST_Request([], ['fields'=>$fields]));
	$assert($denied instanceof WP_Error && $denied->data['status']===403, 'permission loss');
	$assert(count($GLOBALS['events'])===3, 'audit events');
	$GLOBALS['allow'] = true;
	\CB\Work\Repository\RecurrenceRules::$rule['next_occurrence_on'] = '2026-10-14';
	\CB\Work\Repository\RecurrenceRules::$rule['is_active'] = false;
	$writesBefore = count($GLOBALS['writes']);
	$pending = $c::lifecycle(new WP_REST_Request(['id'=>18], ['action'=>'activate','revision'=>3]));
	$assert($pending instanceof WP_Error && $pending->code === 'work_recurrence_catchup_confirmation_required'
		&& $pending->data['status'] === 409 && count($GLOBALS['writes']) === $writesBefore, 'past-due rule requires confirmation before write');
	$accepted = $c::lifecycle(new WP_REST_Request(['id'=>18], ['action'=>'activate','revision'=>3,'confirmed'=>true]));
	$assert($accepted instanceof WP_REST_Response && $accepted->data['revision'] === 4, 'explicit confirmed catch-up activates canonically');
	\CB\Work\Repository\RecurrenceRules::$rule['next_occurrence_on'] = '2026-11-07';
	$unnecessary = $c::lifecycle(new WP_REST_Request(['id'=>18], ['action'=>'deactivate','revision'=>4,'confirmed'=>true]));
	$assert($unnecessary instanceof WP_Error && $unnecessary->data['status'] === 400, 'confirmed is not accepted for ordinary lifecycle');
	$unknown = $c::lifecycle(new WP_REST_Request(['id'=>18], ['action'=>'deactivate','revision'=>4,'unexpected'=>true]));
	$assert($unknown instanceof WP_Error && $unknown->data['status'] === 400, 'unexpected lifecycle fields rejected');
	echo "Work M3-B4-B3 REST write transport: PASS\n";
}

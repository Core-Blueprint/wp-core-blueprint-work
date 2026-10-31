<?php
declare(strict_types=1);
namespace {
	define('ABSPATH', '/tmp/cb-work-revision/');
	define('CB_WORK_SCHEMA_VERSION', '2.5');
	define('ARRAY_A', 'ARRAY_A');
	$GLOBALS['revision_schema'] = '2.5';
	class WP_Error {
		public function __construct(public string $code, public string $message = '', public array $data = []) {}
	}
	class RevisionFakeDB {
		public string $last_error = '';
		public array $row;
		public int $writes = 0;
		public function __construct() {
			$this->row = [
				'id'=>17, 'title'=>'Before', 'description'=>'Private', 'work_context'=>'internal',
				'customer_provider'=>'','customer_type'=>'','customer_id'=>'',
				'project_id'=>0,'service_id'=>0,'work_type_id'=>0,'priority'=>'normal',
				'estimated_minutes'=>0,'billing_disposition'=>'non_billable','frequency'=>'weekly',
				'interval_count'=>1,'start_on'=>'2026-10-31','end_on'=>null,
				'next_occurrence_on'=>'2026-11-07','create_ahead_days'=>14,'due_offset_days'=>0,
				'is_active'=>0,'revision'=>5,'trashed_at'=>null,'trashed_by'=>0,
				'created_by'=>8,'updated_by'=>8,'created_at'=>'2026-10-31 10:00:00','updated_at'=>'2026-10-31 10:00:00',
			];
		}
		public function prepare(string $sql, mixed ...$values): string {
			return $sql;
		}
		public function get_var(string $sql): mixed {
			return str_contains($sql, 'engagement_id') ? 0 : 17;
		}
		public function get_col(string $sql): array { return []; }
		public function get_row(string $sql, mixed $output): array { return $this->row; }
		public function update(string $table, array $data, array $where, array $formats, array $whereFormats): int|false {
			if (count($data) !== count($formats)) return false;
			$this->row = array_merge($this->row, $data);
			++$this->writes;
			return 1;
		}
	}
	$wpdb = new RevisionFakeDB();
	function get_option(string $name, string $fallback = ''): string { return $GLOBALS['revision_schema']; }
	function current_time(string $kind, bool $gmt = false): string { return '2026-10-31 10:00:00'; }
	function get_current_user_id(): int { return 8; }
	function sanitize_text_field(string $value): string { return trim(strip_tags($value)); }
	function sanitize_key(string $value): string { return strtolower(trim($value)); }
	function absint(mixed $value): int { return abs((int) $value); }
}
namespace CB\Work\Database { final class Schema {
	public const OPTION = 'cb_work_db_version';
	public static function recurrence_rules_table(): string { return 'wp_cb_work_recurrence_rules'; }
	public static function recurrence_assignments_table(): string { return 'wp_cb_work_recurrence_rule_assignments'; }
	public static function engagement_rules_table(): string { return 'wp_cb_work_service_engagement_rules'; }
}}
namespace CB\Work\Domain {
	final class WorkContext {
		public const INTERNAL = 'internal'; public const CUSTOMER = 'customer';
		public static function sanitize(string $v): string { return $v; }
		public static function is_valid(string $v): bool { return in_array($v, ['internal','customer'], true); }
	}
	final class WorkItemPriority {
		public const NORMAL = 'normal';
		public static function is_valid(string $v): bool { return in_array($v, ['normal','high'], true); }
	}
	final class BillingDisposition {
		public const NON_BILLABLE = 'non_billable';
		public static function is_valid(string $v): bool { return in_array($v, ['non_billable','billable'], true); }
	}
	final class RecurrenceSchedule {
		public static function normalize(string $frequency, int $interval, string $start, ?string $end): ?array {
			if ($interval < 1 || !in_array($frequency, ['weekly','daily'], true)) return null;
			return ['frequency'=>$frequency,'interval_count'=>$interval,'start_on'=>$start,'end_on'=>$end];
		}
	}
}
namespace CB\Work\Repository {
	final class WorkSqlTransaction {
		public static function run(callable $fn, mixed $failure): mixed {
			return $fn();
		}
	}
}
namespace {
	require_once dirname(__DIR__) . '/src/Repository/RecurrenceRules.php';
	$c = \CB\Work\Repository\RecurrenceRules::class;
	$check = static function(bool $ok, string $label): void {
		if (!$ok) { fwrite(STDERR, "M3-B4-B revision runtime FAIL: {$label}\n"); exit(1); }
	};
	$stale = $c::update_if_revision(17, ['title'=>'Should not save'], 4);
	$check($stale instanceof WP_Error && $stale->code === 'work_recurrence_revision_conflict' && $wpdb->writes === 0, 'stale revision denies writing');
	$ok = $c::update_if_revision(17, ['title'=>'Workspace edit'], 5);
	$check(true === $ok && $wpdb->row['revision'] === 6 && $wpdb->row['title'] === 'Workspace edit', 'revision increments under lock');
	$native = $c::update(17, ['title'=>'Native edit']);
	$check($native && $wpdb->row['revision'] === 7 && $wpdb->row['updated_at'] === '2026-10-31 10:00:00', 'native edit increments even in same second');
	$stale = $c::update_if_revision(17, ['title'=>'Overwrite'], 6);
	$check($stale instanceof WP_Error && $wpdb->writes === 2, 'native edit invalidates stale Workspace revision');
	$bad = $c::update_if_revision(17, ['title'=>'No'], 0);
	$check($bad instanceof WP_Error && $bad->data['status'] === 400, 'invalid revision rejected');
	$GLOBALS['revision_schema'] = '2.4';
	$schema = $c::update_if_revision(17, ['title'=>'No'], 7);
	$check($schema instanceof WP_Error && $wpdb->writes === 2, 'outdated schema blocks writers');
	echo "Work M3-B4-B revision runtime: PASS (same-second stale/native/schema).\n";
}

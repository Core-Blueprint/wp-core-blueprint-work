<?php
declare(strict_types=1);

/** M3-C2 standalone REST runtime with real TimeRange (no WordPress/DB mutation). */
namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'CB_WORK_SCHEMA_VERSION', '1.0' );
	class WP_REST_Server { public const READABLE = 'GET'; public const CREATABLE = 'POST'; }
	class WP_REST_Request {
		/** @param array<string,mixed> $data */
		public function __construct( private array $data = [] ) {}
		public function get_param( string $name ): mixed { return $this->data[ $name ] ?? null; }
	}
	class WP_REST_Response { public function __construct( public array $data ) {} }
	class WP_Error {
		public function __construct( public string $code, public string $message = '', public array $data = [] ) {}
	}
	function current_user_can( string $cap ): bool { unset( $cap ); return $GLOBALS['m3c2_manager']; }
	function get_current_user_id(): int { return $GLOBALS['m3c2_actor']; }
	function cb_work_runtime_ready(): bool { return $GLOBALS['m3c2_ready']; }
	function get_option( string $name, string $default = '0' ): string {
		unset( $name, $default ); return $GLOBALS['m3c2_schema'];
	}
	function wp_timezone(): \DateTimeZone { return new \DateTimeZone( 'Europe/Amsterdam' ); }
	function __( string $value, string $domain ): string { unset( $domain ); return $value; }
	function is_wp_error( mixed $value ): bool { return $value instanceof WP_Error; }
	function rest_authorization_required_code(): int { return 403; }
	function rest_ensure_response( array $data ): WP_REST_Response { return new WP_REST_Response( $data ); }
	function add_action( string $hook, callable $cb ): void { unset( $hook, $cb ); }
	function register_rest_route( string $ns, string $route, array $args ): void {
		$GLOBALS['m3c2_routes'][ $ns . $route ] = $args;
	}
}
namespace CB\Work {
	final class Capabilities { public const MANAGE = 'manage_work'; }
}
namespace CB\Work\Database {
	final class Schema { public const OPTION = 'cb_work_schema'; }
}
namespace CB\Work\Governance {
	final class Events {
		public const TIME_ENTRY_CREATED = 'time.created';
		public const TIME_ENTRY_UPDATED = 'time.updated';
	}
}
namespace CoreBlueprint\Core\Governance {
	final class Audit {
		public static function record( string $event, string $level, array $context ): void {
			$GLOBALS['m3c2_events'][] = [ $event, $level, $context ];
		}
	}
}
namespace CB\Work\Time {
	final class Access {
		public static function can_view_entry( array $entry ): bool {
			return (int) $entry['user_id'] === \get_current_user_id();
		}
		public static function can_edit_entry( array $entry, int $target ): bool {
			return self::can_view_entry( $entry ) && (int) $entry['work_item_id'] === $target;
		}
		public static function can_track_work_item( int $item, int $user ): bool {
			return $item === 15 && $user === \get_current_user_id();
		}
	}
	final class WorkItemPickerSearch {}
}
namespace CB\Work\Repository {
	final class WorkItems {
		public static function get( int $id ): ?array {
			return $id === 15 ? [ 'id' => 15, 'title' => 'Site maintenance' ] : null;
		}
	}
	final class Timers {}
	final class TimeEntries {
		public const SOURCE_MANUAL = 'manual';
		public static function get( int $id ): ?array { return $GLOBALS['m3c2_rows'][ $id ] ?? null; }
		public static function create_manual(
			int $item, int $user, string $start, string $end, string $note, int $actor
		): int {
			unset( $actor );
			if ( $item !== 15 || $user !== 7 ) { return 0; }
			$seconds = \CB\Work\Domain\TimeRange::duration_seconds( $start, $end );
			if ( null === $seconds || $seconds <= 0 ) { return 0; }
			$GLOBALS['m3c2_rows'][99] = [
				'id' => 99, 'user_id' => $user, 'work_item_id' => $item,
				'started_at' => $start, 'ended_at' => $end, 'duration_seconds' => $seconds,
				'note' => $note, 'revision' => 1, 'entry_source' => self::SOURCE_MANUAL,
			];
			return 99;
		}
		public static function update_completed(
			int $id, int $expected, int $item, int $user,
			string $start, string $end, string $note, int $actor
		): bool {
			unset( $actor );
			$old = $GLOBALS['m3c2_rows'][ $id ] ?? null;
			if ( $GLOBALS['m3c2_race'] ) {
				$GLOBALS['m3c2_rows'][ $id ]['revision']++;
				$GLOBALS['m3c2_race'] = false;
				return false;
			}
			if ( ! $old || (int) $old['revision'] !== $expected || $old['ended_at'] === null ) { return false; }
			$seconds = \CB\Work\Domain\TimeRange::duration_seconds( $start, $end );
			$preserve_zero = $seconds === 0 && $old['entry_source'] === 'timer'
				&& $old['duration_seconds'] === 0 && $old['started_at'] === $start && $old['ended_at'] === $end;
			if ( null === $seconds || ( $seconds === 0 && ! $preserve_zero ) ) { return false; }
			$GLOBALS['m3c2_rows'][ $id ] = [
				...$old, 'user_id' => $user, 'work_item_id' => $item,
				'started_at' => $start, 'ended_at' => $end,
				'duration_seconds' => $seconds, 'note' => $note, 'revision' => $expected + 1,
			];
			return true;
		}
	}
}
namespace {
	require_once dirname( __DIR__ ) . '/src/Domain/TimeRange.php';
	require_once dirname( __DIR__ ) . '/src/Transport/Rest/WorkspaceTimeController.php';
	$GLOBALS['m3c2_actor'] = 7;
	$GLOBALS['m3c2_manager'] = true;
	$GLOBALS['m3c2_ready'] = true;
	$GLOBALS['m3c2_schema'] = '1.0';
	$GLOBALS['m3c2_rows'] = [
		42 => [
			'id'=>42, 'user_id'=>7, 'work_item_id'=>15, 'revision'=>2,
			'started_at'=>'2026-10-25 01:30:05', 'ended_at'=>'2026-10-25 01:31:05',
			'duration_seconds'=>60, 'note'=>'Existing note', 'entry_source'=>'timer',
		],
		43 => [
			'id'=>43, 'user_id'=>7, 'work_item_id'=>15, 'revision'=>1,
			'started_at'=>'2026-10-25 01:30:05', 'ended_at'=>'2026-10-25 01:30:05',
			'duration_seconds'=>0, 'note'=>'Zero timer', 'entry_source'=>'timer',
		],
		88 => [
			'id'=>88, 'user_id'=>8, 'work_item_id'=>15, 'revision'=>1,
			'started_at'=>'2026-10-25 01:30:05', 'ended_at'=>'2026-10-25 01:31:05',
			'duration_seconds'=>60, 'note'=>'Private note', 'entry_source'=>'manual',
		],
	];
	$GLOBALS['m3c2_events'] = [];
	$GLOBALS['m3c2_race'] = false;
	$count = 0;
	$check = static function ( bool $ok, string $label ) use ( &$count ): void {
		++$count;
		if ( ! $ok ) { fwrite( STDERR, "M3-C2 runtime FAIL: {$label}\n" ); exit( 1 ); }
	};
	$api = \CB\Work\Transport\Rest\WorkspaceTimeController::class;
	$api::register_routes();
	$check( count( $GLOBALS['m3c2_routes'] ) === 5, 'route count' );
	$check( $GLOBALS['m3c2_routes']['cb-work/v1/workspace/time/entry']['permission_callback'] ===
		[ $api, 'can_manage' ], 'write authorization' );
	$detail = $api::entry_detail( new WP_REST_Request( [ 'id'=>42 ] ) );
	$check( $detail instanceof WP_REST_Response && $detail->data['revision'] === 2
		&& $detail->data['start_date'] === '2026-10-25'
		&& $detail->data['start_time'] === '02:30:05'
		&& $detail->data['note'] === 'Existing note', 'local DST-fold detail with note' );
	$check( $api::entry_detail( new WP_REST_Request( [ 'id'=>88 ] ) ) instanceof WP_Error,
		'cross-user details denied' );
	$base = [
		'operation'=>'create', 'work_item_id'=>15, 'start_date'=>'2026-10-23',
		'start_time'=>'08:12:03', 'end_date'=>'2026-10-23', 'end_time'=>'08:30:04', 'note'=>'Done',
	];
	$made = $api::write_entry( new WP_REST_Request( $base ) );
	$check( $made instanceof WP_REST_Response && $made->data['id'] === 99
		&& $GLOBALS['m3c2_rows'][99]['started_at'] === '2026-10-23 06:12:03'
		&& $GLOBALS['m3c2_rows'][99]['duration_seconds'] === 1081, 'manual seconds UTC conversion' );
	$check( count( $GLOBALS['m3c2_events'] ) === 1
		&& $GLOBALS['m3c2_events'][0][0] === 'time.created', 'audit created only once' );
	$zero = [ ...$base, 'end_time'=>'08:12:03' ];
	$invalid = $api::write_entry( new WP_REST_Request( $zero ) );
	$check( $invalid instanceof WP_Error && $invalid->data['status'] === 422, 'manual zero duration denied' );
	$fold = [ ...$base, 'start_date'=>'2026-10-25', 'start_time'=>'02:30:00',
		'end_date'=>'2026-10-25', 'end_time'=>'03:00:00' ];
	$invalid = $api::write_entry( new WP_REST_Request( $fold ) );
	$check( $invalid instanceof WP_Error && $invalid->data['status'] === 422, 'new DST fold rejected' );
	$gap = [ ...$base, 'start_date'=>'2026-03-29', 'start_time'=>'02:30:00',
		'end_date'=>'2026-03-29', 'end_time'=>'03:30:00' ];
	$invalid = $api::write_entry( new WP_REST_Request( $gap ) );
	$check( $invalid instanceof WP_Error && $invalid->data['status'] === 422, 'spring gap rejected' );
	$correction = [
		'operation'=>'update', 'id'=>42, 'expected_revision'=>2, 'work_item_id'=>99, 'user_id'=>88,
		'start_date'=>'2026-10-25', 'start_time'=>'02:30:05',
		'end_date'=>'2026-10-25', 'end_time'=>'02:31:05', 'note'=>'Correction',
	];
	$stale = $api::write_entry( new WP_REST_Request( [ ...$correction, 'expected_revision'=>1 ] ) );
	$check( $stale instanceof WP_Error && $stale->data['status'] === 409, 'stale form rejected' );
	$update = $api::write_entry( new WP_REST_Request( $correction ) );
	$check( $update instanceof WP_REST_Response && $update->data['revision'] === 3
		&& $GLOBALS['m3c2_rows'][42]['started_at'] === '2026-10-25 01:30:05'
		&& $GLOBALS['m3c2_rows'][42]['ended_at'] === '2026-10-25 01:31:05', 'preserve fold instants' );
	$check( $GLOBALS['m3c2_rows'][42]['user_id'] === 7 && $GLOBALS['m3c2_rows'][42]['work_item_id'] === 15,
		'correction cannot change owner or item via client' );
	$check( count( $GLOBALS['m3c2_events'] ) === 2
		&& $GLOBALS['m3c2_events'][1][0] === 'time.updated', 'update audited exactly once' );
	$GLOBALS['m3c2_race'] = true;
	$race = $api::write_entry( new WP_REST_Request( [ ...$correction, 'expected_revision'=>3 ] ) );
	$check( $race instanceof WP_Error && $race->data['status'] === 409
		&& count( $GLOBALS['m3c2_events'] ) === 2, 'two-editor CAS race has no false audit' );
	$zeroCorrection = $api::write_entry( new WP_REST_Request( [
		...$correction, 'id'=>43, 'expected_revision'=>1, 'start_time'=>'02:30:05',
		'end_time'=>'02:30:05',
	] ) );
	$check( $zeroCorrection instanceof WP_REST_Response
		&& $GLOBALS['m3c2_rows'][43]['duration_seconds'] === 0, 'unchanged zero timer correction' );
	$other = $api::write_entry( new WP_REST_Request( [ ...$correction, 'id'=>88, 'expected_revision'=>1 ] ) );
	$check( $other instanceof WP_Error && $other->data['status'] === 403, 'cross-actor correction denied' );
	$GLOBALS['m3c2_manager'] = false;
	$blocked = $api::write_entry( new WP_REST_Request( $base ) );
	$check( $blocked instanceof WP_Error && $blocked->data['status'] === 403, 'tracker cannot write Work-only API' );
	$GLOBALS['m3c2_manager'] = true;
	$GLOBALS['m3c2_ready'] = false;
	$blocked = $api::entry_detail( new WP_REST_Request( [ 'id'=>42 ] ) );
	$check( $blocked instanceof WP_Error, 'runtime unready denied' );
	echo "Work M3-C2 Time PHP: PASS ({$count} assertions; owner, CAS, seconds, DST, audit).\n";
}

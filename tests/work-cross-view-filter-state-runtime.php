<?php
declare(strict_types=1);

// CV-G-002: render the actual private focus preset implementation with
// canonical Work view state. No WordPress install or production data required.
namespace {
	define( 'ABSPATH', '/tmp/wp/' );

	final class WP_Error {}

	function sanitize_key( mixed $raw ): string {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $raw ) ?? '' );
	}
	function sanitize_text_field( string $raw ): string {
		return trim( strip_tags( $raw ) );
	}
	function absint( mixed $raw ): int {
		return abs( (int) $raw );
	}
	function current_time( string $format ): string {
		return 'Y-m' === $format ? '2026-10' : '2026-10-09';
	}
	function wp_date( string $format, int $timestamp ): string {
		return gmdate( $format, $timestamp );
	}
	function get_current_user_id(): int { return 42; }
	function __( string $label, string $domain ): string { return $label; }
	function esc_attr_e( string $label, string $domain ): void {
		echo htmlspecialchars( $label, ENT_QUOTES, 'UTF-8' );
	}
	function esc_html( string $label ): string {
		return htmlspecialchars( $label, ENT_QUOTES, 'UTF-8' );
	}
	function esc_url( string $url ): string {
		return htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' );
	}
	function admin_url( string $path ): string {
		return 'https://example.test/wp-admin/' . $path;
	}
	function add_query_arg( array $args, string $url ): string {
		return $url . '?' . http_build_query( $args );
	}
	function focus_assert( bool $ok, string $message ): void {
		if ( ! $ok ) {
			fwrite( STDERR, "Cross-view focus preset runtime FAILED: {$message}\n" );
			exit( 1 );
		}
	}
}

namespace CB\Work\Domain {
	final class WorkContext {
		public const INTERNAL = 'internal';
		public const CUSTOMER = 'customer';
		public static function all(): array { return [ self::INTERNAL, self::CUSTOMER ]; }
		public static function is_valid( string $value ): bool { return in_array( $value, self::all(), true ); }
		public static function sanitize( mixed $value ): string {
			$value = \sanitize_key( is_scalar( $value ) ? (string) $value : '' );
			return self::is_valid( $value ) ? $value : '';
		}
	}
	final class WorkItemStatus {
		public const PLANNED = 'planned';
		public const IN_PROGRESS = 'in_progress';
		public const BLOCKED = 'blocked';
		public static function active(): array { return [ self::PLANNED, self::IN_PROGRESS, self::BLOCKED ]; }
		public static function is_valid( string $value ): bool {
			return in_array( $value, [ self::PLANNED, self::IN_PROGRESS, self::BLOCKED, 'completed', 'skipped', 'cancelled' ], true );
		}
	}
	final class WorkItemPriority {
		public static function is_valid( string $value ): bool {
			return in_array( $value, [ 'low', 'normal', 'high', 'urgent' ], true );
		}
	}
	final class BillingDisposition {
		public static function is_valid( string $value ): bool {
			return in_array( $value, [ 'hourly', 'fixed', 'included', 'non_billable' ], true );
		}
	}
}

namespace CB\Work\Integration {
	final class CRMCustomers {
		public static function reference( string $token ): array|null|\WP_Error {
			return '' === $token ? null : new \WP_Error();
		}
	}
}

namespace CB\Work\Admin {
	final class Menu {
		public const WORK_ITEMS_SLUG = 'core-blueprint-work-items';
	}
}

namespace {
	require dirname( __DIR__ ) . '/src/Query/WorkItemQuery.php';
	require dirname( __DIR__ ) . '/src/Admin/WorkItemViewState.php';
	require dirname( __DIR__ ) . '/src/Admin/Operations.php';

	$render = new \ReflectionMethod( \CB\Work\Admin\Operations::class, 'render_work_item_focus_views' );

	/**
	 * Render the actual focus links for normalized request state.
	 *
	 * @return array{selected:array<int,string>,links:array<string,array<string,mixed>>,state:array<string,mixed>}
	 */
	function focus_links( \ReflectionMethod $render, array $request ): array {
		$state = \CB\Work\Admin\WorkItemViewState::from_request( $request );
		ob_start();
		$render->invoke( null, $state );
		$html = (string) ob_get_clean();
		focus_assert( str_contains( $html, 'class="cb-work-fast-paths"' ), 'canonical shared focus navigation must render' );
		preg_match_all( '~<a\b([^>]*)>(.*?)</a>~s', $html, $matches, PREG_SET_ORDER );
		$selected = [];
		$links = [];
		foreach ( $matches as $match ) {
			$label = trim( strip_tags( $match[2] ) );
			focus_assert( in_array( $label, [ 'All', 'My work', 'Active', 'Blocked', 'Overdue' ], true ), 'unknown preset' );
			focus_assert( preg_match( '~href="([^"]+)"~', $match[1], $url_match ) === 1, 'focus link missing URL' );
			$url = html_entity_decode( $url_match[1], ENT_QUOTES, 'UTF-8' );
			parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $args );
			$current = str_contains( $match[1], 'aria-current="page"' );
			$css_current = str_contains( $match[1], 'is-current' );
			focus_assert( $current === $css_current, 'selected CSS class and aria-current must agree' );
			if ( $current ) {
				$selected[] = $label;
			}
			$links[ $label ] = $args;
		}
		focus_assert( count( $links ) === 5, 'all five presets must be available to this signed-in operator' );
		return [ 'selected' => $selected, 'links' => $links, 'state' => $state ];
	}

	function expect_preset( \ReflectionMethod $render, array $request, array $expected, string $message ): void {
		$result = focus_links( $render, $request );
		focus_assert( $expected === $result['selected'], $message . ': expected ' . implode( ',', $expected ) . ' got ' . implode( ',', $result['selected'] ) );
		focus_assert( (string) ( $result['links']['All']['view'] ?? '' ) === (string) $result['state']['view'],
			$message . ': All must retain current Work view' );
		if ( 'calendar' === $result['state']['view'] ) {
			focus_assert( '2026-10' === (string) ( $result['links']['All']['calendar_month'] ?? '' ), 'Calendar preset retains selected month' );
		} else {
			focus_assert( ! isset( $result['links']['All']['calendar_month'] ), 'Non-Calendar preset cannot leak calendar month' );
		}
		focus_assert( ! isset( $result['links']['All']['sort'] ), 'All preset must restore view-owned default sort' );
	}

	// Before this fix, the List default 'title' sort erroneously disabled All.
	foreach ( [ 'table', 'list', 'kanban', 'calendar' ] as $view ) {
		$base = [ 'view' => $view, 'calendar_month' => '2026-10' ];
		$plain = focus_links( $render, $base );
		focus_assert( false === $plain['state']['sort_explicit'], "{$view}: default sort must not be explicit" );
		focus_assert( ( 'list' === $view ? 'title' : 'workload' ) === $plain['state']['sort'],
			"{$view}: canonical default sort is preserved" );
		expect_preset( $render, $base, [ 'All' ], "{$view}: default All" );
		expect_preset( $render, $base + [ 'status' => 'active' ], [ 'Active' ], "{$view}: Active" );
		expect_preset( $render, $base + [ 'status' => 'blocked' ], [ 'Blocked' ], "{$view}: Blocked" );
		expect_preset( $render, $base + [ 'status' => 'active', 'assignee_id' => 42 ], [ 'My work' ], "{$view}: My work" );
		expect_preset( $render, $base + [ 'status' => 'active', 'due_to' => '2026-10-08' ], [ 'Overdue' ], "{$view}: Overdue" );

		// Compound criteria are filtered views, not exact presets.
		expect_preset( $render, $base + [ 'status' => 'blocked', 'assignee_id' => 42 ], [], "{$view}: blocked + assignee" );
		expect_preset( $render, $base + [ 'status' => 'blocked', 'due_to' => '2026-10-08' ], [], "{$view}: blocked + due" );
		expect_preset( $render, $base + [ 'status' => 'active', 'assignee_id' => 42, 'due_to' => '2026-10-08' ], [], "{$view}: assignee + due" );
		expect_preset( $render, $base + [ 'sort' => 'due' ], [], "{$view}: explicit sort" );
		expect_preset( $render, $base + [ 's' => 'draft' ], [], "{$view}: Search filter" );
		expect_preset( $render, $base + [ 'project_id' => 17 ], [], "{$view}: Project filter" );
		expect_preset( $render, $base + [ 'customer' => 'invalid-customer' ], [], "{$view}: invalid customer filter" );
	}
	echo "Cross-view focus preset runtime passed (4 views, defaults, exact presets, compound filters, canonical URLs).\n";
}

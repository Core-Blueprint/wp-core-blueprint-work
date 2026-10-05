<?php
declare(strict_types=1);

namespace {
	define( 'ABSPATH', '/tmp/wp/' );

	final class WP_Error {}

	function sanitize_key( string $value ): string {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', $value ) ?? '' );
	}

	function sanitize_text_field( string $value ): string {
		return trim( strip_tags( $value ) );
	}

	function absint( mixed $value ): int {
		return abs( (int) $value );
	}

	function current_time( string $format ): string {
		return 'Y-m' === $format ? '2026-09' : '';
	}

	function assert_true( bool $condition, string $message ): void {
		if ( ! $condition ) {
			fwrite( STDERR, "D2 calendar state smoke failed: {$message}\n" );
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
		public static function is_valid( string $value ): bool { return in_array( $value, [ self::PLANNED, self::IN_PROGRESS, self::BLOCKED, 'completed', 'skipped', 'cancelled' ], true ); }
	}

	final class WorkItemPriority {
		public static function is_valid( string $value ): bool { return in_array( $value, [ 'low', 'normal', 'high', 'urgent' ], true ); }
	}

	final class BillingDisposition {
		public static function is_valid( string $value ): bool { return in_array( $value, [ 'hourly', 'fixed', 'included', 'non_billable' ], true ); }
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

	$calendar = \CB\Work\Admin\WorkItemViewState::from_request( [
		'view'           => 'calendar',
		'calendar_month' => '2028-02',
		'status'         => 'active',
		'priority'       => 'high',
		'project_id'     => '12',
		'paged'          => '2',
	] );

	assert_true( '2028-02' === $calendar['calendar_month'], 'Calendar preserves a valid YYYY-MM month.' );
	assert_true( '' === $calendar['scheduled_from'] && '' === $calendar['scheduled_to'], 'Calendar month bounds do not masquerade as explicit scheduled filters.' );
	assert_true( 500 === $calendar['per_page'], 'Calendar uses the established 500-item operational page ceiling.' );
	assert_true( '2028-02-01' === $calendar['query']['calendar_from'] && '2028-02-29' === $calendar['query']['calendar_to'], 'Calendar viewport boundaries flow into dedicated canonical Calendar criteria.' );
	assert_true( '' === $calendar['query']['scheduled_from'] && '' === $calendar['query']['scheduled_to'], 'Calendar viewport never impersonates explicit scheduled filters in canonical criteria.' );
	assert_true( 2 === $calendar['query']['page'], 'Calendar preserves canonical pagination.' );

	$args = \CB\Work\Admin\WorkItemViewState::query_args( $calendar );
	assert_true(
		[
			'page'           => 'core-blueprint-work-items',
			'view'           => 'calendar',
			'status'         => 'active',
			'priority'       => 'high',
			'project_id'     => 12,
			'calendar_month' => '2028-02',
			'paged'          => 2,
		] === $args,
		'Calendar URLs persist viewport month without derived scheduled filters.'
	);

	$table_from_calendar = \CB\Work\Admin\WorkItemViewState::query_args( $calendar, [ 'view' => 'table', 'page' => 1 ] );
	assert_true( ! isset( $table_from_calendar['calendar_month'] ), 'Leaving Calendar drops Calendar-only viewport state.' );
	assert_true( ! isset( $table_from_calendar['scheduled_from'] ) && ! isset( $table_from_calendar['scheduled_to'] ), 'Leaving Calendar never leaks derived month bounds into Table filters.' );

	$default_month = \CB\Work\Admin\WorkItemViewState::from_request( [
		'view'           => 'calendar',
		'calendar_month' => 'invalid',
	] );
	assert_true( '2026-09' === $default_month['calendar_month'], 'Invalid Calendar month falls back to the WordPress site month.' );
	assert_true( '' === $default_month['scheduled_from'] && '' === $default_month['scheduled_to'], 'Default Calendar viewport does not create visible scheduled filters.' );
	assert_true( '2026-09-01' === $default_month['query']['calendar_from'] && '2026-09-30' === $default_month['query']['calendar_to'], 'Default Calendar query stays bounded to the site month through dedicated Calendar criteria.' );

	$table = \CB\Work\Admin\WorkItemViewState::from_request( [
		'view'           => 'table',
		'calendar_month' => '2028-02',
		'scheduled_from' => '2028-03-01',
		'scheduled_to'   => '2028-03-31',
	] );
	assert_true( '' === $table['calendar_month'], 'Non-Calendar views ignore Calendar month state.' );
	assert_true( '2028-03-01' === $table['scheduled_from'] && '2028-03-31' === $table['scheduled_to'], 'Non-Calendar views retain explicit scheduled filters.' );
	assert_true( 50 === $table['per_page'], 'Non-Calendar operational views retain the 50-item page size.' );

	$calendar_with_filter = \CB\Work\Admin\WorkItemViewState::from_request( [
		'view'           => 'calendar',
		'calendar_month' => '2028-02',
		'scheduled_from' => '2028-03-01',
		'scheduled_to'   => '2028-03-31',
	] );
	assert_true( '2028-03-01' === $calendar_with_filter['scheduled_from'] && '2028-03-31' === $calendar_with_filter['scheduled_to'], 'Calendar preserves explicit scheduled filter state separately from its viewport.' );
	assert_true( '2028-02-01' === $calendar_with_filter['query']['calendar_from'] && '2028-02-29' === $calendar_with_filter['query']['calendar_to'], 'Calendar viewport remains authoritative for Calendar query bounds.' );
	assert_true( '2028-03-01' === $calendar_with_filter['query']['scheduled_from'] && '2028-03-31' === $calendar_with_filter['query']['scheduled_to'], 'Explicit scheduled filters remain explicit canonical criteria alongside Calendar viewport bounds.' );

	$back_to_table = \CB\Work\Admin\WorkItemViewState::query_args( $calendar_with_filter, [ 'view' => 'table', 'page' => 1 ] );
	assert_true( '2028-03-01' === ( $back_to_table['scheduled_from'] ?? '' ) && '2028-03-31' === ( $back_to_table['scheduled_to'] ?? '' ), 'Explicit scheduled filters survive view switching.' );
	assert_true( ! isset( $back_to_table['calendar_month'] ), 'Explicit filters survive without leaking Calendar viewport state.' );

	echo "D2 calendar state smoke passed.\n";
}

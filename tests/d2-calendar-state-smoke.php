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
	final class WorkItemStatus {
		public const PLANNED = 'planned';
		public const IN_PROGRESS = 'in_progress';
		public static function active(): array { return [ self::PLANNED, self::IN_PROGRESS ]; }
		public static function is_valid( string $value ): bool { return in_array( $value, [ self::PLANNED, self::IN_PROGRESS, 'completed', 'skipped', 'cancelled' ], true ); }
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
	assert_true( '2028-02-01' === $calendar['scheduled_from'], 'Calendar derives the first day of the selected month.' );
	assert_true( '2028-02-29' === $calendar['scheduled_to'], 'Calendar derives the leap-year month end correctly.' );
	assert_true( 500 === $calendar['per_page'], 'Calendar uses the established 500-item operational page ceiling.' );
	assert_true( '2028-02-01' === $calendar['query']['scheduled_from'] && '2028-02-29' === $calendar['query']['scheduled_to'], 'Calendar month boundaries flow into the canonical Work Item query.' );
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
		'Calendar URLs persist only canonical month state, not the derived scheduled range.'
	);

	$default_month = \CB\Work\Admin\WorkItemViewState::from_request( [
		'view'           => 'calendar',
		'calendar_month' => 'invalid',
	] );
	assert_true( '2026-09' === $default_month['calendar_month'], 'Invalid Calendar month falls back to the WordPress site month.' );
	assert_true( '2026-09-01' === $default_month['scheduled_from'] && '2026-09-30' === $default_month['scheduled_to'], 'Default Calendar month derives canonical September boundaries.' );

	$table = \CB\Work\Admin\WorkItemViewState::from_request( [
		'view'           => 'table',
		'calendar_month' => '2028-02',
		'scheduled_from' => '2028-03-01',
		'scheduled_to'   => '2028-03-31',
	] );
	assert_true( '' === $table['calendar_month'], 'Non-Calendar views ignore Calendar month state.' );
	assert_true( '2028-03-01' === $table['scheduled_from'] && '2028-03-31' === $table['scheduled_to'], 'Non-Calendar views retain explicit scheduled filters.' );
	assert_true( 50 === $table['per_page'], 'Non-Calendar operational views retain the 50-item page size.' );

	echo "D2 calendar state smoke passed.\n";
}

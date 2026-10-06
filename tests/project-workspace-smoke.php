<?php
declare(strict_types=1);

namespace {
	define( 'ABSPATH', '/tmp/wp/' );
	$GLOBALS['cb_work_project_summary_queries'] = [];
	$GLOBALS['cb_work_project_explicit_counts'] = [
		'in_progress' => 1,
		'blocked'     => 1,
		'completed'   => 1,
		'skipped'     => 0,
		'cancelled'   => 1,
	];

	final class WP_Query {
		public int $found_posts = 0;
		public function __construct( array $args ) {
			$GLOBALS['cb_work_project_summary_queries'][] = $args;
			$status = '';
			foreach ( (array) ( $args['meta_query'] ?? [] ) as $clause ) {
				if ( is_array( $clause ) && '_cb_work_item_status' === ( $clause['key'] ?? '' ) ) {
					$status = (string) ( $clause['value'] ?? '' );
				}
			}
			$this->found_posts = (int) ( $GLOBALS['cb_work_project_explicit_counts'][ $status ] ?? 0 );
		}
	}

	function assert_true( bool $condition, string $message ): void {
		if ( ! $condition ) {
			fwrite( STDERR, "Project workspace smoke failed: {$message}\n" );
			exit( 1 );
		}
	}
}

namespace CB\Work\Content {
	final class PostTypes { public const WORK_ITEM = 'cb_work_item'; }
	final class WorkItemMeta {
		public const PROJECT_ID = '_cb_work_item_project_id';
		public const STATUS = '_cb_work_item_status';
	}
}

namespace CB\Work\Domain {
	final class WorkItemStatus {
		public const PLANNED = 'planned';
		public const IN_PROGRESS = 'in_progress';
		public const BLOCKED = 'blocked';
		public const COMPLETED = 'completed';
		public const SKIPPED = 'skipped';
		public const CANCELLED = 'cancelled';
		public static function all(): array {
			return [ self::PLANNED, self::IN_PROGRESS, self::BLOCKED, self::COMPLETED, self::SKIPPED, self::CANCELLED ];
		}
	}
}

namespace CB\Work\Repository {
	final class WorkItems {
		public static function count_for_project( int $project_id ): int {
			return 77 === $project_id ? 6 : 0;
		}
	}
}

namespace {
	$root = dirname( __DIR__ );
	require $root . '/src/Repository/ProjectWorkSummary.php';

	$counts = \CB\Work\Repository\ProjectWorkSummary::counts_by_status( 77 );
	assert_true( 2 === $counts['planned'], 'Unclassified remainder follows canonical planned semantics.' );
	assert_true( 1 === $counts['in_progress'], 'In-progress Work Items are counted.' );
	assert_true( 1 === $counts['blocked'], 'Blocked Work Items are counted.' );
	assert_true( 1 === $counts['completed'], 'Completed Work Items are counted.' );
	assert_true( 1 === $counts['cancelled'], 'Cancelled Work Items are counted.' );

	$queries = $GLOBALS['cb_work_project_summary_queries'];
	assert_true( 5 === count( $queries ), 'Only the five explicit non-planned statuses require count queries.' );
	foreach ( $queries as $query ) {
		assert_true( 'cb_work_item' === ( $query['post_type'] ?? '' ), 'Summary reads only canonical Work Items.' );
		assert_true( 1 === ( $query['posts_per_page'] ?? 0 ) && false === ( $query['no_found_rows'] ?? true ), 'Each status query returns at most one id while using found_posts for the exact count.' );
		assert_true( 77 === (int) ( $query['meta_query'][0]['value'] ?? 0 ), 'Every status count is scoped to the requested Project.' );
	}

	$menu       = file_get_contents( $root . '/src/Admin/Menu.php' );
	$workspace  = file_get_contents( $root . '/src/Admin/ProjectWorkspace.php' );
	$projects   = file_get_contents( $root . '/src/Admin/Projects.php' );
	$assets     = file_get_contents( $root . '/src/Admin/Assets.php' );
	$summary    = file_get_contents( $root . '/src/Repository/ProjectWorkSummary.php' );
	$css        = file_get_contents( $root . '/assets/project-workspace.css' );
	assert_true( false !== $menu && false !== $workspace && false !== $projects && false !== $assets && false !== $summary && false !== $css, 'Project workspace source files are readable.' );

	assert_true(
		str_contains( $menu, "PROJECT_WORKSPACE_SLUG = 'core-blueprint-work-project'" )
		&& str_contains( $menu, "[ ProjectWorkspace::class, 'render' ]" )
		&& str_contains( $menu, "add_submenu_page( self::TOP_LEVEL_SLUG, __( 'Project Workspace'" )
		&& str_contains( $menu, 'remove_submenu_page( self::TOP_LEVEL_SLUG, self::PROJECT_WORKSPACE_SLUG )' )
		&& str_contains( $menu, 'project_workspace_url' ),
		'Project workspace is a capability-gated contextual route registered under Work and hidden only from submenu navigation.'
	);
	assert_true(
		str_contains( $workspace, 'ProjectWorkSummary::counts_by_status' )
		&& str_contains( $workspace, 'WorkItems::for_project' )
		&& str_contains( $workspace, 'CRMCustomers::label' ),
		'Workspace composes existing Project, Work Item and CRM read contracts.'
	);
	assert_true(
		str_contains( $workspace, 'Add Work Item' )
		&& str_contains( $workspace, 'View all Work Items' )
		&& str_contains( $workspace, 'Edit project details' ),
		'Workspace exposes the three intended operational fast paths.'
	);
	assert_true(
		! preg_match( '/\b(?:update_post_meta|add_post_meta|delete_post_meta|wp_insert_post|wp_update_post|wpdb->)\b/', $workspace )
		&& ! preg_match( '/\b(?:update_post_meta|add_post_meta|delete_post_meta|wp_insert_post|wp_update_post|wpdb->)\b/', $summary ),
		'Project workspace and summary read model do not become persistence owners.'
	);
	assert_true(
		! str_contains( $summary, "'posts_per_page'         => -1" )
		&& str_contains( $summary, "'posts_per_page'         => 1" ),
		'Project summary does not load an unbounded result set.'
	);
	assert_true(
		str_contains( $projects, "add_filter( 'post_row_actions'" )
		&& str_contains( $projects, 'Open workspace' )
		&& str_contains( $projects, 'Open Project Workspace' ),
		'Native Projects admin exposes the workspace without replacing WordPress Edit.'
	);
	assert_true(
		str_contains( $assets, "CB_WORK_DIR . 'assets/project-workspace.css'" )
		&& str_contains( $assets, 'PROJECT_WORKSPACE_SLUG' )
		&& str_contains( $css, '@media screen and (max-width: 782px)' ),
		'Project workspace presentation is route-scoped and responsive.'
	);

	echo "Project workspace smoke passed.\n";
}

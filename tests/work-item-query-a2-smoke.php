<?php
declare(strict_types=1);

/** A2 source contract. Real SQL behavior is tested by WP/MariaDB Level 2. */
$root = dirname( __DIR__ );
$repo = (string) file_get_contents( $root . '/src/Repository/WorkItems.php' );
$read = (string) file_get_contents( $root . '/src/Repository/WorkItemReadQuery.php' );
$fixtures = (string) file_get_contents( $root . '/tests/integration/WorkItemQueryScalabilityIntegrationTest.php' );

$checks = [
	'No unbounded Work Item IDs or PHP-side page slicing' =>
		! str_contains( $repo, "'posts_per_page'         => -1" )
		&& ! str_contains( $repo, 'array_slice( $ids' )
		&& ! str_contains( $repo, 'assigned_work_item_ids(' )
		&& ! str_contains( $repo, 'sort_query_ids(' ),
	'Canonical all/project/search pathways use bounded database-side paging' =>
		substr_count( $repo, 'self::search(' ) >= 2
		&& str_contains( $repo, 'WorkItemReadQuery::page( $args, $criteria )' )
		&& str_contains( $read, "'posts_per_page']         = \$per_page" )
		&& str_contains( $read, "'paged']                  = \$page" )
		&& str_contains( $read, "'no_found_rows']          = false" ),
	'Assignment membership remains in a scoped SQL join' =>
		str_contains( $read, 'INNER JOIN ' )
		&& str_contains( $read, 'cb_work_a2_assigned' )
		&& str_contains( $read, 'cb_work_a2_scope' )
		&& str_contains( $read, 'remove_filter( ' )
		&& str_contains( $read, "'distinct'] = 'DISTINCT'" ),
	'Five canonical sorting contracts run in SQL before LIMIT' =>
		str_contains( $read, 'WorkItemQuery::SORT_TITLE' )
		&& str_contains( $read, 'WorkItemQuery::SORT_UPDATED' )
		&& str_contains( $read, 'WorkItemQuery::SORT_DUE' )
		&& str_contains( $read, 'WorkItemQuery::SORT_SCHEDULED' )
		&& str_contains( $read, "'in_progress'" )
		&& str_contains( $read, 'orderby_sql(' ),
	'Status counts aggregate in SQL instead of loading all post IDs' =>
		str_contains( $repo, 'WorkItemReadQuery::counts_by_status()' )
		&& str_contains( $read, 'COUNT(*) AS item_count' )
		&& str_contains( $read, 'GROUP BY canonical_status' ),
	'Only current page posts and Work links are primed' =>
		str_contains( $repo, 'WorkItemReadQuery::prime_page( $page_ids )' )
		&& str_contains( $repo, 'WorkItemReadQuery::related_page( $page_ids, self::schema_ready() )' )
		&& str_contains( $read, '_prime_post_caches(' )
		&& str_contains( $read, 'WHERE work_item_id IN (' ),
	'MariaDB integration covers paging, sorts, assignment, counts and 1k/10k/25k' =>
		str_contains( $fixtures, 'test_bounded_workload_paging_total_and_out_of_range_page' )
		&& str_contains( $fixtures, 'test_sort_modes_keep_stable_id_ties_and_filters' )
		&& str_contains( $fixtures, 'test_assignment_sql_filter_and_page_batched_links' )
		&& str_contains( $fixtures, 'test_status_aggregate_uses_planned_fallback_and_excludes_trash' )
		&& str_contains( $fixtures, 'CB_WORK_A2_PROFILE_SIZE' )
		&& str_contains( $fixtures, '[ 1000, 10000, 25000 ]' ),
];
foreach ( $checks as $name => $ok ) {
	if ( ! $ok ) {
		fwrite( STDERR, "Work A2 query Golden smoke failed: {$name}\n" );
		exit( 1 );
	}
}
echo "Work A2 Query Scalability Golden smoke passed (SQL paging, sorts, counts, bounded hydration and opt-in profile).\n";

<?php
declare(strict_types=1);

define( 'ABSPATH', '/tmp/wp/' );

require dirname( __DIR__ ) . '/src/Domain/WorkItemStatus.php';
require dirname( __DIR__ ) . '/src/Domain/WorkItemPriority.php';
require dirname( __DIR__ ) . '/src/Domain/BillingDisposition.php';

use CB\Work\Domain\BillingDisposition;
use CB\Work\Domain\WorkItemPriority;
use CB\Work\Domain\WorkItemStatus;

$checks = [
	'baseline Work Item statuses are canonical' => WorkItemStatus::all() === [ 'planned', 'in_progress', 'blocked', 'completed', 'skipped', 'cancelled' ],
	'active workload includes blocked work and excludes terminal states' => WorkItemStatus::active() === [ 'planned', 'in_progress', 'blocked' ],
	'planned can enter progress, become blocked or close explicitly' => WorkItemStatus::can_transition( 'planned', 'in_progress' ) && WorkItemStatus::can_transition( 'planned', 'blocked' ) && WorkItemStatus::can_transition( 'planned', 'completed' ) && WorkItemStatus::can_transition( 'planned', 'skipped' ) && WorkItemStatus::can_transition( 'planned', 'cancelled' ),
	'in progress can become blocked or close but cannot silently return to planned' => WorkItemStatus::can_transition( 'in_progress', 'blocked' ) && WorkItemStatus::can_transition( 'in_progress', 'completed' ) && ! WorkItemStatus::can_transition( 'in_progress', 'planned' ),
	'blocked work can resume, return to planned or close explicitly' => WorkItemStatus::can_transition( 'blocked', 'in_progress' ) && WorkItemStatus::can_transition( 'blocked', 'planned' ) && WorkItemStatus::can_transition( 'blocked', 'completed' ) && WorkItemStatus::can_transition( 'blocked', 'skipped' ) && WorkItemStatus::can_transition( 'blocked', 'cancelled' ),
	'terminal states stay terminal in D1' => [] === WorkItemStatus::transitions_from( 'completed' ) && [] === WorkItemStatus::transitions_from( 'skipped' ) && [] === WorkItemStatus::transitions_from( 'cancelled' ),
	'priority set is bounded' => WorkItemPriority::all() === [ 'low', 'normal', 'high', 'urgent' ],
	'billing disposition stays separate from completion state' => BillingDisposition::all() === [ 'hourly', 'fixed', 'included', 'non_billable' ] && ! BillingDisposition::is_valid( 'completed' ),
];

foreach ( $checks as $label => $passed ) {
	if ( ! $passed ) {
		fwrite( STDERR, "D1 domain smoke failed: {$label}\n" );
		exit( 1 );
	}
}

echo "D1 domain smoke passed.\n";

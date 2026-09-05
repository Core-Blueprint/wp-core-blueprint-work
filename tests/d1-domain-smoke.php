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
	'baseline Work Item statuses are canonical' => WorkItemStatus::all() === [ 'planned', 'in_progress', 'completed', 'skipped', 'cancelled' ],
	'active workload excludes terminal states' => WorkItemStatus::active() === [ 'planned', 'in_progress' ],
	'planned can enter progress or close explicitly' => WorkItemStatus::can_transition( 'planned', 'in_progress' ) && WorkItemStatus::can_transition( 'planned', 'completed' ) && WorkItemStatus::can_transition( 'planned', 'skipped' ) && WorkItemStatus::can_transition( 'planned', 'cancelled' ),
	'in progress can close but cannot silently return to planned' => WorkItemStatus::can_transition( 'in_progress', 'completed' ) && ! WorkItemStatus::can_transition( 'in_progress', 'planned' ),
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

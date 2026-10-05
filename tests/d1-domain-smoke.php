<?php
declare(strict_types=1);

define( 'ABSPATH', '/tmp/wp/' );

require dirname( __DIR__ ) . '/src/Domain/WorkContext.php';
require dirname( __DIR__ ) . '/src/Domain/WorkItemStatus.php';
require dirname( __DIR__ ) . '/src/Domain/WorkItemPriority.php';
require dirname( __DIR__ ) . '/src/Domain/BillingDisposition.php';

use CB\Work\Domain\BillingDisposition;
use CB\Work\Domain\WorkContext;
use CB\Work\Domain\WorkItemPriority;
use CB\Work\Domain\WorkItemStatus;

$checks = [
	'Work context explicit states are bounded to Internal and Customer' => WorkContext::all() === [ 'internal', 'customer' ],
	'Work context validation accepts only canonical explicit states' => WorkContext::is_valid( 'internal' ) && WorkContext::is_valid( 'customer' ) && ! WorkContext::is_valid( '' ) && ! WorkContext::is_valid( 'unclassified' ),
	'only Customer context requires a customer reference' => WorkContext::requires_customer( 'customer' ) && ! WorkContext::requires_customer( 'internal' ),
	'baseline Work Item statuses are canonical' => WorkItemStatus::all() === [ 'planned', 'in_progress', 'blocked', 'completed', 'skipped', 'cancelled' ],
	'active workload includes blocked work and excludes terminal states' => WorkItemStatus::active() === [ 'planned', 'in_progress', 'blocked' ],
	'planned can enter progress, become blocked or close explicitly' => WorkItemStatus::can_transition( 'planned', 'in_progress' ) && WorkItemStatus::can_transition( 'planned', 'blocked' ) && WorkItemStatus::can_transition( 'planned', 'completed' ) && WorkItemStatus::can_transition( 'planned', 'skipped' ) && WorkItemStatus::can_transition( 'planned', 'cancelled' ),
	'in progress can be replanned, blocked or closed explicitly' => WorkItemStatus::can_transition( 'in_progress', 'planned' ) && WorkItemStatus::can_transition( 'in_progress', 'blocked' ) && WorkItemStatus::can_transition( 'in_progress', 'completed' ),
	'blocked work can resume, return to planned or close explicitly' => WorkItemStatus::can_transition( 'blocked', 'in_progress' ) && WorkItemStatus::can_transition( 'blocked', 'planned' ) && WorkItemStatus::can_transition( 'blocked', 'completed' ) && WorkItemStatus::can_transition( 'blocked', 'skipped' ) && WorkItemStatus::can_transition( 'blocked', 'cancelled' ),
	'completed work has an explicit reopen exception while closed states remain terminal' => [ 'in_progress' ] === WorkItemStatus::transitions_from( 'completed' ) && [] === WorkItemStatus::transitions_from( 'skipped' ) && [] === WorkItemStatus::transitions_from( 'cancelled' ) && WorkItemStatus::is_terminal( 'completed' ) && WorkItemStatus::is_terminal( 'skipped' ) && WorkItemStatus::is_terminal( 'cancelled' ),
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

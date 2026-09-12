<?php
declare(strict_types=1);

$root       = dirname( __DIR__ );
$bootstrap  = file_get_contents( $root . '/core-blueprint-work.php' );
$schema     = file_get_contents( $root . '/src/Database/Schema.php' );
$unit       = file_get_contents( $root . '/src/Domain/BillingUnit.php' );
$builder    = file_get_contents( $root . '/src/Billing/SnapshotBuilder.php' );
$repository = file_get_contents( $root . '/src/Repository/BillingUnits.php' );
$reads      = file_get_contents( $root . '/src/PublicApi/Billing.php' );
$actions    = file_get_contents( $root . '/src/PublicApi/BillingActions.php' );
$resolver   = file_get_contents( $root . '/src/Pricing/Resolver.php' );
$providers  = file_get_contents( $root . '/src/PublicApi/PricingProviders.php' );
$events     = file_get_contents( $root . '/src/Governance/Events.php' );
$docs       = file_get_contents( $root . '/docs/PUBLIC-BILLING-API.md' );

$checks = [
	'F2 advances only Work schema to 1.8 while plugin remains rc1' => str_contains( $bootstrap, "CB_WORK_VERSION', '1.0.0-rc1'" ) && str_contains( $bootstrap, "CB_WORK_SCHEMA_VERSION', '1.8'" ),
	'billing units snapshots and external refs are separate Work-owned tables' => str_contains( $schema, "'cb_work_billing_units'" ) && str_contains( $schema, "'cb_work_billing_snapshots'" ) && str_contains( $schema, "'cb_work_billing_external_refs'" ),
	'each Work billing source unit has one current readiness identity' => str_contains( $schema, 'UNIQUE KEY source_unit (unit_type,unit_id)' ),
	'concurrent first prepare converges through database identity upsert and row locking' => str_contains( $repository, 'ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)' ) && str_contains( $repository, 'LIMIT 1 FOR UPDATE' ),
	'snapshot history is versioned instead of overwritten' => str_contains( $schema, 'UNIQUE KEY unit_version (billing_unit_id,snapshot_version)' ) && ! str_contains( $repository, 'UPDATE ' . "' . Schema::billing_snapshots_table()" ),
	'commercial references are provider-neutral and allow one external resource to aggregate multiple Work units' => str_contains( $schema, 'UNIQUE KEY unit_resource (billing_unit_id,provider,resource_type,resource_id)' ) && str_contains( $schema, 'KEY external_ref (provider,resource_type,resource_id)' ),
	'hourly readiness uses Time Entries while fixed readiness uses Work Items' => str_contains( $unit, "TIME_ENTRY = 'time_entry'" ) && str_contains( $unit, "WORK_ITEM = 'work_item'" ) && str_contains( $builder, 'BillingDisposition::HOURLY' ) && str_contains( $builder, 'BillingDisposition::FIXED' ),
	'included and non-billable Work cannot become billing units' => str_contains( $builder, 'BillingDisposition::INCLUDED' ) && str_contains( $builder, 'BillingDisposition::NON_BILLABLE' ) && str_contains( $builder, "'work_billing_not_billable'" ),
	'billing eligibility fail-closes on pricing-model mismatch' => str_contains( $builder, 'ServicePricing::MODEL_HOURLY' ) && str_contains( $builder, 'ServicePricing::MODEL_FIXED' ) && str_contains( $builder, "'work_billing_pricing_mismatch'" ),
	'non-exempt readiness requires concrete applicable Work tax context' => str_contains( $builder, "'work_billing_tax_context_required'" ) && str_contains( $builder, "'work_billing_tax_context_invalid'" ) && str_contains( $builder, 'TaxRates::is_available' ),
	'Work handoff snapshot freezes rate tax and provenance input without invoice totals' => str_contains( $builder, "'rate_amount_minor'" ) && str_contains( $builder, "'pricing_context'" ) && str_contains( $builder, "'tax_context'" ) && ! str_contains( $builder, 'invoice_total' ) && ! str_contains( $builder, 'tax_total' ) && ! str_contains( $builder, 'payment_status' ),
	'pricing provider context preserves provider and opaque customer reference while retaining numeric compatibility' => str_contains( $resolver, "'customer_provider'" ) && str_contains( $resolver, "'customer_reference_id'" ) && str_contains( $providers, 'customer_reference_id:string' ) && str_contains( $providers, 'legacy numeric compatibility field' ),
	'snapshot fingerprint detects source and commercial-input drift' => str_contains( $builder, "hash( 'sha256'" ) && str_contains( $reads, "'stale'" ) && str_contains( $repository, "'work_billing_snapshot_stale'" ),
	'already-linked changed sources fail closed rather than re-billing silently' => str_contains( $repository, "'work_billing_linked_source_changed'" ) && str_contains( $repository, "'work_billing_already_linked'" ),
	'billing reads and mutations require Work management authority' => str_contains( $reads, 'current_user_can( Capabilities::MANAGE )' ) && str_contains( $actions, 'current_user_can( Capabilities::MANAGE )' ),
	'billing audit contexts keep internal correlation but omit raw external resource identifiers' => str_contains( $actions, "'billing_unit_id'" ) && str_contains( $actions, "'snapshot_id'" ) && ! preg_match( "/Audit::record\([^;]*['\"]resource_id['\"]\s*=>/s", $actions ),
	'billing lifecycle is governed and observable' => str_contains( $events, 'work.billing.ready' ) && str_contains( $events, 'work.billing.external.linked' ) && str_contains( $actions, "do_action( 'cb_work_billing_external_linked'" ),
	'F2 stays Invoice and Quotes neutral in executable source' => ! str_contains( $builder . $repository . $reads . $actions, 'Invoice' ) && ! str_contains( $builder . $repository . $reads . $actions, 'CB\\Invoice' ),
	'documentation explicitly separates Work input snapshots from financial document truth' => str_contains( $docs, 'operational/commercial input frozen at handoff time' ) && str_contains( $docs, 'authoritative financial document calculation' ),
	'F2 introduces no cross-plugin SQL foreign keys' => ! str_contains( strtoupper( $schema ), 'FOREIGN KEY' ),
];

foreach ( $checks as $label => $passed ) {
	if ( ! $passed ) {
		fwrite( STDERR, "F2 billing readiness smoke failed: {$label}\n" );
		exit( 1 );
	}
}

echo "F2 billing readiness smoke passed.\n";

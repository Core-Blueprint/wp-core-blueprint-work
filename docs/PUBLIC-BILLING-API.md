# Core Blueprint Work — Public Billing Readiness API

## Authority boundary

Work owns operational billing readiness and the immutable handoff input used when operational work becomes commercially processable.

Work does **not** own invoice numbering, invoice/credit-note lifecycle, payment state, legal document totals, VAT rounding, financial fingerprints or authoritative financial document revisions. Those remain the responsibility of a commercial provider such as Core Blueprint Invoice & Quotes.

A Work snapshot therefore means:

> operational/commercial input frozen at handoff time

not:

> authoritative financial document calculation

## Billing units

Billing readiness is attached to a concrete Work-owned billing unit rather than one global flag on a Work Item.

- `work_item` — used for fixed-price Work Items;
- `time_entry` — used for completed hourly Time Entries.

This allows one long-running hourly Work Item to produce multiple independently processable Time Entries across different billing periods.

`included` and `non_billable` Work remain available for operational reporting but cannot become externally billable units.

## Eligibility

Readiness is fail-closed.

A fixed Work Item must:

- use billing disposition `fixed`;
- be completed;
- have a customer reference and Service;
- resolve to a fixed or recurring Service pricing model;
- have usable rate and tax-input context.

An hourly Time Entry must:

- be completed and have positive duration;
- belong to a Work Item using billing disposition `hourly`;
- have a customer reference and Service;
- resolve to an hourly Service pricing model;
- have usable rate and tax-input context.

Pricing is resolved for the billing unit's operational effective date: Time Entry end date for hourly work and Work Item completion date for fixed work.

## Immutable snapshots

`BillingActions::prepare()` creates an immutable snapshot version. The snapshot stores only the Work-side handoff input required for later commercial processing, including:

- billing unit identity and quantity basis;
- Work Item and Service references;
- customer provider/type/id reference;
- billing disposition;
- resolved rate/currency/pricing-model context;
- pricing source/provenance and agreement reference when present;
- tax mode and the applicable Work tax-rate projection;
- source revision/context needed to detect later changes.

Snapshots are append-only. Refreshing a not-yet-linked billing unit creates a new snapshot version; it never overwrites the historical one.

The snapshot intentionally does **not** calculate or store authoritative invoice line totals, tax totals, rounding results, invoice totals, payment state or credit state.

## Freshness and correction safety

Each snapshot has a SHA-256 fingerprint over its Work handoff payload.

Before an external commercial reference can be attached, Work rebuilds the current candidate and requires it to match the current snapshot fingerprint.

If a Time Entry, Work Item, Service pricing, pricing-provider agreement or tax context changes after readiness, the unit becomes stale for commercial handoff. A not-yet-linked unit may be deliberately prepared again, producing a new immutable snapshot version.

Once a unit is externally linked, a changed Work source is not silently re-prepared or re-billed. Work fails closed so later reconciliation can be handled explicitly instead of risking duplicate commercial processing.

## External commercial references

External references are provider-neutral. They contain:

- `provider`;
- `resource_type`;
- `resource_id`;
- `display_reference`;
- `status_projection`;
- `linked_at` / `updated_at`.

A Work billing unit may be linked to one canonical external commercial resource through the F2 action. The same external resource may be linked to multiple Work billing units, allowing one invoice/document to aggregate several Work units.

`status_projection` is explicitly an external-provider projection. Values such as `issued`, `paid` or `credited` never become Work-owned financial states.

## Public PHP contracts

### Reads

`CB\Work\PublicApi\Billing`

- `inspect( string $unit_type, int $unit_id )`
- `ready( int $limit = 100 )`
- `snapshot( int $snapshot_id )`
- `snapshots( string $unit_type, int $unit_id, int $limit = 50 )`

### Mutations

`CB\Work\PublicApi\BillingActions`

- `prepare( string $unit_type, int $unit_id )`
- `link_external_reference( string $unit_type, int $unit_id, string $provider, string $resource_type, string $resource_id, string $display_reference = '', string $status_projection = '' )`
- `update_external_status( string $unit_type, int $unit_id, string $provider, string $resource_type, string $resource_id, string $status_projection, string $display_reference = '' )`

All F2 billing reads and mutations require the current actor to hold `cb_manage_work`. No unauthenticated transport or system-actor bypass is introduced in F2.

## Preferred integration direction

```text
Work Public Billing API
        ↓
optional commercial-provider adapter
        ↓
provider application/domain
        ↓
authoritative financial/document truth
        ↓
generic Work external-reference/status projection
```

Invoice & Quotes is one possible consumer/provider, not a dependency or special case in Work.

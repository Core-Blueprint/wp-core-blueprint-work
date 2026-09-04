# Core Blueprint Work — v1 architecture

## Product boundary

Core Blueprint Work is the suite authority for operational work: service catalog and VAT/tax rates, projects, work items, recurrence, time tracking, billing-ready records, reporting and export.

CRM remains authoritative for contacts, organizations and customer-specific commercial agreements. Helpdesk remains authoritative for tickets. Work never reads sibling private repositories or tables.

## Ownership

### Work-owned

- Service catalog and default service pricing.
- VAT/tax rate catalog and validity windows.
- Projects and work items.
- Recurrence/templates.
- Server-authoritative time entries and timers.
- Billable classification, rate snapshots, timesheet review/locking.
- Billing-ready aggregation, reporting and export.

### CRM-owned

- Contacts and organizations.
- Customer/service assignments and assignment lifecycle.
- Customer-specific pricing/tax overrides.

CRM resolves Work catalog defaults through a public Work contract. Work may store soft references to CRM records but never foreign-key into CRM-owned tables.

### Helpdesk-owned

- Tickets, messages, attachments and ticket lifecycle.

Optional Work automation consumes Helpdesk public lifecycle hooks (`cb_helpdesk_ticket_*`) and public authorization-aware ticket projections only. Helpdesk remains fully functional when Work is inactive, and Work remains fully functional when Helpdesk is inactive.

## Storage direction

- Services are WordPress-native `cb_work_service` content because they have a title and rich description.
- Standard pricing is stored in Work-owned post meta using integer minor currency units; floating-point money is not stored.
- Pricing models are `hourly`, `fixed` and `recurring`. Recurring service pricing has an explicit weekly/monthly/quarterly/yearly billing period. This is commercial cadence, not Work Item recurrence.
- Work owns `cb_work_tax_rates`; tax percentages are integer basis points with optional country and validity windows.
- Tax rates are deactivated instead of edited/deleted so historical references can remain reliable.
- CRM's current `cb_crm_service` records will be migrated in Phase C to `cb_work_service` while preserving WordPress post IDs for CRM assignment references.
- CRM tax-rate records will be migrated preserving numeric IDs before CRM switches its validators/readers to the public Work contract.
- Projects, work items, recurrence, time, timesheets and billing-ready records use Work-owned relational tables registered through `CB\Core\Database\SchemaRegistry`.
- Cross-plugin references are soft provider/type/id references. No cross-plugin SQL foreign keys.

## Admin information architecture

Work registers one Base-owned Core Admin page. Product sections use the Base `nav-tabs` foundation inside that page instead of creating WordPress submenu entries for configuration details.

- `Services` is the primary Phase-B product surface.
- `Settings` contains configuration that is not part of the daily operational workflow.
- VAT rates live under `Work → Settings`; Work does not register a separate VAT submenu item.
- As Work Items arrive, the default Work surface will become the operational work overview and Services will remain an internal product section.

## Public contract direction

Phase B exposes read-only sibling integration contracts under `CB\Work\PublicApi\Services` and `CB\Work\PublicApi\TaxRates`. These contracts prevent CRM or other extensions from reading Work private repositories/tables directly.

The builder-neutral frontend layer remains separate and will expose:

- `CB\Work\Frontend\Data\*` immutable/read-only projections.
- `CB\Work\Frontend\Queries\*` authorization-aware reads.
- `CB\Work\Frontend\Conditions\*` reusable conditions.
- `CB\Work\Frontend\Actions\*` governed mutation entry points where frontend mutation is appropriate.
- Post-persistence lifecycle hooks for sibling integrations.

Bricks is an optional thin adapter under `CB\Work\Integration\Builders\Bricks`; it never owns business logic, validation, authorization or persistence.

## v1 phases

1. **A — First-party foundation:** identity, Base requirements, status, capability and Base-native admin shell. **Complete.**
2. **B — Services + VAT authority:** Work service CPT, pricing/tax domain, Work tax schema, public read contracts. **Current phase.**
3. **C — CRM handoff:** migrate service post type/meta and tax rates without changing service IDs; CRM keeps assignments/overrides and consumes Work public contracts.
4. **D — Projects + Work Items:** relational project/task domain, statuses, priorities, assignments and generic external relations.
5. **E — Recurrence + Time:** recurring work/templates, server-authoritative timers, completed entries, billable classification and rate snapshots.
6. **F — Timesheets + billing-ready output:** review/lock workflow, aggregation, reports and export.
7. **G — Helpdesk integration:** optional ticket-to-work-item flows through Helpdesk public hooks/projections only.
8. **H — Frontend contracts + Bricks adapter:** complete builder-neutral data/query/condition/action layer first; Bricks follows as an optional adapter.
9. **I — Release closure:** localization EN/NL/DE/FR/ES/IT/PT, packaging, integration tests, live Dashboard smoke and suite regression.

## Non-goals for v1

- Work does not become a second CRM, helpdesk or invoicing system.
- No direct sibling-table reads/writes.
- No hard dependency on CRM, Helpdesk or Bricks.
- No port of the legacy workspace Project Manager or Time Tracking implementation.

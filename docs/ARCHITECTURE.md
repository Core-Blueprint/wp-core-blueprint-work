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
- Customer-specific Service Agreements that reference Work Services.
- Customer-specific pricing/tax overrides carried by those agreements.

CRM resolves Work catalog defaults through the public Work pricing contract. Work may store soft references to CRM records but never foreign-key into CRM-owned tables.

### Helpdesk-owned

- Tickets, messages, attachments and ticket lifecycle.

Optional Work automation consumes Helpdesk public lifecycle hooks (`cb_helpdesk_ticket_*`) and public authorization-aware ticket projections only. Helpdesk remains fully functional when Work is inactive, and Work remains fully functional when Helpdesk is inactive.

## Storage direction

- Services are WordPress-native `cb_work_service` content because they have a title and rich description.
- Standard pricing is stored in Work-owned post meta using integer minor currency units; floating-point money is not stored.
- Pricing models are `hourly`, `fixed` and `recurring`. Recurring service pricing has an explicit weekly/monthly/quarterly/yearly billing period. This is commercial cadence, not Work Item recurrence.
- Work owns `cb_work_tax_rates`; tax percentages are integer basis points with optional country and validity windows.
- Tax rates are deactivated instead of edited/deleted so historical references can remain reliable.
- Phase C removed duplicate CRM Service/VAT ownership. CRM now references Work Services through public contracts and owns only customer-specific Service Agreements.
- Projects, work items, recurrence, time, timesheets and billing-ready records use Work-owned relational tables registered through `CB\Core\Database\SchemaRegistry`.
- Cross-plugin references are soft provider/type/id references. No cross-plugin SQL foreign keys.

## Admin information architecture

The suite-wide navigation rule is explicit: operational product work lives in the product's own normal WordPress Admin menu; the `Core Blueprint` menu is reserved for suite and extension settings/configuration.

Work therefore owns two distinct admin surfaces:

- Top-level `Work` is the canonical daily operational workspace. Phase C1 exposes `Overview` and `Services`; future operational sections such as Work Items, Projects, Time and Billing extend this same menu.
- `Core Blueprint → Work` is settings-only. It must not become a second operational Work workspace.
- VAT rates are configuration and live under `Core Blueprint → Work` settings; Work does not register a separate VAT submenu item in the operational menu.
- The Service CPT remains hidden from WordPress automatic menu placement and is deliberately mounted beneath the top-level Work menu, preserving one canonical operational navigation owner.

Operational Work screens use normal WordPress Admin interaction patterns. The Core Blueprint settings surface consumes the Base Core Admin page/foundation contract.

## Public contract direction

Phase B exposes read-only sibling integration contracts under `CB\Work\PublicApi\Services` and `CB\Work\PublicApi\TaxRates`. Phase C adds the Work-owned pricing provider seam and effective-pricing resolver. These contracts prevent CRM or other extensions from reading Work private repositories/tables directly.

The builder-neutral frontend layer remains separate and will expose:

- `CB\Work\Frontend\Data\*` immutable/read-only projections.
- `CB\Work\Frontend\Queries\*` authorization-aware reads.
- `CB\Work\Frontend\Conditions\*` reusable conditions.
- `CB\Work\Frontend\Actions\*` governed mutation entry points where frontend mutation is appropriate.
- Post-persistence lifecycle hooks for sibling integrations.

Bricks is an optional thin adapter under `CB\Work\Integration\Builders\Bricks`; it never owns business logic, validation, authorization or persistence.

## v1 phases

1. **A — First-party foundation:** identity, Base requirements, status, capability and initial admin shell. **Complete.**
2. **B — Services + VAT authority:** Work service CPT, pricing/tax domain, Work tax schema, public read contracts. **Complete.**
3. **C — CRM handoff:** remove duplicate CRM Service/VAT ownership, add CRM Service Agreements and route effective pricing through Work public contracts. **Complete.**
4. **C1 — Canonical admin navigation:** top-level operational Work menu plus settings-only `Core Blueprint → Work` surface. **Current patch.**
5. **D — Projects + Work Items:** relational project/task domain, statuses, priorities, assignments and generic external relations.
6. **E — Recurrence + Time:** recurring work/templates, server-authoritative timers, completed entries, billable classification and rate snapshots.
7. **F — Timesheets + billing-ready output:** review/lock workflow, aggregation, reports and export.
8. **G — Helpdesk integration:** optional ticket-to-work-item flows through Helpdesk public hooks/projections only.
9. **H — Frontend contracts + Bricks adapter:** complete builder-neutral data/query/condition/action layer first; Bricks follows as an optional adapter.
10. **I — Release closure:** localization EN/NL/DE/FR/ES/IT/PT, packaging, integration tests, live Dashboard smoke and suite regression.

## Non-goals for v1

- Work does not become a second CRM, helpdesk or invoicing system.
- No direct sibling-table reads/writes.
- No hard dependency on CRM, Helpdesk or Bricks.
- No port of the legacy workspace Project Manager or Time Tracking implementation.

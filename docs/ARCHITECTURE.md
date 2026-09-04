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

- Services remain WordPress-native content because they have a title and rich description. The target Work post type is `cb_work_service`.
- CRM's current `cb_crm_service` records will be migrated in place to `cb_work_service` so WordPress post IDs remain stable for CRM assignment references.
- Work owns a dedicated tax-rate table. CRM tax-rate IDs are migrated preserving numeric IDs before CRM switches its validators/readers to the public Work contract.
- Projects, work items, recurrence, time, timesheets and billing-ready records use Work-owned relational tables registered through `CB\Core\Database\SchemaRegistry`.
- Cross-plugin references are soft provider/type/id references. No cross-plugin SQL foreign keys.

## Public contract direction

Work exposes builder-neutral public domain contracts before any builder adapter:

- `CB\Work\Frontend\Data\*` immutable/read-only projections.
- `CB\Work\Frontend\Queries\*` authorization-aware reads.
- `CB\Work\Frontend\Conditions\*` reusable conditions.
- `CB\Work\Frontend\Actions\*` governed mutation entry points where frontend mutation is appropriate.
- Post-persistence lifecycle hooks for sibling integrations.

Bricks is an optional thin adapter under `CB\Work\Integration\Builders\Bricks`; it never owns business logic, validation, authorization or persistence.

## v1 phases

1. **A — First-party foundation:** identity, Base requirements, status, capability and Base-native admin shell.
2. **B — Services + VAT authority:** Work service CPT, pricing/tax domain, Work tax schema, public read contracts.
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

# Core Blueprint Work — v1 architecture

## Product boundary

Core Blueprint Work is the suite authority for operational work: service catalog and VAT/tax rates, projects, work items, recurrence, time tracking, billing-ready records, reporting and export.

CRM remains authoritative for contacts, organizations and customer-specific commercial agreements. Helpdesk remains authoritative for tickets. Work never reads sibling private repositories or tables.

## Ownership

### Work-owned

- Service catalog and default service pricing.
- VAT/tax rate catalog and validity windows.
- Projects and work items.
- Work Types and operational classification.
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
- Projects, Work Types, Work Items, assignments and generic external relations are Work-owned relational tables registered through `CB\Core\Database\SchemaRegistry`.
- Recurrence, time, timesheets and billing-ready records continue on the same Work-owned relational direction in later phases.
- Cross-plugin references are soft provider/type/id references. No cross-plugin SQL foreign keys.

### D1 operational tables

- `cb_work_projects` — optional grouping layer with optional soft customer reference.
- `cb_work_types` — user-configurable work classification catalog; D1 seeds Support, Design, Development, Consultancy, Maintenance, Content and Administration by stable code.
- `cb_work_items` — the central actionable entity.
- `cb_work_item_assignments` — many-to-many WordPress user assignments.
- `cb_work_item_relations` — generic provider/type/external-id relations for future Helpdesk and other integrations.

A Work Item may exist without a Project. A customer reference is also optional at the storage level so Work remains usable without CRM, but any populated reference must be complete and explicit.

## Work Item lifecycle

Baseline status values:

- `planned`
- `in_progress`
- `completed`
- `skipped`
- `cancelled`

`planned` and `in_progress` are active workload states. `completed`, `skipped` and `cancelled` are terminal in D1. Reopening is intentionally not implicit; a future reopen workflow must be explicit so completion history is not silently rewritten.

Completion stores `completed_at` and `completed_by`. Billing classification remains a separate field and never doubles as completion/invoice state.

D1 billing dispositions:

- `hourly`
- `fixed`
- `included`
- `non_billable`

Invoice/payment lifecycle remains outside D1.

## Admin information architecture

The suite-wide navigation rule is explicit: operational product work lives in the product's own normal WordPress Admin menu; the `Core Blueprint` menu is reserved for suite and extension settings/configuration.

Work therefore owns two distinct admin surfaces:

- Top-level `Work` is the canonical daily operational workspace.
- D1 mounts `Overview`, `Work Items`, `Projects` and `Services` beneath this menu.
- `Core Blueprint → Work` is settings-only. It must not become a second operational Work workspace.
- VAT rates are configuration and live under `Core Blueprint → Work` settings; Work does not register a separate VAT submenu item in the operational menu.
- The Service CPT remains hidden from WordPress automatic menu placement and is deliberately mounted beneath the top-level Work menu, preserving one canonical operational navigation owner.

Operational Work screens use normal WordPress Admin interaction patterns. The Core Blueprint settings surface consumes the Base Core Admin page/foundation contract.

The long-term operational overview should answer **“What do I still need to do?”** with workload views such as Today, Overdue, In Progress, Upcoming and Completed. D1 establishes the underlying status model and a basic testable operational surface; D2 can refine those daily-work views without changing the domain model.

## Public contract direction

Phase B exposes read-only sibling integration contracts under `CB\Work\PublicApi\Services` and `CB\Work\PublicApi\TaxRates`. Phase C adds the Work-owned pricing provider seam and effective-pricing resolver. D1 adds read-only `Projects`, `WorkItems` and `WorkTypes` contracts.

D1 also emits post-persistence lifecycle hooks for future integrations:

- `cb_work_project_created`
- `cb_work_work_item_created`
- `cb_work_work_item_status_changed`

These hooks expose Work-owned lifecycle facts without allowing sibling extensions to reach into private repositories/tables.

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
4. **C1 — Canonical admin navigation:** top-level operational Work menu plus settings-only `Core Blueprint → Work` surface. **Complete.**
5. **D1 — Projects + Work Items foundation:** relational Projects/Work Types/Work Items, lifecycle, priorities, assignments, generic external relations, read contracts and testable admin operations. **Current patch.**
6. **D2 — Operational Work UX:** refine Today/Overdue/In Progress/Upcoming/Completed views and daily workflow ergonomics without redesigning the D1 domain.
7. **E — Recurrence + Time:** recurring work/templates, server-authoritative timers, completed entries, billable classification and rate snapshots.
8. **F — Timesheets + billing-ready output:** review/lock workflow, aggregation, reports and export.
9. **G — Helpdesk integration:** optional ticket-to-work-item flows through Helpdesk public hooks/projections only.
10. **H — Frontend contracts + Bricks adapter:** complete builder-neutral data/query/condition/action layer first; Bricks follows as an optional adapter.
11. **I — Release closure:** localization EN/NL/DE/FR/ES/IT/PT, packaging, integration tests, live Dashboard smoke and suite regression.

## Non-goals for v1

- Work does not become a second CRM or Helpdesk.
- Work is not a Jira clone and is not board-first.
- Work is not a bookkeeping system.
- No direct sibling-table reads/writes.
- No hard dependency on CRM, Helpdesk or Bricks.
- No port of the legacy workspace Project Manager or Time Tracking implementation.

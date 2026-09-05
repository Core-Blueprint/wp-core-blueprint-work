# Core Blueprint Work — v1 architecture

## Product boundary

Core Blueprint Work is the suite authority for operational work: service catalog and VAT/tax rates, Projects, Work Items, recurrence, time tracking, billing-ready records, reporting and export.

CRM remains authoritative for Contacts, Organizations and customer-specific commercial agreements. Helpdesk remains authoritative for tickets. Work never reads sibling private repositories or tables.

## Permanent architecture rules

- Work is builder-neutral and fully functional without any page builder. Frontend data, queries, conditions and governed actions belong to Work-owned public contracts.
- Bricks is the first officially supported builder adapter. It remains thin, optional and replaceable; future builders must be addable without redesigning the Work domain.
- Frontend capability is authorization-aware and opt-in. A CPT, REST support or builder adapter never implies public exposure.
- Optional sibling integrations are fail-soft.
- No cross-plugin SQL foreign keys or private table/class coupling.
- Pre-v1 internal architecture is corrected directly: no legacy bridges, dual reads/writes or migration complexity for disposable staging data.

## Ownership and storage

### Services

Services are WordPress-native content using `cb_work_service`. Standard pricing lives in Work-owned registered post meta using integer minor currency units. Supported pricing models are `hourly`, `fixed` and `recurring`; recurring commercial pricing is not Work Item recurrence.

### VAT

Work owns `cb_work_tax_rates`. Rates use stable IDs/codes and basis points. Historical references remain valid when a rate is deactivated.

### Projects

Projects are WordPress-native content using `cb_work_project`.

- title/content live in `wp_posts`;
- customer reference, start date and due date live in registered Work-owned post meta;
- Projects are private by default and are not publicly queryable;
- native WordPress list/edit administration is the canonical Project management surface;
- the Project editor uses Gutenberg through authenticated REST support without turning the CPT into a public frontend resource.

The relational `cb_work_projects` table introduced in the first D1 implementation was transitional. D1.1 removes it destructively rather than preserving a compatibility layer.

#### Gutenberg / REST boundary

`cb_work_project` enables WordPress REST support so the native block editor can operate. Project post reads use a Work-owned REST controller that requires the Work management capability before delegating to WordPress core. This REST route is authenticated admin editing infrastructure, not a frontend resource contract.

Future frontend/portal access remains a separate concern. D3 may expose Projects through explicit, authorization-aware and opt-in builder-neutral resources. D4 may then expose those D3 resources to Bricks as the first officially supported builder adapter. Neither Gutenberg nor `show_in_rest` is used as a shortcut around that boundary.

### Work Items

Work Items remain Work-owned relational records in `cb_work_items`. They are operational records, not content documents.

A Work Item may exist without a Project. When `project_id` is populated it is the WordPress post ID of a valid `cb_work_project`; the relation is a validated Work-owned soft reference, never an SQL foreign key.

Work Items retain:

- lifecycle/status;
- priority;
- customer reference;
- optional Project;
- optional Service and Work Type;
- scheduled date and due date as separate semantics;
- billing classification separate from completion state;
- many-to-many WordPress user assignments;
- generic integration/source relations outside the normal human create flow.

### Work Types

Work Types remain a Work-owned relational operational catalog in `cb_work_types`. They are managed under the top-level Work product area, not under Core Blueprint settings.

### Assignments and integration relations

- `cb_work_item_assignments` stores many-to-many WordPress user assignments.
- `cb_work_item_relations` stores provider-neutral external/source metadata for integrations such as Helpdesk.
- Raw provider/type/id fields are internal persistence metadata and are not primary user-facing controls.

## CRM customer integration

Work stores an optional provider/type/id customer reference but does not recreate CRM.

When CRM is active, Work consumes only CRM's documented builder-neutral query contracts for Contacts and Organizations to provide a human customer picker. If CRM is unavailable or the current user is not authorized by CRM, customer selection becomes unavailable while Work itself remains usable.

A Project customer may be inherited by a Work Item when the Work Item does not choose a different customer.

## Work Item lifecycle

Baseline status values:

- `planned`
- `in_progress`
- `completed`
- `skipped`
- `cancelled`

`planned` and `in_progress` are active workload states. `completed`, `skipped` and `cancelled` remain terminal at this stage. Completion metadata and billing classification are separate.

D1 billing dispositions:

- `hourly`
- `fixed`
- `included`
- `non_billable`

Invoice/payment lifecycle remains outside D1.1.

## Admin information architecture

Operational product work lives under the normal top-level **Work** menu. `Core Blueprint → Work` is settings/configuration only.

Top-level Work contains:

- Overview;
- Work Items;
- Projects;
- Services;
- Work Types.

Services and Projects use WordPress-native CPT list/edit surfaces while remaining mounted coherently under Work. Work Items use a specialized workload-first Work surface because they are relational operational records.

### Project context

The native Project edit screen includes a contextual Work Items section that:

- shows active Work Items for the current Project;
- links to open/edit a Work Item;
- creates a Work Item with the Project preselected;
- links to global Work Items with the Project filter active.

This is a contextual view of the same Work Item records. Global **Work → Work Items** remains the canonical all-work management surface.

### Work Item UX

The Work Items screen is list/workload-first. Creation/editing prioritizes:

- Title;
- Customer;
- Project;
- Service;
- Work Type;
- Priority;
- Due date;
- Assignees.

Description, scheduled date and billing classification are secondary details. External/source integration metadata is not exposed in the normal create/edit flow.

Customer selection uses public CRM queries when available. Assignees use Base's public searchable Object Picker foundation over WordPress users. No jQuery is used.

## Public contracts

Read-only sibling contracts remain under `CB\Work\PublicApi` for Services, VAT, pricing, Projects, Work Items and Work Types. `PublicApi\Projects` resolves the Project CPT through the Work Project repository, hiding storage from consumers.

These sibling PHP contracts do not automatically grant frontend exposure. D3 owns the eventual frontend resource/query/condition/action boundary and its authorization/opt-in policy.

Work lifecycle hooks include:

- `cb_work_project_created`;
- `cb_work_work_item_created`;
- `cb_work_work_item_updated`;
- `cb_work_work_item_status_changed`.

Storage type is never a frontend contract.

## Revised v1 sequence

1. **A — First-party foundation:** complete.
2. **B — Services + VAT authority:** complete.
3. **C — CRM / Work domain consolidation:** complete.
4. **C1 — Canonical admin navigation:** complete.
5. **D1 — Projects + Work Items foundation:** merged and staging-reviewed; relational Project storage superseded.
6. **D1.1 — Project CPT + Admin UX correction:** current phase.
7. **D2 — Operational Views Foundation:** one canonical Work Item query/filter/view-state engine for List, Kanban, Table and Calendar; reused globally and in Project context.
8. **D3 — Builder-neutral Frontend Resource Contracts:** authorization-aware and opt-in Services, Projects and Work Items resources; Work remains fully usable without a builder.
9. **D4 — Bricks Adapter:** first officially supported builder adapter; thin and optional over D3 contracts.
10. **E — Recurrence + Time.**
11. Commercial, document, commerce/accounting integration, reporting, Helpdesk and release phases follow the authoritative Work roadmap.

## Non-goals

- Work does not become a second CRM or Helpdesk.
- Work is not a Jira clone and is not board-first.
- Work is not bookkeeping/accounting software.
- No direct sibling-table reads/writes.
- No hard dependency on CRM, Helpdesk, WooCommerce or Bricks.
- No legacy Project compatibility system.

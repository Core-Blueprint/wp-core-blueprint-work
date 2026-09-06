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

Work Items are WordPress-native content using `cb_work_item`.

- title/content live in `wp_posts`;
- customer reference, Project, Service, Work Type, priority, planning dates, operational status, billing classification and completion facts live in registered Work-owned post meta;
- Work Items are private by default and are not publicly queryable;
- the native Gutenberg editor is the canonical individual Work Item edit surface;
- the global **Work → Work Items** workspace remains the canonical all-work operational surface and links into the native editor.

The relational `cb_work_items` table introduced in D1 was transitional. D1.2 removes it destructively rather than carrying migration, fallback or dual-read compatibility for disposable pre-v1 staging data.

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

#### Work Item Gutenberg / REST boundary

`cb_work_item` enables WordPress REST support only so Gutenberg can edit the private CPT. Work Item collection/item reads use a Work-owned REST controller that requires `cb_manage_work` before delegating to WordPress core.

The WordPress publishing state is editor persistence state and is not the Work operational lifecycle. Work status remains the Work-owned `planned` / `in_progress` / terminal model. D3 remains the only place where authorization-aware frontend Work Item resources may be exposed, and those resources remain opt-in.

### Work Types

Work Types remain a Work-owned relational operational catalog in `cb_work_types`. They are managed under the top-level Work product area, not under Core Blueprint settings.

### Assignments and integration relations

- `cb_work_item_assignments` stores many-to-many WordPress user assignments keyed by the canonical `cb_work_item` post ID.
- `cb_work_item_relations` stores provider-neutral external/source metadata keyed by the canonical `cb_work_item` post ID for integrations such as Helpdesk.
- Raw provider/type/id fields are internal persistence metadata and are not primary user-facing controls.
- high-volume child facts such as future time entries, recurrence executions and billing/history records remain Work-owned relational/custom-table data rather than WordPress posts.

### Work Item recurrence

Work Item recurrence is a Work-owned operational scheduling domain. It is separate from a Service's recurring commercial pricing model and does not introduce a second task/content model.

The recurrence foundation uses three relational tables:

- `cb_work_recurrence_rules` stores the reusable Work Item template/context plus recurrence schedule state;
- `cb_work_recurrence_rule_assignments` stores the WordPress users inherited by generated occurrences;
- `cb_work_recurrence_occurrences` is the execution ledger linking one rule/date occurrence to the canonical `cb_work_item` later generated for it.

A rule may carry the same operational context as a Work Item: customer reference, optional Project, optional Service and Work Type, priority, billing disposition and assignees. It additionally owns:

- frequency: daily, weekly, monthly or yearly;
- interval count;
- start date and optional end date;
- create-ahead window in days;
- due-date offset in days;
- active state;
- the canonical next occurrence date.

Monthly and yearly recurrence stays anchored to the original calendar day. Dates that do not exist in the target month clamp to that month's final day without permanently drifting the anchor; for example a rule anchored to the 31st can produce February 28/29 and then return to the 31st in March.

Finite rules have a real terminal state: `next_occurrence_on` becomes `NULL` after the final valid occurrence. Sentinel dates such as `9999-12-31` are forbidden.

The occurrence ledger has a unique `(rule_id, occurrence_on)` key. That database constraint is the hard idempotency boundary for repeated/concurrent generation attempts. A reserved occurrence projects into the existing canonical `Repository\WorkItems::create()` input contract, including inherited assignees, `scheduled_on`, calculated `due_on` and a Work-owned `recurrence_occurrence` source relation. The ledger may link the generated Work Item only when that canonical Work Item carries the matching occurrence relation. A rule advances only after the occurrence has a generated Work Item.

The create-ahead window is evaluated per rule. The foundation itself does not register WP-Cron, generate Work Items automatically or add recurrence administration screens. Scheduler locking/retry/recovery policy and the operator-facing recurrence UI are separate follow-up work over this storage/domain boundary.

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

Invoice/payment lifecycle remains outside D1.2.

## Admin information architecture

Operational product work lives under the normal top-level **Work** menu. `Core Blueprint → Work` is settings/configuration only.

Top-level Work contains:

- Overview;
- Work Items;
- Projects;
- Services;
- Work Types.

Services and Projects use WordPress-native CPT list/edit surfaces while remaining mounted coherently under Work. Work Items use a specialized workload-first global Work surface for operational management, while individual Work Item creation/editing uses the native Gutenberg CPT editor.

### Project context

The native Project edit screen includes a contextual Work Items section that:

- shows active Work Items for the current Project;
- links to the native Gutenberg Work Item editor;
- creates a Work Item in Gutenberg with the Project preselected;
- links to global Work Items with the Project filter active.

This is a contextual view of the same canonical Work Item records. Global **Work → Work Items** remains the canonical all-work management surface.

### Work Item UX

The global Work Items screen is the canonical D2 multi-view operational workspace. Table, List, Kanban and Calendar are renderers over one shared Work Item query/filter/view-state engine; they do not own separate persistence or business logic. Add/edit actions open the native Gutenberg Work Item screen.

The shared operational filter contract covers status, priority, Project, Service, Work Type, customer, assignee, billing class, planning/due ranges and sorting. Calendar derives its scheduled range from canonical `calendar_month` state and groups the already queried Work Items by `scheduled_on`; Kanban groups the same result set by canonical Work Item status. Transition actions preserve sanitized canonical workspace state when returning to the operational view.

The Work Item editor uses Gutenberg title/content plus Work-owned meta boxes for:

- Customer;
- Project;
- Service;
- Work Type;
- Priority;
- operational status;
- due date;
- assignees;
- scheduled date;
- billing classification.

External/source integration metadata is not exposed in the normal create/edit flow. Customer selection uses public CRM queries when available. Assignees use Base's public searchable Object Picker foundation over WordPress users. No jQuery is used.

## Public contracts

Read-only sibling contracts remain under `CB\Work\PublicApi` for Services, VAT, pricing, Projects, Work Items and Work Types. `PublicApi\Projects` and `PublicApi\WorkItems` resolve the canonical CPT repositories while hiding storage from consumers.

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
5. **D1 — Projects + Work Items foundation:** merged and staging-reviewed; original relational object shapes later corrected pre-v1.
6. **D1.1 — Project CPT + Admin UX correction:** complete and merged.
7. **D1.2 — Work Item CPT conversion:** complete and merged; Golden Standard audit hardening follows without changing the canonical storage model.
8. **D2 — Operational Views Foundation:** complete; one canonical Work Item query/filter/view-state engine powers Table, List, Kanban and Calendar while the native Gutenberg editor remains the individual edit surface.
9. **E1 — Recurrence Foundation:** current launch-priority phase; relational rule/assignment/occurrence storage plus pure schedule semantics and canonical Work Item occurrence projection. Scheduler and admin UI are deliberately separate.
10. **D3 — Builder-neutral Frontend Resource Contracts:** parked until the recurrence foundation closes; authorization-aware and opt-in Services, Projects and Work Items resources; Work remains fully usable without a builder.
11. **D4 — Bricks Adapter:** first officially supported builder adapter; thin and optional over D3 contracts.
12. **E2 — Recurrence Scheduler + Admin UX:** generation orchestration, locking/recovery policy and operator-facing management over the E1 foundation.
13. **E3 — Time:** time-entry and timer domain follows recurrence without changing canonical Work Item storage.
14. Commercial, document, commerce/accounting integration, reporting, Helpdesk and release phases follow the authoritative Work roadmap.

## Non-goals

- Work does not become a second CRM or Helpdesk.
- Work is not a Jira clone and is not board-first.
- Work is not bookkeeping/accounting software.
- No direct sibling-table reads/writes.
- No hard dependency on CRM, Helpdesk, WooCommerce or Bricks.
- No legacy Project or Work Item compatibility system.

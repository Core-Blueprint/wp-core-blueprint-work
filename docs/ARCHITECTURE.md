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
- `cb_work_item_relations` stores provider-neutral external/source metadata keyed by the canonical `cb_work_item` post ID for integrations such as Helpdesk and Work recurrence.
- Raw provider/type/id fields are internal persistence metadata and are not primary user-facing controls.
- high-volume child facts such as future time entries, recurrence executions and billing/history records remain Work-owned relational/custom-table data rather than WordPress posts.

### Work Item recurrence

Work Item recurrence is a Work-owned operational scheduling domain. It is separate from a Service's recurring commercial pricing model and does not introduce a second task/content model.

The recurrence domain uses three relational tables:

- `cb_work_recurrence_rules` stores the reusable Work Item template/context plus recurrence schedule state;
- `cb_work_recurrence_rule_assignments` stores the WordPress users inherited by generated occurrences;
- `cb_work_recurrence_occurrences` is the execution ledger linking one rule/date occurrence to the canonical `cb_work_item` generated for it.

A rule may carry the same operational context as a Work Item: customer reference, optional Project, optional Service and Work Type, priority, billing disposition and assignees. It additionally owns:

- frequency: daily, weekly, monthly or yearly;
- interval count;
- start date and optional end date;
- create-ahead window in days;
- due-date offset in days;
- active state;
- the canonical next occurrence date.

Monthly and yearly recurrence stays anchored to the original calendar day. Dates that do not exist in the target month clamp to that month's final day without permanently drifting the anchor; for example a rule anchored to the 31st can produce February 28/29 and then return to the 31st in March.

Finite rules have a real terminal state: `next_occurrence_on` becomes `NULL` after the final valid occurrence. Sentinel dates such as `9999-12-31` are forbidden. Once a rule has occurrence history, its frequency, interval, start date and end date are immutable so execution history cannot be rewound; template/context fields and planning offsets may still be updated.

#### Occurrence identity, claims and recovery

The occurrence ledger has a unique `(rule_id, occurrence_on)` key. That database constraint is the hard occurrence-identity boundary for repeated or concurrent reservation attempts.

E2 extends the same ledger with bounded execution state: `claim_token`, nullable `claimed_at`, `attempt_count` and `last_error`. A worker may claim only an ungenerated occurrence with no live claim. Claims older than the recovery timeout may be taken over by a later worker. This prevents normal concurrent cron workers from creating the same occurrence twice while allowing an interrupted generation to resume.

A reserved occurrence projects into the existing canonical `Repository\WorkItems::create()` input contract, including inherited assignees, `scheduled_on`, calculated `due_on` and a Work-owned `recurrence_occurrence` source relation. The scheduler never inserts a separate task record and never bypasses the canonical Work Item repository.

Before creating a Work Item, a worker looks for an existing canonical Work Item carrying the occurrence source relation. This recovers the ordinary interruption case where Work Item creation and its source relation succeeded but the occurrence ledger was not linked yet. Multiple Work Items claiming the same occurrence relation are treated as corruption and generation fails closed rather than guessing which item is authoritative.

The occurrence ledger may link a generated Work Item only when that canonical Work Item carries the matching occurrence relation and the worker still owns the execution claim. A rule advances only after its occurrence has a generated Work Item. If the ledger link succeeded but rule advancement was interrupted, the next run observes the linked Work Item and resumes from advancement without creating another child.

WordPress post creation and the relational source relation cannot be made one atomic SQL transaction across every WordPress storage path. The claim/recovery protocol therefore prevents ordinary concurrent duplication and recovers once the canonical source relation exists; it does not pretend that a hard process termination between initial post insertion and relation persistence can be made transactionally impossible.

#### Scheduler and catch-up

Recurring Work uses WordPress Cron through the Work-owned `cb_work_generate_recurring_work` hook. Work ensures one hourly event exists and clears only that hook when Work is deactivated; recurrence data and generated Work Items remain persistent.

Each generator pass:

1. selects active rules whose canonical next occurrence is inside that rule's own create-ahead horizon;
2. reserves or resolves the unique occurrence ledger row;
3. atomically claims the occurrence or leaves it to the current worker;
4. recovers an already source-linked Work Item when present, otherwise creates one through `Repository\WorkItems::create()`;
5. attaches the canonical Work Item to the ledger under the active claim;
6. advances the rule to its next valid occurrence;
7. repeats until the rule's create-ahead horizon is satisfied.

Catch-up is bounded to 250 occurrences per rule per generator request and 100 due rules per pass so a long-dormant site cannot monopolize one PHP request. If work remains inside the horizon after a bound is reached, a later hourly or manual run continues from the still-canonical next occurrence.

Generator runs, generated/recovered items and generation failures are recorded through Base Governance/Audit using registered Work event IDs. The operator may also run the generator explicitly from WordPress Admin; manual and cron execution use the same scheduler service.

#### Recurring Work administration

**Work → Recurring Work** is the canonical operator-facing management surface. It uses the same `cb_manage_work` authorization boundary as the rest of Work and reuses Base Object Picker for CRM customers and WordPress-user assignees.

Operators can:

- create recurring Work rules;
- edit template/context and planning fields;
- edit schedule identity only before occurrence history exists;
- activate or deactivate a rule;
- inspect next occurrence and occurrence history count;
- run the generator manually.

There is no destructive recurrence-rule delete flow in v1. Executed recurrence is operational history. Rules are deactivated rather than erased, while finite rules naturally stop when `next_occurrence_on` becomes `NULL`.

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
- Recurring Work;
- Projects;
- Services;
- Work Types.

Services and Projects use WordPress-native CPT list/edit surfaces while remaining mounted coherently under Work. Work Items use a specialized workload-first global Work surface for operational management, while individual Work Item creation/editing uses the native Gutenberg CPT editor. Recurring Work is an operational rule/scheduler surface over the same canonical Work Items, not a second task manager.

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
9. **E1 — Recurrence Foundation:** complete and merged; rule/assignment/occurrence storage, pure schedule semantics and canonical Work Item occurrence projection are established.
10. **E2 — Recurrence Scheduler + Admin UX:** current launch-priority phase; hourly generation, bounded catch-up, stale-claim recovery, governance and operator-facing Recurring Work management over E1.
11. **E3 — Time:** time-entry and timer domain follows recurrence without changing canonical Work Item storage.
12. **D3 — Builder-neutral Frontend Resource Contracts:** parked until launch-critical recurrence/time work closes; authorization-aware and opt-in Services, Projects and Work Items resources; Work remains fully usable without a builder.
13. **D4 — Bricks Adapter:** first officially supported builder adapter; thin and optional over D3 contracts.
14. Commercial, document, commerce/accounting integration, reporting, Helpdesk and release phases follow the authoritative Work roadmap.

## Non-goals

- Work does not become a second CRM or Helpdesk.
- Work is not a Jira clone and is not board-first.
- Work is not bookkeeping/accounting software.
- No direct sibling-table reads/writes.
- No hard dependency on CRM, Helpdesk, WooCommerce or Bricks.
- No legacy Project or Work Item compatibility system.

# M3-A — Operations Foundation, read-only cohesive source batch

Date: 2026-10-30
Source branch: `feature/work-m3-operations-foundation-20261030`
Parent: operator FIELD-stable M2/M4 source `2428f0118b666fc0090900b8b1c77e61bffeb4b5`
Status: source candidate; Level 1/2/3 not yet run; WordPress FIELD HOLD.

## Architecture audit

The existing Work project already owns three independent, security-critical operation domains:

- Service Engagements are the canonical contract in `CB\Work\ServiceOperations\Engagements`, exposed with permission checks through `PublicApi\ServiceOperations::search()`. The query has indexed COUNT and bounded record hydration. The periodic execution status lives in `ServiceOperations\Periods`, not another dashboard table.
- RecurrenceRules owns the canonical search, activation state, generated-occurrence ledger and scheduler lifecycle. `RecurrenceRules::search_page()` returns total counts separately from pagination. `Recurrence\Scheduler` remains the **only** generator.
- Time `Repository\Timers::active_for_user()` reads one user’s active timer; `Time\Access` and existing Time Actions govern writes, locking and actor access.

The M2 app routes its original three sections (Work Items, Projects, Services) through the frozen M1 state and canonical Table/List/Board/Calendar. Expanding or replacing that router early would increase regression risk before M3 parity is established.

## Implemented foundation

1. **Read model**: `Operations\WorkspaceSnapshot::read(actor_id)` is manager-only, same-actor, schema-checked, bounded, and read-only. It gets Engagement total/active counts via existing authorized ServiceOperations PublicApi (one hydrated row per query), recurrence total/active counts through existing RecurrenceRules search, and the actor’s active timer through Timers. It does not return customer identities or timer notes, initiate a scheduler, or touch business writes.
2. **REST**: independent GET-only `cb-work/v1/workspace/operations` endpoint, with native WordPress REST permission callback and a repeated permission check inside the read callback. This does **not** widen the existing `WorkspaceReadController::ROUTE` regex or expose Time-only users’ data.
3. **Persistent Work surface**: `WorkspaceOperationsPanel::render()` adds an accessible, small, Work-scoped operations navigation strip just below the Work heading and above Work Items/Projects/Services tabs. It links to the canonical, still-authorized Service Operations, Recurring Work and Time screens until their interactive in-page replacements meet M3-B/C parity; no deceptive half-working buttons. This consolidates the three older contextual links formerly embedded only in Work Items view switcher, giving consistent access in every Work section.
4. **Progressive hydration**: `assets/js/workspace/operations.js` fetches the manager-scoped snapshot with the WP REST nonce and same-origin GET. It uses textContent, validates payloads, supports cancellation and refresh on window focus, and hides stale metrics on denied/invalid responses while keeping native fallback links. The module is imported by the already stable Work app and added to the immutable content-addressed release module graph.
5. **Styling/i18n**: scoped Base/Work surface/border/spacing CSS with keyboard focus and narrow viewport. Uses existing canonical translated labels (`Service Operations`, `Recurring Work`, `Time`, `Active`, `Timer running`, `Work storage unavailable`). No new text catalog messages, Base code, schema or core architecture changes.

## Tests and quality gates

`tests/work-m3a-operations-snapshot-runtime.php` exercises WordPress REST registration, manager-only and same-actor access, runtime/schema loss, bounded canonical counts, time actor isolation and write-free domain behavior without a database.

`tests/work-m3a-operations-runtime.mjs` exercises client hydration, REST nonce/same-origin GET, invalid payload/403 handling, state cleanup, cross-origin denial and no writes.

`tests/work-m3a-operations-source-smoke.php` guards plugin boot, read path, access controls, persistent shell, module graph, scoped CSS, protected G0 and test registration.

The existing UWE-03C.2 and M2 view-routing source contracts are deliberately updated to keep the same four native view URLs without a legacy link, and require the new persistent operations panel. No M1 view router, Base Modal, protected renderer or A1/B2/D1.2 contracts changed. All checks run in canonical Level 1; full WordPress Level 2 and release gate must be run before any install.

## Next M3 batches and holds

**M3-B**: Service Operations + Recurring Work: context-aware, real in-page engagement/rule lifecycle; existing scheduler and billing segregation; internal vs customer provenance and useful unscheduled/no-rule actions. Do not add a second scheduler, Engagement store or invoicing engine.

**M3-C**: Time: embed the canonical timer/manual/entries workflows with the existing Time CAS and per-role contracts; preserve the separate Time landing for time-only roles until independently accepted.

**HOLD**: M3-A production readiness, Work main merge, global Base modifications, schema/data migrations, public release and production. Under approved milestone cadence, run focused tests while implementing M3-B/C, then one full Level 1/2/3 and single sandbox FIELD at the agreed M3 gate (or corrective round only after a real failure). No automatic Work main promotion.

# M3 source parity audit | Unified Work vs. native Work screens

Date: 2026-10-30
Source HEAD reviewed: `77f4f610b4b345ca22c3a54c2326c0840dcac414`
Classification: **SOURCE AUDIT ONLY / parity NOT ACCEPTED / Golden HOLD**

## Method and evidence boundaries

Compared existing native WordPress surfaces to the new Work shell and M3 REST/UI consumers at source level. No independent WordPress/MariaDB, interactive browser, keyboard, mobile, dark mode, actual DST switch or genuine two-connection DB contention was run. These are *required FIELD/Level 2 gates*, not green audit conclusions. The accepted M2 ZIP and its 71/1690 Level 2 evidence do **not** validate M3 changes.

| Domain / operator action | Native source of truth | In-page M3 status | Required before native removal |
|---|---|---|---|
| Work Operations overview | `Admin/ServiceOperations.php`, `Admin/Recurrence.php`, `Admin/Time.php` | M3-A manager-only live counters and native links present | Verify role/empty/error/focus, no stale figures |
| Service Engagement list/create | `Admin/ServiceOperations.php`; `PublicApi/ServiceOperationsActions.php` | M3-B1 in-place list/search/page/basic create via canonical action | Native filters and status/coverage browsing parity remain open |
| Execution status and Recurrence Rule binding | `ServiceOperations/Periods.php`, `RecurrenceRules.php`, `ServiceOperationsActions.php` | M3-B2 month-specific state and context-matched rule binding in Base Modal | Confirm no schedule/history/identity leakage, exact period states, all read filters |
| External billing registration and terms | `ServiceOperations/ServiceBilling.php` and public actions | M3-B3 period coverage, terms and external invoice *attestation* inside Work | Verify operational filter/overview parity; never misrepresent as real invoice/payment |
| **Recurring Work rule creation/edit** | **`Admin/Recurrence.php`, `Admin/RecurrenceActions.php`, `RecurrenceSchedule.php`, `RecurrenceRules.php`** | **OPEN (P0): M3-B2 binds existing rules, but cannot author or edit rules in Work** | **Build in-place Base Modal/fullscreen editor for title, description, Project, context/CRM customer, Service, Work Type, assignees, priority, estimate, billing, frequency, interval, start/end, create-ahead and due offset. Show canonical upcoming-occurrence preview.** |
| **Recurring Work lifecycle / guardrails** | `Admin/RecurrenceActions.php`, `RecurrenceOccurrences.php`, `RecurrenceRules.php` | **OPEN (P0)** | Preserve occurrence-history schedule lock, active/inactive, Trash, restore-as-inactive, permanent-delete confirmation only with empty occurrence ledger, quick edit, generator, search/filter/sort and pagination. Do not introduce new scheduler or SQL writes. |
| Actor Time Timer start/stop + global HUD | `Time/Access.php`, `Timers.php`, `Admin/TimeActions.php` | M3-C1 source complete via own actor only | PHP/Node Level 1, WP/MariaDB Level 2 and browser HUD sync/route fallback FIELD |
| Own Time Entries history | `TimeEntries::query_completed`, `Admin/TimeEntryList.php` | M3-C1 10/page and own-only projection | Full date range, Work Item search, source/user filters, sort, aggregate totals and stateful pagination OPEN |
| Own manual Time entry / own correction | `TimeRange.php`, `TimeEntries.php`, `Admin/TimeActions.php` | **M3-C2 SOURCE implemented** in Base Modal with own entry detail, local seconds, canonical conversion, revision-CAS, conflict 409, actor isolation | Run full tests and browser acceptance. Check non-ambiguous creation, unchanged DST-fold preservation, spring-gap rejection, zero-time semantics, audit only after success |
| **Manager Time cross-user edit/creation** | `Admin/Time.php`, `Admin/TimeActions.php` | **OPEN (P1)**; Work intentionally limits own entries | Reconcile explicit owner selection and authorized reassignment; must not enlarge tracker privileges |
| **Time bulk edits** | `Admin/TimeEntryList.php`, `Admin/TimeEntryBulkEdit.php` | **OPEN (P1)** | 25-row guarded bulk selection, Work Item reassignment manager-only, note keep/append/replace/clear, per-entry revision check, partial outcome reporting, no silent overwrite |
| Tracker-only Time landing | `Admin/Time.php`, `Time/Access.php` | Native retained by design | Keep role-scoped native route or design a separate equivalent tracker experience. Do **not** grant manager Workspace access to trackers |

## Security and behavior invariants

- Existing WordPress capability and authenticated REST nonce checks remain enforced on every route and callback. M3-C2 must never expose another actor's Time Entry note or permit owner reassignment by forged payload. Native Time retains broader manager scope.
- Work Items are canonical CPTs; Service Engagements, Recurring Work, Timer and Time Entry databases / public APIs remain authoritative. **No parallel stores, implicit billing evidence, duplicate scheduler or new Base modal implementation.**
- Work timezone is the configured WordPress site timezone, not the browser timezone. The `TimeRange::local_to_utc` original-UTC preservation is mandatory for edits falling within duplicated local hours; new ambiguous times are rejected; zero-duration manual entries remain prohibited.
- Historical Recurrence occurrence provenance is immutable. Schedule identity stays locked once occurrences exist; restore must stay paused. Binding rules to Engagements is NOT editing the Recurring Rule itself.
- A failed/uncertain write cannot trigger a success toast, stale CAS replay or false activity/audit event. Preserve keyboard/focus/escape and native fallback when Base Modal or REST is unavailable.

## Decisions and ordered migration batches

1. **M3-B4 | Advanced Recurring Rule authoring/lifecycle** (P0): source-first editor + read/write adapters through canonical domain. Prefer expandable fullscreen Base Modal when the field count requires it; basic recurrence setup stays simple by default. Must preserve schedule history locks and exact preview.
2. **M3-C3 | Time overview & manager parity** (P1): bounded filters/sorts, optional cross-actor manager operations and bulk CAS, without weakening Time-only roles.
3. **M3-B5 | Service Operations filtering/parity** (P1): monthly operational filters, execution and billing projections, empty/error and larger dataset behavior, preserving bounded queries.
4. **M3 acceptance gate**: complete canonical `./tools/check`, isolated WordPress/MariaDB `./tools/check-integration`, `./tools/build-release`, ZIP and checksum; then owner FIELD on manager, tracker, Light/Dark, phone/narrow width, keyboard, Start/Stop HUD, Time create/correct/stale CAS/DST and Recurrence CRUD/lifecycle. Correct defects and re-run changed gates.
5. **M4 retirement**: *separately authorized* switch of canonical links and hidden routes only after each capability demonstrates parity and owner acceptance; do not remove native fallback in a single blind operation.

## Current verdict

**M3-C2 SOURCE delivered but unvalidated by canonical gates; M3 overall parity FAIL/OPEN.**
Native Recurring Work, Time and Service Operations remain reachable. Work `main`, Base, schemas, scheduler, CI, public/prod and release are HOLD.

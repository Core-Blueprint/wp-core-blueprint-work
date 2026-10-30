# M3-B1 — In-place Service Engagements list and basic creation

Date: 2026-10-30
Branch: `feature/work-m3-operations-foundation-20261030`
Parent: M3-A `c5f1fca1e0c0e3d83cc50d4392b338794a21f7e8`
Status: SOURCE CANDIDATE / LOCAL PHP + FULL RELEASE GATES NOT EXECUTED / FIELD HOLD

## Functional scope

- The existing Service Operations link in the persistent Work Operations strip progressively becomes an **in-page Engagement view** only when the M3-B client, existing Work REST transport and Base Modal are available. Without JavaScript, Base modal, or a healthy runtime, its original native admin link remains authoritative and functional.
- The in-page view shows a bounded, pageable Engagement list and search. Each row includes only safe, projected data: Work Project title, Work Service title, optional label, canonical Internal/Customer context, start date, active state. It never sends customer IDs, customer provider fields, invoices or timer notes to the browser.
- **Add service** opens the existing Base modal. Existing Projects and Services are selected through the already protected, searchable and paginated `cb-work/v1/workspace/projects` and `/workspace/services` APIs, not new query engines. Choosing a Project/Service and start date submits a same-origin `POST /cb-work/v1/workspace/engagements` with the REST nonce. The backend calls the **existing** `PublicApi\ServiceOperationsActions::create()`, preserving server-derived Project context/customer provenance and its audit event. The result refreshes both the in-place Engagement list and M3-A operational count. No entire browser reload.
- New route only supports GET and POST creation. Both paths enforce `cb_manage_work`, current user and runtime availability in native REST permission callbacks **and again inside each callback**; direct-callback wrong types fail closed rather than coercing arrays. Server bounds list pages to 25 and search to 160 characters. Invalid dates/IDs are rejected; client-provided context, customer, billing, or recurrence fields are deliberately not forwarded to the canonical writer.
- Canonical recurring rule linking, customer billing terms/registration, scheduling, execution status, and invoice coverage are *not* reimplemented here. The legacy Service Operations link remains available within the new panel for these advanced workflows. This batch must **not** be represented as complete M3-B parity. M3-B2 will integrate rule browsing/binding and execution, then billing presentation with separate acceptance.

## Architectural invariants

- No schema migration, second Engagement table, parallel scheduler, duplicated ServiceOperations write policy, alternate time engine, or changes to Base Modal. The public API and canonical `Engagements::create` still own business invariants.
- The four Work Items Table/List/Board/Calendar views, M1 Work router, M2 editor modal, and G0/B1 protected `src/Admin/Operations.php` remain untouched.
- A new module `assets/js/workspace/engagements.js` is imported from the existing Work application. `tools/prepare-workspace-modules.py` includes it in the immutable content-addressed release graph and `src/Admin/Assets.php` fails closed if the source module is missing.
- User-facing labels reuse already canonical translated Work strings; no new locale catalog messages.
- CSS is Work-scoped, uses existing Base/Work tokens and allows native keyboard tab/focus and narrow viewport.

## Quality gates and evidence

- New WordPress REST-unit fixture `tests/work-m3b-engagements-runtime.php` covers role/guest/runtime failure, GET bounded projection, no customer provenance disclosure, invalid context/pagination/input types, canonical writer dispatch and error propagation.
- New client runtime `tests/work-m3b-engagements-runtime.mjs` proves in-page list, escaped text via `textContent`, Base modal creation, three canonical GET/POST calls with nonce, result refresh, M3-A summary refresh, 403/invalid payload, teardown and native fallback.
- New source contract `tests/work-m3b-engagements-source-smoke.php` enforces preserved G0/B1 blob, REST permissions and public API use, legacy fallback, immutable module inclusion, safe DOM, and canonical Level 1 test registration.
- Focused JS runtime has been evaluated in an isolated JavaScript environment against the staged new source and **PASS**; this is a targeted proof, **not** the full Node/npm or real WordPress runtime. Full `./tools/check`, WP/MariaDB Level 2, built ZIP SHA and sandbox FIELD remain **NOT RUN** on this branch, per M3 milestone delivery cadence.

## Next and hold

Next source batch M3-B2: context-aware existing Recurring Work rule search/selection and `PublicApi\ServiceOperationsActions::bind_rule()` inside Work, plus honest Not scheduled and execution-context presentation via `Periods::resolve`. Independent external invoice/billing presentation follows only with provenance and CAS contracts preserved. After M3-B parity, M3-C embeds Time actions through the already Golden Time APIs.

Work `main`, Base, deployment, public release, new schema, M3 functional Golden and automatic merging remain HOLD. No operator ZIP loop for this internal source batch.

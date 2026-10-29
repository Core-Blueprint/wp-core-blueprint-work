# Core Blueprint Work — M1 Work Items Complete

Decision date: 2026-10-29
Overseer: christiaanbruinsma/wp-core-blueprint-overseer, M1–M5 authority main@8b6997ec5a347652c6baf93938c6ae8fdf435e55
Work baseline: feature/work-uwe-03c3a-interaction-lifecycle-20261029@c2b877e5a2a3b1ce9e9519a1cc2fb88970e00489
Development branch: feature/work-m1-work-items-complete-20261029

**Milestone status: IN DEVELOPMENT. No FIELD ZIP after each internal commit.**

## Product acceptance

Work Items is the primary persistent Work workspace. All existing native functionality must become available across Table, List, Board, Calendar, cross-view presets, quick and bulk edit, action menus, saved preferences, search, filters, sort, pagination, status transitions, Calendar modal and Board drag-and-drop. Avoid full page reloads for ordinary operations; stay accessible and state-consistent. The native Work Items and Gutenberg fallback stay until M2/M4 approvals. User acceptance of a subset does not imply full feature parity.

## Canonical architecture

- Reuse exact-source Operations and Table/List/Board/Calendar renderers; protected Operations.php Git blob SHA 92a4814f24b43f96d16e3d96ce61a49ee9f21500.
- WorkItemViewBridge stays a manager-only, nonce-protected GET HTML projection. Projects and Services use the existing secured REST reader.
- All mutations must call existing authenticated Work-owned actions, respecting domain validation, outbox, conflicts and rollback. No second query, editor or mutation engine.
- Use the reserved WordPress page=core-blueprint-work-workspace route; Work pagination is uwe_page on UI and paged for canonical PHP.
- No Base modifications, schema changes, production data deletion or legacy editor retirement.

## Feature-parity matrix

| Surface | Baseline | M1 acceptance | Current state |
| --- | --- | --- | --- |
| Table display, data, columns | Golden renderer visible | Full parity and accessibility | Visual present |
| List grouping and expansion | Golden renderer visible | Full keyboard parity | User-tested interactions working |
| Board Kanban reorder | Base reorder lifecycle | Secure persisted changes, counts, focus | User-tested interactions working |
| Calendar modal, navigation | Existing Base modal linked | Full modal, date state, focus | User-tested interactions working |
| Native presets | Native admin links | Async Work route | **Source adapter added; FIELD OPEN** |
| Search / canonical advanced filters | Hidden form | Work-local async search, sort, customer/project/assignee/etc. | **Source adapter added; FIELD OPEN** |
| Pagination | Native admin links | Async uwe_page | **Source adapter added; FIELD OPEN** |
| Quick Edit | Hidden native form | Guarded async, conflict/error feedback | OPEN |
| Bulk Move and Edit | Hidden native form | Guarded async partial success/errors | OPEN |
| Table/list columns and view preferences | Native scripts | Persist and initialize on repeated mount | OPEN |
| Row actions and status menus | Native post actions | Safe async or explicitly navigated fallback | OPEN |
| Board/Calendar persisted updates | Guarded writes | Correct refreshed counts, error feedback | FIELD OPEN |
| Error, loading, empty, permission | Partial | Full accessible recovery | OPEN |
| Mobile, keyboard, dark/light | Partial | Complete | OPEN |
| Native Work Items / Gutenberg | Preserved | Keep until M2/M4 | PRESERVED |

## Internal development sequence — not separate operator patch ceremonies

1. **Canonical read navigation:** expose exact existing Work GET filter form; route forms, presets, clear, pagination through history, without WordPress page reload. Preserve focus.
2. **Saved presentation preferences:** mount/unmount Table/List display and columns, view preference controls with the existing guarded preference actions.
3. **Guarded mutations:** reintroduce Quick Edit, bulk status/edit and row actions with authorized nonce-checked Work endpoints, conflict handling and server-confirmed updates.
4. **Full parity stabilization:** correct counts, view state, keyboard/mouse, mobile/light/dark, stale/cancelled requests, error and accessibility. Cover negative permissions.
5. **ONE full M1 Golden + comprehensive WordPress FIELD**, then a separate explicit promotion/merge GO. Corrective builds only for observed issues.

During development run targeted PHP/JS behavioral and integration checks. Complete Level 1/2/3, ZIP and checksum are mandatory for milestone acceptance, not every internal source commit. No CI, deployment, public release or main merge from this approval.

## First in-progress source slice (no acceptance claim)

- New Work-owned canonical-filters.js converts native Work GET forms and safe same-origin native Work links to the UWE route. Keeps WordPress page separate from uwe_page, preserves preset filter state and rejects action links.
- canonical-mount.js delegates native form, advanced-filter, live search and pagination events; History API refresh; search focus is restored after rehydration.
- Unified Workspace CSS shows only the canonical GET filter form while all unapproved mutation forms remain hidden.
- Dedicated runtime tests verify URL filters, reset, preset, pagination, cross-tab state snapshots, and rejected unsafe links; source test protects G0 and explicit GET-only boundary.
- The top-level app now snapshots all Work-specific filters (including calendar_month, customer, project, priority, view and uwe_page) before switching to Projects/Services, strips them from those other URLs and restores them on return.
- No full Golden and no WordPress FIELD evidence for this slice. Continue on the same M1 branch.

## Internal development slice 2 — retained preference contracts

- The existing Work Item Table preference implementation now listens for canonical view mount and unmount; repeated mount is idempotent, and Base reorder controllers/document click listeners are released when the fragment is replaced.
- The existing List group/order display preferences initialize for each new fragment, with document listeners scoped to an AbortController and one-time per-root registration.
- The existing View Preferences Base modal initializes dynamically from the imported canonical template. Saved policies still POST to the existing nonce/capability-guarded Work action.
- Successful saves on the native Work Items route preserve the existing reload flow; saves inside the new Work Workspace emit a scoped refresh event instead. This is view hydration, not a second preferences store.
- Enqueue the original Work view preference module on the Unified Workspace only alongside the already accepted Base Modal/Reorder module dependencies.
- Continue hiding Quick Edit, bulk-action and other mutation forms pending the guarded mutation parity slice. No change to Operations.php, domain, schema, or other products.
- Targeted preference source/runtime checks have been added to Level 1. FULL Golden and WordPress FIELD remain deferred until full M1 acceptance.

- User-configured view order is now rendered by the outer Workspace switcher from the canonical existing preference store, and synchronized from canonical HTML after subsequent preference saves. The inner native view navigation stays hidden while its existing preferences button becomes available.

## Internal development slice 3 — guarded Work Item mutations (SOURCE CANDIDATE)

- The four existing POST handlers (single status transition, Quick Edit, bulk status transition, bulk details edit) are registered for authenticated admin-ajax requests **in addition to** the accepted admin-post routes. Each action still invokes the same OperationalActions method, capability guard, per-action nonce verification, repository mutation, status transition validation and audit calls. Native redirects remain for regular admin-post submissions.
- OperationalActions emits a bounded JSON result only when WordPress is handling AJAX; it reports success/invalid notices and updated/attempted counts, including partial bulk outcomes. The server remains the sole authority for persistence.
- The Work-owned canonical-mutations.js module intercepts allowlisted form submissions, forwards the original FormData and _wpnonce to the authenticated admin-ajax endpoint with same-origin POST, guards double submissions, preserves the editing form on 403/409/malformed/network failures, and rehydrates canonically only on server-confirmed success. Unknown POST actions are denied rather than navigating to a legacy endpoint.
- The existing work-admin.js binds native table selection, Quick Edit row display, and status action menus on canonical mount; document-level menu handlers are owned by an AbortController and removed on canonical unmount. Original native ready() behavior stays.
- CSS enables mutation forms ONLY when both the original dynamic table handlers and the secure Workspace AJAX adapter are present. The existing filter form and preference controls remain usable.
- Work Items G0/B1 server renderer, WorkItemViewBridge GET reader, project/service REST, core WorkItems repository, schemas and Base are unchanged.
- Behavioral Node test verifies exact action allowlist, original nonce forwarding, same-origin, POST, success/partial counts, rejection/network recovery, no optimistic refresh, and fail-closed unknown actions. Source smoke protects legacy and AJAX route equivalence and full Golden wiring.
- **No WordPress FIELD or Level 1–3 acceptance yet.** This is another internal M1 commit only. Final M1 Golden/FIELD will include negative capability/nonce tests, bulk partial-success data checks, edit and row action parity.

- Additional fail-closed safeguards: the mutation transport respects native form-validation preventDefault, and the original document-ready initializer explicitly skips injected canonical fragments to prevent double attachment.

## Internal stabilization slice — date filters and write serialization (2026-10-29)

- Fixed the only missing canonical bridge query mappings: `scheduled_from`, `scheduled_to`, `due_from`, `due_to`. WorkItemViewState and the GET-only protected renderer remain unchanged.
- Serialized Work Item form mutations at the Workspace boundary to prevent parallel Quick Edit, bulk and row-action submits from creating confusing stale results. The original nonce/capability-guarded server handlers remain authoritative.
- Added localized Saving feedback and `aria-busy` during the request. Every success, denial, network failure or malformed payload clears the busy flag. Unsuccessful operations keep the current form state intact.
- On a successful mutation, refresh the **currently selected** Work Items view even if the old form was replaced by a user-initiated view switch. If the Work Items tab is hidden, the next tab activation performs the standard refresh.
- Added Node regressions for all four date ranges reaching the bridge, overlapping submits, an in-flight view switch, partial bulk results, stale response handling and error recovery.
- No milestone Golden or FIELD acceptance yet; continue M1 parity work on this single branch.

## Internal stabilization — cross-view status consistency (2026-10-29)

- Board remains on existing Base Reorder and `WorkItemBoardActions`. After a server-confirmed drag, the canonical view receives a scoped, bubbling `cb-work:canonical-status-committed` event. Native Work Items keeps its original reload; the Unified Workspace gets an authoritative read after the reorder interaction settles.
- Calendar reuses its existing Base modal and backing day-template update. Only after a persisted change **and modal promise resolution** does it notify the Workspace to re-read current day counts and canonical cards. No modal is forcibly closed by the Workspace.
- `createCanonicalStatusSync` in the canonical Work mount coalesces rapid status changes into one refresh; view switches cancel pending scheduled updates and already cause their own authenticated read. Hidden Work tab never receives a forced refresh.
- Preserve keyboard focus on a matching Board card after canonical rehydration; fall back to the selected view navigation when the moved/filtered card is no longer shown.
- Source and Node tests cover success-only notification, native-vs-Workspace handling, cancel/debounce and continued original Work domain write contracts. Exact G0/B1 Operations renderer stays immutable. A historical Calendar smoke expectation is updated to check the expanded callback contract without weakening the native behavior requirement.
- Milestone Level 1/2/3 and WordPress FIELD remain OPEN until M1 parity is complete; no intermediate ZIP or acceptance request.

## Internal stabilization — advanced filter disclosure (2026-10-29)

- Preserve `More Filters` expanded/collapsed state across every canonical GET rehydrate; filter changes no longer collapse the advanced controls unexpectedly.
- Match native keyboard behavior: opening the panel focuses its first available control, and Escape closes the panel, synchronizes `aria-expanded`, and restores focus to the toggle.
- Reuse the canonical PHP filter form and markup; the new `syncAdvancedFilters` helper only reapplies local disclosure state to server-rendered HTML. No saved settings, duplicate filters, or additional REST/AJAX endpoints.
- Extend existing filter runtime and source tests rather than adding a separate acceptance patch. Final M1 Level 1/2/3 + FIELD remain OPEN.

## Internal stabilization — read recovery and authorization safety (2026-10-29)

- For a **transient GET failure on the same Work Items view**, keep the last successfully rendered canonical HTML and its filter/read controls visible alongside the existing accessible error and retry control. Disable mutation forms until a fresh, authenticated canonical GET succeeds. This avoids replacing live content with outdated SSR workspace records during a network hiccup.
- For an **initial GET failure or a failed navigation to a different view**, use the existing SSR fallback plus error, rather than presenting another view as current.
- For **HTTP 401/403**, immediately unmount and clear cached canonical Work Items markup, disable mutations, and suppress the potentially privileged SSR fallback. Subsequent offline retries must never reveal the old SSR records. Only an authorized successful GET restores the Work Items view.
- A **401/403 POST** also forces a permission-boundary re-read, which will purge the cached UI if the user no longer has read access. Ordinary 409/transport write failures keep the form available for correction.
- Existing canonical bridge, Base components, DOM-safe parsing, nonce and capability boundaries, golden Operations renderer, Projects and Services code remain unchanged.
- UWE-03C.2 runtime is extended to exercise same-view transient retention, cross-view fallback, denial purge, offline-after-denial and authorized retry; Work M1 mutation runtime adds 403 write reauthorization. Source test is wired to Level 1.
- No full Golden, integration WordPress or FIELD acceptance yet. Continue under the same M1 featurebranch until complete product parity.

## Internal M1 parity — detached Calendar menus and daily keyboard shortcut (2026-10-29)

- **Discovered parity gap:** Base Modal mounts the Calendar day dialog outside the Work Items canonical destination. Its existing status form submits were therefore outside the Work M1 delegated AJAX handler, risking a full WordPress admin-post navigation. Work now marks *only* a day modal created from the Unified Workspace, and delegates its existing nonce/capability-guarded status POST to the **same** authenticated OperationalActions AJAX handler used by Board/Table. No duplicate status write endpoint, Base modification, or renderer rewrite.
- On server-confirmed Calendar menu status changes, notify the owning modal and defer the canonical GET rehydration until Base's modal promise resolves. A rejected write keeps the dialog and its form available; HTTP 401/403 additionally clears the detached private modal DOM immediately and rechecks the canonical authorization boundary.
- **Shortcut parity:** keep the original vanilla-JS `/` focus-search fast path on native Work Items, but resolve the active canonical `.cb-work-items-page` dynamically so it works after AJAX mounts; do not steal focus while typing in an input or while Projects/Services is active. Enqueue the original fast-path asset via the existing Work asset helper; no second shortcut implementation.
- Added a focused actual-module runtime test for detached form capture, nonce forwarding, 409/403 behavior, no premature modal refresh, native form isolation and shortcut lifecycle; a source contract protects the guarded routing and protected G0 renderer. Level 1 wiring updated.
- **Status:** internal M1 source slice, not FIELD or full Golden acceptance. Continue comprehensive parity validation and audit.

## Internal M1 parity — keyboard and responsive navigation (2026-10-29)

- The Work Item Table/List/Board/Calendar switcher now supports ArrowLeft, ArrowRight, Home, and End. Navigation respects the user's **actual saved view order**, not the renderer's hardcoded default order, and restores keyboard focus to the selected view link. No second view-preferences store.
- The canonical GET mount cancels a pending debounced search whenever a navigation/refetch starts, preventing an old Table search from overwriting a newly selected Board or Calendar view. The original native forms are unchanged.
- Focus restoration recognizes a matching element ID in the newly imported canonical HTML, preserving keyboard context across filters/sorts/pagination; if the element no longer exists, focus falls back to the currently selected view tab. Board card-specific and search caret restoration remain intact.
- On narrow viewports, the user-ordered view navigation becomes a horizontally scrollable single row with touch-size targets rather than stacked tab bands. Removed the obsolete visible `UWE-03C` developer label from the Work page. No generated images, no Base UI override.
- Extended the existing canonical UWE runtime tests to exercise keyboard navigation and stale-search timer cancellation, and added source guards to the existing M1 filter contract. All full Golden, cross-product integration and WordPress FIELD acceptance remain **OPEN** until the M1 milestone is ready.

## M1 filter-safe read recovery and acceptance evidence (2026-10-29)

- Hardened transient-recovery parity: retained Work Items HTML is valid only when **both** the view and exact canonical server query (filters, sorting, calendar month and paging) match the successful prior GET. A new query that fails never masquerades stale data as the requested results.
- If a new query or view cannot load, the JS app shows the existing accessible error/Retry state while hiding the initial, unfiltered SSR bootstrap list; that bootstrap remains available for genuine no-JS progressive enhancement. When access is revoked, the existing 401/403 purge remains authoritative.
- Added focused runtime assertions for identical-query retention, differing-filter fallback and hidden stale SSR results. Updated read-recovery source regression accordingly; existing work-m1 tests remain in the full Level 1 gate.
- Source-only audit and targeted pure-function runtime review are *not* equivalent to Level 1/2/3 or FIELD evidence. M1 remains IN DEVELOPMENT; complete execution and WordPress acceptance are still pending.

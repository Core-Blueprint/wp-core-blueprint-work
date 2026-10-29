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

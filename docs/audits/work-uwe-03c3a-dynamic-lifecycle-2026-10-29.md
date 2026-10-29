# UWE-03C.3A — Dynamic Interaction Lifecycle

Date: 2026-10-29
Baseline: UWE-03C.2.1 technical Golden, Work HEAD 1880771a67e11240b23727801d577a25d96f1e9e
Baseline release SHA-256: d553454b9aa3a795e924f8c948d1eb5d69e762faf9f1f0d31434a954d5e3a4c4
Branch: feature/work-uwe-03c3a-interaction-lifecycle-20261029
Status: SOURCE CANDIDATE, Level 1/2/3 and FIELD OPEN. Main merge HOLD.

## Problem and observed FIELD

The canonical Table, List, Board and Calendar HTML renders successfully in UWE-03C.2. User reports all interactions inactive; no browser console errors. This is expected from UWE-03C.2 read-only mounting because legacy scripts initialize only once when the document loads and their controls are partly hidden by scoped CSS.

## 03C.3A approved slice

- Reuse existing Work script modules and their existing Base dependencies (modal and reorder) on the Unified Workspace screen, but not on unrelated pages.
- After importing canonical HTML, notify the original scripts with one document event, cb-work:canonical-view-mounted, carrying the inserted root.
- Before replacing a previous view, notify cb-work:canonical-view-unmount to destroy Base reorder controllers for the outgoing board, avoiding stale event handlers on removed elements.
- Refine Board and List on dynamic fragments using existing Work refinement helpers with per-page idempotence. The existing native ready() invocation remains unchanged.
- Enhance Board drag and keyboard reorder via the existing Base reorder controller and WorkItemBoardActions authorization, expected-status guards, and outbox flow. The Workspace disables page reload after a persisted move; the native Work Items screen retains its existing reload behavior. Do not copy drag logic, bypass backend mutations, or introduce new endpoints.
- Register existing Calendar day modal triggers on newly inserted calendar fragments; keep Base modal lifecycle, focus restoration, dialog dismissal, and embedded Board reorder unchanged.
- Intercept canonical Calendar previous/today/next month links and update the existing Workspace History URL with calendar_month. Preserve WordPress reserved admin page and avoid full navigation. Leave the native fallback hrefs intact for no-JS operation.
- Do not enable forms or preference controls in this patch; Table quick edit, bulk actions, view preferences, filter form interception and all other mutations require explicit 03C.3B parity checks.
- Maintain Work Item Gutenberg editing and all native Work routes.

## Regression requirements

1. Source smoke tests identify all script modules, ensure the explicit mount/unmount contract exists, and protect Operations.php G0/B1 blob.
2. Node URL tests verify canonical Calendar links retain the correct WordPress admin route and map calendar_month into the bridge query; reject cross-origin/malformed URLs.
3. Existing 03C.2 mounting and fallback tests remain unchanged and must pass.
4. Level 1: ./tools/i18n/check && ./tools/check.
5. Level 2: ./tools/check-integration.
6. Level 3: ./tools/build-release + ZIP integrity and SHA-256.
7. Native Work Items Table/List/Board/Calendar and their mutation scripts must remain unaffected.

## WordPress FIELD

1. Load Work -> Work Items -> Board. Drag one card to an allowed status. Verify expected-status transition, moved card, count updates, no page reload and server persistence after refresh. A disallowed transition must not persist.
2. Load Calendar. Click a date containing Work Items. The existing Base modal opens and dismisses with Escape/Close, restores focus, and shows its content.
3. Calendar previous/today/next month navigation changes the month in-place, and browser Back/Forward correctly restores the month.
4. Load List. Expand and collapse a project and a Work Item, including Escape; no duplicate toggles after switching views repeatedly.
5. Switch among views rapidly and return; no double initialization, duplicate network mutations, stale dragged DOM or console errors.
6. Test keyboard navigation, narrow viewport, Base Light/Dark and reduced motion.
7. Verify Projects and Services still use UWE-03B REST search and are unaffected; old Work Items fully functional.

## Explicit HOLD

Do not claim full parity until 03C.3B. Table bulk and quick edits, native view preferences, all filter controls, mutable menus and advanced Calendar interactions remain out of scope. No change to Base, protected Work Operations renderer, schemas, write contracts, or production data. No merge/deploy without separate GO.

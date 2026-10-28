# UWE-03A — Unified Workspace Shell Foundation

Date: 2026-10-28
Branch: feature/work-uwe-03a-unified-workspace-shell-20261028
Base Work SHA: f40f0cfcce18a4f20d227218539e622e80584b0c
Status: SOURCE CANDIDATE — Level 1/2/3 and WordPress FIELD require actual evidence. Main merge HOLD.

## Scope
- Hidden, manager-only WordPress admin route: admin.php?page=core-blueprint-work-workspace.
- Read-only first page with bounded canonical Work Items, Projects and Services records (25 each).
- Deep-linkable section navigation with browser Back/Forward and per-section scroll positions.
- Semantic ARIA tabs, native anchor fallback without JS, arrow/Home/End keyboard selection.
- New assets scoped to the new route and Base Admin Theme; vanilla ES modules, no framework.
- Existing WordPress submenu entries, Gutenberg/native editors, database and mutators remain unchanged.

## Non-goals
- No new read/write REST endpoint or mutation.
- No Board/Table/List/Calendar feature parity yet; this is a limited read-only data sample.
- No fullscreen editor, modal, time, recurrence or Service Operations integration yet.
- No Base-source changes, legacy retirement, migration or production navigation switch.

## Golden boundaries
- src/Admin/Operations.php G0/B1 blob 92a4814f24b43f96d16e3d96ce61a49ee9f21500 untouched.
- UX-00A existing eight visible and two contextual entries stay in their exact order;
  third contextual route is appended and intentionally hidden.
- Tracker-only Time landing stays unchanged, cannot enter the manager-only new route.
- Existing Work repositories and public API contracts remain authoritative.

## Validation
1. php -l src/Admin/UnifiedWorkspace.php; php -l src/Admin/Menu.php; php -l src/Admin/Assets.php
2. node --check assets/js/workspace/router.js; node --check assets/js/workspace/app.js
3. php tests/work-uwe-03a-shell-smoke.php
4. node --experimental-default-type=module tests/work-uwe-03a-router-runtime.mjs
5. ./tools/i18n/check; ./tools/check; ./tools/check-integration; ./tools/build-release
6. unzip -tqq dist/core-blueprint-work.zip; (cd dist && sha256sum -c core-blueprint-work.zip.sha256)

## WordPress FIELD
- Login as Work manager. Open admin.php?page=core-blueprint-work-workspace directly.
- Work Items default; switch to Projects and Services without full document navigation.
- Verify native records, empty states, direct ?section=projects/services and refresh.
- Verify browser Back/Forward, scroll restoration, Arrow Left/Right, Home/End, Enter, Tab, Shift+Tab.
- Verify Base light/dark and desktop, 782px, 480px layouts.
- With JS unavailable, each tab link still navigates to its server-rendered section.
- With TRACK_TIME only, the new route is inaccessible and old Time landing works.
- Verify existing Work menus, native create/edit, and all management operations unchanged.
- No deletion, no record mutation, no merge, no release.

# Core Blueprint Work | B1.3 Board Renderer Golden Candidate

**Date:** 2026-10-13
**Status:** IMPLEMENTED ON FEATURE BRANCH, OPERATOR L1/L2/L3 AND SANDBOX ACCEPTANCE PENDING. NOT MERGED.
**Repository:** `Core-Blueprint/wp-core-blueprint-work`
**Branch:** `feature/work-admin-board-renderer-golden-b1-3-v1`
**Golden base:** `main` `256478ad0cfc350ae0e6dc191607ef3ef6fbc562`
**B1.2 Operations exact Git blob:** `8a1f353f3248999d35b0a29749f0211e3247575a`

## Change boundary

Two Board-only private renderer methods, `render_work_item_board_actions` and `render_work_item_kanban`, are moved **byte for byte** into `src/Admin/WorkItemBoardRenderer.php`, a Work-owned interim trait mounted by `Operations`. Shared status/action, humanize, priority, due and assignee helpers stay in `Operations`. No UI CSS/JS, database, AJAX endpoint, Calendar, Time, List, Table, Base interface, schema, version or release-process change.

G0 `tests/work-admin-renderer-g0-smoke.php` now reconstructs (1) the exact B1.2 Operations Git blob by inserting Board methods, (2) the exact B1.1 Operations blob by inserting List methods, and (3) the pre-B1 Operations blob by inserting Table methods. Existing Board Reorder, Board Efficiency, UX refinement source tests now target the moved Board implementation; isolated cross-view runtime explicitly requires the Board trait before the Operations host when plugin autoload is absent. Original semantic assertions retained.

## Validation and release gate

Run locally, on this exact branch:

```bash
cd ~/Downloads/wp-core-blueprint-work
git fetch origin --prune
git switch --track origin/feature/work-admin-board-renderer-golden-b1-3-v1
git pull --ff-only
git rev-parse HEAD
php -l src/Admin/Operations.php
php -l src/Admin/WorkItemBoardRenderer.php
php -l tests/work-admin-renderer-g0-smoke.php
php tests/work-admin-renderer-g0-smoke.php
php tests/work-item-board-reorder-smoke.php
php tests/work-board-efficiency-golden-smoke.php
php tests/work-items-ux-refinement-smoke.php
php tests/work-cross-view-filter-state-runtime.php
./tools/check
./tools/check-integration
./tools/build-release
unzip -tqq dist/core-blueprint-work.zip
unzip -Z1 dist/core-blueprint-work.zip | grep -Fx 'core-blueprint-work/src/Admin/WorkItemBoardRenderer.php'
sha256sum dist/core-blueprint-work.zip
(cd dist && sha256sum -c core-blueprint-work.zip.sha256)
git diff --exit-code
git status --short
```

Expected release output `dist/core-blueprint-work.zip`. The new checksum must come from the actual operator build; no SHA-256 asserted in advance. `?? dist/` is expected untracked build output. On failures, repair this branch and rerun full tests.

## Functional sandbox acceptance

Install **only on sandbox** `wp-sandbox.infused.academy`. Verify Board lane headings and counts, active/terminal statuses, cards/details/badges, compact actions and transitions, empty/long lanes and header/footer quick-add, keyboard/pointer drag-and-drop and error rollback. Recheck Table quick/bulk, List grouping/disclosure, Calendar wide expandable modal, focus/filter URLs, light/dark and responsive behavior. No production deployment. Request distinct merge GO only after user reports stability and test gates PASS.

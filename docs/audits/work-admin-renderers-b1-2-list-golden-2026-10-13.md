# Core Blueprint Work | B1.2 List Renderer Golden Candidate

**Date:** 2026-10-13  
**Status:** SOURCE PATCH READY, OPERATOR GOLDEN NOT YET RUN; NOT MERGED  
**Repository:** `Core-Blueprint/wp-core-blueprint-work`  
**Feature branch:** `feature/work-admin-list-renderer-golden-b1-2-v1`  
**Golden base:** Work `main` `c4fe0fd77f3e6481113bca71da67f19ab4bd518d`  
**No Base, Calendar, Board, Time, A1 or A2 product changes.**

## Resolved sandbox Calendar baseline

The operator updated Core Blueprint Base on `wp-sandbox.infused.academy`.
The formerly narrow, footer-close Calendar modal now displays correctly.
This confirms the sandbox environment was missing the current Base modal
behavior. No change to Work's Calendar was necessary. Keep that correct
wide/expandable modal as the **visual nonregression baseline for B1.2**.
Production was not changed by this architecture patch.

## B1.2 engineering boundary

`Operations.php` on accepted B1.1 `main` had **1,573** lines.
B1.2 extracts an exact **five-method** List implementation into
`src/Admin/WorkItemListRenderer.php`, leaving the host at about **1,283**
lines. Moved code is *byte-for-byte* the accepted B1.1 source:

- `render_work_item_list`, including grouped/ungrouped branches
- `work_item_project_groups`, including order and the no-project group
- `render_work_item_list_item`, including actions, badges and detail toggles
- `render_work_item_list_details`
- `render_work_item_list_actions`

**Conservative design:** a Work-owned PHP trait, analogous to B1.1 Table,
preserves access to `Operations`' existing private shared presentation
helpers and keeps all UI, CSS classes, URLs, status/nonce forms, escaping,
translations and Base contracts unchanged. It is an **intermediate
renderer ownership boundary**, not yet a standalone injected service.
Separate List responsibilities are isolated without a forced expansion
of the public `Operations` API.

The following remain unchanged at this stage: Board renderer and its
drag-and-drop, Calendar view/day modal, shared toolbar/filter renderer,
Table implementation, Work/Time storage and repository mutations,
Base adapter, input permissions and product version.

## G0 source integrity: continuous chain

`tests/work-admin-renderer-g0-smoke.php` now verifies two exact
reconstructions:

1. Put the five extracted List methods back at the original mount:
   reconstructed B1.1 `Operations.php` must have original Git blob
   **`8c87f5911d6baafc0f283627f139f38970d2fd3b`**.
2. Put the six B1.1 Table methods back: resulting pre-B1
   `Operations.php` must have Git blob
   **`1473f8fa1aa8d38fb102019b47d768f421a962a8`**.

No whitespace, markup, action or method-body difference is permitted.
The previously accepted A1/A2 and Table source-smokes remain intact.
`tests/work-items-ux-refinement-smoke.php` and
`tests/work-item-list-preferences-smoke.php` now read the dedicated
List file, not an assumed List block in `Operations.php`; their
semantic assertions remain. The isolated focus-preset runtime fixture
explicitly loads both Table and List traits before directly requiring
the host (the normal Work autoloader resolves both in WordPress).

## Operator acceptance

```bash
(
  set -euo pipefail
  cd ~/Downloads/wp-core-blueprint-work

  git fetch origin --prune
  if git show-ref --verify --quiet refs/heads/feature/work-admin-list-renderer-golden-b1-2-v1; then
    git switch feature/work-admin-list-renderer-golden-b1-2-v1
  else
    git switch --track origin/feature/work-admin-list-renderer-golden-b1-2-v1
  fi
  git pull --ff-only
  git rev-parse HEAD

  php -l src/Admin/Operations.php
  php -l src/Admin/WorkItemTableRenderer.php
  php -l src/Admin/WorkItemListRenderer.php
  php tests/work-admin-renderer-g0-smoke.php
  php tests/work-items-ux-refinement-smoke.php
  php tests/work-item-list-preferences-smoke.php
  php tests/work-cross-view-filter-state-runtime.php
  php tests/work-item-mutation-golden-smoke.php

  ./tools/check
  ./tools/check-integration
  ./tools/build-release

  unzip -tqq dist/core-blueprint-work.zip
  unzip -Z1 dist/core-blueprint-work.zip | grep -Fx 'core-blueprint-work/src/Admin/WorkItemListRenderer.php'
  sha256sum dist/core-blueprint-work.zip
  (cd dist && sha256sum -c core-blueprint-work.zip.sha256)
  git status --short

  echo 'WORK B1.2 LIST TECHNICAL GOLDEN: PASS'
)
```

**Do not assert exact-HEAD/ZIP checksum PASS until operator executes these commands.**
Expected generated `?? dist/` is not a source diff.

**Sandbox acceptance:** List grouping (project and ungrouped), project
ordering, no-project group, expand/collapse and keyboard behavior,
per-item detail disclosure, status actions and conflict handling,
filters and paging; revisit Table Quick Edit/bulk and Board drag/drop;
verify Calendar modal **still uses the desired wide/expandable header
presentation**, light/dark and mobile. No production deployment.

On sandbox stability, request a **separate explicit user merge GO**.
After merge, exact-source post-merge L1/L2/L3/ZIP checksum gate is needed
before marking B1.2 CLOSED. Only then plan B1.3 Board extraction /
shared UI helper architecture. Do not stack unfinished slices.

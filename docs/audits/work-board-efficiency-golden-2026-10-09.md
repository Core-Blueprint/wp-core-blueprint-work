# CV-G-004 | Work Board Efficiency Golden

Date: 2026-10-09  
Status: **SOURCE CANDIDATE / LOCAL REGRESSION, BUILD AND BROWSER ACCEPTANCE PENDING**  
Repository: `Core-Blueprint/wp-core-blueprint-work`  
Branch: `feature/work-board-efficiency-golden-v1`  
Direct base: **CV-G-003** `feature/work-cross-view-toolbar-golden-v1`
`63d50fa42194a602d8128e6ec6ecb3508ec3e1bc`.  
Currently merged `main`: accepted CV-G-002
`56bbb4416d033fd90b2d3f64a01e414a74d2fb6d`.

**Dependency:** CV-G-003 has been reported stable by the operator but
has **not** received a separate explicit merge GO in the present
conversation. Accordingly, CV-G-004 is a **stacked, unmerged featurebranch**,
not a candidate to merge independently into the current `main`.
Do not claim either gate closed until its merge is separately approved
and verified. Before a subsequent CV-G-004 merge, verify CV-G-003 is
already in `main`, or explicitly reconcile the stacked base.

## Why this patch

Operator's original Board screenshot shows 19 Planned, 1 In Progress,
and empty Blocked/Completed lanes. Current canonical Board layout is
visually consistent and has accepted Base Reorder interaction. Two
daily-efficiency issues remain:

1. **Long-lane Add action is below all cards.** The pre-existing
   `.cb-work-board__lane-footer` link is accessible only after
   scrolling past 19 Planned cards, increasing friction for a
   high-frequency action.
2. **Empty-lane presentation occupies unnecessary height.** The
   empty placeholder and nested minimum sizes reserve space even
   when there are no cards. However, empty lanes must remain **valid
   drop targets** with a generous target during a drag.

The desired result is a **small interaction/density correction**, not a
new board architecture or status policy.

## Isolated implementation

- `src/Admin/Operations.php`: a small header shortcut is rendered
  only for active statuses containing **8 or more Work Items**.
  The label/tooltip reuse the existing translated `Add Work Item`
  string, and `aria-describedby` references the lane heading
  to provide status context to assistive technology.
  It uses the exact existing canonical
  `Menu::new_work_item_url( $lane_project_id, (string) $status )`
  and `data-cb-work-quick-status-label` that the current Quick Add
  interaction already understands. There is no new route, nonce,
  privilege or REST/AJAX endpoint.
- The existing footer Add link is unchanged and remains **outside**
  the Base Reorder list. No new creation links in terminal statuses
  (Completed/Skipped/Cancelled).
- `assets/work-board-efficiency-golden.css`: isolated Board-only
  styling. Header shortcut is visible, keyboard-focusable and
  legible under existing Base Light/Dark semantic tokens.
  Empty lanes occupy less vertical space. A live `:has()`
  selector checks the pre-existing placeholder's `[hidden]`
  state, which the accepted reorder implementation updates.
  When `.is-reordering` is active, empty destination lists
  retain **144px minimum target height**; lists remain present
  in the DOM with unchanged Base Reorder attributes and markers.
- `src/Admin/Assets.php`: enqueues the new stylesheet only when
  Work Items resolves to Board/Kanban, after Work's Refinement CSS.
  Calendar day-modal's board renderer does **not** receive this
  CSS and has not been modified.
- No JS changes, status-transition changes, database writes,
  localization additions, Base component changes or edits to
  Table/List/Calendar/Time.

## Regression coverage

New `tests/work-board-efficiency-golden-smoke.php` validates:

1. Long active lane threshold and existing status policy; genuine
   `WorkItemStatus::active()` versus terminal statuses for 0,1,7,8,19
   items.
2. Canonical Quick Add status + project URL, status label metadata,
   translated accessible name and lane heading relationship.
3. Existing footer action and Base Reorder root/list/item/handle
   contracts remain untouched.
4. Existing JS hides/reveals empty placeholder as count changes,
   and Base cross-list status validation is still authoritative.
5. Work Board-only CSS scope and conditional enqueue (not Calendar).
6. Compact empty lane sizing, and larger dropzone while dragging.
7. Theme semantic tokens and keyboard-focus handling.

The test is included in `./tools/check`. Run existing Board reorder,
Calendar day-modal/reorder, View preferences, CV-G-002 filter,
CV-G-003 toolbar, Time and Toast regressions too.

## Operator terminal gates

Start from the local Work checkout. This is a **stacked** branch:
its ancestry includes CV-G-003. It is safe to test its full ZIP as
one candidate; it is **not** permission to merge both gates.

```bash
cd ~/Downloads/wp-core-blueprint-work

git status --short
git fetch origin --prune
git switch --track origin/feature/work-board-efficiency-golden-v1
git pull --ff-only
git rev-parse HEAD

php -l src/Admin/Operations.php
php -l src/Admin/Assets.php
php -l tests/work-board-efficiency-golden-smoke.php

php tests/work-board-efficiency-golden-smoke.php
php tests/work-item-board-reorder-smoke.php
php tests/work-item-calendar-reorder-smoke.php
php tests/work-toolbar-composition-smoke.php
php tests/work-cross-view-filter-state-runtime.php
php tests/work-cross-view-filter-style-smoke.php

./tools/i18n/check
./tools/check

./tools/build-release
unzip -tqq dist/core-blueprint-work.zip
sha256sum dist/core-blueprint-work.zip
(cd dist && sha256sum -c core-blueprint-work.zip.sha256)
```

No interactive `set -e`, shell `exit`, destructive clean or CI. A
pre-existing untracked `dist/` directory is an expected build output.
Do not reuse the CV-G-003 ZIP SHA-256
`6c51854fb19ce95b580ee27ed499112311ef27432f4ede29a23ef9ac9b1f86b8`;
a new source change needs a new artifact checksum.

## WordPress acceptance

After a green local full build, install the newly built ZIP in the
operator-controlled WordPress environment (clear cached CSS if needed):

- **19 Planned:** compact `+` in the Planned lane heading; it opens
  Quick Add with Planned status and respects the current Project
  filter. Footer Add still works at the end of the lane.
- **Small populated lanes (1–7):** no unnecessary second header Add.
- **Empty Blocked:** shorter placeholder and existing Add action;
  lane remains visible, full-width and a drop destination.
- **Completed and any other terminal lane:** no new creation
  shortcut; no illegal transitions or accidental controls.
- **Dragging:** temporarily larger dropzone, existing Base drag
  marker/highlight, legal-status move constraints; counts and
  placeholder update when a move succeeds or fails.
- **Responsive and themes:** Board Light/Dark, 1660/1280/1024px,
  horizontally scrollable narrow Board and mouse/keyboard focus.
- **Regressions:** Board actions menu, keyboard drag, modal Quick Add,
  project/status context, CV-G-003 toolbar, Table/List/Calendar and
  Time operation remain unchanged.

## Approval and continuation

Await operator run of local focused and full regressions, six locales,
release ZIP/integrity/SHA verification and WordPress browser acceptance.
Neither CV-G-003 nor CV-G-004 is authorized to merge based solely on
the operator's "stable, continue with next patch". Require an explicit
merge GO and guarded fast-forward, reconciling stacked ancestry.
Then update Work and Overseer with exact sha/checksum before planning
CV-G-005 icon-action accessibility.

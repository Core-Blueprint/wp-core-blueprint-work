# Work Calendar Golden | Responsive month and day workspace

Date: 2026-10-08  
State: **feature candidate, operator validation pending**  
Repository: `Core-Blueprint/wp-core-blueprint-work`  
Branch: `feature/work-calendar-final-ux-golden-v1`  
Baseline: `main` at `93a10cc74aa984bad46954b5803bc5123f1ee0e4`

## Scope and design contract

This is the first focused final Work UI/UX patch. It keeps the existing
Table/List/Board Golden features and Time Golden untouched. Calendar stays
a compact seven-column month view. A date opens the accepted Base
`workspace` modal, including Base's native expand/restore control;
the modal reuses the Work Board renderer and its AJAX status transitions.

What changes:

1. A consistent calendar navigation row: previous month, readable current
   month, Today and next month, with responsive rearrangement at narrow
   WordPress admin viewport widths. Existing Work Item filter/view state
   survives links through `WorkItemViewState::query_args`.
2. Today is resolved using the WordPress site clock rather than the
   server timezone, gets an explicit focus-aware visual ring, and has
   `aria-current="date"` plus a screen-reader Today label.
3. The month table has a screen-reader caption and is wrapped in an
   independently keyboard-scrollable, labelled horizontal region.
   Cell buttons expose a contextual date/count name and
   `aria-haspopup="dialog"`.
4. The day modal's Board lanes preserve 320-pixel minimum widths and
   use a labelled/focusable horizontal scroll region with inline-end
   padding to keep the last lane clear of the scrollbar.
5. Base inserts modal contents outside `.cb-work-items-page`; the
   day-modal root now explicitly inherits the same Work semantic token
   aliases so light/dark cards and text do not depend on parent layout.
6. A dedicated read-only regression smoke is included in
   `./tools/check`; accepted Calendar reorder smoke continues to
   verify reuse of Base Modal and Board AJAX state persistence.

**No new strings:** all displayed labels are already present in the
canonical Work POT and all six locale catalogs. No Base code changes,
new modal system, data changes, or release metadata changes.

## Not included

- New calendar date drag/drop or additional calendar views;
- Global Work Item toolbar/filter redesign (separate cross-view slice);
- Changing the Base Modal default maximum width (already supports
  workspace up to 1480px with expand/restore);
- Changes to Board/List/Table or Time state machines.

## Operator validation

Fetch the branch, confirm the exact candidate commit, then run:

```bash
cd ~/Downloads/wp-core-blueprint-work
git status --short
git fetch origin --prune
git switch --track origin/feature/work-calendar-final-ux-golden-v1
git pull --ff-only
git rev-parse HEAD

php -l src/Admin/WorkItemCalendarView.php
php tests/work-calendar-final-ux-smoke.php
php tests/work-item-calendar-reorder-smoke.php
./tools/i18n/check
./tools/check
./tools/build-release
unzip -tqq dist/core-blueprint-work.zip
sha256sum dist/core-blueprint-work.zip
(cd dist && sha256sum -c core-blueprint-work.zip.sha256)
```

These are separate commands safe for an interactive terminal; no
`set -e`, `exit` or forced CI execution. Never accept a releasebuild
if the preceding checks fail.

After green local checks, inspect the WordPress Calendar view on
coreblueprint.io with realistic test Work Items:

- At desktop 1440px: navigation alignment, real Today marker, mixed
  scheduled/due dates and neutral empty cells.
- At around 1024px, 782px and 390px: month scrolls within Calendar
  rather than forcing the toolbar to scroll; previous/Today/next controls
  remain visible and do not overlap.
- Use keyboard to focus the month viewport and scroll it horizontally.
  Calendar day buttons announce a meaningful date/count, open the
  existing Base modal with keyboard activation, and restore focus via
  Base's normal dialog close flow.
- In the day modal, inspect full/expanded widths, active and closed
  entries, lane overflow and inline-end spacing. Status drag/move
  persists without a forced page reload and stays correct after reopening.
- Verify admin Light/Dark both style month cells and modal cards correctly;
  with reduced motion enabled, cards do not lift on hover.
- Confirm Table/List/Board, Time and Recurring Work remain unaffected.

## Golden merge gate

Require local PHP lint, source smoke, existing Calendar reorder smoke,
canonical i18n for all 6 locales, `./tools/check`, release ZIP integrity,
SHA-256, operator WordPress visual/runtime acceptance and **explicit GO**.
No merge, CI, release publication or production deployment is authorized
by the creation of this candidate.

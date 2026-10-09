# Work Calendar | Final UI/UX Golden Candidate

Date: 2026-10-08  
State: **POLISH CANDIDATE / updated local regression and visual re-acceptance pending**  
Repository: `Core-Blueprint/wp-core-blueprint-work`  
Branch: `feature/work-calendar-presentation-golden-v1`  
Base: accepted Work `main` `93a10cc74aa984bad46954b5803bc5123f1ee0e4`

## User-facing scope

This patch polishes the *existing* WordPress Work Items Calendar,
without introducing a new calendar, modal implementation or planning
semantics. It deliberately leaves accepted Table/List/Board, Time,
Work Item workflows, nonce boundaries and status persistence unchanged.

- **Month navigation:** retain server-side Previous month, Today and
  Next month routes, WordPress user filter state, and a readable title,
  but separate the month label from right-aligned actions. Wrap
  controls as three responsive buttons on narrow screens.
- **Calendar grid:** place the native seven-column table in its own
  keyboard-focusable, labeled horizontal scrolling region. The
  top-level Work page should not be forced wider by calendar columns.
  A `screen-reader-text` table caption names the displayed month.
- **Today:** highlight the WordPress-site-local current date
  (`current_time('Y-m-d')`) using semantic
  `<time datetime="…" aria-current="date">` markup.
- **Timezone:** create calendar dates directly with
  `wp_timezone()` and render them through `wp_date()`. This uses the exact
  WordPress site-local date without a potentially incorrect UTC-midday
  approximation, including extreme timezone offsets.
- **Accessible day actions:** modal-opening buttons announce the
  full site-local day and Work Item count and expose `aria-haspopup="dialog"`.
- **Modal Light/Dark:** Base moves modal body outside the Work Items page.
  Scope existing Work semantic color/surface aliases directly to the
  transplanted day root so dark-mode cards and text retain valid tokens.
- **Day modal:** preserve Base `size:'workspace'` and
  `expandable:true`; make the Board lane viewport keyboard-scrollable
  and leave padding beside its scrollbar. Active Work status
  transitions, AJAX persistence, Card Golden hierarchy and the closed
  Work Items disclosure are unchanged.

## Design and architecture constraints

- `src/Admin/WorkItemCalendarView.php` is the canonical server renderer;
  no separate client-side calendar renderer or duplicate state model.
- `assets/work-calendar-golden.css` is a small, dedicated presentation
  stylesheet enqueued **only for the Calendar view** after the existing
  refinement styles. Avoid more monolithic global Work CSS.
- Base continues to own dialog focus, Escape, close, workspace sizing
  and expand/restore. Do not override the generic Base modal CSS.
- Existing accessible WordPress strings are reused; no new source
  gettext keys are required. Source/POT/PO alignment must be checked.
- New `tests/work-calendar-presentation-smoke.php` is included in
  `./tools/check` alongside the already accepted Calendar/Board tests.

## Acceptance matrix

1. **Desktop (1366px+):** calendar title left, Previous / Today / Next
   actions right; table fits its own viewport; today's date highlighted;
   no visual disruption to filters.
2. **Intermediate (around 1024px):** seven weekday columns remain readable
   within the calendar's horizontal scroll; page-wide horizontal overflow
   is not introduced.
3. **Narrow (782px down to 375px):** navigation buttons become an equal
   three-column group; scroll the calendar horizontally with mouse/touch
   or focus the viewport and use a keyboard.
4. **Day modal:** open a populated day; review legible 320px+ Board
   lanes, scroll them inside the modal, expand/restore Base workspace,
   and verify Close/Escape and focus behavior.
5. **Status move:** use the canonical drag handle to change a status,
   close/reopen the same day and confirm the card and lane counts stayed
   in sync without a page reload.
6. **Light/Dark:** verify body text, surface contrast, today marker,
   modal lanes, focus outline and scrollbars in the admin theme.
7. **Other Work views:** Table, List, Board and Time must show no layout
   change from this calendar-only stylesheet.

## Operator feedback and follow-up polish (2026-10-09)

The operator confirmed the preceding Calendar candidate stable after:
PHP syntax PASS, Calendar presentation/day-modal/state smokes PASS,
all six locale i18n catalogs and full canonical Work suite PASS,
releasebuild and ZIP checksum PASS. Validated preceding artifact:
`71e68f77ba1dfc3246ff13369d20860b29e6927111247f28085c956eddd0fb62`.

Two visual issues remain and are addressed on **the same canonical feature
branch**, without changing Base:

1. The clickable Work Items summary inside each month-day cell receives
   explicit internal `var(--cb-space-3)` padding, `var(--cb-space-1)` gap
   and `var(--cb-radius-md)` radius. Scoped selector overrides the
   WordPress button-link reset, not global day cells or other views.
2. Only the Calendar's Base workspace dialog promotes its **existing**
   dismiss-only Close button from the footer to a two-control header group,
   ordered Expand/Restore at left, icon Close at far right. The empty
   footer action menu is removed from the DOM. Close retains Base's
   actual event handler, modal promise resolution, Escape and focus
   restoration. Unknown Base markup falls back to its original footer.
   Local Calendar-only CSS aligns the buttons and reserves title space,
   including visible keyboard focus and localized Close accessible name.

`tests/work-calendar-modal-header-runtime.js` executes the real Calendar
JS in a dependency-free fake DOM to check that the exact Base button is
relocated without losing its prior listener, that the footer disappears,
and that unfamiliar Base markup fails safely. The script is wired into
`./tools/check`; the presentation smoke also guards the padding/radius,
and this Calendar-only modal treatment.

**Follow-up candidate is not yet locally validated**. Required gates:
focused PHP/Node smokes, all six locales, `./tools/check`, the complete
release ZIP and checksum, then visual acceptance of both screenshot
corrections in the WordPress Light/Dark Calendar. **No merge without
explicit new operator GO.**

## Candidate reconciliation

A second experimental implementation
`feature/work-calendar-final-ux-golden-v1` was discovered while this
Calendar-only candidate was already registered in Overseer. To avoid
parallel merge targets, **this branch remains the single canonical
acceptance candidate**. The experimental alternative must not be
merged or installed for this gate. Two useful safeguards from the
alternative were applied here: local modal theme aliases and accessible
day/action naming. Only this candidate's exact SHA, tests and release
checksum are authoritative for WC-G-001.

## Operator local checks

Run from `~/Downloads/wp-core-blueprint-work` with a clean tracked tree.
No commands below change error handling in the user's interactive shell.

```bash
git status --short
git fetch origin --prune
git switch --track origin/feature/work-calendar-presentation-golden-v1
git pull --ff-only
git rev-parse HEAD

php -l src/Admin/WorkItemCalendarView.php
php -l src/Admin/Assets.php
php -l tests/work-calendar-presentation-smoke.php

php tests/work-calendar-presentation-smoke.php
node tests/work-calendar-modal-header-runtime.js
php tests/work-item-calendar-reorder-smoke.php
php tests/work-items-ux-refinement-smoke.php

./tools/i18n/check
./tools/check

./tools/build-release
unzip -tqq dist/core-blueprint-work.zip
sha256sum dist/core-blueprint-work.zip
(cd dist && sha256sum -c core-blueprint-work.zip.sha256)
```

Build only after green tests. If the branch already exists locally,
use `git switch feature/work-calendar-presentation-golden-v1` rather
than `git switch --track`.

## Merge and product readiness gate

The original Calendar candidate passed local PHP/i18n/Work release
checks and received stable WordPress operator feedback; two screenshot
polishes were subsequently requested. **This updated polish candidate
still requires fresh local tests, ZIP checksum, visual re-acceptance,
and an explicit merge GO.**
Do not merge, deploy, publish, or run CI autonomously.

The remaining cross-view theme/filter/keyboard polish and final Work
Product Golden Audit are independent follow-up gates.

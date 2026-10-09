# CV-G-003 | Work Items cross-view Toolbar & Search Golden

Date: 2026-10-09  
Status: **SOURCE CANDIDATE / LOCAL AUTOMATED TESTS AND BROWSER ACCEPTANCE PENDING**  
Repository: `Core-Blueprint/wp-core-blueprint-work`  
Branch: `feature/work-cross-view-toolbar-golden-v1`  
Base: accepted CV-G-002 merged `main`
`56bbb4416d033fd90b2d3f64a01e414a74d2fb6d`

## Problem and direction

Operator accepted CV-G-002 in Light and Dark and requested the next
cross-view UI pass, emphasizing unused toolbar whitespace, shifting
Search and visual consistency. The Board/Table/List/Calendar screenshots
show identical filter families but variable right-hand settings.

Source analysis found the Work toolbar owns three regions:
`cb-work-toolbar__head` (switcher/preferences),
`cb-work-toolbar__primary` (status/project/More filters),
`cb-work-toolbar__search` (Search plus optional Table/List controls).
The accepted CSS uses `auto minmax(0,1fr) auto`: the primary region
is stretched over empty space while Search retains a fixed width and
aligns right. Table-specific controls then change Search's position.

Design objective: maximize useful Search space without changing any
query state or control semantics. Default operations remain calm and
predictable; secondary utility actions should not compete visually
with the selected focus chip or Add Work Item.

## Scope of isolated patch

Added `assets/work-toolbar-golden.css`, a Work-only scoped stylesheet
enqueued after the existing refinement stylesheet using an explicit
WP enqueue dependency, behind the Work Items screen context.

- **Wide Work container (1480px+ available content width):** three intentional columns
  `max-content max-content minmax(0,1fr)`. Search begins at a common
  horizontal anchor and flexes to fill the available third region.
  Table/List settings retain fixed width **after** Search.
- **Medium Work container (1100..1479px content width):** view/filters first row, Search
  spans a deliberate second row. Avoid squeezing Search beneath
  Table-only controls.
- **Narrow Work container (below 1100px content width):** stack the three toolbar
  regions, so admin sidebar width does not force horizontal clipping.
- **Mobile (viewport 782px and below):** preserve already accepted
  mobile field sizing and grid controls from Work refinement. The
  container-only layout keeps the same single-column composition.
- CSS container queries target the Work toolbar's own inline size.
  A wide browser with a visible WordPress admin sidebar therefore
  receives the correct compact layout when its content is narrower.
- **Light and Dark:** restyle *only* Work Items utility actions
  (view preferences, More filters, Table/List display, Columns and
  Calendar previous/today/next month) using Base semantic tokens
  `--cb-surface-1`, `--cb-border`, `--cb-text-strong`,
  `--cb-interactive-hover` and `--cb-interactive-focus`.
  Preserve visible `:focus-visible` rings and expanded-button states.
  Selected view and focus presets keep their accepted distinct styles.
- No Core/Base global changes, no user preferences or filter state
  edits, no AJAX action rewrites, no Time or Board drag changes,
  no new translatable strings, no theme-specific hardcoded hex.

## Regression gates

New `tests/work-toolbar-composition-smoke.php` verifies:
- Source markup still has the canonical three Work toolbar regions
  and existing filter/query contract.
- Asset is Work-only, loaded once after Refinement; CSS is separate
  from the existing large Work stylesheets.
- All three responsive width bands and the mobile fallback are
  represented; Search flex, Table/List action size preservation.
- Secondary-control design-token and focus scope.
- CV-G-002 current-filter and selected-view styles remain untouched.

Canonical `tools/check` includes the new smoke. Existing CV-G-002,
Calendar, Board, List, Time, Toast and local i18n regressions must
also pass. No browser measurement is implied by source-contract tests.

## Operator validation

```bash
cd ~/Downloads/wp-core-blueprint-work
git status --short
git fetch origin --prune
git switch --track origin/feature/work-cross-view-toolbar-golden-v1
git pull --ff-only
git rev-parse HEAD

php -l src/Admin/Assets.php
php -l tests/work-toolbar-composition-smoke.php
php tests/work-toolbar-composition-smoke.php
php tests/work-cross-view-filter-state-runtime.php
php tests/work-cross-view-filter-style-smoke.php
php tests/work-items-ux-refinement-smoke.php
php tests/d2-workspace-routing-smoke.php
./tools/i18n/check
./tools/check

./tools/build-release
unzip -tqq dist/core-blueprint-work.zip
sha256sum dist/core-blueprint-work.zip
(cd dist && sha256sum -c core-blueprint-work.zip.sha256)
```

Do not use `set -e`, `exit` or shell-dependent abort commands in
the operator's interactive terminal. Existing untracked `dist/`
is expected after building. Stop and report any failed test; do
not reuse the accepted CV-G-002 ZIP checksum for this new candidate.

## WordPress acceptance

Inspect Board/Table/List/Calendar at approximately 1660px desktop,
1440px laptop, 1024px tablet/narrow admin, and 782/390px mobile.
Confirm Light and Dark:

1. Desktop: no large dead gap between More filters and Search;
   Search **begins at the same horizontal position** across views.
2. Table/List display and Columns controls remain on the right,
   reachable without overlap, crop or horizontal page scroll.
3. At narrow widths Search gets its own row; filters wrap sensibly;
   mobile form stays keyboard accessible.
4. Utility buttons are visually neutral until hover/focus/expanded,
   while `All` (focus preset), selected view and primary Add Work Item
   remain clearly distinguished.
5. Keyboard Tab focus and expanded menus/panels work; press Escape
   where implemented previously.
6. Existing filters, project selection, sort, view preference,
   Table actions, Board reorder, List disclosure and Calendar
   day-modal all work as before.

## Release gate

No local PHP runtime, full suite, release ZIP or browser test is
claimed until operator results are provided. Do not merge, publish,
run CI/Actions or deploy from this request without a separate
explicit operator GO. Upon approval, use expected-HEAD safe
fast-forward and update Overseer CV-G-003.

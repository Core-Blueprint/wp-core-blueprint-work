# CV-G-002 | Work cross-view filter state and Light/Dark focus contrast

Date: 2026-10-09  
Status: **SOURCE CANDIDATE / LOCAL AND BROWSER ACCEPTANCE PENDING**  
Repository: `Core-Blueprint/wp-core-blueprint-work`  
Branch: `feature/work-cross-view-filter-state-golden-v1`  
Base: `main` `5e094b1df0bd878960afa2679bd41ba4eacb4fc2`

## Screenshot anomaly and root cause

In the operator's four Work Items screenshots, **All** appeared active
in Table/Board/Calendar but not in List, even when List was in its
unfiltered default state.

The same server-side method
`Operations::render_work_item_focus_views()` renders all four views.
The `All` preset's `$has_other_filters` predicate previously flagged
any sort other than `WorkItemQuery::SORT_WORKLOAD` as a filter.
However, `WorkItemViewState` intentionally defaults **List to Title**
and other views to Workload. Consequently, the List default produced
a false non-current `All` state, **not a different underlying query
filter**.

## Focus state correction

- Compare `! empty( $state['sort_explicit'] )` instead of comparing a
  view's sort against a hardcoded Workload default.
- Keep the original canonical Work state and sort preferences.
  Unfiltered List `All` is now current without changing its Title
  default or the canonical query results.
- Detect non-empty **customer token** as a filter, including malformed
  or unresolved customer filters, so `All` cannot appear selected
  while the invalid-customer notice is active.
- Make focus presets exact: `My work`, `Blocked` and `Overdue` are
  not marked current when additional assignee/due filters modify the
  result set. Preset URLs remain owned by
  `WorkItemViewState::query_args` and preserve view/month only.
- Do not create client-side selected state, parallel filter stores or
  new WordPress meta/user preference keys. PHP continues to output
  matching `is-current` and `aria-current="page"` for each preset.

## Accessible Light/Dark presentation

Existing Work-only `assets/work-fast-paths.css` now:

- Uses Base theme semantic tokens for readable inactive chips
  (`--cb-text-strong`), hover (`--cb-interactive-hover`) and
  active chips (`--cb-accent` / `--cb-on-accent`) in either theme;
  avoids baked-in Light/Dark color values.
- Gives focused preset anchors a visible two-pixel outline and
  three-pixel offset using `--cb-interactive-focus`.
- Supports both `.is-current` and `[aria-current="page"]` so styling
  and assistive semantics agree.
- Provides forced-colors support for selected state and focus.
- Remains scoped to the Work Items page and is enqueued after
  the accepted refinement CSS on Work views.
- Does not change Table/List/Board/Calendar structure, Base theme,
  Calendar day modal, Time, search positions or toolbar composition.

**Contrast statement:** the implementation uses intended Base semantic
tokens. Exact WCAG AA ratios cannot be certified by source inspection;
operator browser verification of actual Light/Dark rendered themes,
focus rings and system high-contrast behavior is part of acceptance.

## Regression and quality gates

- New `tests/work-cross-view-filter-state-runtime.php` executes the
  **actual** `Operations::render_work_item_focus_views` using actual
  `WorkItemViewState::from_request`, normalizes query state, captures
  anchor markup and inspects `aria-current`, CSS current class and URLs.
- All four views: unfiltered `All` selected; List default Title and
  other views' default Workload remain unchanged.
- Exact `Active`, `Blocked`, `My work`, and `Overdue` presets
  selected; selected view and Calendar month retained in URLs.
- Compound blocked/assignee, blocked/due, my work/overdue combinations,
  explicit sort, Search, Project and invalid customer prevent false
  current presets.
- New `tests/work-cross-view-filter-style-smoke.php` checks theme
  tokens, focus outline, forced-colors, matched `aria-current`
  selectors and Work-only enqueue dependencies.
- Both new tests are wired into the full `./tools/check`.
- Still required: local PHP lint, new focused runtime/style checks,
  existing view preference + workspace routing checks, canonical
  `./tools/i18n/check` (six locales), full `./tools/check`,
  release ZIP determinism, ZIP integrity and SHA-256.
- No new translatable strings. No expected DB schema or data writes.

## Operator safe commands

Run from the local Work repo. If the branch is already local,
switch directly without `--track`. Do not use `set -e` in an
interactive shell.

```bash
cd ~/Downloads/wp-core-blueprint-work
git status --short
git fetch origin --prune
git switch --track origin/feature/work-cross-view-filter-state-golden-v1
git pull --ff-only
git rev-parse HEAD

php -l src/Admin/Operations.php
php -l tests/work-cross-view-filter-state-runtime.php
php -l tests/work-cross-view-filter-style-smoke.php
php tests/work-cross-view-filter-state-runtime.php
php tests/work-cross-view-filter-style-smoke.php
php tests/work-item-view-preferences-smoke.php
php tests/d2-workspace-routing-smoke.php
php tests/work-items-ux-refinement-smoke.php
./tools/i18n/check
./tools/check

./tools/build-release
unzip -tqq dist/core-blueprint-work.zip
sha256sum dist/core-blueprint-work.zip
(cd dist && sha256sum -c core-blueprint-work.zip.sha256)
```

## WordPress operator acceptance

Use the tested candidate ZIP on the operator-controlled site after
all automated gates pass. Ensure script/CSS cache refresh.

1. **All default in all four views:** open Table, List, Board and
   Calendar with no additional filters. The `All` chip is filled
   consistently and accessible `aria-current` matches the selected chip.
   List must still sort by Title; no spurious explicit sort chip.
2. **Exact presets:** select My work, Active, Blocked and Overdue
   and verify the selected focus matches the real filter. Combined
   Search/Project/assignee/due filters should not falsely highlight
   a simple preset.
3. **Light and Dark:** inactive/readable text, active foreground on
   accent background, hover and keyboard Tab focus clearly visible;
   no theme-specific hardcoded colors.
4. **Navigation:** moving Table -> List -> Board -> Calendar retains
   the expected explicit filters and the Calendar month; no duplicated
   filters, changed query semantics or broken search.
5. **Other views unchanged:** Table bulk/status actions, List disclosure,
   Board drag/reorder, Calendar modal and Time remain stable.

## Governance

Source changes are limited to the focus predicate and Work-only
`work-fast-paths.css`, plus tests and this audit. No merge, release,
CI/Actions or production deployment is implied. Await full operator
regression/build results, WordPress acceptance and **explicit merge GO**.
Then close CV-G-002 in Overseer and proceed independently to CV-G-003
toolbar/Search layout.

# Core Blueprint Work UX-02B | Native Projects List Empty States

**Date:** 2026-10-28
**Status:** SOURCE CANDIDATE; full canonical PHP and Node Level 1, pinned Base Level 2, Level 3 and browser field acceptance OPEN.
**Repository:** `Core-Blueprint/wp-core-blueprint-work`
**Branch:** `feature/work-ux-02b-projects-list-empty-states-20261028`
**Parent:** UX-02A `feature/work-ux-02a-projects-native-header-20261028@0d1162844fc24c760639a56cb7414fa90720a375`; Technical Golden and owner field stability acknowledged.
**Parent ZIP SHA-256:** `8d22fa380db5c005dd28d2fbcfce33e06f8d72db45a63881021a34321e0a4a6b`
**Authority:** GitHub-only Overseer `extensions/work/ux-golden-v1-2026-10-28.md`.

## Intent

UX-02A made the **native WordPress Projects page** visually consistent with Work. UX-02B must communicate accurately why the native list has no rows, without replacing the WordPress CPT list, its filtering/search engine, Post Types, status semantics, bulk actions, sorting, pagination, Project editor, Project Workspace or Data Exchange.

| Native state | Title | Explanation | Additional action |
| --- | --- | --- | --- |
| No non-trash, non-autodraft Projects and no active view/search/filter | No projects yet. | Create an Internal Project for your own work, or a Customer Project for client work. | None: the header's existing **Add Project** stays the only primary action |
| Search, context, author, date, status filter or non-first page yields no rows, including when the complete site has zero Projects | No matching Projects. | Try another search or clear your filters. | **Clear filters**, linking to the canonical unfiltered native Project list |
| Trash list yields no rows | Trash is empty. | None | **View all Projects**, linking to the unfiltered native Project list |
| Existing Project rows | N/A | N/A | No changes: the native list continues normally |

**Important:** `src/Repository/Projects.php::count()` is the existing canonical status count excluding `trash` and `auto-draft`; it is queried once **only when no filter/view is active and the route is authorized**. The actual query output remains native WordPress: the code only enhances an existing `tr.no-items td` in the authoritative WordPress list table. This avoids claiming "no projects" in a filtered-zero state even if the global count is zero. If no native no-items row exists, the code does not add or hide content.

## Implementation boundaries

- `src/Admin/Assets.php`: the existing manager-only `enqueue_project_list_action()` passes a small, localized **read-only empty display hint** to the established `cbWorkProjectsList` configuration. It treats `s`, `cb_work_context`, `post_status`, `author`, `m` and `paged > 1` as narrowing contexts; WordPress default `post_status=all` and `paged=1` are not active filters. Invalid non-scalar GET values are ignored rather than reflected. Trash takes precedence. It uses `Menu::projects_url()` for the reset route. The existing required direct `'importUrl'   => ProjectDataExchange::import_url()` expression stays inside its conditional provider branch; Project Data Exchange Golden cannot be weakened.
- `assets/projects-list.js`: when and only when WordPress emits the native no-items table row, replace **the text inside its current td** with a semantic h2, short explanatory paragraph when applicable, and one small native reset anchor for filtered/Trash. Existing table, td, row, headers and controls remain. Use only `textContent`, never `innerHTML`. The enhancement is idempotent; if JS or recognized DOM is unavailable, core WordPress's original no-items text remains.
- `assets/projects-list.css`: Work-scoped empty cell spacing and inherited Base typography/tokens; no hardcoded colors, new theme or global WordPress styling.
- Five **new** English msgids and six translations (`nl_NL/de_DE/fr_FR/es_ES/it_IT/pt_PT`) have been added to canonical POT/PO. Reuses existing **Trash is empty.** and **Clear filters**. Do not add obsolete gettext keys or bypass the canonical i18n source/catalog/reference tooling.
- **No changes:** `src/Admin/Menu.php`, `src/Admin/Projects.php`, `src/Admin/ProjectWorkspace.php`, `src/Repository/Projects.php`, `src/Admin/Operations.php` (G0 B1 blob `92a4814f24b43f96d16e3d96ce61a49ee9f21500`), WordPress CPT table, persistence, CRM, permissions or backend search/filter implementations.

## Automated evidence

1. New `tests/work-ux-02b-projects-empty-assets-runtime.php` executes actual `Assets::enqueue_project_list_action()` with deterministic WP stubs: true-zero, existing-project fallback, free text search, context, draft status, author, date, pagination, empty Trash, default "all"/page 1, non-scalar input, manager capability denial, canonical URI, no count for active filters and seven gettext catalogs.
2. New `tests/work-ux-02b-projects-empty-dom-runtime.js` executes the production script with a native-list DOM fixture: true-zero, filtered and Trash display; original row/table/header/Add/Import preserved; first-run no duplicated CTA; exactly one reset for filtered/Trash; idempotence; existing populated Project row never replaced; malformed state/config falls back to native WordPress.
3. The existing `tests/work-ux-02a-projects-assets-runtime.php` gained only stubbed dependencies for the new read-only state computation, preserving its previously accepted behavioral checks.
4. Both UX-02B tests are registered in `tools/check`; all previous UX-00/UX-01/UX-02A regressions and existing Project Data Exchange Golden are still mandatory.
5. The JS DOM tests were executed in a V8 evaluation harness with Node module facades: **UX-02A 24 assertions PASS, UX-02B 42 assertions PASS**; **canonical local Node and PHP execution remains open**. Do not present static checks or V8 facades as independently verified WordPress browser behavior.

## Required local technical Golden

```bash
cd ~/Downloads/wp-core-blueprint-work
git status --short
git fetch origin --prune
git switch --track origin/feature/work-ux-02b-projects-list-empty-states-20261028
git pull --ff-only
git rev-parse HEAD

BASE_PIN="$HOME/Downloads/wp-core-blueprint-so-ux1-base-pin"
(
  set -e
  php tests/work-ux-02b-projects-empty-assets-runtime.php
  node tests/work-ux-02b-projects-empty-dom-runtime.js
  php tests/work-ux-02a-projects-assets-runtime.php
  node tests/work-ux-02a-projects-list-runtime.js
  php tests/project-bundle-data-exchange-smoke.php
  php tests/work-admin-renderer-g0-smoke.php
  ./tools/i18n/check
  ./tools/check
  CB_TEST_BASE_SOURCE="$BASE_PIN" ./tools/check-integration
  CB_TEST_BASE_SOURCE="$BASE_PIN" ./tools/build-release
  unzip -tqq dist/core-blueprint-work.zip
  (cd dist && sha256sum -c core-blueprint-work.zip.sha256)
  sha256sum dist/core-blueprint-work.zip
  git rev-parse HEAD
  git status --short
)
```

**STOP and repair the exact failed source/test/i18n invariant before rerunning the complete gate.** No relaxation of old Golden tests, no merge and no customer plugin publication on a failed gate. Store observed Level 1/2/3 results and the exact ZIP checksum after operator execution.

## Browser field acceptance

- On a **disposable blank Work installation**: Work → Projects displays `No projects yet.` inside WordPress's native empty row, with appropriate explanation and one Add Project control in the header. **No dummy user data created**.
- Existing Work sandbox: check Projects list with real records remains unchanged, including Context filter, search, pagination, native row actions, bulk edit and Import only when available.
- Search a deliberately unmatched string without changing data: the native empty row displays `No matching Projects.` and **Clear filters** returns to all Projects. Verify original search term is not reflected through unsafe HTML.
- Context/status filtered views, empty Trash where available: accurate state and return to unfiltered Projects.
- Verify no duplicate Project/import buttons, no interaction with Project Workspace or editor; Base Light/Dark, desktop/782px/480px and keyboard focus; native list remains usable when JS is disabled.
- Do not erase or mutate Website Maintenance projects to manufacture an empty state. Check true zero on a disposable empty installation only.

**UX-02B source candidate is not Technical Golden or field accepted yet. No merge to Work main or release without separate explicit GO.**

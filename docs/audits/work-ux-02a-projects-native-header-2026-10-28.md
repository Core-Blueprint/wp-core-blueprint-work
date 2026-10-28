# Core Blueprint Work UX-02A | Native Projects List Header

**Date:** 2026-10-28
**Status:** SOURCE CANDIDATE; local runtime, Level 1/2/3, browser acceptance OPEN.
**Repository:** `Core-Blueprint/wp-core-blueprint-work`
**Branch:** `feature/work-ux-02a-projects-native-header-20261028`
**Parent:** accepted UX-01B `feature/work-ux-01b-overview-queue-empty-states-20261028@a929620d6d19933e520b8134e1c7fca1e8a620c1`
**Parent ZIP SHA-256:** `07f2f60538e44128274b7eb48f8dc7927ddaedd7a4c5ae8455c29ea682969b0d`
**Roadmap authority:** Overseer `extensions/work/ux-golden-v1-2026-10-28.md`

## Why this increment

The owner accepted UX-01B as stable and wants Work to feel like a modern productivity app without breaking native WordPress contracts. The Projects list is still a default native WordPress heading with an Add Project button and a conditionally injected Import project link adjacent to the title. UX-02A brings a shared heading/action hierarchy without replacing the native Project CPT list, editor, toolbar, sorting, search, filters or bulk actions.

The Project list has two separately owned surfaces:
- **Project list** `edit.php?post_type=cb_work_project`: native WordPress authoritative list and Add/Edit controls.
- **Project workspace** `admin.php?page=core-blueprint-work-project&project_id=...`: read-only Work context and action-first overview. This remains unchanged. Project editing uses WordPress's native editor.

## UX-02A implementation

- Existing `assets/projects-list.js` now **progressively groups** the *same* WordPress `h1.wp-heading-inline` node and existing `a.page-title-action` nodes in a semantic Work-only header. It does not replace, clone, hide or reimplement their destinations/events. The native list table, the separator, filters and all standard WP functionality stay untouched. Script is idempotent and fails safe when a recognized WordPress heading separator is unavailable. No jQuery or UI transport.
- Native Project **Add Project** is the first action. **Import project** remains the optional second action only when `ProjectDataExchange::available()` is true. **Critically:** the header enhancement still enqueues when Import is unavailable. Its authorization and route are unchanged.
- New description: “Organize internal and customer projects. Open a workspace to see its Work Items.”, localized through `wp_localize_script`. This description explains Work Project context without implying a mandatory customer integration or billing.
- New `assets/projects-list.css` applies scoped `cb-work-workspace-screen` layout only to the new Work wrapper: quiet heading typography consistent with UX-00B, right-aligned actions at desktop width, stacked actions below 480px, Base spacing tokens. It does **not** theme global WP controls, color tokens, post list tables or sidebars. If JavaScript is disabled, the native page remains usable.
- New POT key and translations added to all six supported `nl_NL/de_DE/fr_FR/es_ES/it_IT/pt_PT` catalogs. Canonical `./tools/i18n/check` remains the authoritative source/catal​og drift gate; no stale messages or hand-edits to i18n tooling/reference.
- The existing Project CPT, screen routing, `src/Admin/Projects.php`, `src/Admin/ProjectWorkspace.php`, `src/Admin/Menu.php` and G0-frozen `src/Admin/Operations.php` are untouched. No schema, Base, permissions or domain changes.

## Regression coverage

- `tests/work-ux-02a-projects-assets-runtime.php` executes the real `Assets::enqueue_project_list_action()` against deterministic WP stubs: manager on native Project list with or without Data Exchange; no assets for unrelated WP screens, other Work CPTs or tracker-only actors; exact canonical CSS/JS routes, optional provider behavior and localized copy.
- `tests/work-ux-02a-projects-list-runtime.js` executes real `assets/projects-list.js` with a deterministic DOM fixture: original title/Add link moved, optional Import injected once, native list table and separator preserved; rerun idempotence; missing provider and missing WordPress separator graceful fallback; correct DOMContentLoaded deferral; no injection on unrelated screens.
- Both are registered in canonical `tools/check`. Existing UX-00A/00B/00C, UX-01A/01B, full G0 renderer, six locales and all WordPress integration suites remain mandatory.

## Exact field matrix

1. Install a newly built **exact hash verified ZIP** in the WordPress sandbox. Manager opens Work → Projects: heading left, one short description underneath, Add Project then optional Import project on the right, same style rhythm as Overview, Work Items and Time.
2. Click **Add Project** to confirm the native WordPress Project editor opens. Do not save a new Project merely for acceptance if the site has real client data.
3. Click **Import project** only to confirm its existing navigation, if Data Exchange is available. Do not submit a real import.
4. Confirm search, Bulk actions, status links, native pagination, **Context** filter, row operations (Edit / Open workspace), active sidebar highlight and accessible WP keyboard focus remain intact.
5. Disable or block JavaScript in a disposable browser session: native title, Add Project and Project table remain usable, without a blank header. (Import link has always been a JavaScript progressive addition.)
6. Validate on desktop, 782px, 480px, Base Light/Dark; no horizontal overflow, no duplicated buttons, readable description and reachable controls.
7. Project editor/workspace remains unchanged. No changes to the live Website Maintenance data or any customer Project to manufacture an empty state.

**Out of scope:** UX-02B project-list first-run/filtered-empty states, UX-02C Project workspace polish/editor progressive disclosure, UX-03 Services, UX-04–06 Recurring Work. No new Project data model, filtering algorithm, import handler, Base design-system override or custom WP list replacement.

## UX-02A Data Exchange Golden compatibility correction

**2026-10-28 — SOURCE CORRECTED / Technical Golden OPEN.** During the operator's first `./tools/check` run, the new Projects Assets fixture **PASS (24 assertions)**, JS DOM runtime **PASS (24 assertions)**, Project workspace smoke, G0/B1 exact renderer and canonical six-locales i18n all **PASS**. Further existing Work regressions passed through the VAT checks. The existing `tests/project-bundle-data-exchange-smoke.php` halted the Level 1 run:

`Project bundle Data Exchange smoke failed: Project import is a page-level action beside Add Project rather than a filter control`.

**Root cause:** UX-02A had replaced the existing direct `'importUrl'   => ProjectDataExchange::import_url()` value in `src/Admin/Assets.php` with an inline ternary. This changed the source expression protected by the existing Golden contract even though the DOM runtime still passed. The tested contract is semantically correct and **was not modified**.

**Fix:** The optional import fields are now assembled as a conditional array. When Data Exchange is available, the source retains the original canonical `'importUrl'   => ProjectDataExchange::import_url()` action mapping, and the existing `'importLabel'`. When unavailable, both values are empty, ensuring no Import button and **zero calls** to the import URL provider. The optional array is spread into the same localized script payload. The enhanced native Projects header, original Add Project button, list table, fallback and all scripts remain unchanged.

**Corrected source:** `feature/work-ux-02a-projects-native-header-20261028@b5fc7ab82e89932758b7369870269621246312cd`. Delta from original candidate `c8ed1ec1b8b16723d090b09c54d1337aeacf03f8` is `src/Admin/Assets.php` **only** prior to this documentation update. Nine scoped GitHub source checks confirm contract text, conditional URL invocation, and unchanged previous/new tests, JS and other files. **These are static checks, not executed PHP tests.**

**Required immediate retest:** `php tests/project-bundle-data-exchange-smoke.php`; `php tests/work-ux-02a-projects-assets-runtime.php`; `node tests/work-ux-02a-projects-list-runtime.js`; `./tools/check`; pinned Base L2; `./tools/build-release`; SHA/ZIP verification. Preserve fail-fast behavior and do not mark Golden until actual full rerun passes.

---

## Local Golden validation

```bash
cd ~/Downloads/wp-core-blueprint-work
git status --short
git fetch origin --prune
git switch --track origin/feature/work-ux-02a-projects-native-header-20261028
git pull --ff-only
git rev-parse HEAD

BASE_PIN="$HOME/Downloads/wp-core-blueprint-so-ux1-base-pin"
(
  set -e
  php tests/work-ux-02a-projects-assets-runtime.php
  node tests/work-ux-02a-projects-list-runtime.js
  php tests/project-workspace-smoke.php
  php tests/work-ux-00c-experience-runtime.php
  php tests/work-admin-renderer-g0-smoke.php
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

Actual operator logs and ZIP SHA are required before Technical Golden. Browser acceptance is a separate owner decision. No merge, release, customer publication or production deployment without explicit authorization.

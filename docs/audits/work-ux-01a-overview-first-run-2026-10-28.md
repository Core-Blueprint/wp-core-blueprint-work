# Core Blueprint Work UX-01A | Overview First Run

**Date:** 2026-10-28
**Status:** SOURCE CANDIDATE; Level 1 / Level 2 / Level 3 and field acceptance OPEN.
**Branch:** `feature/work-ux-01a-overview-first-run-20261028`
**Base:** `feature/work-ux-00c-experience-regression-20261028@2c0c38d668d7b30306e6a087b37a7dd7cbab2ca6`
**Baseline ZIP SHA-256:** `87312f4c5f1225e1cb99456fc3062b21a4375b5c3b3382d6e22eced993614d0a` (UX-00C same production archive as accepted UX-00B).
**Authority:** Overseer `extensions/work/ux-golden-v1-2026-10-28.md`.

## Product intent

An entirely new Work user should understand how to start immediately, without first configuring a Work Service, Work Type, recurring rule, customer provider or project. An experienced user must not be interrupted by beginner guidance.

UX-01A is one scoped increment of UX-01. Later UX-01 work may refine zero/filtered-empty queues, subtle indicators and personalized signals, but should not disrupt Today and Needs attention.

## Exact implementation

- The existing `Overview::render()` still enforces `Capabilities::MANAGE` *before* any queries.
- Read-only state `$first_run = 0 === WorkItems::count()` uses the existing canonical WP Work Item count; it is never stored as new onboarding metadata. Project presence is intentionally irrelevant: projects are optional for first-use Work Items. No fake sample tasks are created, and no CMS/schema changes occur.
- First-run only: show one accessible `Getting started` section with `No Work Items yet.` and two clear native links: **Add Work Item** (canonical `Menu::new_work_item_url()`) and **Add Project** (WordPress native `post-new.php?post_type=cb_work_project`). Avoid redundant top-right actions while first-run panel is visible; normal header's **Add Work Item / Open Time** remains for established Work users.
- Text strings already exist in the canonical POT and all six locales; no new i18n keys or translation drift.
- Work-scoped `assets/work-overview.css` uses Base semantic tokens and mobile wrapping. No independent theme, global styling, image or component framework.
- The existing six focus cards, bounded DailyOverview read model, timer indicator, four queue sections, Ready-to-bill panel, and especially **Today / Needs attention two-column equal-height grid** are not removed or reordered. Internal Work remains supported and is not presented as necessarily customer/commercial.
- No change to `src/Admin/Operations.php` or its immutable Table/List/Board G0 source-hash contract. No changes to Work permissions, recurrence, Service Operations, Time, customer provider, billing or Base.

## QA and acceptance

New runtime `tests/work-ux-01a-overview-first-run-runtime.php`, included in `tools/check`, runs actual `Overview::render()` with deterministic WordPress function and read-model stubs. It asserts:

1. Blank workspace shows exactly one accessible first-use section with canonical Work Item and Project links.
2. Project-only workspace without Work Items still shows first-use actions.
3. Existing Work Items suppress the beginner section even if no tasks are due today.
4. Existing focus, grid, Ready-to-bill sections and paired Today/Needs attention remain.
5. Unauthorized manager capability fails before invoking Work Item counting.
6. All first-run strings exist in the shipped POT; First Run CSS is responsive, Base-token based and paired desktop grid stays two columns.

### Field checks

1. In a **disposable empty Work sandbox**, open Overview and check one concise first-use banner with **Add Work Item** and **Add Project**, correct links and no redundant primary header action. Do not create or delete sample data in an environment containing the existing Website Maintenance registration.
2. In an **existing Work sandbox**, open Overview: no first-use banner; normal Add Work Item/Open Time actions; Today and Needs attention remain paired equal height.
3. Open Project-only state if available in a disposable test account: no Work Items still shows getting-started. Do not modify a production client/project to manufacture this state.
4. Check desktop and <782px mobile, Base Light/Dark and keyboard tab/focus. The new card must not obscure operational alerts or timer state.
5. Verify the canonical six locales and that actions do not lead to missing WordPress screens. Never infer full WCAG conformance from these source checks alone.

### Reproducible operator gate

```bash
cd ~/Downloads/wp-core-blueprint-work
git status --short
git fetch origin --prune
git switch --track origin/feature/work-ux-01a-overview-first-run-20261028
git pull --ff-only
git rev-parse HEAD

BASE_PIN="$HOME/Downloads/wp-core-blueprint-so-ux1-base-pin"
(
  set -e
  php tests/work-ux-01a-overview-first-run-runtime.php
  php tests/work-daily-ux-smoke.php
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

Record actual Level 1, Level 2, Level 3, ZIP integrity and checksum after execution. Owner confirmation is required before labeling field stable or continuing to UX-01B. Do not merge or publish without separate explicit GO.

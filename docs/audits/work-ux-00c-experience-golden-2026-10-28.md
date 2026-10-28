# Core Blueprint Work UX-00C | Experience Regression Foundation

**Date:** 2026-10-28
**Scope:** QA and acceptance foundation after UX-00A navigation and UX-00B shared heading rhythm.
**Status:** Source candidate, **not** Technical Golden / not field accepted / no merge or deployment.
**Branch:** `feature/work-ux-00c-experience-regression-20261028`
**Accepted parent:** `feature/work-ux-00b-experience-foundation-20261028@34d1372b0f7c9dd124df6e5095bb47559377f610`
**Parent development ZIP:** `87312f4c5f1225e1cb99456fc3062b21a4375b5c3b3382d6e22eced993614d0a` (SHA-256).
**Product direction authority:** Overseer `extensions/work/ux-golden-v1-2026-10-28.md`.

## Purpose

UX-00A and UX-00B are technical- and owner-field accepted for continuing. UX-00C consolidates a **repeatable cross-screen experience gate** before work shifts to UX-01 Overview and first-run. It is **not** a third redesign, a new app shell or a new shared UI framework.

Keep the following existing decisions:
- Eight manager links in workflow order: Overview, Projects, Work Items, Time, Service Operations, Recurring Work, Services, Work Types.
- Tracker-only landing is Time; no manager links. Non-authorized users get no Work menu.
- Native WordPress CPT screens remain canonical for Project, Service and Work Item editors. Contextual Project Workspace and Import routes remain registered but not visible.
- Base owns theme, controls, typography primitives, WordPress admin canvas. Work owns only its scoped composition. Do not broaden Work CSS to unrelated WP pages.
- Default **display order** of Work Item views is Board → List → Table → Calendar; the default **selected** view remains Table. Explicit URL and stored personal view order/default take precedence where intended.
- Overview Today and Needs attention remain paired at equal height; do not refactor those cards in this gate.
- Preserve the exact G0 Table/List/Board renderer reconstruction. Do not change `src/Admin/Operations.php` or relax `tests/work-admin-renderer-g0-smoke.php`.

## Automated UX-00C evidence

New: `tests/work-ux-00c-experience-runtime.php`.

This **executes** production `Menu` and `Workspace` methods using deterministic, minimal WordPress stubs and no database. It asserts:
1. Manager gets the correct top-level route, eight visible labels, capabilities, positions and two contextual registrations.
2. Project Workspace and Import remain registered but hidden from the manager menu.
3. Tracker-only user receives Time as top-level plus its registered/hidden alias; Time URL uses correct route; manager Time URL remains subordinate.
4. User without manager or tracker permission gets no Work menu.
5. Runtime `screen_context`, `parent_file`, `submenu_file` and Work-scoped `admin_body_class` for Overview, Work Items, Time, Service Operations, Recurring Work, Work Types, Project Workspace/Import, native Project/Service/Work Item CPTs, tracker-only Time and unrelated WordPress posts.
6. Base's public Admin Theme registration accepts Work CPT screens but not unrelated post screens.
7. Shared `assets/work-workspace.css` selectors stay Work-scoped, with desktop, tablet and small mobile breakpoints.

The test is registered in `tools/check` beside existing UX-00A/00B checks. It **does not claim** to verify visual correctness, actual server permissions, color contrast, screen-reader announcements or real cross-browser interactions.

The separately existing Level 1 regressions remain mandatory: G0 immutable view sources, six locale catalogs, Work Item View Preferences, cross-view state, toolbar, Base public theme seam, Recurring Work, Time, Service Operations and security.

## Manual field acceptance matrix

Use only the **new exact build output** from this branch on a disposable WordPress sandbox. Do not modify the existing Website Maintenance operational record.

| Persona / context | Test | Pass criterion |
| --- | --- | --- |
| Manager | Open every Work menu destination | Expected order, correct highlighted entry, no broken route |
| Manager | Open native Project and Service lists, create/editor screens (view only if existing) | WordPress-native context retained; no duplicate Work shell; expected highlight |
| Manager | Open Project Workspace/Import from existing supported links | Works; not duplicated in visible sidebar |
| Tracker-only | Open Work | Lands at Time; no managerial tabs or actions visible |
| Non-Work user | Open wp-admin unrelated screen | No Work menu or Work body class |
| Work Items | View control; Reset to defaults | Board/List/Table/Calendar display order; selected Table remains when no personal preference |
| Work Items | Keep saved custom view order/default | Existing personal preferences remain intact, not silently rewritten |
| Work Items | Switch views | Board, List, Table, Calendar all load; view and filter semantics preserved |
| Work Items | Quick Add / row actions | Existing actions, permissions, no unexpected reload |
| Overview | Today / Needs attention | Side by side equal-height panels on desktop |
| Recurring Work | Rule list / open editor / back | Header aligned, no nested forms or accidental activation |
| Service Operations | Empty/setup/ready/operational states as available | Contextual CTA, no false invoice requirement; no live maintenance mutations |
| Time | Timer, Manual, Entries (view only) | Consistent heading; existing timer never reset by navigation |
| Work Types | Read list | Title/description consistent; no surprise edit or status mutation |
| Light/Dark | Toggle via Base (where safe) | No independent Work theme and no unreadable surface |
| Desktop/mobile | Wide, ~782px and ~480px | Headers wrap, actions reachable, no horizontal page overflow |
| Keyboard | Tab, Shift+Tab, Enter, Escape on available controls | Focus visible, controls reachable without a mouse |
| i18n | Existing six localizations | No new untranslated Work strings in this patch |

**Out of scope:** new modal/search/command palette, Project/Service redesign (UX-02/UX-03), Recurring Work guided editor (UX-04–06), Service Operations guided wizard (UX-07), Time layout overhaul (UX-08), Work Items view refinements (UX-10). Accessibility automation beyond this focused fixture belongs to final UX-11 acceptance; no claim of WCAG conformance from source tests alone.

## Local gate

```bash
cd ~/Downloads/wp-core-blueprint-work
git status --short
git fetch origin --prune
git switch --track origin/feature/work-ux-00c-experience-regression-20261028
git pull --ff-only
git rev-parse HEAD

BASE_PIN="$HOME/Downloads/wp-core-blueprint-so-ux1-base-pin"
(
  set -e
  php tests/work-ux-00c-experience-runtime.php
  php tests/work-ux-00a-navigation-smoke.php
  php tests/work-ux-00b-experience-smoke.php
  php tests/work-admin-renderer-g0-smoke.php
  php tests/work-item-view-preferences-smoke.php
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

Document the real test counts and generated ZIP checksum after operator execution. **Field acceptance and an explicit separate merge GO** are required before claiming an accepted integrated UX foundation. Overseer documentation branches are GitHub-only for the owner.

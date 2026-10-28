# Core Blueprint Work UX-01B | Contextual Overview Empty States

**Date:** 2026-10-28  
**Status:** Source candidate; executed PHP, full Level 1, pinned Base Level 2, Level 3 and sandbox browser acceptance OPEN.  
**Repository:** `Core-Blueprint/wp-core-blueprint-work`  
**Branch:** `feature/work-ux-01b-overview-queue-empty-states-20261028`  
**Parent:** `feature/work-ux-01a-overview-first-run-20261028@4a407264bae669b8eeecfaa2bff91d8f6257eda2`  
**Parent release ZIP SHA-256:** `4d00f42d36d6da439a62bb5355b034bb025590edc4fe7074e0de2b2ad76db116`  
**Roadmap authority:** Overseer `extensions/work/ux-golden-v1-2026-10-28.md`.

## Product decision

Maintain an actionable yet quiet manager Overview. UX-01A already provides a beginner-first banner when there are zero Work Items. UX-01B makes empty operational queues truthful in all states:

| Panel | Empty message | Secondary action if Work already contains Work Items |
| --- | --- | --- |
| Today | No work scheduled or due today. | Open Work Items |
| Needs attention | No overdue or blocked work. | None: zero is healthy |
| In progress | No work in progress. | Open Work Items |
| Coming up | No work due in the next seven days. | None: no false urgency |
| Ready to bill | Existing “Nothing is waiting for commercial handoff.” | Unchanged: external invoicing is optional |

- Each message is about **its own bounded queue**, not the global Work Item count. An empty Today does not mean the Project or all Work is empty.
- No additional action is shown for fully blank first-run; the accepted UX-01A Getting started panel already provides Add Work Item and optional Add Project. The existing operational header actions remain on established Work pages.
- Both Work Items links resolve through the canonical `Menu::WORK_ITEMS_SLUG` URL, without forcing a different default view, status filter or potentially confusing date filter. Saved personal view/order preferences remain authoritative.
- Existing populated queue rows, actual Work Item title/edit routes, duplicate attention merge, timer information, six KPI counters, commercial snapshot and bounded `DailyOverview::snapshot` remain untouched.
- The **Today and Needs attention** cards remain the first row of the same two-column CSS grid and therefore maintain equal height, including when one is empty.
- Work owns only scoped empty-state composition via `assets/work-overview.css`, using Base color tokens and native anchors/focus. No new modal, app framework, image, global admin style, JS runtime or schema.

## Internationalization

Four context-specific English msgids were added to the POT and translated in all six supported locales: `nl_NL`, `de_DE`, `fr_FR`, `es_ES`, `it_IT`, `pt_PT`. Existing PO entries and i18n reference tooling are unchanged. The normal **canonical i18n Level 1 gate is authoritative**: the product's `./tools/i18n/check` regenerates a temporary POT from source and rejects any missing/stale source keys, incomplete translations or gettext errors. This must actually run before Golden acceptance. Do not bypass the gate or edit the canonical tooling reference.

## Runtime regression

New `tests/work-ux-01b-overview-empty-runtime.php` executes the real `Overview::render()` with deterministic WordPress read-model stubs and **no database writes**. It asserts:

1. All four empty panels render distinct context-accurate strings in expected order, and Ready to bill retains its distinct current behavior.
2. New empty Work: one accepted first-run CTA panel, zero additional empty-queue navigation actions.
3. Established Work but no due/active tasks: exactly two Open Work Items links, in Today and In progress, never Needs attention or Coming up.
4. Populated queues do not show empty messages/links and preserve Work Item display and deduplication.
5. All new msgids exist in POT and have non-empty translations in each of the six POs. PHP, POT and gettext semantics are ultimately verified by Level 1.
6. CSS is limited to Work Overview, uses canonical Base accent/focus tokens and preserves paired 2-column desktop grid.

The fixture is wired into `tools/check`, beside UX-00A/00B/00C/01A. The immutable `src/Admin/Operations.php` B1/G0 reconstruction contract must continue to PASS. No changes to Base or backend access control.

## Operator Golden validation

```bash
cd ~/Downloads/wp-core-blueprint-work
git status --short
git fetch origin --prune
git switch --track origin/feature/work-ux-01b-overview-queue-empty-states-20261028
git pull --ff-only
git rev-parse HEAD

BASE_PIN="$HOME/Downloads/wp-core-blueprint-so-ux1-base-pin"
(
  set -e
  php tests/work-ux-01b-overview-empty-runtime.php
  php tests/work-ux-01a-overview-first-run-runtime.php
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

**Gate:** do not claim Technical Golden until this actual procedure reports PASS, WordPress 7.0/MariaDB integration and the new build ZIP checksum are captured. If the test or canonical i18n check fails, repair source/tests/translations at this branch, rerun all levels, do not relax Golden checks.

## Field acceptance

- Install the **new exact verified ZIP** on the sandbox. Validate empty Today/Needs attention/In progress/Coming up and the two restrained links.
- On a populated workday, validate actual list links and no duplicate or inaccurate empty messages.
- Confirm Today and Needs attention retain side-by-side equal-height desktop cards and stack normally below the responsive breakpoint.
- Verify keyboard link focus, Base Light/Dark, Dutch and English strings; ideally check one additional locale. No visual overflow.
- Test blank first-run only in a **disposable empty Work site**, never by modifying the existing Website Maintenance customer/internal operational record.
- Existing Service Operations, recurring scheduler, Time, projects, contracts, invoices and Work Items are unchanged.

**No merge to Work main, no release/publication/production and no user-data mutation without separate explicit approval.** UX-01B field acceptance is separate from technical acceptance.

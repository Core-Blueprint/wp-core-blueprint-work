# UWE-03C.2 — Canonical View Mounting (Read-only Field Preview)

Date: 2026-10-29
Work baseline: 52872d933088dd9bc1c3a44685e953e078b6289f
Prior UWE-03C.1 technical result: Level 2 PASS (57 tests / 1567 assertions), Level 3 ZIP SHA-256 a5e5b6c38311a9c3a0d4bd76bf0d35290945adf375f6201069f06dbaaabf9a15
Branch: feature/work-uwe-03c2-canonical-view-mount-20261029
Status: SOURCE CANDIDATE, all L1/L2/L3 and WordPress FIELD acceptance OPEN, main merge HOLD.

## Purpose

Provide the existing Work Items Table, List, Board and Calendar views inside the
Unified Workspace using the same server-side renderer delivered by UWE-03C.1.
No second data or renderer implementation is allowed.

## Architectural approach

- Work Items tab receives a four-view selector and a clearly accessible
  existing native Work Items link. Other tabs (Projects, Services) keep their
  accepted UWE-03B.2 REST read and search behavior unchanged.
- An ES module requests authenticated GET-only admin-ajax bridge HTML for
  the selected view. It passes canonical filters and maps the existing
  uwe_page query parameter to the canonical paged parameter.
- The canonical HTML is DOM-parsed, checked for unexpected active content,
  and imported into the Work Items panel. Never reimplement legacy Table,
  List, Board, or Calendar card markup from REST.
- The selected view is recorded as view=table|list|kanban|calendar in the
  current Workspace URL. WordPress's reserved page remains unchanged. A view
  change updates history via pushState and does not reload the full page.
- Historical UWE-03A SSR fallback cards are preserved and remain visible if
  the authenticated bridge cannot load. No-JS links go to the existing native
  Work Items screen, where all interactions remain available.
- Other panels continue to use the UWE-03B.1 REST transport. No changes
  to the read controller or Work Item writer, mutation contract, schemas,
  WordPress post editors, menus, action audit, or Base code.

## Existing Golden action lifecycle: explicit hold

The classic Work Items scripts initialize in DOMContentLoaded and depend
on native page-bound UI. Do NOT pretend those scripts can operate safely on
DOM inserted after the initial page load. The canonical views mounted by this
patch are intentionally visual/read-only, not editing surfaces.

- The native screen keeps all established bulk actions, quick edit, drag
  and drop, view preferences, calendar modal, and mutations intact.
- This preview loads only canonical CSS and the view mount script. It does
  not auto-run the existing mutation scripts on imported HTML.
- Form and preference controls in the mounted preview are visually hidden
  until UWE-03C.3 reconciles their life cycle.
- Existing native Work Items links still allow users to open the complete
  operational page for editing; the fullscreen editor remains future UWE-04.
- Full interactive parity is a strict requirement before menu retirement.

## Tests and acceptance

Source smoke: tests/work-uwe-03c2-source-smoke.php
Runtime Node: tests/work-uwe-03c2-runtime.mjs
Both wired into tools/check. Revalidate ./tools/i18n/check,
./tools/check, ./tools/check-integration, ./tools/build-release,
ZIP/SHA256, and G0/B1 renderer hash.

WordPress FIELD checks:
1. New UWE Work Items header shows Table, List, Board, Calendar and native
   "Open Work Items" fallback link.
2. All four canonical views display correctly without page reload while
   their original native appearances remain unchanged.
3. Verify each view's query parameter and browser Back/Forward; WordPress's
   page=core-blueprint-work-workspace must remain.
4. Verify Projects/Services REST search and pagination still work.
5. Test changing sections during a slow request; stale responses must not
   overwrite another section.
6. Test a bridge request failure. SSR fallback remains accessible, error
   state appears, and full Work Items page remains usable.
7. Verify no mutation forms are accidentally active in the preview, no
   uninitialized modals, and no duplicated navigation or console errors.
8. Test dark mode, keyboard navigation, mobile widths.
9. Verify native Table/List/Board/Calendar retains all established actions
   and no existing WordPress editor/workflows are modified.

Next: UWE-03C.3 controlled behavior reinitialization, secure mutations,
filter and pagination interception, modal lifecycle and full Golden parity.
No merge, no deployment, no data deletion.

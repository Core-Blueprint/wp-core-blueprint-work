# UWE-03B.2 — Reactive Work Workspace

Date: 2026-10-28
Branch: feature/work-uwe-03b2-reactive-workspace-20261028
Source baseline: 4ecc9adb3c73b068ec6ff93f1663c66619692dab
Baseline Level 2: PASS, 55 tests / 1530 assertions
Baseline Level 3 ZIP SHA-256: 926ec860d16e43e3b7a419b024032421f19e0d635319eb44499deb5b3c13b194
Status: SOURCE CANDIDATE, Level 1/2/3 and FIELD OPEN. Main merge HOLD.

## Scope
- Keep the UWE-03A WordPress manager-only route and server-rendered preview as progressive fallback.
- Bootstrap the UWE-03B.1 GET-only REST API from three WordPress-generated endpoint URLs. This supports both permalink REST and ?rest_route= fallback.
- Authenticate REST reads with wp_create_nonce('wp_rest') in the X-WP-Nonce request header, same-origin GET credentials. No nonce in URLs and no write routes.
- Vanilla ES modules (app, router, state, data-client), no framework and no Base-source changes.
- Per-section, shareable URL state: search, sort, status (Work Items), page, per_page. Preserve unrelated wp-admin args.
- Search with bounded debounce, filter and sort change, direct deep-links, Back/Forward, remembered state per section.
- Abort obsolete requests and discard stale responses, including overlapping same-section fetches.
- Render API titles only via textContent, no HTML injection.
- Accessible search controls, ARIA busy/error messages, loading/empty/error states, previous/next with page count.
- During API failure, leave the last server/client-rendered list readable; show an error and retry button.
- Reduced-motion-aware spinner; CSS remains limited to .cb-work-unified.

## Explicit non-goals
- No new REST mutations, no CPT editor changes, no schema or events.
- No Table/List/Board/Calendar parity in this patch (UWE-03C).
- No fullscreen editors (UWE-04+) or legacy menu retirement.
- No new i18n strings; UI uses existing translated Work POT entries.
- No Base changes, merge, deployment, production data deletion or WordPress data writes.

## Hard boundaries
- The existing REST-only read controller remains unchanged (UWE-03B.1).
- Operations.php G0/B1 Git blob 92a4814f24b43f96d16e3d96ce61a49ee9f21500 remains unchanged.
- All existing eight visible Work manager menu entries and tracker-only Time landing remain unchanged.
- All files and their import/enqueue paths must exist. Preserve progressive fallback if module loading fails.

## Validation
1. php -l src/Admin/UnifiedWorkspace.php; php -l src/Admin/Assets.php
2. node --check assets/js/workspace/{app,router,state,data-client}.js
3. php tests/work-uwe-03a-shell-smoke.php; php tests/work-uwe-03b2-source-smoke.php
4. node --experimental-default-type=module tests/work-uwe-03b2-runtime.mjs
5. ./tools/i18n/check; ./tools/check; ./tools/check-integration; ./tools/build-release
6. unzip -tqq dist/core-blueprint-work.zip; (cd dist && sha256sum -c core-blueprint-work.zip.sha256)
7. git hash-object src/Admin/Operations.php -> 92a4814f24b43f96d16e3d96ce61a49ee9f21500

## WordPress FIELD
1. Install the rebuilt Work ZIP in the accepted WordPress sandbox, preserving existing data.
2. Open /wp-admin/admin.php?page=core-blueprint-work-workspace. The three tabs and SSR fallback must remain usable.
3. Search multiple titles in Work Items, Projects and Services. Observe results change without a full page refresh.
4. Change Work Item status and sort, navigate pages, verify Back/Forward and direct URL reload.
5. Switch sections and return; the previous section search/filter/pagination should be remembered.
6. Check empty results, error + retry, and 403/expired session behavior without leaking user-specific data in network responses.
7. Verify browser network GET endpoints /wp-json/cb-work/v1/workspace/{section}, X-WP-Nonce header, no POST request; no JS console errors.
8. Check dark/light, keyboard navigation, narrow viewport, and reduced-motion preference.
9. Verify legacy Work Items Table/List/Board/Calendar, native editors, tracker-only access unchanged.

## Next
UWE-03C — integrate the existing Work Item Table, List, Board, Calendar views and Golden interactions before the fullscreen UWE-04 editor. Hold merge/deploy pending FIELD acceptance.

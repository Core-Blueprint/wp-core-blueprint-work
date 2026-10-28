# UWE-03C.1 — Canonical Work Items View Bridge

Date: 2026-10-28
Source baseline: c21fbffb499fd76bebe19f859e3463c46c0217a9
Prior FIELD: UWE-03A stable; UWE-03B.2 UI preview approved for progression.
Previous Technical Golden: 55 tests / 1530 assertions, ZIP SHA-256 37e100ed3b51992a7810b107bcde70b563b309bfc9eedfadeceb3205fcb325fa
Branch: feature/work-uwe-03c1-canonical-view-bridge-20261028
Status: SOURCE CANDIDATE, Level 1/2/3 and FIELD OPEN. No merge or deployment.

## Purpose

Enable the future Unified Work Items panel to request the *existing* exact-source
Table, List, Board, and Calendar HTML, rather than copying Golden renderer markup.

The existing Work Items native screen stays authoritative until 03C.2/03C.3
reconcile assets and interactions. This patch intentionally does **not** replace
the cards already shown by UWE-03B.2.

## Work-owned read-only transport

Endpoint: authenticated WordPress admin-ajax GET with:
- action=cb_work_uwe_render_view
- nonce=<nonce minted by WorkItemViewBridge::NONCE_ACTION>
- view=table|list|kanban|calendar
- optional canonical filters (s, status, priority, project_id, service_id,
  work_type_id, assignee_id, billing, work_context, customer, calendar_month,
  scheduled_from/to, due_from/to, sort, paged)

Authentication:
- wp_ajax only, never wp_ajax_nopriv;
- require cb_manage_work;
- require Work runtime readiness;
- verify separate WordPress nonce;
- require GET; return 405 for all writes;
- whitelist scalar query keys, bound lengths and page number;
- reject unknown canonical views.

Rendering:
- call Operations::render_work_items() in an isolated temporary $_GET scope;
- use WorkItemViewPreferences and WorkItemViewState to resolve view metadata;
- return {success: true, data: {view, html}};
- restore $_GET and output-buffer levels in finally, including exceptions;
- preserve all existing Golden renderers, actions, markup, modal hooks,
  query contracts, WordPress editors, and native route URLs byte-for-byte.

The currently rendered Unified Workspace Work Items panel receives a dormant,
Work-only endpoint, action, and nonce via data attributes. No new JS behavior
is activated in 03C.1.

## Important limitation before integration

Existing canonical view scripts mostly initialize on DOMContentLoaded. Simply
injecting HTML from this transport would not safely activate Table bulk actions,
inline editing, View Preferences, Board reorder, Calendar modal/dragging,
filter actions or user preferences. These must be reused and lifecycle-tested in
03C.2 before switching the visible Work Items panel to the canonical renderer.

03C.3 will reconcile full feature parity, keyboard navigation, view-state routing,
modals, mutations, and fallback behavior before legacy menu retirement.

No iframe, no second renderer, no new domain mutations, no Base changes,
no data migration or test data deletion.

## Validation

Level 1: php tests/work-uwe-03c1-bridge-smoke.php, ./tools/check.
Level 2: tests/integration/WorkItemViewBridgeIntegrationTest.php in the canonical
isolated WordPress 7.0 / MariaDB test suite. Confirms native HTML equivalence,
four view variants, missing rights, invalid input and $_GET restoration.
Level 3: ./tools/build-release + ZIP integrity + SHA.
G0 renderer protected Git blob:
92a4814f24b43f96d16e3d96ce61a49ee9f21500

WordPress FIELD 03C.1: No visual change expected. Confirm existing Work Items
table/list/board/calendar and UWE-03B.2 preview remain stable; standalone bridge
is exercised in automated integration rather than exposed as public UI.

Next: UWE-03C.2 dynamic canonical view mounting with controlled JS lifecycle.
Main merge and rollout remain HOLD.

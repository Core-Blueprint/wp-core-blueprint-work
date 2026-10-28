# UWE-03B.1 — Work Workspace Read API

Date: 2026-10-28
Work baseline (UWE-03A FIELD STABLE): 7d7325746b52758da46a693fe9229b5508c4d991
Baseline ZIP SHA-256: 87d90bf3e95ecde84b952e83e72a7ba7af0d3ed31f859567fab2bbaebd7ef209
Branch: feature/work-uwe-03b1-readonly-workspace-api-20261028
Status: SOURCE CANDIDATE; Level 1/2/3 and FIELD OPEN. Main merge HOLD.

## Route and trust boundary

GET /wp-json/cb-work/v1/workspace/{work-items|projects|services}

- REST namespace belongs exclusively to private Work admin use, not D3 builder-neutral frontend API.
- WordPress authenticated REST requests require a valid WP REST nonce under cookie authentication; no anonymous/tracker access.
- Both permission callback and read callback verify cb_manage_work and Work runtime readiness.
- No POST/PUT/PATCH/DELETE handlers, no export of raw customer links, relations, descriptions, pricing, audit trails or raw post meta.
- Register on rest_api_init outside is_admin; REST requests do not run inside wp-admin.
- Controller uses canonical WorkItems::search for all Work Item filtering, ordering and count.
- Project/Service list paging loads only 1–100 WP post IDs, hydrates through existing Projects::get / Services::get readers, and projects id/title/status only.
- Managed post statuses: publish, draft, pending, private, future. Trash and auto-draft excluded.
- No schema, WordPress CPT, Base, current workspace, existing admin menu or Operations G0 renderer changes.

## Request and response

URL query options:
- page (integer >= 1, default 1);
- per_page (integer 1–100, default 25);
- search (string at most 160 chars, sanitized by REST schema);
- sort (one of workload, due, scheduled, updated, title; Work Item defaults to workload; Projects/Services default to title and honor updated);
- status (Work Item status enum, Work Items only);
- priority (Work Item priority enum, Work Items only);
- project_id (non-negative integer, Work Items only).

Example: GET /wp-json/cb-work/v1/workspace/work-items?page=2&per_page=25&status=planned&sort=due

JSON body:
{
  "section": "work-items",
  "items": [ { "id": 123, "title": "Check server", "status": "planned", "priority": "normal", "project_id": 5 } ],
  "total": 42,
  "page": 2,
  "per_page": 25,
  "pages": 2,
  "search": "",
  "sort": "due"
}

The example is schematic; actual Work Item records include post_status, service_id, work_type_id, scheduled_on, due_on and updated_at.

## Integration + regression

Level 1: php tests/work-uwe-03b1-rest-smoke.php and ./tools/check.
Level 2: tests/integration/WorkspaceReadRestIntegrationTest.php is auto-discovered by the canonical suite.
Level 3: ./tools/build-release, unzip -tqq dist/core-blueprint-work.zip, (cd dist && sha256sum -c core-blueprint-work.zip.sha256).

Critical tests:
- manager-only API access (anonymous and tracker denied);
- GET only; no record mutation;
- safe projected fields and no raw pricing, customer ID or relation data;
- bounded page size and invalid filter rejection;
- stable pagination, search and out-of-range totals for Projects/Services;
- canonical Work Item status filtering and paging;
- no effect on FIELD STABLE UWE-03A tabs or native WordPress CRUD.

## Follow-up roadmap (approved)

UWE-03B.2: client-side asynchronous data consumption, loading/empty/error states, abortable fetch, filters and routing state.
UWE-03C: migrate the existing Work Items Table, List, Board and Calendar views into the persistent Workspace with feature parity before old navigation retirement.
UWE-04: fullscreen Work Item editor in the same Workspace.

No Base changes, no legacy/editor retirement, no merge/deploy or production data mutation in 03B.1.

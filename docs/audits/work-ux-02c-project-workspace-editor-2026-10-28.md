# UX-02C — Project Workspace & Native Editor Polish

Date: 2026-10-28
Status: SOURCE CANDIDATE; Level 1/2/3 and WordPress FIELD OPEN; no main merge.
Base: fe0154e5691720c12f5988f18957892f28ece514 (UX-02B accepted).
Feature: feature/work-ux-02c-project-workspace-editor-polish-20261028

## Scope
- Workspace: one primary Add Work Item action, secondary view/edit/optional export links, native Projects breadcrumb, context badge and compact status labels; Customer fact omitted on Internal Projects.
- Native Project editor: existing context and CRM selector grouped separately from start/due dates, accessible headings and responsive Work-owned CSS.
- Native WordPress CPT, editor, ProjectMeta::save, CRM picker, nonce, capabilities, canonical summary, imports and existing Work Item links unchanged.
- Editor-only CSS enqueued through admin_enqueue_scripts on Project post screens; no Base changes or additional front-end framework.
- No new translatable messages; all reused English msgids are present in canonical POT.

## Golden boundaries
- src/Admin/Operations.php G0/B1 Git blob 92a4814f24b43f96d16e3d96ce61a49ee9f21500 untouched.
- Project Data Exchange Import page action unchanged.
- Work-owned presentation scope only; no new persistence, data migration or service operations changes.
- New runtime-oriented editor smoke test registered in tools/check; older Project Workspace, Work Context and Data Exchange tests remain mandatory.

## Mandatory local acceptance
Run targeted PHP syntax, tests/work-ux-02c-project-experience-smoke.php, tests/project-workspace-smoke.php, tests/work-context-foundation-smoke.php, tests/crm-customer-picker-smoke.php, tests/project-bundle-data-exchange-smoke.php, tests/work-admin-renderer-g0-smoke.php; then ./tools/i18n/check, ./tools/check, pinned-Base ./tools/check-integration, pinned-Base ./tools/build-release, unzip -tqq and sha256sum -c.

## FIELD
Verify new Internal Project and Customer Project, CRM selector visibility, save/reopen, existing linked customer, title/description dates, one primary workspace action, optional JSON Export only for eligible internal projects, responsive desktop/782px/480px, keyboard navigation and Base light/dark. Use existing Projects. Do not delete Website Maintenance records to test empty states.

At publication time this is *not* technical Golden, FIELD stable, merged or released. Record actual local outputs, exact SHA and ZIP checksum in GitHub-only Overseer once provided.

# Work Time Golden Audit

Date: 2026-10-08
Repository: `Core-Blueprint/wp-core-blueprint-work`
Audit branch: `audit/work-time-golden-v1`
Source baseline: `1a547f400b2d5345777be26b9ee63a7d8ba98711`
Status: **AUDIT IN PROGRESS / NOT GOLDEN / NOT MERGED**

## Scope

Review the Timer, global Timer HUD, Manual Entry, Time Entries, Quick Edit,
Bulk Edit, repositories, domain validation, authorization, browser interactions,
i18n, package determinism and production acceptance. PHP baseline is 8.4.
No release or merge is authorized by this audit.

## Findings and candidate changes

| ID | Severity | Finding | Candidate action | Status |
| --- | --- | --- | --- | --- |
| WT-G-001 | High | Full Edit converted persisted UTC timestamps into minute-only local input; note-only correction could erase seconds and silently alter duration | Render second-precision native time controls when correcting, retain Base TimePicker for new manual entries, and reuse the original UTC instant whenever local fields are unchanged | Patched, local test pending |
| WT-G-002 | High | During the wintertime fold, two UTC instants share the same local wall clock; a correction could silently switch to the other instant | Preserve the existing UTC instant for unchanged values and reject newly entered ambiguous local timestamps; the springtime gap remains rejected | Patched, local test pending |
| WT-G-003 | Medium | Both TimeEntries and Timers contained identical note sanitization and 4000-character truncation | Introduce one `Domain/TimeNote.php` policy, remove duplicate repository methods, and retain active-timer regression fixture | Patched, local test pending |
| WT-G-004 | Medium | Full Edit Work Item options used only the first 500 results; historical entries outside that range could lack the originally selected item | Add the persisted Work Item when missing from the list | Patched, local test pending |
| WT-G-005 | Low | Entry-ID request routing used broad coercion rather than a positive decimal identifier check | Strict scalar-digit parsing in one helper | Patched, local test pending |
| WT-G-006 | Review | Bulk Edit deliberately performs preflight followed by per-entry compare-and-swap; concurrent changes can yield a partial result | Preserve explicit partial-result reporting. Verify in a real multi-user database test before Golden | Open acceptance gate |
| WT-G-007 | Review | Manual create and Bulk Edit Work Item dropdowns use the canonical `WorkItems::all(500)` limit; underlying Work Item search loads a complete ID set | Measure at realistic Work Item scale before deciding whether an indexed/autocomplete picker is warranted | Deferred until profiling |
| WT-G-008 | Review | `time-workspace.css` contains shared Time workspace layout and editor styling; it is larger than a single view stylesheet but still presentation-only | Do not split it without an independent maintainability/performance benefit; no monolithic mixed PHP/JS controller should be introduced | No patch required |

## Existing positive contracts retained

- A single revision-guarded repository update path for completed Time entries.
- Capability checks and WordPress nonces at mutation boundaries.
- Per-entry governance audit events, without writing note contents to audit logs.
- No new database schema or destructive deletion action.
- Browser Quick Edit and Bulk Edit use progressive enhancement and canonical server state.
- List state remains URL-backed, validated and server-paginated.
- The existing six locale catalogs remain unchanged by this wave; no new translatable UI strings were introduced.
- Source code remains partitioned across `Admin`, `Domain`, `Repository`, `Time`, and separate JS assets. Avoid cross-domain monolithic consolidation.

## Targeted regression gates

```bash
php tests/time-golden-precision-smoke.php
php tests/time-golden-note-smoke.php
php tests/time-golden-access-smoke.php
php tests/time-foundation-smoke.php
php tests/time-workspace-smoke.php
php tests/time-quick-edit-smoke.php
php tests/time-bulk-edit-smoke.php
php tests/global-time-hud-note-runtime.php
node tests/time-bulk-visibility-runtime.js
./tools/i18n/check
./tools/check
```

## Release gate (after all tests pass)

```bash
./tools/build-release
unzip -tqq dist/core-blueprint-work.zip
sha256sum dist/core-blueprint-work.zip
(cd dist && sha256sum -c core-blueprint-work.zip.sha256)
```

Do not mark Golden until installation, Light/Dark theme, keyboard/navigation,
13-second timer correction, manual correction, autumn DST fold behavior,
real tracker/manager permissions, stale revision conflicts, and partial bulk
results are accepted against a real WordPress test environment. Also test
at least one Work Item beyond the first 500 picker results.

## Outstanding

1. Run all local PHP and JS regressions; fix any detected source or contract failure.
2. Build the deterministic ZIP; record commit SHA, ZIP SHA-256 and test logs.
3. Complete sandbox installation smoke and the operator's production functional/visual acceptance.
4. Close or explicitly classify WT-G-006/007 based on observed behavior.
5. Request explicit merge GO separately. The audit's completion is not merge authorization.

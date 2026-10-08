# Work Time Golden Audit

Date: 2026-10-08
Repository: `Core-Blueprint/wp-core-blueprint-work`
Audit branch: `audit/work-time-golden-v1`
Source baseline: `1a547f400b2d5345777be26b9ee63a7d8ba98711`
Status: **ACCEPTED IMPLEMENTATION MERGED / FINAL HARDENING OPEN**.
Merged and operator-accepted `main` HEAD on 2026-10-08:
`5dd1bdd575ae086f38ad8700db03d9ee4b0de4de`.
Operator validated `./tools/check`, 6 locales and release ZIP;
SHA-256 `d3672dbd70a00f71fd09f00743fca1e53a0c62b5846958dbbea1c345447d8a53`.
This is NOT a claim that WT-G-006, WT-G-007 or WT-G-012 have closed.

## Scope

Review the Timer, global Timer HUD, Manual Entry, Time Entries, Quick Edit,
Bulk Edit, repositories, domain validation, authorization, browser interactions,
i18n, package determinism and production acceptance. PHP baseline is 8.4.
The accepted Time/Toast implementation was merged by explicit operator GO. Further source changes or releases still require their normal approval gates.

## Findings and candidate changes

| ID | Severity | Finding | Candidate action | Status |
| --- | --- | --- | --- | --- |
| WT-G-001 | High | Full Edit converted persisted UTC timestamps into minute-only local input; note-only correction could erase seconds and silently alter duration | Render second-precision native time controls when correcting, retain Base TimePicker for new manual entries, and reuse the original UTC instant whenever local fields are unchanged | MERGED / OPERATOR ACCEPTED |
| WT-G-002 | High | During the wintertime fold, two UTC instants share the same local wall clock; a correction could silently switch to the other instant | Preserve the existing UTC instant for unchanged values and reject newly entered ambiguous local timestamps; the springtime gap remains rejected | MERGED / OPERATOR ACCEPTED |
| WT-G-003 | Medium | Both TimeEntries and Timers contained identical note sanitization and 4000-character truncation | Introduce one `Domain/TimeNote.php` policy, remove duplicate repository methods, and retain active-timer regression fixture | MERGED / OPERATOR ACCEPTED |
| WT-G-004 | Medium | Full Edit Work Item options used only the first 500 results; historical entries outside that range could lack the originally selected item | Add the persisted Work Item when missing from the list | MERGED / OPERATOR ACCEPTED |
| WT-G-005 | Low | Entry-ID request routing used broad coercion rather than a positive decimal identifier check | Strict scalar-digit parsing in one helper | MERGED / OPERATOR ACCEPTED |
| WT-G-006 | Review | Bulk Edit deliberately performs preflight followed by per-entry compare-and-swap; concurrent changes can yield a partial result | Preserve explicit partial-result reporting. Verify in a real multi-user database test before Golden | Opt-in MariaDB CAS fixture added on hardening branch; real DB execution + multi-user acceptance pending |
| WT-G-007 | Review | Manual create and Bulk Edit Work Item dropdowns use the canonical `WorkItems::all(500)` limit; underlying Work Item search loads a complete ID set | Measure at realistic Work Item scale before deciding whether an indexed/autocomplete picker is warranted | Deferred until profiling |
| WT-G-008 | Review | `time-workspace.css` contains shared Time workspace layout and editor styling; it is larger than a single view stylesheet but still presentation-only | Do not split it without an independent maintainability/performance benefit; no monolithic mixed PHP/JS controller should be introduced | No patch required |
| WT-G-009 | High | Quick Edit POST used `form.action`, which resolves to WordPress's hidden `name="action"` input instead of the form URL in affected browsers; the request goes to `/wp-admin/[object HTMLInputElement]` and returns 404 | Read `form.getAttribute('action')` explicitly. Add named-control collision runtime regression in `tools/check` | MERGED / OPERATOR ACCEPTED |
| WT-G-010 | High | Bulk Edit repeats the DOM named-control collision with `bulk.action` and a hidden `name="action"` field | Read `bulk.getAttribute('action')` and test the actual submit listener against success, partial, conflict, HTTP failure and missing action | MERGED / OPERATOR ACCEPTED |
| WT-G-011 | High (test reliability) | Initial Quick Edit runtime mock omitted `window.location.origin` and `document.createElement`, masking the real assertion with a TypeError | Repair the browser mock and exercise success, conflict, HTTP failure and missing action | MERGED / OPERATOR ACCEPTED |
| WT-G-012 | Review | Timer stop can persist a zero-second completed entry but `TimeEntries::update_completed()` rejects zero-duration changes, including note-only edits | Permit corrections preserving the exact instants of an existing zero-second TIMER row; guard this in the atomic CAS SQL; keep manual zero and positive-to-zero forbidden | Candidate patch; local tests + live PHPUnit pending |

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
node tests/time-quick-edit-submit-runtime.js
node tests/time-bulk-submit-runtime.js
php tests/time-golden-cas-fixture-smoke.php
php tests/time-golden-zero-second-smoke.php
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

The submit-listener tests exercise actual JS handlers with DOM mocks; source-only smoke tests are not sufficient evidence of correct browser POST routing.

Do not mark Golden until installation, Light/Dark theme, keyboard/navigation,
13-second timer correction, manual correction, autumn DST fold behavior,
real tracker/manager permissions, stale revision conflicts, and partial bulk
results are accepted against a real WordPress test environment. Also test
at least one Work Item beyond the first 500 picker results.

## WT-G-006: existing WordPress PHPUnit / MariaDB integration

The original standalone `wp eval-file` fixture was unsuitable for the
canonical Base test installation: Base uses `WP_CORE_DIR`,
`WP_TESTS_DIR` and the pinned WordPress PHPUnit bootstrap, not an
independently WP-CLI-configured site. That fixture was removed before
operator execution.

The replacement is a **separate, opt-in PHPUnit test**:

- `tests/phpunit-time-bootstrap.php` loads the existing Base
  `tests/bootstrap.php` and then the Work entrypoint;
- `tests/phpunit-time-cas.xml.dist` isolates this integration test;
- `tests/integration/WorkTimeCasIntegrationTest.php` runs production
  `TimeEntries::create_manual()` and `update_completed()` against
  MariaDB SQL with a temporary InnoDB table shadowing Work's Time Entries.
  WordPress PHPUnit owns rollback of posts/users/options and the test
  tears down its temporary SQL table;
- `tests/time-golden-cas-fixture-smoke.php` is a read-only contract
  included in the existing `tools/check` gate.

This tests stale revisions and an interleaved two-row partial update,
**not two independent simultaneous connections**, so two-user UI
acceptance remains open. It does not install WordPress, create an
independent `wp-config.php`, mutate an existing Work table, or call WP-CLI.

### Run in the existing Base test environment

Use the already-provisioned local `cb-base-test-db` and pinned
WordPress/PHPUnit installation. Do **not** rerun the destructive Base
WordPress installer just for this test.

```bash
cd ~/Downloads/wp-core-blueprint-work
export CB_BASE_SOURCE_DIR="$HOME/Downloads/wp-core-blueprint"
export WP_CORE_DIR=/tmp/core-blueprint-wp
export WP_TESTS_DIR=/tmp/core-blueprint-wp-tests
export CB_PLUGIN_FILE="$WP_CORE_DIR/wp-content/plugins/core-blueprint/core-blueprint.php"
export WP_DB_NAME=wordpress_test
export WP_DB_USER=root
export WP_DB_PASSWORD=root
export WP_DB_HOST=127.0.0.1:3307
php8.4 "$CB_BASE_SOURCE_DIR/vendor/bin/phpunit" \
  -c tests/phpunit-time-cas.xml.dist --do-not-cache-result
```

If the existing directories, pinned PHPUnit dependency or Base test plugin
copy are absent, stop and re-establish the standard environment using
its usual controlled workflow. This is not a reason to improvise a
standalone WordPress setup.

## WT-G-012: zero-second correction policy (candidate)

A timer may start and stop within the same UTC second. Such a completed
entry is an actual record, not an invalid manual entry. Previously, even
correcting its note failed because both the admin time-range parser and
`TimeEntries::update_completed()` required strictly positive duration.

The candidate accepts a **previously persisted timer entry with 0 seconds**
when correcting its note or Work Item **without changing either UTC instant**.
The SQL `WHERE` matches revision, timer source, zero duration, original
started_at and ended_at in one atomic conditional write. Direct requests
cannot change a different timestamp to a fresh zero-duration range.

Positive corrections can extend the end timestamp of a zero-second timer.
New zero-second manual records and converting positive time into zero remain
disallowed. No migrations or destructive record deletion are added.

Regression scope: Time Golden precision reflection cases, static SQL
contracts, and optional real-MariaDB PHPUnit tests for zero timer update,
revision conflict, changed zero timestamps, manual zero rejection and
extension to positive time. Browser acceptance is still required.

```bash
php tests/time-golden-zero-second-smoke.php
php tests/time-golden-precision-smoke.php
./tools/check
php8.4 "$CB_BASE_SOURCE_DIR/vendor/bin/phpunit" \
  -c tests/phpunit-time-cas.xml.dist --do-not-cache-result
./tools/build-release
sha256sum dist/core-blueprint-work.zip
```

Do not mark WT-G-012 closed before the operator supplies the real test
results and WordPress UI acceptance.

## Outstanding

1. Validate the safe CAS fixture gate and full canonical suite on the hardening branch.
2. Build deterministic ZIP and record HEAD / SHA-256.
3. Run the optional PHPUnit integration test in the existing local Base WordPress TEST DB.
4. Run real multi-user admin conflict acceptance (WT-G-006).
5. Decide/test zero-second correction semantics (WT-G-012), profile >500 picker (WT-G-007).
6. Return to final Work cross-view UX and product audit rounds; require GO before merge.

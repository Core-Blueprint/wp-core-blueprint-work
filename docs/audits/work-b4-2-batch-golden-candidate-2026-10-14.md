# Core Blueprint Work | B4.2 combined hardening batch, Golden candidate

Date: 2026-10-14  
Repository: `Core-Blueprint/wp-core-blueprint-work`  
Branch: `feature/work-b4-2-security-final-golden-v1`  
Branch parent: `audit/work-b4-security-static-qa-v1`, whose production runtime and WordPress package matched accepted `main` `322436dddefee833b629e3294c38e6daca4c684f`.  
State: **CODE PREPARED, OPERATOR LEVEL 1/2/3 AND SANDBOX PENDING. NOT MERGED.**

## Scope combined into one operator acceptance cycle

### B4.2a: recurrence concurrency

`src/Recurrence/Scheduler.php` now encloses the **new** Work Item creation and the exact claimed occurrence attachment inside `WorkSqlTransaction::run()`. It obtains a `SELECT id, is_active, trashed_at, created_by ... FOR UPDATE` lock on the same rule row locked by `RecurrenceRules::trash()`. This serialization prevents an independent Trash transaction from committing between the decisive current status check and the Work Item insert/ledger attachment. The locked row **itself** determines active/trashed status, never an ordinary consistent snapshot under `REPEATABLE READ`. The claimed occurrence must still match the source rule, be ungenerated and carry the exact claimant token. Failures roll back the Work-owned transaction or savepoint and release the occurrence token, without generating further items after a verified Trash. Recovered Work Items retain the old idempotent attachment flow.

This presumes the canonical WordPress posts/postmeta and Work-owned tables are InnoDB, as already assumed by the Work Mutation A1 and SQL B3.2 contracts. WordPress hooks run during WordPress post creation; side effects from unrelated third-party plugins are not covered by SQL rollback.

`tests/integration/WorkB42LockAndAccessIntegrationTest.php` adds a **real two-connection MariaDB lock ordering fixture**: connection B is prevented from committing the Trash-like update while connection A holds its rule row `FOR UPDATE` lock; after A commits creation, B can Trash; a subsequent attempted generation cannot pass active/not-trashed condition. This is a **deterministic SQL isolation test**, not a full concurrent two-process invocation of `Scheduler::run()` with a paused WordPress post-insert hook. Existing Recurrence Lifecycle integration cases cover real rule Trash / restore, history and generator exclusion, while a new Level 1 smoke asserts the exact guarded creation, token and attachment sequence. UI and real scheduled-cron acceptance remain operator gates.

### B4.2a: authorization

The same integration suite adds explicit unauthenticated/subscriber/track-time-denied native Project and Work Item REST read checks, private frontend Work Item opt-in checks, and real `admin_post` denial for wrong actor and invalid nonce across representative Recurrence, Work Item and VAT endpoints. Older suites already cover Time assignments and Work Item lifecycle CAS. No claim is made that every one of the **46** indexed registration expressions has an individually exercised negative HTTP test; future capability changes must maintain explicit endpoint/nonce guards.

### B4.2b: small correctness hardening

- `src/Lifecycle.php` and `core-blueprint-work.php`: caller escapes `wp_die()` `link_url` with `esc_url()` and localized `link_text` with `esc_html__()`.
- `src/Admin/Operations.php`: **intentionally unchanged** after discovering the G0/B1.2 immutable-source Golden hash. Its historical misplaced `@param int[] $ids` remains visible as one precisely measured PHPStan baseline exception, implemented **only in the external QA workbench**. We do not revise renderer source merely to silence static tooling.
- `src/Governance/WorkEventOutbox.php`: WordPress cron action now uses `drain_cron(): void` adapter while keeping direct `drain(): int` interface and outbox delivery count untouched.
- No mass PHPCBF fix, static-analysis suppression, UI feature expansion, new WordPress schema or namespace change.

### B4.2c: final Golden QA

`tools/check` now requires `tests/work-b4-2-security-golden-smoke.php`. The five new WordPress integration methods are discovered by the existing `tests/phpunit-time-cas.xml.dist` suffix convention. `tools/b4-final-acceptance` runs:
1. External-workbench `tools/audit-b4-phpstan-constants` first requires **exactly 1 unfiltered legacy** `parameter.notFound` message at `src/Admin/Operations.php`, then allows **only that one** via a scoped external PHPStan exception with `count: 1`, path, identifier and exact error pattern. The final calibrated scan requires **0 remaining unignored errors**. No plugin source or Golden hash is altered. This is a minimum static baseline, not a universal security proof.
2. WPCS WordPress.Security reporting without broad disablement. Legacy security sniff warnings require manual documentation; PHPCS is not currently a strict zero-warning gate because it does not resolve centralized guards and pre-escaped Base UI primitives.
3. `./tools/build-release`, which mandatorily executes Level 1, then Level 2 on WordPress 7.0/MariaDB 10.11.19, then builds deterministic Work `dist/core-blueprint-work.zip`.
4. ZIP integrity + sidecar verification, disallowed developer paths, source status.

External QA scripts accept the B4.2 feature branch and remain development-only. Release ZIP contains only runtime files. The prior reproducible ZIP SHA `0afe356d899fba6a6aa3b92a15234870eb0078bb2f62580860a958b1b865c155` is **not** the expected new checksum: runtime code changed. Record the new SHA from the actual operator build, never precompute or invent it.

## B4.2 first operator gate and corrective follow-up

The operator fetched feature SHA `723948db90ff4b0d00d083eaef53250118f5cac9` and ran `bash tools/b4-final-acceptance`. PHPStan calibrated **0**, WPCS Security **227** (109 Missing nonce, 78 Recommended, 40 escape, down from 44), initial Level 1 checks passed until **G0 Table/List/Board exact Git-blob reconstruction FAILED** with B1.2 Operations hash `48f9e36cb492de51f77fed4c07381dd23c27e313`. Level 2/Level 3 and release were **not executed** for that candidate.

The feature now restores `src/Admin/Operations.php` **exactly** to main Git blob SHA-1 `92a4814f24b43f96d16e3d96ce61a49ee9f21500`, preserving the original renderer and its immutable acceptance reconstruction. The new Level 1 B4.2 smoke explicitly verifies the legacy annotation remains. Calibrated PHPStan script transparently runs **unfiltered** first, requires exactly one legacy annotation error, then applies a count-1 external scoped exception; final 0 unignored issues is no longer misleadingly claimed as 0 raw errors. Changes since operator first run have **not yet been executed on operator's host**. The updated batch must be rerun before any Golden PASS claim.

## Source/environment caveat

The preceding B4.1 integration PASS used **Base 98d54ca0760ddbde016ff7c216834f737de53d68**, whereas the earlier accepted Golden used **Base 2bbb695abba4e7bdf9bfb5c95de82e93a65d0c77**. GitHub confirms divergent development branches. The final acceptance script prints the exact Base SHA used. It must not change Base. If it differs, test results are scoped to the actual checkout, not both Base versions.

## Required operator acceptance and governance

1. Fetch and track `feature/work-b4-2-security-final-golden-v1`, confirm branch HEAD and that only `?? dist/` is untracked.
2. `bash -n tools/b4-final-acceptance` and `bash tools/b4-final-acceptance`; provide **complete failing output** if any gate stops. No claim of B4.2 PASS before the operator supplies output.
3. Install produced ZIP on **staging** and exercise Recurring Work active generation, Trash during/after generation, Restore always inactive, admin quick edit, List/Table/Board/Calendar, timer/notes, data export/import, light/dark Base UI. Verify no changes to existing history or provenance.
4. Only after sandbox reports **STABIEL**, ask for separate **merge GO**, then merge and execute post-merge Golden on `main` plus Overseer reconciliation. Neither user GO for batching nor sandbox PASS itself grants merge permission.
5. CI/Actions and production remain out of scope without separate approval.

## Second operator Level 1 blocker: Missing-Base WordPress stub (2026-10-14)

Operator on Work feature SHA `143a628f04f26fbdf34cce3d81ef600b6bbb86ac` ran the G0 source Golden and `bash tools/b4-final-acceptance`. G0 exact Table/List/Board reconstruction **PASS**. PHPStan unfiltered found precisely the one expected historical G0 PHPDoc error; external-only count-1 exception yielded **0 other errors (PASS)**. WPCS WordPress.Security baseline unchanged at **227 findings**, monitored but not a blanket-clean status. New B4.2 Security source contract PASS, and the full Level 1 suite advanced through Runtime smoke. `tests/missing-base-smoke.php` then **FATAL**: missing global `esc_url()` stub during intentional Base-absent `Lifecycle::fail_activation()` invocation. The PHP fatal identifies test-bootstrap incompleteness, not a WordPress runtime missing function; `esc_url()` exists in real WordPress.

**Targeted test-only correction:** `tests/missing-base-smoke.php` now declares a global `esc_url()` stub backed by `htmlspecialchars(... ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`, records each call in `$GLOBALS['esc_url_inputs']`, and asserts the failed activation esc_url-escapes the exact canonical Plugins URL **once**. Runtime `src/Lifecycle.php` and `core-blueprint-work.php` remain unchanged; explicit URL and label escaping remains mandatory. No Base, main or CI modifications. The local runner after this correction is **NOT YET RERUN**; Level 2, Level 3, ZIP integrity and staging approval are still pending. The earlier last fully accepted Work ZIP checksum must not be reused for the B4.2 runtime changes.

## Third operator Golden gate: isolated B4.2 two-session fixture visibility (2026-10-14)

Operator ran `bash tools/b4-final-acceptance` at `dd76336b32e69cb1b83e4c5287836fb389bbae69`. Exact B1/G0 renderer Golden, all Level 1 smoke tests (including Missing-Base and B4.2 security source), and scoped external PHPStan level 2 (zero unignored errors, one known exact legacy PHPDoc exception) **PASS**. WPCS focused monitor remained 227 findings (109 missing nonce, 78 recommended, 40 escaping; monitored, not a clean security scan). On WordPress 7.0 / MariaDB 10.11.19 / PHPUnit 9.6.36, **39 tests, 1,232 assertions, one failure**: new `WorkB42LockAndAccessIntegrationTest::test_two_connections_serialize_creation_before_trash` line 82 could not update `cb_wb42_lock_<nonce>` from connection B because MySQL reported **table does not exist**. All other 38 integration tests succeeded. Level 2 FAIL, so Level 3 release build and new ZIP SHA **NOT produced or verified**. This run used local Base SHA `15dafa47ad043498b73187e546970cd540da3d9a`, not older Base snapshots.

The failed test did not establish a WordPress Recurrence runtime failure or validate a genuine InnoDB lock timeout: it lacked a pre-lock assertion that B could access the purportedly shared table. B4.2 feature-only fixture patched to follow the already passing Work A1 `WorkItemTwoConnectionCasIntegrationTest` template: explicit `mysqli_select_db` on A/B; independent connection identity; autocommit enabled; persistent table creation via `mysqli_query` on A (not WordPress `wpdb::query`); `owns_table`-guarded raw mysqli DROP in teardown; **B must read the committed shared row before any transaction**; B must show `@@in_transaction = 0`. During A `SELECT ... FOR UPDATE`, B's attempted Trash-like UPDATE must return false **specifically because of “Lock wait timeout exceeded”**; a table visibility error is no longer accepted as concurrency evidence. The Level 1 B4.2 smoke now asserts those exact fixture safety requirements.

**Status: corrective fixture commit pushed; local test and all Golden levels await fresh operator execution.** Runtime `Scheduler.php` row-lock implementation, Base and production were NOT changed in this corrective batch. No CI, merge or sandbox installation authorized until the local B4.2 acceptance actually passes. Unproven remaining risks: this fixture models row-lock ordering on a disposable table, not simultaneous real WordPress Scheduler/Trash invocations. No zero-warning security conclusion.

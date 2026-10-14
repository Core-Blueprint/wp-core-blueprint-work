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
- `src/Admin/Operations.php`: invalid `@param int[] $ids` removed from date-label signature and documented on actual assignment-label signature.
- `src/Governance/WorkEventOutbox.php`: WordPress cron action now uses `drain_cron(): void` adapter while keeping direct `drain(): int` interface and outbox delivery count untouched.
- No mass PHPCBF fix, static-analysis suppression, UI feature expansion, new WordPress schema or namespace change.

### B4.2c: final Golden QA

`tools/check` now requires `tests/work-b4-2-security-golden-smoke.php`. The five new WordPress integration methods are discovered by the existing `tests/phpunit-time-cas.xml.dist` suffix convention. `tools/b4-final-acceptance` runs:
1. External-workbench `tools/audit-b4-phpstan-constants` and requires **0** calibrated PHPStan level 2 errors. This is a minimum static baseline, not a universal security proof.
2. WPCS WordPress.Security reporting without broad disablement. Legacy security sniff warnings require manual documentation; PHPCS is not currently a strict zero-warning gate because it does not resolve centralized guards and pre-escaped Base UI primitives.
3. `./tools/build-release`, which mandatorily executes Level 1, then Level 2 on WordPress 7.0/MariaDB 10.11.19, then builds deterministic Work `dist/core-blueprint-work.zip`.
4. ZIP integrity + sidecar verification, disallowed developer paths, source status.

External QA scripts accept the B4.2 feature branch and remain development-only. Release ZIP contains only runtime files. The prior reproducible ZIP SHA `0afe356d899fba6a6aa3b92a15234870eb0078bb2f62580860a958b1b865c155` is **not** the expected new checksum: runtime code changed. Record the new SHA from the actual operator build, never precompute or invent it.

## Source/environment caveat

The preceding B4.1 integration PASS used **Base 98d54ca0760ddbde016ff7c216834f737de53d68**, whereas the earlier accepted Golden used **Base 2bbb695abba4e7bdf9bfb5c95de82e93a65d0c77**. GitHub confirms divergent development branches. The final acceptance script prints the exact Base SHA used. It must not change Base. If it differs, test results are scoped to the actual checkout, not both Base versions.

## Required operator acceptance and governance

1. Fetch and track `feature/work-b4-2-security-final-golden-v1`, confirm branch HEAD and that only `?? dist/` is untracked.
2. `bash -n tools/b4-final-acceptance` and `bash tools/b4-final-acceptance`; provide **complete failing output** if any gate stops. No claim of B4.2 PASS before the operator supplies output.
3. Install produced ZIP on **staging** and exercise Recurring Work active generation, Trash during/after generation, Restore always inactive, admin quick edit, List/Table/Board/Calendar, timer/notes, data export/import, light/dark Base UI. Verify no changes to existing history or provenance.
4. Only after sandbox reports **STABIEL**, ask for separate **merge GO**, then merge and execute post-merge Golden on `main` plus Overseer reconciliation. Neither user GO for batching nor sandbox PASS itself grants merge permission.
5. CI/Actions and production remain out of scope without separate approval.

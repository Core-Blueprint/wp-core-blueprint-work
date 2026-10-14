# Core Blueprint Work | B4.1 Security & Static QA Source Audit

Date: 2026-10-14  
Mode: **AUDIT ONLY**, no runtime patch, CI, production deployment or merge  
Source branch: `audit/work-b4-security-static-qa-v1`  
Accepted parent Work `main`: `322436dddefee833b629e3294c38e6daca4c684f`  
Baseline: B2 and Recurrence Golden CLOSED; Work 34 integration tests, 1,187 assertions; reproducible approved release SHA-256 `0afe356d899fba6a6aa3b92a15234870eb0078bb2f62580860a958b1b865c155`.

## Classification and limits

This is a **targeted read-only source review and a reproducible local audit bootstrap**, NOT a complete PHPStan, WordPress Coding Standards or penetration-test result. Priority indicates what to investigate next, not a confirmed vulnerability. Do not conflate legacy source-smoke PASS with permission/SQL/concurrency security verification. User must run local tool inventory before a static-analysis result can be claimed. No tool, dependency or CI workflow is installed or activated by this audit.

## Environment and reproducibility (B4-000)

The accepted Work source contains `tools/check`, `tools/check-integration`, `tools/build-release`, `tests/` and six-locale i18n tooling. It contains **no `composer.json`, `phpstan.neon`, `phpcs.xml`, vendor folder or Actions workflow**. Existing local regression and release acceptance is already strong, but PHPStan/PHPCS/WPCS have not been demonstrated or baselined.

The audit-only `bash tools/audit-b4-environment` command prints exact branch/commit/status and finds PHP >=8.4 plus local `composer`, `phpstan` and `phpcs` installations. It **never downloads, installs or executes static analysis**. It returns exit code 2 if tooling is missing, which is **not** a security scan failure or PASS. The script is deliberately separate from `tools/check` and is excluded by the existing release packager.

Before an actual tool baseline, decide approved **offline/local** Composer cache/dependency acquisition for PHPStan, WordPress stubs and WordPress Coding Standards; record exact tool versions and standards. Do not install packages, modify global PHP or trigger network activity as part of this approved read-only audit. Avoid blanket ignore rules that conceal safety findings. Future static QA must be reproducible on PHP 8.4 against canonical Base public contracts.

## Focused source review

| ID | Priority | Status | Evidence and required next verification |
|---|---|---|---|
| **B4-001** | High investigation priority | **Unverified concurrency risk** | `src/Recurrence/Scheduler.php::process_rule()` rechecks active/not-trashed status **after** obtaining a claim, but then projects input and calls `WorkItems::create()` without holding the rule lock across the creation. A concurrent Trash may complete between the recheck and the Work Item create. `src/Repository/RecurrenceRules.php::occurrence_work_item_input()` independently checks only rule existence. Prior occurrence uniqueness, claim tokens, Work Item source idempotency and later cursor CAS reduce other races, but do not prove an absolute stop-after-Trash guarantee. Create a *deterministic two-connection gate test* to characterize user-visible behavior and decide whether an in-flight generation is permitted to finish under the declared lifecycle contract. Do not silently change the scheduler in B4.1. |
| **B4-002** | Medium investigation priority | **Authorization matrix incomplete** | `src/Content/WorkItemRestController.php` and `ProjectRestController.php` guard read/list with Work `MANAGE` and delegate to parent; `src/Content/PostTypes.php` maps read/edit/delete/create capabilities to Work `MANAGE` and is private. This is sound at source level but needs WordPress REST HTTP tests for unauthenticated/subscriber/track-time/operator, across list/read/create/update/delete and metadata exposure, plus opt-in frontend filters. No bypass confirmed. |
| **B4-003** | Medium investigation priority | **Audit delivery semantics** | `src/Admin/RecurrenceActions.php` commits Trash/Restore/Delete first, then invokes `Audit::record` with no explicit failed-audit handling. Existing Work event outbox protections apply to other flows, not necessarily these admin mutations. Confirm intended Base audit contract and whether security-critical lifecycle events need durable delivery/reconciliation. No loss event demonstrated; this is a governance review gate. |
| **B4-004** | Medium investigation priority | **High-value import guard regression** | `src/Admin/ProjectDataExchange.php` has capability + nonce checks, verified uploaded JSON, size bound and fingerprint; `src/Integration/DataExchange/ProjectBundleTransaction.php` uses InnoDB owner/savepoint and deferred event outbox. Existing B3.1 two-connection tests are substantial. Retain checks for hostile uploads, partial commit rollback, cache invalidation, false-success audit, customer reference stripping and data ownership. |
| **B4-005** | Existing mitigations observed | **Source reviewed; runtime security matrix pending** | Admin mutations in `RecurrenceActions`, `OperationalActions` and `TimeActions` use manager/time capabilities and admin nonces. Recurrence Trash/Restore/permanent removal is row-locked, permanent delete fails closed on occurrence history lookup errors and generated Work Items are not cascade-deleted. Dedicated tests cover history, assignment rollback, restores and schema migration. Deep authorization/nonce tests with negative actors remain a B4 goal. |
| **B4-006** | Existing mitigations observed | **Source reviewed; static proof pending** | `src/Repository/TimeEntries.php::all()` interpolates a `LIMIT` **after** integer clamping to 1..500; other filters use WordPress preparation. No SQL injection is concluded from that bounded integer usage. Audit other dynamic order-by/table identifier constructs, %i compatibility and SQL-error handling before final certification. |
| **B4-007** | Already passed | **Golden baseline** | B2, B3.1/B3.2 and Recurrence source/integration gates, schema v2.2 Base reconciliation, six locales, zip manifest and deterministic SHA validated by operator. No runtime change in B4.1, so the accepted release artifact remains canonical. |

## Audit coverage checklist for B4.2

1. **Authentication and authorization:** negative actor/nonce controls for admin-post, AJAX, native REST, opt-in frontend, time ownership and assignment-scoped transitions.
2. **Persistence and privacy:** raw SQL whitelists and bounds, SQL error states, audit delivery, no cross-customer data access, no raw secrets or network egress.
3. **Concurrency and data integrity:** separate DB sessions for recurrence Trash vs claim/create, nested transactions/savepoints, Work CAS, Work Item idempotency, bundle import rollback, event outbox.
4. **Static QA:** actual tool availability and versions, PHPStan and PHPCS/WPCS configurations using Base public contracts, segregated legacy style debt versus security findings; zero unsupported blanket suppressions.
5. **Regression and distribution:** `./tools/check`, `./tools/check-integration`, `./tools/build-release`, checksum sidecar, clean tracked source, no unapproved CI, production or external services.

## Operator acceptance gate

Run on the B4 audit branch, after checking exact SHA:

```bash
bash -n tools/audit-b4-environment
bash tools/audit-b4-environment
```

**Expected:** environment inventory with explicit FOUND/MISSING versions. Exit 2 due to missing Composer/PHPStan/PHPCS is an accepted *inventory outcome*, not a B4 PASS. No current evidence permits a static QA PASS claim.

Once results are received, document the approved offline toolchain and settle whether B4-001 and B4-003 need B4.2 runtime patch/test work. Maintain a separate explicit merge GO after audit acceptance. Source-only audit report and inventory script are development-only and are not included in the WordPress release ZIP.

**Current B4.1 status:** preliminary source audit READY FOR OPERATOR TOOL INVENTORY; static analysis **PENDING**; B4.2 hardening **NOT STARTED**; Final Technical Golden **OPEN**.

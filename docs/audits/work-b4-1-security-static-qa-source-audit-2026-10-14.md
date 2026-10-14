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

## Operator-confirmed local inventory (2026-10-14)

The operator ran `bash -n tools/audit-b4-environment` (no syntax errors) and `bash tools/audit-b4-environment` from audit branch `e85774da162316f03c365e9592d43b585a4a8d8c`; tree showed only `?? dist/`.

- PHP CLI **8.4.26**, NTS, built 2026-09-24: ready for Work runtime and analysis.
- Composer **2.7.1**, installed `/usr/bin/composer`, running on PHP 8.4.26. Emits deprecation notice for `E_STRICT` in system Composer Silencer; this is a **tooling compatibility notice**, not a Work code defect or a static-analysis result.
- PHPStan: **NOT INSTALLED**.
- PHPCS: **NOT INSTALLED**.
- Script reports `B4 TOOLCHAIN INVENTORY: INCOMPLETE` (expected missing-dependency exit code 2). No download, dependency installation, external action or static analysis occurred.

**Key repository-specific safety decision:** Work has **no `.gitignore`** and the existing `tools/check` recursively `find`-lints every `*.php` under the Work checkout except `.git`, `dist` and `build`. Installing Composer's `vendor/` inside Work would therefore pollute tracked-status expectations and risk linting third-party dependencies in the canonical Golden suite. **Do not install QA dependencies inside the Work repository or globally.** Place the QA workbench in a separately managed, user-approved path such as `~/Downloads/core-blueprint-qa-tools`; pin versions in that workbench's `composer.lock`, keep binary paths explicit and do not copy `vendor/` into the Work release. Composer dependency retrieval uses external Packagist/GitHub and therefore requires deliberate operator opt-in before running an installation command. Audit/lock versions first; avoid broad Composer plugin allowances. WordPress 7.0 source and Base public PHP symbols must be modeled explicitly in PHPStan.

Proposed staged gates:
1. **B4.1a COMPLETE:** local inventory, sources and scoped risk register.
2. **B4.1b NEXT:** user-approved installation of isolated dev tools, then actual PHPStan+PHPCS security baseline, report counts/severities/false positives. No opportunistic code-style mass rewrite.
3. **B4.2 PENDING:** negative-actor/nonce/REST and deterministic concurrency tests; fix only proven findings under new explicit GO.

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

## First executed PHPStan and WPCS baseline (operator, 2026-10-14)

The explicitly approved isolated Composer installation **succeeded** on the local Ubuntu/Linux host; all requested direct packages were installed and locked. QA workbench: `~/Downloads/core-blueprint-qa-tools/`, entirely outside Work. Composer used disabled plugins/scripts; bundled dealerdirect installer was downloaded transitively but **not executed**. Registered standards include WordPress, WordPress-Core, WordPress-Docs and WordPress-Extra.

- PHPStan 2.2.14 initial level 2: **67 errors**, analyzer exit **1**.
- PHP_CodeSniffer 3.13.5 / WPCS 3.4.1 WordPress-Extra: **3,631 errors and 846 warnings in 111 files**; analyzer exit **2**. PHPCBF advertising 3,816 automatable violations is **NOT authorization to auto-fix**.
- QA Composer lockfile SHA-256 **`59090ca60483ce091c701071eb0c7327175b7013dd3f5fd96efd3f26df9cd806`**.
- Operator Work audit checkout **`3004f055f9b88ce57c3fa865a19975e95e5f8c00`**, `?? dist/` only; no Work source changes.
- Reports retained locally in the external QA workbench as `reports/work-phpstan.txt` and `reports/work-phpcs-summary.txt`.
- Composer deprecation warnings reference system Composer 2.7.1 / libraries on PHP 8.4; they are tooling noise, not Work execution errors.
- Explicit security-status: **NO complete static QA pass and NO 4,477 security defects established**. WordPress-Extra includes style/doc/spacing along with security checks.

**Two representative PHPStan findings from log:** Work bootstrap constants (`CB_WORK_URL`, `CB_WORK_BASENAME`) reported missing although actually defined conditionally in `core-blueprint-work.php`; analysis/constant discovery must be calibrated outside runtime code. `src/Governance/WorkEventOutbox.php` action callback `drain(): int` triggers `return.void`; WordPress ignores action return values, so determine contract/accuracy before classifying a runtime defect. Do not patch either based only on a single tool line.

**Next measured audit step:** run JSON-format PHPStan to tally identifiers with line examples and run PHPCS source-category and WordPress.Security-only summaries in the already installed workbench; keep reports outside Work and provide aggregate outputs. Only then prioritize proven correctness/security fixes versus analysis stubs and existing style debt. B4.1 remains **BASELINE MEASURED / TRIAGE PENDING**. B4.2 and final Golden OPEN; no CI, merge or runtime change.

## Second operator triage: PHPStan categories and PHPCS selection blocker (2026-10-14)

The operator updated the audit branch to `675aa3faa7e3cbf6d02bb3fe3b056740a4cec582` with only `?? dist/` untracked. A JSON PHPStan rerun (same 2.2.14, level 2) classified all **67 findings**, **0 general errors**:
- `constant.notFound`: **65**, example `src/Admin/Assets.php:90` `CB_WORK_DIR`; Work defines its `CB_WORK_*` constants in a guarded plugin bootstrap in `core-blueprint-work.php`. Address first using **external-only analysis bootstrap/symbol definitions**, never modifying production constants for an analyzer. Verify the exact set before claiming every constant is a false positive.
- `parameter.notFound`: **1**, `src/Admin/Operations.php:1043`: `/** @param int[] $ids */` is incorrectly attached to `work_item_date_label(string $date)`; the actual `assignment_label(array $ids)` follows it. This is a **confirmed PHPDoc misplaced tag** (not a runtime vulnerability). Plan a tightly scoped documentation fix only with B4.2 GO.
- `return.void`: **1**, `src/Governance/WorkEventOutbox.php:29`: WordPress `add_action( self::HOOK, [ self::class, 'drain' ] )` references `drain(): int`. WordPress action callbacks ignore return values; direct Work outbox tests call `drain()` and rely on the integer. Evaluate a dedicated void wrapper **without changing public drain() return** during approved B4.2 if necessary. Not established as a security flaw.

The attempted PHPCS `--sniffs=WordPress.Security` was **invalid**: this option requires full sniff identifiers, not a category, and no security audit happened in that run. **Assistant command mistake**, not Work source behavior. The audit branch now includes `tools/audit-b4-security`, a **read-only isolated-local** runner that discovers the installed `WordPress/Sniffs/Security/*Sniff.php` files (including output escaping, nonce verification, sanitized inputs, safe redirects and plugin menu validation), builds explicit `WordPress.Security.Name` sniff codes, and runs WPCS with report output in the external QA workbench. The patched runner has not yet been executed by the operator: WPCS Security findings remain **PENDING**.

Next: operator fetches new audit branch SHA, runs `bash -n tools/audit-b4-security`, then `bash tools/audit-b4-security`, and provides the full source-category report. Do **not** auto-fix 3,816 PHPCS warnings, install extra packages, or declare B4 finished. B4.2 runtime fixes need separate GO; Work main unchanged.

## Operator WPCS Security result and detail drill-down (2026-10-14)

Operator ran `bash tools/audit-b4-security` on Work audit branch `cd550532f5844cd8caab789059062502de4d2395`. The scanner discovered five installed WPCS WordPress.Security sniff groups: EscapeOutput, NonceVerification, PluginMenuSlug, SafeRedirect and ValidatedSanitizedInput. **The invocation worked** and returned:
- **109** `Nonce verification missing`.
- **78** `Nonce verification recommended`.
- **44** `Escape output output not escaped`.
- **231** aggregate sniff violations across 3 source categories, PHPCS exit 1.
- Tracked Work source unchanged, only `?? dist/`.

These are **pattern matches, not 231 confirmed security vulnerabilities**. A source spot check confirms capability checks and central `self::guard()` plus `check_admin_referer()` on each main mutating handler in `src/Admin/RecurrenceActions.php` and `src/Admin/OperationalActions.php`; `src/Admin/TimeActions.php` similarly guards admin posts, and `ProjectDataExchange.php` verifies AJAX capability and `check_ajax_referer`. WPCS source-scanning can report input read before following transitive guard analysis, and read-only filters do not necessarily need CSRF tokens. Nevertheless **each distinct mutation route must be checked manually** and representative output-escaping findings inspected in the actual sink/context before reclassifying.

Added **read-only** `tools/audit-b4-security-detail`: reruns the five **real installed** sniff codes with PHPCS JSON output to the external QA workbench `reports/work-security-detail.json`, then prints each exact sniff identifier with 3 location examples, top 20 offending files and counts. It does not install tools, suppress warnings, auto-fix source or change the WordPress release. **Await the operator's local detail result** before any B4.2 remediation scope or security clearance. B4.1 remains SECURITY TRIAGE OPEN; no runtime fixes authorized.


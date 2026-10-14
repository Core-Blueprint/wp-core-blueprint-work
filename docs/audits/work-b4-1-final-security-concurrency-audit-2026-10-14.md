# Core Blueprint Work | B4.1 Final Source Triage and B4.2 Test Design

Date: 2026-10-14  
Scope: audit and acceptance design only. No Work runtime edits, Base edits, CI or production changes.  
Source authority: Work `main` `322436dddefee833b629e3294c38e6daca4c684f`; audit `audit/work-b4-security-static-qa-v1`.  
QA host: PHP 8.4.26, PHPStan 2.2.14, PHPCS 3.13.5, WPCS 3.4.1, isolated workbench locked SHA-256 `59090ca60483ce091c701071eb0c7327175b7013dd3f5fd96efd3f26df9cd806`.

## B4.1 measured facts

- PHPStan at level 2: **67** = 65 `constant.notFound` for guarded `CB_WORK_*` bootstrap symbols, 1 incorrect `@param $ids` on `Operations::work_item_date_label(string $date)`, 1 `return.void` for `WorkEventOutbox::drain(): int` registered as a WP action. No other identifier groups at this analysis level. These results are **not an advanced static-proof pass**.
- WPCS `WordPress-Extra`: 3,631 errors + 846 warnings in 111 files; mostly broad convention scans, not an exploit count.
- Focused `WordPress.Security` sniffs: **231 messages** = 109 Missing nonce, 78 Recommended nonce, 44 OutputNotEscaped.
- Source-level output escaping review: all 44 findings mapped to 7 files. `Notice`, `StateBadge`, `ObjectPicker` canonical Base renderers internally escape; do not HTML-escape the returned markup a second time. Two Work `wp_die` callsites (`src/Lifecycle.php` and `core-blueprint-work.php`) merit narrowly scoped `esc_html__` label and `esc_url(admin_url(...))` defense-in-depth. No proven XSS in the scoped samples.
- The read-only GET success-notice/filter endpoints **do not automatically require mutation nonces**. Scan results must be classified by actual effect and guard call graph.

## B4.1 permission and mutation route matrix (source-level evidence)

The following registrations were inspected in the accepted source and their handler guard paths traced at source level. This is NOT a real HTTP/role-test matrix and does not represent an exhaustive enumeration of third-party hook registrations. Run `tools/audit-b4-route-inventory` to generate a complete matching `add_action` inventory for this checkout. Its printed guard tokens are **file-level hints, not handler security proof**.

| Source group | Registered endpoints | Source guard or boundary seen | Negative B4.2 test |
|---|---|---|---|
| `Admin/RecurrenceActions.php` | 7 `admin_post_*` + 3 `wp_ajax_*` | `self::guard()` with `Capabilities::MANAGE` and `check_admin_referer` for admin posts; AJAX handlers check `current_user_can` and `check_ajax_referer`. | Visitor/subscriber/manager: missing, invalid, wrong-rule nonce for trash/restore/delete, quick edit, toggle, generator; no mutation after failed request |
| `Admin/OperationalActions.php` | 6 `admin_post_*` | `self::guard()` with `Capabilities::MANAGE` and per-action `check_admin_referer`. | No-nonce/invalid nonce, no capability, stale state/CAS and bulk input bounds |
| `Admin/TimeActions.php` + `TimeEntryBulkEdit.php` | 4 + 1 `admin_post_*` | Time `Access::can_track` / ownership and `check_admin_referer`; bulk-edit checks target work item and editing permissions. | Track-time actor assigned/unassigned, cannot act as another user, invalid nonce, stale revision, same actor on newly reassigned Work Item |
| `Admin/TaxRateActions.php` + `TaxRateDataExchange.php` | 2 admin posts + 1 export admin post + 3 AJAX mapper actions | MANAGE + `check_admin_referer` or guarded AJAX with `check_ajax_referer`. | Unprivileged/invalid nonce, hostile CSV, oversize, apply without valid preview |
| `Admin/ProjectDataExchange.php` | 1 export admin post + 2 AJAX actions | MANAGE + explicit nonce; JSON upload validation, preview fingerprint, InnoDB transaction and deferred events. | Unprivileged/invalid nonce, tampered fingerprint, malformed/oversize import and rollback invariant |
| `Admin/QuickAdd.php` | 1 `admin_post_*` | MANAGE + `check_admin_referer`; notice GET is read-only. | Subscriber/invalid nonce cannot create; GET notice cannot perform mutation |
| `Admin/TimerHud.php` | 3 `wp_ajax_*` | `guard()` first enforces time capability/schema + `check_ajax_referer`, then stop/note actor ownership. | Invalid nonce, unassigned tracker, actor tries another user's timer/entry, old active-entry revision |
| `Admin/Pickers.php` | 3 `wp_ajax_*` | MANAGE or TRACK_TIME scope + `check_ajax_referer`; time Work Item results filtered by actor. | No nonce, subscriber, tracker searches outside assignment, metadata leakage |
| `Admin/WorkItemBoardActions.php` + `WorkItemCalendarActions.php` | 2 `wp_ajax_*` | MANAGE + `check_ajax_referer`. | No nonce, unprivileged actor, stale calendar/board state |
| `Admin/WorkItemViewPreferences.php`, `WorkItemTablePreferences.php`, `WorkItemListPreferences.php` | 3 preference AJAX actions | MANAGE + `check_ajax_referer`. | Unauthorized preference mutation; wrong actor preferences remain unaffected |
| `Admin/WorkItems.php` + `Projects.php` | 2 `save_post_*` hooks | MANAGE + `edit_post` + verified editor nonce; post revision/autosave safeguards. | Unauthorized save, missing/invalid nonce, wrong post type, autosave/revision |
| Native REST controllers in `Content/` | Private Project/Work Item CPT REST access | `Capabilities::MANAGE` reads and WP capability mapping for mutations; parent WP REST semantics. | Guest/subscriber/time tracker CRUD/meta requests; manager update only as intended |
| `Frontend/Access.php` and public Work actions | Opt-in user-facing API | Runtime gate, explicit read access and transition filter, no default exposure. | Opt-out fail closed, no unintended private Work disclosure, assigned/unassigned negative cases |

Across **17 inspected callback-owning admin files**, approximately **44 directly visible mutation-hook registrations** are accounted for above; this number is a scoped human inventory, **not** a blanket completeness claim. The machine-readable inventory identifies exact matching registration lines on the local checkout. Cross-file and dynamic registrations need independent assessment.

## B4-001: Recurrence Trash versus generation, source-demonstrable TOCTOU window

The existing `RecurrenceRules::reserve_next_occurrence()` transaction takes a rule `SELECT ... FOR UPDATE` lock only while creating a unique occurrence-ledger reservation, and then releases that transaction. `RecurrenceOccurrences::claim()` atomically marks a claim on the **occurrence** row. `Scheduler::process_rule()` then re-reads the rule and checks `is_active`/`is_trashed` **after** the claim. This is a valuable mitigation but is not atomic with the later Work Item insert.

The observable source sequence can be:
1. Connection A (scheduler) reserves/claims occurrence, reads rule as active and not trashed, and obtains `occurrence_work_item_input()`.
2. Connection B (admin) calls `RecurrenceRules::trash()`, acquires its rule row lock, sets `is_active = 0` and `trashed_at`, and **commits**. No lock is held by A across the later `WorkItems::create()`.
3. A resumes `WorkItems::create()`, calling `wp_insert_post()` and linking the Work Item to the previously claimed occurrence. It may commit a new Work Item **after** Trash has completed. The later recurrence-cursor CAS does not prevent the already inserted post.

**Classification: high-priority source-supported concurrency gap, not yet a proven two-connection runtime reproduction.** Existing unique occurrence index / claim token / relation recovery reduce duplicates and preserve audit history, but are not a substitute for making the create-versus-trash outcome serializable.

**B4.2 deterministic acceptance test design:** Use two independent MariaDB connections and two controlled workers/processes. Put a barrier immediately before the Work Item insertion (e.g., WordPress `wp_insert_post_data` hook in a test-only fixture, not production). Pause A after its last active check and immediately before inserting; complete B Trash and verify committed `is_active=0`; release A; assert no post-Trash Work Item insert (if strict pause-at-commit semantics are the accepted lifecycle contract), no extra relations, no lost ledger provenance, no duplicate on subsequent generator, and no lost historical Time/Billing. Repeat reverse ordering (A finishes first then B Trash) to prove serialized legitimate in-flight generation. Never delete user Work Items as race compensation in production without explicit product semantics.

**Remediation design review:** Any gate/transaction must serialize the rule row with Trash *through the final Work Item creation and occurrence attachment*, respect existing InnoDB/nested savepoint ownership, WP post/meta mutation and deferred audit side effects, and avoid lock-order inversion with `RecurrenceRules::trash()`. A second one-time recheck without a lock is not a sufficient strict guarantee. No runtime implementation authorized under B4.1.

## PHPStan calibration gate

Current `phpstan.neon` is in the external QA workbench. The new Work audit script `tools/audit-b4-phpstan-constants` checks actual Work bootstrap definitions and writes **only** to `~/Downloads/core-blueprint-qa-tools/stubs/work-runtime-constants.php` and `phpstan-b4.neon`. The latter includes the original external baseline and loads the eight guarded `CB_WORK_*` symbol declarations only inside the analysis process. It runs PHPStan JSON again and reports identifier counts. It does not execute the WordPress plugin bootstrap, ship stubs in Work, suppress missing constants or install anything. **Expected hypothesis:** most or all 65 symbol errors disappear; actual operator output must confirm. Remaining `parameter.notFound` and `return.void` are separately triaged, not hidden.

## B4.1 operator acceptance and B4.2 planning

B4.1 current outcome: scoped escaping source audit COMPLETE; security route/capability source audit PARTIALLY COMPLETE; **PHPStan calibration LOCAL RUN PENDING**; **concurrency runtime reproduction PENDING**; full negative actor role/nonce tests PENDING. No static/security Golden PASS claimed.

Before B4.2 GO, report:
- PHPStan calibrated identifier/count output and unchanged Work Git status.
- Read-only route inventory count and discovered registrations.
- Explicit acknowledgement of the strict no-post-Trash-generation acceptance target; if a different in-flight policy is desired, record it before patching.

B4.2 candidate batches (implementation needs separate user GO): (A) deterministic concurrency/authorization tests and high-severity fixes if confirmed, (B) two `wp_die()` argument hardening sites and misplaced PHPDoc, with bounded action callback adapter if justified, (C) retest with isolated PHPStan/WPCS/security audit, Work `./tools/check`, `./tools/check-integration` and `./tools/build-release`, verified ZIP/SHA and sandbox if runtime changes; then separate merge GO and post-merge Golden. Avoid mass PHPCBF rewrite or global WPCS suppressions.

# Work B3.1 | Project Bundle Database Integrity | implementation candidate

## B3.1 Golden integration expansion | isolated acceptance tests awaiting operator run (2026-10-13)

The previous **operator-validated candidate** at Work HEAD c73de7cd919264027137eefcf14aaf4ca7462372 has **Level 1/2/3 PASS**, 19 PHPUnit tests / 944 assertions, and local ZIP SHA-256 3d92968d5e2261f69acf170cb7c321be0bfe61eba1ac9a91f5b4ea81f98b2afc. The operator also reports Work **stable on the existing sandbox**. The sandbox-installed artifact checksum was not independently reported. That stability is operational evidence; it does not prove the following database Golden guarantees.

**This further Golden candidate is TEST-ONLY, UNVALIDATED.** New ProjectBundleGoldenAcceptanceIntegrationTest must exercise the real, disposable core_blueprint_work_test and exactly cbworktests_ prefix, with no outer PHPUnit-owned transaction and the incoming autocommit mode restored:
1. Seed an existing Work Type row; simulate installed schema **2.0** with all Work data preserved but without the 2.1 outbox table; run **Base's actual DB::reconcile_registered_schema()**. Verify marker **2.1**, new InnoDB table, unique event key, claim/delivery indexes, all critical columns, surviving old Work Type, idempotent second reconciliation. Reject populated outbox deletion even in the disposable database.
2. Use separate real wpdb connections against the same isolated database. Enqueue within a transaction/savepoint and assert the second connection sees **zero** before outer commit and after rollback. Commit a second event; verify it becomes visible and the **second connection** can invoke the actual outbox drain once with the stable event key. Preserve the production guard refusing dispatch inside the first connection's open transaction.
3. Extend existing isolated outbox delivery tests: malformed event at attempt 11 reaches bounded attempt **12** and remains inspectable without being retried indefinitely; a claimed processing row is not stolen before lease expiry, but is recovered and acknowledged after the lease expires; delivered records are not normally replayed.

**Limits and authorization:** Schema upgrade tests operate only on the canonical disposable WP integration database, not the sandbox or production. B3.1 remains *at-least-once*, not exactly-once across crashes; downstream consumers must honor stable event keys for durable idempotency. Base audit callback and non-Work native WordPress save/postmeta hooks cannot be rolled back as external effects. WP-Cron on traffic-dependent WordPress hosts can be delayed; backlog and terminal errors need operator inspection (no privileged WordPress UI created in this patch). Nothing here authorizes a Work-main merge, CI/Actions, or another deployment. Test code changes require **fresh** full Level 1/2/3 validation and a **new artifact checksum**; the above SHA applies only to the older Work HEAD.

---

**Date:** 2026-10-13  
**Baseline:** Work `main` `9009577ab962025dbe43ad33ee94239b2864c044`  
**Branch:** `feature/work-project-bundle-integrity-golden-b3-1-v1`  
**Status:** TRANSACTIONAL DATABASE PATCH, TESTS AND EXTERNAL-EVENT GATE PENDING. **Not Golden, not accepted for merge or production.**

## B3.1 Event & Audit Integrity | unvalidated feature candidate (2026-10-13)

**Scope:** Work-owned Project/Work Item success audits and `cb_work_project_*` / `cb_work_work_item_*` lifecycle hooks during **Project bundle** Data Exchange. Does not alter the general Base Data Exchange multi-record `partial` contract, privilege requirements, nor immediate Work events outside a bundle.

- **Schema upgrade:** Work schema **2.0 → 2.1**, with idempotent `cb_work_bundle_event_outbox` InnoDB table. Base SchemaRegistry owns migration and version marker; a missing/nontransactional outbox table causes Project import to fail closed.
- **Event capture:** `DeferredEvents` wraps canonical Work-owned `Audit::record` and `do_action` only while a ProjectBundleTransaction frame is active; unchanged pass-through outside. It queues an upper-bounded number/size of event snapshots, flushes them to the outbox **before** bundle transaction/savepoint release, and discards them on fail-nth/exception. A caller-owned transaction must be independently committed before the rows become visible to a separate connection.
- **Delivery:** `WorkEventOutbox::drain` refuses to run inside an active transaction. WordPress cron polls every five minutes with 50-row bounded batches, race-resistant row claims, up to 12 attempts and seven-day retention of successfully delivered rows. Audit delivery uses the canonical Base `Governance\Audit::record`, preserving public registered Work event IDs; Work lifecycle hooks retain their historic positional arguments plus a stable trailing outbox event UUID for subscriber idempotency.
- **Delivery guarantee: at-least-once**, not exactly-once across a crash after a callback but before the delivered marker. Operators must treat persisted event UUIDs as idempotency keys. Pending rows may wait longer if WP-Cron traffic is absent; failures remain inspectable in Work's event-outbox table. A delivery worker runs without impersonating a privileged Work user; audit context still carries the original actor ID.
- **Resource limits:** no more than 2000 Work events and 8 MiB of serialized event payload per bundle; single event max 128 KiB. Exceeding limits makes the bundle fail closed and rollback. Work-owned event arguments may include imported descriptions and are retained only inside the site's own outbox; they must never be transmitted externally by the plugin.
- **Unavoidable boundary:** Native WordPress `wp_insert_post` / `wp_update_post` post/meta/save hooks are fired during SQL writes and can invoke third-party callbacks **before** commit. They cannot generically be undone by SQL rollback or Work's scoped outbox. This implementation makes **only Work-owned Project bundle success events commit-aware**. External consumers must not interpret generic WordPress hooks as proof of committed project import.
- **Migration/compatibility acceptance required:** verify clean schema 2.1 install AND 2.0→2.1 upgrade on the isolated WordPress/MariaDB fixture. Event callbacks outside Project bundle remain immediate; events inside become eventually delivered. Ensure no duplicate jobs after deactivate/reactivate.

**Mandatory pre-Golden tests:** syntax and source checks; fail-nth CREATE/UPDATE emits no Work success or Base audit-log hook, rolls back outbox rows, and successful nested savepoint queues events but does not deliver them; two-connection commit-vs-rollback visibility; after-commit worker positive dispatch with canonical audit IDs and Work hooks; idempotency/retry/stale-claim and no-impersonation coverage; non-InnoDB schema fail-closed; event size limit; source A1/A2 and G0 regression; full Level 1/2/3 and independent ZIP SHA. This audit records the candidate contract, **not** a verified delivery claim.

**Blocking caveat:** Until cross-connection post-COMMIT delivery/retry and schema-upgrade tests pass, the event patch is *not* B3.1 Golden and must not be installed on sandbox or production or merged to Work `main`. In particular, the source smoke alone cannot prove cron delivery.

---

## Scope

One Work Project-bundle v1 record, containing one internal Project and at most 500 active Work Items, is now processed under a single Work-owned database transaction/savepoint. Base Data Exchange remains free to return `partial` between independent top-level records.

- `ProjectBundleTransaction::run()` rejects missing/non-InnoDB WordPress post/meta, Work identity/assignment/relation/recurrence, or Base audit tables. It detects `@@in_transaction`, uses a savepoint under an existing caller-owned transaction, and never commits the caller's transaction.
- The provider rechecks requested CREATE/UPDATE operation, locks existing Project post rows, revalidates its internal context, and processes all item writes within this boundary. Failed and thrown mutations roll back the entire bundle's **database** writes. Touch tracking invalidates Project/Work Item post/meta caches after finish.
- The existing best-effort Project deletion on partial failure was removed because the transaction owns cleanup.
- All public canonical Project/Work Item actions, portable UUID uniqueness, Base preview+fingerprint, permission/nonce, schema v1 and import modes stay intact. The patch does **not** add a schema migration or external connector.

## Test matrix and evidence still required

The new source smoke `php tests/project-bundle-integrity-b3-1-smoke.php` is part of `./tools/check`. The WordPress 7.0/MariaDB Level 2 integration suite auto-discovers `tests/integration/ProjectBundleIntegrityIntegrationTest.php`. It deliberately throws inside WordPress' second Work Item post mutation on a 3-item bundle, verifies no residual Project/Work Item/portable identities and a successful retry, then updates an existing bundle and ensures the Project+first Item are rolled back while the surrounding PHPUnit transaction stays active. No direct production/database test has been run by the assistant.

**Important unresolved release blocker:** Database rollback cannot reverse WordPress/Work hooks or remote side effects already dispatched by canonical public actions. Base's `AuditLog` also keeps in-memory dedup state prior to DB commit and dispatches an immediate `core_blueprint_audit_log_written` hook. Consequently **B3.1 is currently a database-state atomic candidate only, NOT an end-to-end audit/lifecycle atomic implementation**. Work hook/audit deferral (or a formally durable resumable alternative) must be proven, with negative tests, before final Golden and sandbox install. Do not claim that a single SQL transaction reverses arbitrary plugin callbacks.

Additional B3.1 Golden gates still open: failure of a non-InnoDB table, concurrent late UUID claim, cross-project and terminal item collision, fail-nth 3rd item, outer-owned no-commit, cache projection, stale Base fingerprint, absence of false success audit events, full re-run A1/A2 and all Level 1/2/3, byte-identical deterministic builds where possible, sandbox review. No production or CI permission.

## Candidate local test / build

```bash
(
  set -euo pipefail
  cd ~/Downloads/wp-core-blueprint-work
  git fetch origin --prune
  if git show-ref --verify --quiet refs/heads/feature/work-project-bundle-integrity-golden-b3-1-v1; then
    git switch feature/work-project-bundle-integrity-golden-b3-1-v1
  else
    git switch --track origin/feature/work-project-bundle-integrity-golden-b3-1-v1
  fi
  git pull --ff-only

  php -l src/Integration/DataExchange/ProjectBundleTransaction.php
  php -l src/Integration/DataExchange/ProjectBundleEntity.php
  php -l tests/integration/ProjectBundleIntegrityIntegrationTest.php
  php -l tests/project-bundle-integrity-b3-1-smoke.php
  php tests/project-bundle-data-exchange-smoke.php
  php tests/project-bundle-integrity-b3-1-smoke.php

  ./tools/check
  ./tools/check-integration
  ./tools/build-release

  unzip -tqq dist/core-blueprint-work.zip
  unzip -Z1 dist/core-blueprint-work.zip | grep -Fx 'core-blueprint-work/src/Integration/DataExchange/ProjectBundleTransaction.php'
  sha256sum dist/core-blueprint-work.zip
  (cd dist && sha256sum -c core-blueprint-work.zip.sha256)
  git diff --exit-code
  git diff --cached --exit-code
  git status --short
)
```

This is a **local candidate regression run**, not permission to install on live sites. If any test fails, stop; correct on the same featurebranch. No merge until separately authorized after all side-effect gates.

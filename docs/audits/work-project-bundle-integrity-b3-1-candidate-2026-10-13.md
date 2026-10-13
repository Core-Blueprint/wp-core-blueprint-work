# Work B3.1 | Project Bundle Database Integrity | implementation candidate

**Date:** 2026-10-13  
**Baseline:** Work `main` `9009577ab962025dbe43ad33ee94239b2864c044`  
**Branch:** `feature/work-project-bundle-integrity-golden-b3-1-v1`  
**Status:** TRANSACTIONAL DATABASE PATCH, TESTS AND EXTERNAL-EVENT GATE PENDING. **Not Golden, not accepted for merge or production.**

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

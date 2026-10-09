# Work Product Golden | A1 Mutation Integrity Candidate

**Status:** CANDIDATE / operator local validation required. **No merge/release GO.**
**Branch:** `feature/work-mutation-integrity-golden-a1-v1`.
**Base:** `main` `19160e25627e7f8131fe19b53e940f8f24548409`.
**Finding:** W-GA-002 from Product Golden Audit 2026-10-09.

## Scope implemented

- A Work-owned `WorkItemMutationTransaction` serializes mutations
  through `wp_posts.ID FOR UPDATE`. It uses `START TRANSACTION`
  where independent; an existing transaction gets a unique SAVEPOINT,
  never an implicit nested `START TRANSACTION`.
- `WorkItems::transition_status()` accepts an optional expected source
  status, validates actual status inside the post-row lock, writes using
  WordPress `update_post_meta` previous-value comparison and only
  fires its lifecycle event after successful local commit/release.
- `WorkItemMeta::set_status()` returns verified success/failure and
  checks completion timestamp and actor consistency, including clearing
  completion facts on reopen. DB failure or conflict returns false.
- `WorkItems::update()` and `save_editor()` now combine Work-owned
  metadata, assignments and optional status mutation within one
  guarded transaction/savepoint. Successful events follow the commit.
  `WorkItemMeta::details_match()` checks that fields actually persisted.
  Assignment child rows no longer start a nested transaction.
- Board drag posts `expected_status`, rejects stale changes with HTTP
  409. Table/List action forms post `expected_status`; Quick Edit and
  Gutenberg metabox updates use the optional combined status API.
  Existing Work capabilities, nonces, status transition rules,
  read contracts, UI layouts and Base remain unchanged.
- Existing `WorkItems::update( $id, $input )`,
  `save_editor( $id, $input )`, `transition_status( $id, $to, $actor )`
  signatures remain backward-compatible via optional parameters.
  No WordPress/Base API contract version bump.

## Scope limits / important guarantees

- Work-owned posts/meta/custom-table updates assume transactional
  InnoDB tables. In an existing outer transaction, Work manages only a
  SAVEPOINT: the owner retains final commit/rollback responsibility.
- Gutenberg persists WordPress title/content upstream before its
  Work-metabox callback. Work-owned metabox changes are atomic within
  this callback; the patch does not retroactively undo already-saved
  Gutenberg content after a metabox validation failure.
- WordPress `wp_update_post` can run third-party save hooks while the
  database transaction is open. External network side effects cannot
  be rolled back. A1 avoids firing *Work-owned* lifecycle hooks before
  the Work transaction succeeds; no claim of cross-plugin distributed
  atomicity is made.
- In the Board UI, an HTTP 409 leaves its existing Base Reorder error
  pathway in control. It does not auto-overwrite a different user's
  status. Bulk Work Item changes remain bounded and per-item; no
  global all-or-nothing bulk promise is made.

## Handbook-conformant validation and evidence

The A1 fix requires real WordPress/MariaDB validation. Work previously
depended on manually supplied Base PHPunit paths, contrary to the
approved First-Party Validation Standard. The A1 branch now implements:

1. **Level 1:** `./tools/check`. Includes warning-failing A1 source
   regression, Quick Edit/Board/bulk tests, shell syntax checks and a
   dedicated integration-runner conformance regression.
2. **Level 2:** `./tools/check-integration`. Work provisions a unique
   `/tmp/core-blueprint-tests/core-blueprint-work/run.XXXXXXXX/` WordPress
   7.0 (optionally 7.1) + matching wp-phpunit, stages the current Base
   checkout and Work source, then runs the full Time/A1 WordPress suite
   with the Base vendor PHPUnit. Only the dedicated disposable
   `core_blueprint_work_test` DB on local `cb-base-test-db` is reset.
   Work does not touch the Base `wordpress_test` database.
   Any fixture error, skip or incomplete test blocks the gate.
3. **Level 3:** `./tools/build-release` independently reruns Level 1
   **and** Level 2 before a customer ZIP can be emitted. Archive entries
   are sorted, normalized, independently regenerated and byte-compared.
4. **Independent artifact:** `unzip -tqq dist/core-blueprint-work.zip`,
   `sha256sum dist/core-blueprint-work.zip`, and
   `(cd dist && sha256sum -c core-blueprint-work.zip.sha256)`.
5. **Field:** Board stale-drag, Table/List status conflict, Quick Edit
   details+status, Gutenberg editor and completion/reopen. Separate
   operator merge approval remains mandatory.

The opt-in A1 XML and the full Time/A1 integration XML now share the
product-owned bootstrap. One A1 test intentionally injects an assignment
CHECK constraint failure to prove Work-owned rollback; a second A1
fixture uses two independent MariaDB sessions on a uniquely named
shared test-only table. It models the SQL CAS predicate and does not
directly execute concurrent WordPress HTTP requests. Time CAS fixtures
are included without relaxing their original invariants.

The established Base checkout must contain Composer-locked PHPUnit
9.6.36. The runner verifies exact local Base and Work source state,
logs both source SHAs, reuses only the established MariaDB helper,
serializes Work runs through `flock`, and rejects unsafe environment
overrides. No arbitrary local path exploration or manual PHPUnit
bootstrap export is part of the canonical operator workflow.


**Evidence state:** Remote source review and GitHub branch ancestry
verified. No local PHP lint, WordPress/MariaDB integration execution,
full suite or ZIP checksum can be claimed until the operator runs them.

**Acceptance:** All checks PASS, no new PHP notices/fatals, Board
stale-drag rollback, Admin status form stale conflict, Work Quick Edit
details+status persistence, Gutenberg meta save and completion/reopen
roundtrip. Only after explicit operator acceptance and separate merge GO
may Work main be fast-forwarded. Subsequent Product Golden work: A2
query scalability, B1 monolith splitting and B2/B3 QA.

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

## Tests

1. Mandatory standalone `php tests/work-item-mutation-golden-smoke.php`
   checks root transaction commit, ambient savepoint behavior,
   abort/rollback and source contract wiring.
2. Optional **real local WordPress MariaDB PHPUnit**:
   `phpunit -c tests/phpunit-work-item-mutation.xml.dist`.
   Requires existing Base/WordPress test bootstrap and `wordpress_test`
   or equivalent local test database. Uses connection-local temporary
   InnoDB child tables; a CHECK constraint injects an assignment failure
   and tests rollback of WordPress post/meta within PHPUnit's ambient
   transaction. Verifies stale source status rejection, completion/reopen
   invariants, combined edit/status mutation and savepoints.
3. Existing Board, Calendar, Quick Edit, Time CAS, billing, translation
   and full Work tests should be rerun unchanged on candidate.
4. Run `./tools/build-release`, `unzip -tqq`,
   `sha256sum dist/core-blueprint-work.zip` and
   `(cd dist && sha256sum -c core-blueprint-work.zip.sha256)`.

**Evidence state:** Remote source review and GitHub branch ancestry
verified. No local PHP lint, WordPress/MariaDB integration execution,
full suite or ZIP checksum can be claimed until the operator runs them.

**Acceptance:** All checks PASS, no new PHP notices/fatals, Board
stale-drag rollback, Admin status form stale conflict, Work Quick Edit
details+status persistence, Gutenberg meta save and completion/reopen
roundtrip. Only after explicit operator acceptance and separate merge GO
may Work main be fast-forwarded. Subsequent Product Golden work: A2
query scalability, B1 monolith splitting and B2/B3 QA.

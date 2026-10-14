# Work B2 Repository Architecture Golden Candidate

Date: 2026-10-14  
Repository: `Core-Blueprint/wp-core-blueprint-work`  
Branch: `feature/work-repository-architecture-golden-b2-v1`  
Approved base: Work main `5c820a789f0d99a734ef2c2fb3c2f722ad0c49e0`, B3.2 formally CLOSED.

## Intent and ownership

B2.1 extracts the existing Work Item assignments / relation child-table persistence into
`src/Repository/WorkItemLinks.php`. B2.2 extracts Work-owned validation, input normalization,
query meta filter construction and safe read projection into `src/Repository/WorkItemShape.php`.

`src/Repository/WorkItems.php` remains the **stable canonical API/facade**. All 19 public
method signatures are retained. The facade still owns create/update/editor orchestration,
source-reference compensation, transition CAS, transaction ownership, lifecycle event
timing and integration with the bounded SQL reader.

The extracted methods' bodies are preserved verbatim relative to the accepted B3.2
source except for visibility and deliberate delegation from `self::` into the
new internal classes. In particular:

- `WorkItemMutationTransaction::run` still owns Work Item locking and nested savepoints.
- Assignment writes remain inside the caller's already-established transaction when
  invoked by update/editor; the stand-alone assignment API wraps the canonical mutation.
- `WorkItemReadQuery` SQL paging, sorts, counts, query budget, preload and bounded hydration
  are unchanged. `WorkItemShape` consumes the same read data and returns the same keys.
- `WorkItems::create` continues to validate full source identity and compensate a
  failed identity relation with deletion of the created Work Item.
- Existing Work-managed permission/nonce, frontend visibility, Base event and editor
  contracts are not modified.

No SQL schema migration, new feature, Base change, public API change, frontend/Bricks
change or new external service is in scope.

## Regression contract

- `tests/work-b2-architecture-golden-smoke.php` compares 13 extracted method bodies
  with the exact accepted B3.2 Git source (blob `3c1c59dc44c66de7ef50e99a3450e582d79af23d`)
  and requires the complete 19-method public signature list to remain identical.
  It also checks explicit facade delegation and forbids nested transaction starts.
- Existing A1, D1, D2 and A2 source checks are re-pointed to the file that now
  actually owns their original behavior. No assertion is intentionally waived.
- `tests/integration/WorkItemArchitectureIntegrationTest.php` exercises real WP/MariaDB
  create + linked source reference + assignment + update + read projection + delete
  cleanup and rejects a partially specified source identity without retaining a post.
- Existing Work A1 rollback/CAS, A2 paging/sort/assignment and B3.1/B3.2 integration
  cases must continue to pass.

## Required operator acceptance

1. Verify branch and exact HEAD, tracked source clean, local Base source tracked clean.
2. `php tests/work-b2-architecture-golden-smoke.php`.
3. `./tools/check` (Level 1; PHP 8.4+, static smoke, JS, i18n).
4. `./tools/check-integration` (Level 2; WordPress 7.0, MariaDB 10.11.19;
   expected at least 29 tests from previously accepted 27 plus B2 two tests).
5. `./tools/build-release` (Level 3; reruns Level 1/2, packages all
   `src/` recursively into `dist/core-blueprint-work.zip`).
6. `unzip -tqq dist/core-blueprint-work.zip`, sidecar checksum verification,
   `unzip -Z1` checks for `WorkItemLinks.php` and `WorkItemShape.php`,
   fresh SHA-256 and clean tracked source.
7. Install only on sandbox after all gates green. Exercise Work Item creation,
   assignment changes, relations and source refs, Table/List/Board/Calendar,
   Gutenberg save, Bulk/Quick Edit, Time, recurrence and the Billing views.
8. Operator explicitly confirms stable sandbox and gives **separate merge GO**,
   then exact Work-main post-merge Level 1/2/3 validation.

No current runtime PASS is claimed. GitHub changes are source candidates only until
the operator runs the local integration and release gates. No CI, Actions, merge
or production deployment without their own authorization.

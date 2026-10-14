# Core Blueprint Work | Recurring Work Lifecycle & UI Golden Candidate

Date: 2026-10-14
Branch: `feature/work-recurrence-lifecycle-golden-v1`
Base: Work `main` at B2 merge `001645e79bad896f0f5782261aaf0967f00b4f93`

## Goal and behavior
Close the user-observed gap: the Run Generator Now disclosure touches the preceding table and Recurring Work rules cannot be deleted or recovered through a governed lifecycle.

- **Move to Trash** is available in list and edit. Soft trash records `trashed_at`, `trashed_by`, sets `is_active=0`, excludes rules from ordinary search and due-for-generation, and preserves the rule, assignments and complete occurrence ledger.
- **Trash** is a separate bounded list view; filtered/searchable. **Restore** clears trash metadata and restores as **inactive**, never silently resuming generation.
- **Delete Permanently** requires a separate confirmation screen, explicit user checkbox, a dedicated per-rule nonce and manager capability. The database operation locks the rule, verifies it is trashed with **zero** occurrence-ledger entries, then transactionally deletes assignments and the rule. Failed DB writes must roll back. Rules with *any* occurrence/claim/history (even if Generated column is 0) remain in Trash and cannot be permanently deleted.
- Already-created Work Items, their source identities, registered time, billing and audit/provenance must **never** be cascaded/deleted. Work event registry now has trashed/restored/deleted audits.
- New scheduler reservations are serialized with lifecycle changes using row-locking, and the generator checks trash status before and after claim. In-flight jobs may have begun before the operator's action; user-visible soft trash is not a cancellation mechanism for side effects already committed.
- The generator diagnostics retain semantic `details/summary`, gain `--cb-space-6` (24 px) separation, Base border/surface/radius tokens, and scoped classes replace touched inline styles.
- No changes to Base, WorkItems public facade, Time, Billing, CRM integration or UI renderer Golden snapshots.

## Migration and localization
- Additive Work schema version `2.2`: recurrence rules gain `trashed_at datetime NULL`, `trashed_by bigint unsigned NOT NULL DEFAULT 0`. Existing rows default to not trashed. No destructive backfill or SQL foreign key is introduced.
- Plugin version remains `1.0.0-rc1`.
- POT + six reviewed PO catalogs updated to include 22 new UI, error and audit strings.

## Test gates, NOT YET EXECUTED
- New `tests/recurrence-lifecycle-golden-smoke.php` is included in `./tools/check`. It checks schema and guards, no recurrence cascade, Trash UI and 24px scoped design-system spacing.
- New `tests/integration/RecurrenceLifecycleIntegrationTest.php`: 5 MariaDB tests for trash + inactive restore + scheduler filtering, permanent deletion with assignment cleanup, history preservation, and transactional rollback after injected SQL failure.
- Existing Recurrence foundation/workspace/scheduler, B3.2 transaction/CAS, B2 facade and all prior Level 1/2 tests must remain green.
- **Post-B2-merge Golden** on exact Work main is pending operator output (not an implementation blocker, but must be recorded before declaring B2 closed).
- Candidate operator sequence: `git fetch origin --prune`, `git switch --track origin/feature/work-recurrence-lifecycle-golden-v1`, verify SHA; `php -l` on touched PHP; `php tests/recurrence-lifecycle-golden-smoke.php`, `./tools/check`, `./tools/check-integration`, `./tools/build-release`, ZIP integrity/sidecar/files/SHA; then sandbox installation and functional acceptance before **separate merge GO**.
- Expected Level 2 count: 34 tests (previous 29 plus five lifecycle tests), assertions count to be established by real output.
- No CI, production deployment or Recurrence feature merge authorized/performed.

## Sandbox acceptance
- New inactivate/activate/edit remains stable in both themes; list includes Trash and filter routing.
- Trash from list and editor, Restore inactive, reactivate manually.
- Delete confirmation screen requires explicit checkbox and nonce.
- Generated rules with history must remain in Trash; preservation of existing Work Items/Time/Billing/links checked.
- Generator diagnostics has 24px top gap and consistent borders in Light and Dark.
- Concurrent generation and a lifecycle mutation should not leave orphan data or allow new reservations for trashed rules; report any in-flight race to resolve before Golden closure.

## Pre-test safety correction
- History existence predicate now fails closed if schema readiness fails or the occurrence-ledger SQL query returns an error. This prevents unsafe deletion if history cannot be checked. A dedicated fifth MariaDB test injects a history-query error without touching underlying permanent tables.
- Schema 2.2 adds indexed trash state `KEY trash_active_next (trashed_at,is_active,next_occurrence_on)`.
- Trash and normal views each retain their own Clear filters destination; the confirmation screen explains deletion refusal when history exists.
- Expected Level 2 test total: **34**. Source candidate remains untested pending operator Level 1/2/3.

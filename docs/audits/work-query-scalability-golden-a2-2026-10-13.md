# Core Blueprint Work | A2 Query Scalability Golden Candidate (2026-10-13)

## First local validation and targeted repair (2026-10-13)

**Operator tested initial source** `ec3d1f62c6010d97d422fe6e55eb73544d373f6a`:
- PHP syntax on new query, repository and A2 integration test: **PASS**.
- D2 operational fixture and A2 Query Golden source smoke: **PASS**.
- Full `./tools/check` Level 1: **PASS**, including existing
  Calendar, Board, Time and A1 mutation regressions.
- WordPress 7.0 / MariaDB 10.11.19 Level 2: **15 tests,
  828 assertions, one failure** in
  `WorkItemQueryScalabilityIntegrationTest::test_optional_profile_at_1k_10k_25k`.
  The four normal A2 behavior tests completed; failure occurred because
  the disabled benchmark made no Work Items but omitted the early
  `return` and proceeded to assert a 50-row benchmark page.
- The fail-fast operator shell stopped **before** Level 3 build.
  Thus **no A2 ZIP or checksum accepted** from this initial run.

**Repair:** the optional benchmark now returns after its non-skipped
placeholder assertion unless `CB_WORK_A2_PROFILE_SIZE` is explicitly
1000, 10000 or 25000. The source Golden smoke additionally guards the
early-return contract. This change is **test-only**; Work runtime/SQL,
Calendar and Base files were not changed.

**New candidate HEAD:** `11505f849c360564c357714567d7c8760a999340`.
**Level 1/2/3 on repaired HEAD remain PENDING**. Next operator gate
must re-run `./tools/check`, `./tools/check-integration`,
`./tools/build-release`, then `unzip -tqq` and SHA sidecar verification.
Run optional large datasets separately after normal Level 1/2/3 pass.

**Finding:** W-GA-001 (P1) from Product Golden Audit.  
**Base:** Work `main` `e25a816cee581be0e413f4f255b207bb22949020` (A1 CLOSED).  
**Feature:** `feature/work-query-scalability-golden-a2-v1`.  
**Status:** IMPLEMENTED AS FEATURE CANDIDATE / LOCAL LEVEL 1, 2, 3 PENDING / NO MERGE GO.

## Scope and invariants

- Replace unbounded `WP_Query( posts_per_page=-1 )`, full matching-ID `update_meta_cache()`, `usort()` and `array_slice()` with DB-side paging using `WP_Query` `posts_per_page`, `paged`, `found_posts` and a Work-only `posts_clauses` hook.
- SQL sort modes: workload priority (In Progress, Planned, other), due, scheduled, updated, title. Due/scheduled empty dates last; deterministic ID tie-break.
- Work-owned assignment filter is a scoped, prepared SQL join, no global `post__in` membership materialization. Existing WordPress `meta_query` preserves search, status, priority, project, service, work type, customer, billing, scheduled/due and Calendar OR filters.
- Group by post ID to protect from duplicate metadata joins without MySQL 8 `DISTINCT` and `ORDER BY` incompatibility.
- If a requested page is out of range, the first bounded query has no `found_posts`; one extra **single-ID** first-page probe restores accurate totals, with no full-match fallback.
- Prime only the selected page's post and metadata caches, and batch Work-owned assignment/relation links in two queries, instead of two per hydrated Work Item.
- Replace all-ID status counting with a SQL aggregate over managed Work CPT post statuses and first status meta record. Missing/invalid status maps to Planned.
- No changes to Work Calendar / Board JS, CSS, HTML, Base source, CPT schema or public APIs. Preserve frontend 500-candidate cap and Table/List/Board/Calendar return shapes. PHP 8.4+.

## Validation

- **Level 1:** `./tools/check` including D2 query fixture and A2 source contract.
- **Level 2:** `./tools/check-integration` on canonical isolated MariaDB `core_blueprint_work_test` and WP 7.0. New `WorkItemQueryScalabilityIntegrationTest`: bounded SQL LIMIT, total/page even beyond max, all five sort modes, stable ID ties, assignment join, batched links, grouped aggregate incl. invalid/duplicate/trash.
- **Level 3:** `./tools/build-release`, verify `unzip -tqq`, `sha256sum` and sidecar. No ZIP checksum is asserted before a real build.
- **Optional performance profiles** (only disposable Work test DB, never production):
  `CB_WORK_A2_PROFILE_SIZE=1000 ./tools/check-integration`
  `CB_WORK_A2_PROFILE_SIZE=10000 ./tools/check-integration`
  `CB_WORK_A2_PROFILE_SIZE=25000 ./tools/check-integration`
  Each run seeds 250-row batches with representative status and due metadata and prints workload/due/status_count SQL query counts, latency and incremental PHP peak memory. Profiles are **not run by default** and have no machine-independent latency pass claim.
- **Manual sandbox:** Table and List page 1/2 and out-of-range, five sorting modes, Calendar viewport and Board lane totals, assignee and customer filters, frontend resource queries. Check case/Unicode title collation behavior because MySQL site collation may order international titles differently from the historical PHP bytewise `strcasecmp()` comparator.
- **Security:** only Work isolated database is used in integration; SQL membership, meta keys and status filters use WPDB placeholders or Work constants; no unbounded runtime fallback. No CI/Actions or production deployment.

## Governance

A2 needs operator-visible Level 1/2/3 evidence, measured profile output, functional sandbox acceptance, and **separate explicit merge GO**. A1 remains CLOSED and unchanged. Overall Product Golden remains open for A2 and B1/B2 audit follow-ups.

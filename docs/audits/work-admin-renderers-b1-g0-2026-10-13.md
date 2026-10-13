# Work G0 + B1.1 | Table renderer exact-source extraction (2026-10-13)

**Implementation:** `feature/work-admin-renderers-golden-b1-v1`  
**Frozen base Work main:** `e5edffad2e8d934d8a38fcbc2e87513d5c403061`  
**Source-level original `Operations.php` Git blob:** `1473f8fa1aa8d38fb102019b47d768f421a962a8`  
**Status:** PATCH READY FOR OPERATOR VALIDATION, **NOT MERGED**. No Base, Calendar, production or CI changes.

## Design decision

Keep the first extraction as a cohesive **Work-owned renderer trait**,
`src/Admin/WorkItemTableRenderer.php`, composed into
`src/Admin/Operations.php`. This interim PHP trait preserves access
to existing private presentation utilities, translatable text,
nonce/field markup, CSS class names, JS selectors and URL/filter
helpers **without widening their visibility, adding callbacks, or
duplicating code**. This is **not the final standalone-class boundary**.
Follow-up B1 slices should extract shared presentation utilities and
migrate to discrete renderer classes only if equivalent tests permit.

Moved **exactly six** methods without changing their bodies:
`render_work_item_table`, `render_work_item_quick_edit_row`,
`work_item_table_sort_key`, `render_work_item_table_header`,
`work_item_table_columns`, `render_work_item_table_cell`.
The `Operations` class shrinks by about 407 lines; List, Board, Calendar
and the Work toolbar stay in `Operations` unchanged.

## G0 reproducibility contract

`tests/work-admin-renderer-g0-smoke.php` is included in Level 1.
It takes the mounted renderer trait implementation, reinserts it into
the exact former position inside `Operations.php`, and checks that
the resulting byte-identical source has original **Git blob SHA-1**
`1473f8fa1aa8d38fb102019b47d768f421a962a8`. SHA-1 here
is **only Git object identity**, not a security primitive; the
comparison certifies the source contents are identical to the
accepted baseline. Exactly six Table methods must exist in the trait,
none duplicated in `Operations`. The namespaced autoload path and
Table/Quick Edit selectors are verified as well.

Existing Table preferences, Table bulk, Quick Edit and cross-view
source-smoke fixtures are updated to inspect the **combined**
Operations shell and Work-owned Table renderer source. This is a
source-fixture adaptation to a new file boundary, not a relaxed
assertion. Existing Board/List/Calendar source checks remain intact.

## Operator gate

```bash
cd ~/Downloads/wp-core-blueprint-work
git fetch origin --prune
git switch --track origin/feature/work-admin-renderers-golden-b1-v1
git pull --ff-only
git rev-parse HEAD
php -l src/Admin/Operations.php
php -l src/Admin/WorkItemTableRenderer.php
php tests/work-admin-renderer-g0-smoke.php
php tests/work-item-table-preferences-smoke.php
php tests/work-item-table-bulk-smoke.php
php tests/work-item-inline-edit-smoke.php
php tests/work-items-ux-refinement-smoke.php
./tools/check
./tools/check-integration
./tools/build-release
unzip -tqq dist/core-blueprint-work.zip
sha256sum dist/core-blueprint-work.zip
(cd dist && sha256sum -c core-blueprint-work.zip.sha256)
```

If any test fails, do not install the ZIP and do not merge.
Only after full L1/L2/L3 PASS, verify on **sandbox only**:
Table column reorder/show/hide; density and alternating rows;
Table 2-page filters and sorting; selection/bulk actions;
Quick Edit success/conflict/no-reload; Table priority,
status, due, assignments and translations; Board/List/Calendar
non-regressions, keyboard, light/dark. The earlier independent
Base calendar modal asset-version discrepancy is not B1.

No release ZIP or checksum claimed until operator runs the build.
Post-acceptance **explicit separate user merge GO** required. B1
continues to the next renderer or shared-presentation seam only when
accepted, and no downstream Commercial Golden features are part of
this patch.

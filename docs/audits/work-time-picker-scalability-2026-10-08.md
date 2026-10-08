# WT-G-007: Work Time picker scalability candidate

Date: 2026-10-08  
Branch: `feature/work-time-picker-scalability-v1`  
Base: accepted `main` `3ac7fcae0fc912725cc456c0a22a7b88b23451b9`  
State: **candidate, local tests and operator acceptance pending; NO MERGE**

## Problem and architecture

- Timer, Manual Entry/Correction and Time Bulk Edit previously populated
  native dropdowns from `WorkItems::all(500)`.
- Managers cannot choose an item outside the first 500, unless the correction
  happens to pre-append its previously selected item.
- Tracker items were filtered *after* retrieving the first 500 global items,
  excluding legitimately assigned items with later ordering.
- `WorkItems::search()` materializes and sorts matching IDs before paging.
  Raising its limit would therefore increase memory/CPU work at Time page
  load. Do not change the global operational query in a Time-only patch.

This candidate replaces three dropdowns with the **existing Base ObjectPicker**:
`Pickers::time_work_item()`. This preserves canonical selected numeric IDs,
all existing Time admin-post authorization, and a native numeric ID input
when JavaScript is disabled. The Work Item source text is never trusted.

The new `WorkItemPickerSearch::results()` endpoint:

1. checks `CB_WORK_SCHEMA_VERSION`, current actor, manager/tracker role and
   schema readiness;
2. uses a WordPress-authenticated AJAX action and an existing Work picker
   nonce (no nopriv AJAX route);
3. limits search terms to 80 bytes and requires two characters;
4. searches WP posts by Work Item type, explicit managed post statuses,
   safely prepared title LIKE and deterministic ID tiebreak;
5. restricts trackers in SQL to **their own** assignments through the Work
   assignments table and refuses caller-supplied actor substitution;
6. returns at most 20 `id/label/meta` rows without hydrating all Work Items.

The Work assignment table already has `KEY user_id (user_id)`.
A substring `LIKE '%term%'` can still scan matching title rows. The
20-result limit bounds returned data but is not a promise of constant-time
query execution at arbitrarily large database sizes. Benchmark larger sites
before claiming unlimited scaling.

Time Bulk Edit interprets an empty ObjectPicker result as the existing
`0 = no change` contract, while nonzero targets remain manager-only and
fully validated. After AJAX replacement of the canonical entries list,
the Base picker is initialized again. No second picker implementation or
new CSS framework is introduced.

## Acceptance gates

- PHP/JS syntax, read-only picker contract and existing Time smoke/runtime
  regressions, 6 locale i18n check, full `./tools/check`.
- Existing WordPress PHPUnit test harness `tests/phpunit-time-cas.xml.dist`
  now discovers the optional `WorkTimePickerScalabilityTest`. It inserts
  520 disposable Work Item posts into the WordPress test transaction,
  checks bounded search for item #520 and verifies tracker assignment
  separation using a temporary InnoDB assignment table.
- Explicit operator runtime tests on coreblueprint.io:
  manager searches a late Work Item in Timer, Manual Entry and Bulk Edit;
  tracker finds only assigned items; editing a preselected older Work Item
  survives save and refresh; Bulk Edit no-change and JS/AJAX refresh operate;
  keyboard, Light/Dark and no-JS numeric fallback remain usable.
- On production sites, do **not** synthesize 520 Work Items for testing.
  Use the isolated PHPUnit test DB for the scale fixture.

A successful 520-item fixture proves searchability and bounded response.
It does **not** establish response latency at 10k+ items; a separate
performance budget and EXPLAIN/query profile can be added if needed.

## Commands after fetching candidate

```bash
php -l src/Time/WorkItemPickerSearch.php
php -l src/Admin/Pickers.php
php -l src/Admin/Time.php
php tests/time-work-item-picker-smoke.php
php tests/time-workspace-smoke.php
php tests/time-bulk-edit-smoke.php
node tests/time-bulk-visibility-runtime.js
node tests/time-bulk-submit-runtime.js
./tools/i18n/check
./tools/check

export CB_BASE_SOURCE_DIR="$HOME/Downloads/wp-core-blueprint"
export WP_CORE_DIR=/tmp/core-blueprint-wp
export WP_TESTS_DIR=/tmp/core-blueprint-wp-tests
export CB_PLUGIN_FILE="$WP_CORE_DIR/wp-content/plugins/core-blueprint/core-blueprint.php"
export WP_DB_NAME=wordpress_test WP_DB_USER=root WP_DB_PASSWORD=root WP_DB_HOST=127.0.0.1:3307
php8.4 "$CB_BASE_SOURCE_DIR/vendor/bin/phpunit" -c tests/phpunit-time-cas.xml.dist --do-not-cache-result

./tools/build-release
unzip -tqq dist/core-blueprint-work.zip
sha256sum dist/core-blueprint-work.zip
(cd dist && sha256sum -c core-blueprint-work.zip.sha256)
```

No merge, CI or publication is implied by this candidate.

# WT-G-006 | Two MariaDB Sessions and WordPress Actor Acceptance

Date: 2026-10-08  
State: **candidate on feature branch; operator execution pending**  
Repository: `Core-Blueprint/wp-core-blueprint-work`  
Branch: `feature/work-time-two-connection-cas-golden-v1`  
Base: accepted `main` `f6bd84e58c744795c7fb774170159e193774b6fe`

## Motivation

The merged Work Time repository implements atomic revision CAS corrections:
`UPDATE ... SET revision = revision + 1 ... WHERE id = ? AND revision = ?
AND ended_at IS NOT NULL`. The existing
`WorkTimeCasIntegrationTest` covers actual repository operations on one
connection's `CREATE TEMPORARY TABLE` fixture, including interleaved
bulk results and zero-second timer policy. That fixture **cannot** be
used as evidence for a second SQL connection because MariaDB temporary
tables are connection-local.

## This candidate

`tests/integration/WorkTimeTwoConnectionCasIntegrationTest.php` creates
**two independent `wpdb` connections** to the established local
`wordpress_test` database at `127.0.0.1:3307`. It asserts distinct
server-side `CONNECTION_ID()` values, and both clients read/update one
uniquely named, shared InnoDB test table.

Only when both exact environment guards match may the fixture create
`cb_wtg006_time_<16 hex>`. The table is deliberately not any WordPress or
Core Blueprint production table name; it exists briefly and is dropped
in PHPUnit `tear_down()`. DDL uses the independent connection to avoid
committing WordPress PHPUnit's own transaction. A hard termination before
teardown can leave this uniquely prefixed local test table behind; never
silently delete arbitrary tables as cleanup.

The SQL fixture intentionally reproduces the same query shape and
zero-second conditional WHERE as the production repository. **It does not
replace the existing integration test invoking the actual repository.**
Both tests must PASS, providing complementary evidence without
introducing a production test seam or changing release runtime code.

The new tests verify:

- A and B read the same committed revision; A saves a full correction.
  B's stale Quick Edit is rejected without losing A's note or time.
- With roles reversed, B saves a Quick Edit. A's stale Full Edit cannot
  overwrite it.
- A preflights two Bulk Edit rows, B updates one after preflight, and A
  receives exactly one success and one rejected stale update.
- Cross-session zero-second timer corrections preserve original instants,
  reject stale writes, and reject relocation to a different zero range.
- Two simulated manager identities can correct a Time Entry. Assigned
  tracker A may correct only their own entry; unassigned tracker B may not
  impersonate tracker A. WordPress users/posts live in PHPUnit's
  rollback transaction; Work relation tables are connection-local
  `TEMPORARY` fixtures for permission tests.

The tests cover **two independent DB sessions with deterministic
interleaving**, not a truly parallel HTTP load test or concurrent InnoDB
lock-wait stress test. They cannot by themselves prove AJAX-to-toast
presentation across real browsers. Existing Quick/Bulk JS regressions
and operator-accepted WordPress runtime remain authoritative there.

## Non-goals and change boundary

- Production Work PHP/JS and Base are unchanged.
- Existing Time Quick/Bulk Edit, Timer and user-facing UI unchanged.
- No WP-CLI installs, no site migrations, no CI or Action executions.
- No production data or WordPress user creation outside the local test DB.
- No merge or release without explicit operator acceptance and GO.

## Operator validation (terminal-safe)

```bash
cd ~/Downloads/wp-core-blueprint-work
git status --short
git fetch origin --prune
git switch --track origin/feature/work-time-two-connection-cas-golden-v1
git pull --ff-only
git rev-parse HEAD

php -l tests/integration/WorkTimeTwoConnectionCasIntegrationTest.php
php tests/time-golden-two-connection-smoke.php
./tools/i18n/check
./tools/check

export CB_BASE_SOURCE_DIR="$HOME/Downloads/wp-core-blueprint"
export WP_CORE_DIR=/tmp/core-blueprint-wp
export WP_TESTS_DIR=/tmp/core-blueprint-wp-tests
export CB_PLUGIN_FILE="$WP_CORE_DIR/wp-content/plugins/core-blueprint/core-blueprint.php"
export WP_DB_NAME=wordpress_test
export WP_DB_USER=root
export WP_DB_PASSWORD=root
export WP_DB_HOST=127.0.0.1:3307

php8.4 "$CB_BASE_SOURCE_DIR/vendor/bin/phpunit" \
  -c tests/phpunit-time-cas.xml.dist --do-not-cache-result

./tools/build-release
unzip -tqq dist/core-blueprint-work.zip
sha256sum dist/core-blueprint-work.zip
(cd dist && sha256sum -c core-blueprint-work.zip.sha256)
```

Do not run the final build if earlier checks are red. These commands do not
activate `set -e` in the operator's interactive shell.

## First operator run and fixture correction

Initial operator run on the first candidate: PHPUnit 9.6.36 ran six tests,
**four passed and two failed** (608 assertions). Both failures occurred in
the fixture setup: a separate WordPress `wpdb` connection B could not read
the table that `wpdb::query()` on connection A had apparently created.
The SQL-error path returned an empty value and made the initial visibility
assertion fail. No production repository or application behavior was implicated.

The fixture now forces `wordpress_test` selection and autocommit in both
independent MariaDB sessions, verifies both `DATABASE()` results, performs
test-only InnoDB DDL directly over the existing `mysqli` connection A,
checks visibility through each direct `mysqli` connection, and uses the
same raw handle for explicit fixture cleanup. A failure now identifies the
offending session and reports its MariaDB error instead of silently treating
a failed SELECT as zero rows. The source-contract smoke also enforces these
additional environment and visibility guards.

**Updated candidate is not yet revalidated by PHPUnit.** Wait for a new
six-test run before any claim of WT-G-006 technical completion. Successful
standalone tests are not a substitute for the full `./tools/check` and
artifact SHA verification.

## Decision after evidence

Wait for operator-provided test output, canonical checks and package SHA.
If all pass, WT-G-006 can be classified as **technical two-session CAS
acceptance** without requiring two real people. Any outstanding simultaneous
HTTP concurrency/load claims must remain separate and explicit.

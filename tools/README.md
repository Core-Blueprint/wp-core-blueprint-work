# Core Blueprint Work: local Golden validation and release

This repository follows the approved Core Blueprint Engineering Handbook
First-Party Validation v1 and First-Party Local Integration Runbook.

All execution belongs to **one exact Git SHA** and never authorizes a merge,
CI/Actions execution, publication, or production deployment.

## Canonical operator steps

```bash
cd ~/Downloads/wp-core-blueprint-work
git status --short
git rev-parse HEAD

./tools/check
./tools/check-integration
./tools/build-release

unzip -tqq dist/core-blueprint-work.zip
sha256sum dist/core-blueprint-work.zip
(cd dist && sha256sum -c core-blueprint-work.zip.sha256)
```

- **Level 1** `tools/check`: PHP/JavaScript syntax, all Work-owned source
  regressions, six translation catalogs, source-level integration-runner
  conformance. It is read-only on release-visible source; it **does not**
  invoke the release builder.
- **Level 2** `tools/check-integration`: provisions disposable WordPress
  7.0 + matching wp-phpunit under
  `/tmp/core-blueprint-tests/core-blueprint-work/run.XXXXXXXX/`,
  stages Base and Work, and executes all Work-owned WordPress/MariaDB
  integration fixtures using Base's locked PHPUnit toolchain. Mandatory
  skip/incomplete/error -> nonzero.
- **Level 3** `tools/build-release`: **reruns Level 1 and Level 2**
  before customer packaging. Builds `dist/core-blueprint-work.zip`
  and its SHA-256 sidecar; reproduces and byte-compares the normalized
  archive independently. An unavailable integration gate is **BLOCKED**,
  never silently accepted.
- **Field acceptance**: operator test install of the exact artifact and
  Work A1 affected flows; separate explicit merge GO required.

## Integration infrastructure and safety

The canonical local baseline is PHP 8.4+, WordPress 7.0 (optional 7.1)
and the already provisioned Docker container `cb-base-test-db`
(MariaDB 10.11.19, localhost `127.0.0.1:3307`).

Only the isolated `core_blueprint_work_test` database is reset for Work.
It is **disposable** and must not contain business, customer, staging or
production data. The runner refuses other DB names, external hosts,
unexpected container images and arbitrary temporary roots. A product
lock serializes runs sharing this database. Base's `wordpress_test`
database is never reset by the Work runner.

The runner normally needs **no manually exported**
`WP_CORE_DIR`, `WP_TESTS_DIR`, `WP_DB_NAME`, `WP_DB_HOST`
or `CB_PLUGIN_FILE`. It requires the established local Base checkout
at `~/Downloads/wp-core-blueprint` with its Composer-locked
`vendor/bin/phpunit` already installed. Advanced overrides:

```text
CB_TEST_WP_VERSION=7.0|7.1
CB_TEST_BASE_SOURCE=/absolute/path/to/pinned/base-checkout
```

The canonical Work test root and local DB host/user/password are fixed
during this Golden hardening phase to prevent accidental destructive
configuration. The runner downloads only pinned WordPress and
wp-phpunit version pairs; it does not clone unknown Base sources.

The Level 2 runner's temporary runtime and its tables are not part of
the customer ZIP. If interrupted, an abandoned
`/tmp/core-blueprint-tests/core-blueprint-work/run.*` directory may
need careful operator cleanup; never indiscriminately wipe `/tmp`.

## Translation and package boundaries

The six required source locales are nl_NL, de_DE, fr_FR, es_ES, it_IT
and pt_PT. Reviewed PO source is read-only for release validation;
MO files are generated in temporary customer staging.

The release builder packages only Work runtime files under
`core-blueprint-work/` and fails on development-only paths such as
`.github`, `tests`, `tools`, `docs`, `vendor`, `build` and
`dist`. SHA-256 must match the `.sha256` sidecar.

**Current A1 branch state:** Level 2 source contract is implemented,
but real WordPress/MariaDB execution, complete releasebuild and field
acceptance are pending operator evidence. Do not label Product Golden
PASS until these gates actually succeed.

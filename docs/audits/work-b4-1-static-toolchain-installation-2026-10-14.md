# Core Blueprint Work B4.1 | isolated local QA toolchain

Date: 2026-10-14  
Scope: approved installation of developer-only PHPStan/WordPressCS into a **sibling directory**. No change to runtime Work plugin, Base, CI or production.  
Accepted Work `main`: `322436dddefee833b629e3294c38e6daca4c684f`.  
Audit branch: `audit/work-b4-security-static-qa-v1`.

## Operator approval and trust

The user explicitly authorized a local isolated tool installation on 2026-10-14. Composer installation contacts its configured repositories, typically Packagist/GitHub; it is not an external code-analysis service. Run on the operator's own machine, not through GitHub Actions. Never run `composer global` and never put `vendor/` into Work or Base.

The setup script `tools/audit-b4-qa-setup` writes:
- `~/Downloads/core-blueprint-qa-tools/composer.json`
- `~/Downloads/core-blueprint-qa-tools/composer.lock` (resolved versions; keep for reproducibility)
- `~/Downloads/core-blueprint-qa-tools/vendor/`
- `~/Downloads/core-blueprint-qa-tools/phpstan.neon` with **WordPress 7.0** symbols, Work source and Base symbol scanning, initial level **2**, cached only in the QA workbench.
- `~/Downloads/core-blueprint-qa-tools/reports/`.

The exact top-level dependency constraints are:
- `phpstan/phpstan: 2.2.14`
- `szepeviktor/phpstan-wordpress: 2.0.4`
- `php-stubs/wordpress-stubs: 7.0.1`
- `squizlabs/php_codesniffer: 3.13.5`
- `wp-coding-standards/wpcs: 3.4.1`.

Composer invokes `install --no-plugins --no-scripts`; WordPress CodeSniffer standards are registered via explicit isolated vendor paths, not Composer plugin execution. The setup script refuses to overwrite a pre-existing QA manifest or lockfile. No Work source change is performed.

## Local execution

```bash
cd ~/Downloads/wp-core-blueprint-work
git status --short
git fetch origin --prune
git switch audit/work-b4-security-static-qa-v1
git pull --ff-only origin audit/work-b4-security-static-qa-v1
git rev-parse HEAD

bash -n tools/audit-b4-qa-setup
bash -n tools/audit-b4-static

# This is the explicitly approved first Composer download.
bash tools/audit-b4-qa-setup

# Baseline runs are read-only against Work and Base.
bash tools/audit-b4-static
```

If Composer resolution fails (including a missing XML PHP extension), **stop**; do not use `--ignore-platform-reqs`, `--ignore-platform-req`, unstable branches, `composer global` or an unreviewed alternative. If the setup fails after creating `composer.json`, do not repeatedly rerun the setup; inspect the partial workbench and run a controlled `composer install` only after verifying its manifest and environment.

## Report semantics

`tools/audit-b4-static` saves:
- `~/Downloads/core-blueprint-qa-tools/reports/work-phpstan.txt`
- `~/Downloads/core-blueprint-qa-tools/reports/work-phpcs-summary.txt`.

The script prints the analyzer exit codes separately, allowing both analyzers to run even if the first identifies violations. **Nonzero counts are the initial evidence, not automatic grounds to rewrite code wholesale.** The initial level-2 PHPStan and WordPress-Extra checks are not the final comprehensive security audit. PHPCS is never invoked with `phpcbf` (no auto-fix). Classify findings into security/correctness, missing WordPress/Base symbols, deliberate WordPress style deviations and actionable coding standards. Only justified findings may become B4.2 patches or time-bounded documented exceptions.

## Acceptance

B4.1 tool installation: **await operator output**. No Composer packages have been installed by the assistant on the operator's host; repository scripts alone do not establish success. No static analyzer baseline has been measured yet. Do not mark B4.1 or B4.2 PASS without the operator's reports. Release ZIP excludes tools and audit docs.

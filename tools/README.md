# Core Blueprint Work — local launch gates

GitHub Actions are not part of the launch evidence path for Work.

Run release evidence only from the exact candidate SHA in a clean local checkout with PHP 8.4+, WP-CLI i18n, gettext, Node, zip/unzip and sha256sum available.

## Required order

1. Ensure the canonical translation sources exist and are reviewed:
   - `languages/core-blueprint-work.pot`
   - `languages/core-blueprint-work-nl_NL.po`
   - `languages/core-blueprint-work-de_DE.po`
   - `languages/core-blueprint-work-fr_FR.po`
   - `languages/core-blueprint-work-es_ES.po`
   - `languages/core-blueprint-work-it_IT.po`
   - `languages/core-blueprint-work-pt_PT.po`
2. Run `tools/i18n/check`.
3. Run `tools/check` for PHP/JS lint and the complete isolated regression suite.
4. Run `tools/build-release`.
5. Preserve the emitted ZIP SHA-256, remove the generated `build/` directory, and run `tools/build-release` again from the same exact Git SHA.
6. The two ZIP files must be byte-identical and their SHA-256 values must match exactly.
7. Only that exact package may proceed to Chris staging.

`tools/build-release` packages only the runtime allowlist under the canonical `core-blueprint-work/` root and generates MO files from the reviewed PO sources during release staging. Development-only paths such as `.github`, `tests`, `tools`, `docs`, `vendor`, `node_modules`, `build` and `dist` are rejected from the ZIP.

# Core Blueprint Work — M1 Golden Readiness Source Audit

Date: 2026-10-29
Repository: Core-Blueprint/wp-core-blueprint-work
Branch: feature/work-m1-work-items-complete-20261029
Reviewed baseline: 63d884a4db00f0f31c8d9a09a93c513f97d17238
Corrective source commits:
- f9dd1554035d189258ae9dbea010ea48acb4fc6f
- 830ad64ed2339f0cf7ef4972f1ebe097227f15cd

**Audit status: SOURCE REVIEW DOCUMENTED; LEVEL 1 EXECUTION HOLD.**
**Release/build: HOLD. WordPress FIELD: HOLD. Golden: HOLD. Merge/main: HOLD.**

This report is a source inspection of the GitHub branch and the registered regressions. It is NOT evidence of executing PHP/Node tests, the WordPress/MariaDB integration suite, the customer ZIP builder, or a browser FIELD acceptance.

## Frozen contracts checked

- `src/Admin/Operations.php` still has its exact protected Git blob `92a4814f24b43f96d16e3d96ce61a49ee9f21500`.
- WorkItemViewBridge remains an authenticated, capability- and nonce-guarded GET-only projection of `Operations::render_work_items()`, not a renderer fork.
- Table/List/Board/Calendar still originate from the canonical PHP presentation and original Work-owned scripts.
- Four quick/status/bulk POST actions share the existing `OperationalActions` handlers for native admin-post and authenticated admin-ajax, with capability, nonce, repository and Audit behavior retained.
- The Work-native Gutenberg editor and other existing editor/navigation fallback remain available pending M2/M4; no Base/schema/editor mutation was introduced.
- `tools/check` includes the M1 filter, preferences, mutations, status synchronization, read recovery, modal parity, and UWE canonical runtime regressions.

## Confirmed source audit findings and corrections

### F1 — Detached Calendar private HTML survives GET denial

Before correction, a 401/403 GET cleared the canonical destination but not a Base Calendar dialog hosted outside the Work DOM. An already-open dialog could continue to display previously authorized private day details.

Correction in `f9dd1554`: Work-owned Calendar dialogs are explicitly marked with the parent Work panel ID. On unauthorized GET, the canonical mount removes only the matching detached dialog's private content, leaving unrelated Base dialogs and Base-owned close/focus mechanics untouched. The canonical runtime regression now checks owned purge and unrelated-dialog isolation.

### F2 — Slash shortcut can override dialog focus

Before correction, the Work global `/` shortcut ignored direct input/select/textarea targets but not an open Base modal or nested editable descendants.

Correction in `f9dd1554`: suppress the shortcut when a modal dialog is open, or when the event target belongs to an input, textarea, select, contenteditable subtree or textbox. The Calendar/shortcut runtime regression checks modal and rich-text behavior.

### F3 — Hidden-tab denied POST leaves cached Work HTML

Before correction, an HTTP 401/403 mutation only triggered reauthorization if the Work tab was currently visible. A rejected request finishing after switching to Projects/Services could leave cached, previously authorized Work DOM in memory.

Correction in `830ad64e`: mutation denial invalidates Work's canonical cache immediately through the same `clearUnauthorized()` fail-closed path whether the Work tab is visible or hidden. A visible tab then performs the existing guarded GET; a hidden tab waits for normal activation to re-read. The mutation runtime regression checks immediate invalidation without hidden-tab network refresh.

## Coverage and execution requirements

| Audit area | Source review | Actual execution |
| --- | --- | --- |
| Protected G0 renderer blob | exact SHA verified on GitHub | local Git blob check still required |
| Canonical route/filter/paging/Back-Forward/retry | existing source and regression cases reviewed | Node/PHP Level 1 pending |
| Dynamic mount/unmount / double handlers | single owned lifecycle and AbortControllers reviewed | Node/DOM and FIELD pending |
| Quick Edit, bulk edits, status and partial success | same Work-owned handlers, action allowlist, FormData, server confirmation | Level 1/2 and FIELD pending |
| Calendar detached modal, Board counts/status | actual module events and new denied-read purging reviewed | Level 1 and FIELD pending |
| 401/403 and nonce/capability | guarded source contracts; B4.2 negative admin-post integration fixture and bridge permission fixture exist | WordPress Level 2 negative tests and FIELD pending |
| Keyboard/mobile/dark/light | view order, focus and narrow-row CSS source reviewed | manual browser/accessibility FIELD pending |
| ZIP determinism and six locales | `tools/build-release` contract reviewed; it reruns Level 1 + 2 | Level 3 and checksum pending |

### Known acceptance gaps

- A source-inspection report cannot certify that all existing PHP/Node regressions pass. The product owner must execute `./tools/check` on the exact corrective branch SHA before this readiness audit can be accepted.
- Current negative WordPress security tests explicitly exercise admin-post mutation guards and view-bridge permissions; independent authenticated admin-ajax nonce/permission denial should also be checked in the M1 WordPress acceptance. Both transport registrations point at the same server methods, but source parity is not a substitute for runtime proof.
- Actual narrow viewport, high zoom, keyboard operation, screen-reader feedback, Base Calendar modal lifecycle and light/dark rendering require browser FIELD evidence.
- A source pass is not the Full Golden milestone: follow Level 1 with Level 2, Level 3 and a single comprehensive WordPress FIELD pass; do not merge without explicit owner GO.

## Exact operator execution order

### A. Source audit gate — run now, NO WordPress build

```bash
cd "$HOME/Downloads/wp-core-blueprint-work"
git fetch origin --prune
git switch feature/work-m1-work-items-complete-20261029
git pull --ff-only origin feature/work-m1-work-items-complete-20261029
git rev-parse HEAD
git status --short
git hash-object src/Admin/Operations.php

php tests/work-m1-read-recovery-smoke.php
php tests/work-m1-modal-action-parity-smoke.php
node --experimental-default-type=module tests/work-uwe-03c2-runtime.mjs
node --experimental-default-type=module tests/work-m1-modal-action-parity-runtime.mjs
node --experimental-default-type=module tests/work-m1-mutations-runtime.mjs
./tools/check
```

The protected `git hash-object` output must be `92a4814f24b43f96d16e3d96ce61a49ee9f21500`. Record precise branch HEAD, Level 1 output and any failure before authorizing the next gate.

### B. After source audit acceptance only: mandatory Golden + FIELD

```bash
cd "$HOME/Downloads/wp-core-blueprint-work"
./tools/check-integration
./tools/build-release
unzip -tqq dist/core-blueprint-work.zip
(cd dist && sha256sum -c core-blueprint-work.zip.sha256)
sha256sum dist/core-blueprint-work.zip
git rev-parse HEAD
git status --short
```

`tools/build-release` itself reruns Level 1 and 2 before building `dist/core-blueprint-work.zip`; the separate Level 2 call above is useful when reviewing failures independently. The isolated WordPress/MariaDB test runner resets ONLY its documented disposable test database. Never use it against customer, staging or production data.

**Next gate:** owner reports the exact Level 1 result and protected blob verification. Until this evidence exists, no ZIP, WordPress FIELD test, milestone Golden PASS or main merge.

# Work M2/M4: Workspace boot, cache and navigation consolidation

Date: 2026-10-29
Branch: `feature/work-m2-unified-editors-20261029`
Classification: coordinated SOURCE remediation, not FIELD acceptance.

## Root-cause audit

1. WordPress browser evidence: `Failed to resolve module specifier "@cb-core/object-picker"` and `data-cb-work-uwe-mounted=NO`, leaving the PHP fallback collection visible in every view. Removing the bare import fixed the *source* at the sandbox URL, but a fresh cache-busted GET does not prove which cached module source the browser executed.
2. Release packaging normalized **all** asset file mtimes to January 1980 to make the ZIP reproducible. PHP's `filemtime()` version for the workspace entry and unchanged relative `./editors.js` child URL could therefore reuse older browser module cache keys across builds.
3. The previous navigation still displayed old Work Items, Projects, Services, Time, Service Operations, Recurring Work and Work Types menu entries. Unified Workspace was registered but hidden. This was an intentionally conservative preview strategy, not the approved final user experience.

## Coherent source repair

### Immutable workspace ES-module graph

`tools/prepare-workspace-modules.py` runs **only on the temporary release stage**. It follows the Work app's explicit relative import graph, rewrites each nested relative module import to a deterministic content-hashed file name, and rewrites the packaged `app.js` imports to those immutable filenames. Source checkout assets are unchanged. It fails closed on missing/unexpected modules or cycles. The packaged `app.js` now receives a dynamic `hash_file('sha256')` WordPress asset version, so a changed child changes the parent's bytes and the entry URL. Workspace and editor CSS versions also use content fingerprints. ZIP timestamps may remain normalized without causing stale ESM URLs.

The **M1-frozen** root module dependency array is unchanged, Base Modal and optional Object Picker remain Base-owned and correctly enqueued, and no Base code is modified. This does not introduce an alternate modal, picker, or data renderer.

### Two visible Work menu entries

Only `Overview` and `Work` are visible for managers. `Work` now opens `core-blueprint-work-workspace`. Existing WordPress CPT and operational routes stay registered with their original capabilities but are hidden as contextual routes. Time-only users continue to land in Time with their original minimal menu. The WordPress active submenu mapping now points all manager Work contexts at the visible `Work` entry. The Overview's Work-queue links point at the unified workspace.

While M3 parity is incomplete, **Time**, **Service Operations** and **Recurring Work** remain directly accessible as contextual actions inside Work. Existing Gutenberg advanced editor routes remain intact. This is a navigation migration, not an early deletion of domain functionality.

### Quality gates

- `tests/work-immutable-module-graph.py` tests deterministic staged graph generation, transitive relative imports, change propagation from `editors.js` to `app.js`, and fail-closed missing files. Added to Level 1.
- `tests/work-ux-00a-navigation-smoke.php`, `tests/work-ux-00c-experience-runtime.php` and `tests/work-uwe-03a-shell-smoke.php` are **updated to the explicitly requested M4 visible navigation contract**, not silently bypassed. They still assert manager permissions, registered native routes, contextual hiding, Time-only role isolation and Base Admin Theme integration.
- `tests/work-m2-workspace-view-routing-smoke.php` now enforces four same-page view links and contextual M3 operations access, with no legacy Work Items menu escape hatch.
- B2 Golden, A1 mutation, D1.2 WordPress-native storage and protected `src/Admin/Operations.php` are not modified.

## Acceptance

SOURCE staged only. Run canonical i18n, Level 1/2, release build and archive checksum. Do not infer FIELD from a successful build. Sandbox FIELD must prove the actual installed packaged `app.js?ver=<hash>` imports content-addressed workspace modules, has no unresolved import specifier, and mounts four distinct views in-place. Then validate M2 modal CRUD, Base pickers, permission failures, keyboards, responsiveness and Time/Service Operations/Recurring contextual access.

No change to Work main, Base, production, schema, or public release. This stage does **not** declare M3 integration complete or M2 FIELD Golden.


## UWE-03C.2 source Golden compatibility — 2026-10-30

The operator's M2/M4 release run on `8ecbc521e6cce9d9c8bcb3b30f68425db346570a` passed the focused Overview fixtures, immutable ESM graph and earlier Level 1 gates, including manager navigation and UX-00C role contracts. It then stopped at `UWE-03C.2 canonical view mounting FAIL: canonical Work Item views declared once using existing translations and native fallback`.

Root cause: the old UWE-03C.2 source test asserted `Menu::WORK_ITEMS_SLUG` must appear anywhere in `UnifiedWorkspace.php`, relying on an intentional old native Work Items link. Following the owner's explicit M4 navigation promotion, only Overview and Work remain visible; the primary four view URLs must not redirect to old Work Items. The unified Workspace retains SSR as an accessible **rendering fallback**, not a native **navigation escape**.

The adjusted source test keeps all canonical view IDs/translations, existing SSR fallback collection, protected WorkItemViewBridge, request serialization/cancellation, unsafe-script rejection, immutable G0 renderer and the Level 1 runtime registration checks. It replaces the contradictory old-native-route assertion with stronger checks that Table/List/Board/Calendar URLs target `Menu::UNIFIED_WORKSPACE_SLUG` in the Work Items section, and the separate contextual Time/Service Operations/Recurring Work actions remain reachable.

**Source-only compatibility repair.** Does not modify the PHP renderer, router, Base, underlying B2/A1/D1.2 Golden source, schema or Work `main`. Full PHP/Node Level 1, WordPress Level 2, packaged ZIP, archive checksum and WordPress FIELD require operator revalidation on the new source commit.

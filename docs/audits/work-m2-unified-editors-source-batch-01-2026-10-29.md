# M2 Unified Editors — source batch 01 (2026-10-29)

Repository: `Core-Blueprint/wp-core-blueprint-work`
Branch: `feature/work-m2-unified-editors-20261029`
Parent M1: `e62c80525eb01b6974198228b04135b553ac86db`
Status: **INTERNAL SOURCE CANDIDATE. NOT GOLDEN. NO CUSTOMER BUILD OR FIELD PROMOTION.**

## Intent and ownership

M2 migrates the existing Work Item, Project, Service and Work Type editors into the already accepted Unified Workspace. No new persistence engine, data schema, Base modal implementation or WordPress route is created. Native editors remain reachable until each domain reaches feature parity.

This first internal code batch deliberately implements only **safe scalar create/edit** of Work Items and Projects, using the **existing** `PublicApi\WorkItemActions::create/update` and `PublicApi\ProjectActions::create/update` contracts, including their repository normalization, mutation lifecycle and audit/deferred events. The admin AJAX adapter validates method, capability, nonce, entity, scalar field allowlist, date syntax and optimistic source `updated_at`. On 409 stale, it must not claim Save. The Work UI uses the public `@cb-core/modal` runtime with Workspace size and Expand. Successful saves re-read the visible workspace via its existing canonical endpoint; no browser reload. Actions in the old native page are unchanged.

## Explicit non-lossy scope

- Work Item: title, Project, Service, Work Type, priority, estimate, scheduled and due dates. Existing description/serialized Gutenberg blocks, customer identity, assignments, billing, status lifecycle and source provenance are never accepted in this transport and are retained by existing domain actions.
- Project: title, start and due dates. Existing customer/context ownership and Gutenberg description are preserved by the canonical Project API. New quick-created Projects use Internal context only.
- Service: **not implemented in batch 01**. Pricing, tax modes, post_content and Service Operations coupling must not be rewritten without controlled parity. Native Service editor remains available.
- Work Type: compact Base modal now provides **create** and **activate/deactivate** through the same existing `OperationalActions` handlers and nonce/capability/audit contracts as native admin-post. Rename is unsupported by the current canonical Work Type repository and remains intentionally unavailable. The native Work Types editor remains available.

For advanced editing, the dialog deliberately links to the existing native editor. This is an **intermediate internal source slice**, not the final M2 user promise.

## Next internal batch before milestone build

1. Complete secure Service create/edit via its canonical post and pricing paths, with verified metadata read-back and audit/rollback as needed. Do not invent an ungoverned second service store.
2. Finalize compact Work Type acceptance for create/activate/deactivate (implemented) and explicitly decide whether a governed rename API is necessary; do not silently bypass the current `WorkTypes` repository.
3. Complete richer Work Item / Project fields and customer chooser without corrupting Gutenberg blocks; native editor fallback until proven.
4. Negative-role/nonce/CSRF/stale/concurrency tests; true DOM focus/dismiss/dirty/error tests; repeated reopen after save; Base dark/light and narrow viewport.
5. Six-locale canonical i18n catalog updates; Full Level 1/2/3, deterministic ZIP/SHA, then **one** comprehensive WordPress FIELD acceptance. Do not run operator ZIP per internal commit.

Do not merge Work main, modify Base, run CI/deploy, change schema or remove legacy editor routes under the current authorization.

## Batch 01 continued: source evidence

- Work Type creation and active-state changes use the existing nonce-scoped `OperationalActions` callbacks, now also registered for authenticated AJAX. Native form redirects are unchanged; AJAX gets a scoped JSON acknowledgement. No parallel Work Type repository was created.
- Both new Work editor modal bodies are tagged with the owning Unified Workspace panel ID, so the existing Work denied-read purger can remove detached private modal content. Denied editor POST also discards private fields rather than leaving them editable.
- New M2 UI is an **internal source candidate only**; browser-level execution, updated translations, PHP/Node Level 1, WordPress Level 2, release build, checksum and M2 FIELD remain **OPEN**.
- Work Item and Project detail operations still intentionally use fallback native editors for Gutenberg content, customers/CRM and other advanced fields. No M2 Golden acceptance is implied.

## M2 internal batch 02 — Service editor and Gutenberg content preservation

**Scope:** first governed Service editor inside the Unified Workspace, price controls and non-destructive partial Work Item/Project updates. Same M2 branch, no operator ZIP required per batch.

- Added `PublicApi\\ServiceActions::save/get` as the **single Work-owned adapter** for the existing Service CPT and `Content\\ServicePricing` contract. An InnoDB `WorkSqlTransaction` uses a row lock for existing services, version hash over title/content/price/post-modified, existing price model/tax validation, readback comparison and rollback on uncertain writes. Audit uses the existing Service pricing event; lifecycle notifications remain Work-owned. This is **not** a new Service database/table or generic update system.
- The Service modal edits the existing title and complete supported pricing subset (model, amount, currency, recurring period, tax treatment, tax-rate choice), using a Base workspace dialog. The current historical VAT rate can remain selected, but new rate changes must choose an available rate. The original Service post_content is not passed through a plaintext editor; advanced editing remains on the native WordPress page.
- Work Items and Projects can change scalar metadata without forcibly sending their existing Gutenberg `post_content` through `wp_kses_post` during a partial update. The repository now explicitly changes post_content **only when the caller provides description**. New Level 2 tests check Work Item/Project/Service preservation of existing block content, Service create/readback, stale rejection, malformed financial input and a non-manager.
- Corrected M2 Admin bootstrap by importing `Admin\\UnifiedEditorActions` in `src/Plugin.php`. This missing import was a functional blocker in the previous draft. Corrected disabled Service fields to stay out of the AJAX payload and avoided JS floating-point rounding for currency by using the server-formatted price string.
- Existing `src/Admin/Operations.php` immutable G0/B1 renderer is untouched. Existing Work Type handlers and native editor links stay.
- Service Description/rich Gutenberg editing, customer-binding flows, Work Item full details and Work Type rename are **not accepted as complete** yet. M2 FIELD remains HOLD.

**Evidence/validation:** static JavaScript parse and pure control serialization PASS in review environment; PHP tests, real WordPress tests, i18n gate, full `./tools/check`, `./tools/check-integration`, release ZIP and WordPress FIELD **NOT RUN**. The integration tests are staged to run automatically with the canonical Level 2 harness. Do not promote M2 to technical or functional Golden without that evidence.

### Known constraints for next internal stage

- A WordPress 7.0/MariaDB test may expose behavior of native `wp_update_post` title-only updates around Gutenberg and post hooks. Treat any mismatch as a stop and repair the canonical repository, not as permission to strip markup.
- Existing Project and Work Item `updated_at` checks are second-resolution and are **not** a full row-locked CAS for two simultaneous modal saves. No claim of multi-writer safety; strengthen this before M2 Golden.
- Detached Calendar day modal currently has its own Base lifecycle; nested Work Item editing from its cards requires explicit verified UX handling, not an untested nested-dialog shortcut.
- Public/customer release and merge remain on HOLD.

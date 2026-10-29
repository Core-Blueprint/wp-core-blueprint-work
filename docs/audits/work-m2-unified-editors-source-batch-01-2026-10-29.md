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

## M2 internal batch 03 — locked semantic revisions and billing classification

- Found and fixed a **Service modal initialization order** error: Pricing model / VAT dependent controls are initialized only after their grid is mounted in the editor body.
- Added Work-owned `Repository\\EditorRevisions` with SHA-256 semantic snapshots of Work Item / Project title, Gutenberg body, context/customer reference, date/planning/billing metadata and (for Work Items) sorted assignee IDs. This replaces reliance on second-resolution WordPress `post_modified_gmt` to detect two open editor copies.
- `Repository\\WorkItems::update` receives an *optional* expected revision, checked **inside the already existing Work Item mutation row lock**. `Repository\\Projects::update` receives an *optional* expected revision and invokes the same canonical Project update under a WorkSqlTransaction row lock. Legacy PHP callers without a revision keep their prior signatures and behavior.
- `PublicApi\\WorkItemActions::update` and `PublicApi\\ProjectActions::update` accept that optional revision and retain the same Work-owned mutation/audit contract. The M2 AJAX controller performs a preliminary read comparison and returns HTTP 409 on stale data; the underlying repository checks again after acquiring its lock. Other validation failures are not automatically labelled stale.
- Added billing disposition (hourly / fixed / included / non-billable / unchanged unclassified) using existing `WorkItemShape` normalization, not direct postmeta writes. Existing Work context rules remain authoritative: Internal work stays non-billable.
- Staged WordPress/MariaDB tests verifying a second stale Work Item metadata mutation is rejected within one timestamp second, and a Project date mutation stale replay is rejected under an existing PHPUnit caller transaction. Added a Level 1 source gate for revision and billing boundaries.

**Remaining M2 Golden blockers:** real Level 1/2 validation; canonical translations for newly introduced Base modal UI strings; full field coverage (especially secure customer picker, assignment controls, context/classification consistency); native Gutenberg block-safe advanced content parity; detached Calendar modal lifecycle and accessible error flow; visual dark/light/narrow viewport; one operator FIELD acceptance; no main merge.


## M2 internal batch 04 — CRM customer and assignee selection through Base Object Picker

**Status: internal source slice; M2 Golden still HOLD.**

- The Unified Workspace now loads the public Base Object Picker Foundation via `Assets::enqueue_object_picker()` and imports `@cb-core/object-picker`. Work supplies only the scoped search action, nonce, selected states, and Work-specific validation.
- The new `Admin\\UnifiedEditorPickers` adapter uses the existing `Integration\\CRMCustomers::selected/reference` API to resolve opaque `crm:contact:N` and `crm:organization:N` identifiers. Work Items with a linked Project do not expose a writable independent customer reference; the canonical Work Item model inherits the Project customer.
- Manager-only existing Work AJAX search handlers `cb_work_search_customers` and `cb_work_search_users` remain authoritative; new pickers use their dedicated `cb_work_object_picker` nonce. No new search endpoint or Base modifications.
- Work Item assignees are selected with Base multiple picker and validated by `Repository\\WorkItemLinks::normalize_assignments`. Existing selections larger than the bounded modal transport (100 users or 2,048 bytes) remain unchanged and require the native advanced editor.
- New Work/Project context selector enables Internal or Customer where CRM is available and current ownership is supported. For existing legacy-unclassified or non-CRM customer records the modal does not silently overwrite context. Engagement ownership safeguards remain in `ProjectMeta::normalize`.
- All client nodes are built with DOM methods and `textContent`, avoiding unsanitized HTML; dynamic dialog content is initialized through the documented `window.cbCore.objectPicker.init(body)` Foundation API only after Base attaches the modal body.
- Added bounded PHP source checks, a Node runtime/DOM-mock picker test, and WordPress/MariaDB integration coverage for valid user selections, malformed IDs, unavailable CRM, inherited Project ownership and invalid context.
- Current source checks in authoring environment: JS parse and pure picker/field serialization PASS. **No canonical Level 1 PHP/Node execution or WordPress Level 2/3, ZIP or FIELD validation.**
- **Golden still blocked:** complete canonical seven-language POT/PO synchronization, customer picker + assignee keyboard/manual FIELD acceptance, rich content/Gutenberg parity, documented service pricing verification, and full operator acceptance. Old WordPress editors remain available.


## M2 internal batch 05 — lossless description boundary and modal mount fix

**SOURCE implementation, not a WordPress FIELD-tested candidate.**

- Found and fixed a critical Work Item / Project UI bug: both branches built an editor grid but never attached it to the DOM. Their controls are now explicitly mounted before opening the Base dialog. The existing Service grid mounting remains unchanged.
- Added `Admin\\UnifiedEditorDescription` as a conservative text-only boundary. Plain descriptions under 16,000 bytes and without markup may be edited as text; any HTML, Gutenberg block, malformed HTML marker or over-limit body is treated as **advanced-only**, never round-tripped through a textarea. Rich description content stays intact, and the existing native advanced editor is retained.
- The AJAX transport accepts a bounded `description` field for Work Items, Projects and Services. It validates existing content before allowing mutation; newline-preserving sanitization is applied only to deliberate plain-text edits. Service writes happen through existing ServiceActions CPT + pricing transaction, with per-write readback of the desired content.
- Modal dirty-field logic omits an unchanged plain description from the mutation payload. A deliberate clear sends an empty string. When the current body is rich, the modal presents read-only guidance instead of a lossy editor.
- Corrected two pre-existing M2 Node source checks that only stripped the `@cb-core/modal` import before evaluating the editor code. The editor now also imports Base Object Picker, so tests must remove all static imports for their isolated VM checks.
- Added targeted `UnifiedEditorDescriptionIntegrationTest`, Service and Project/Work Item multiline content integration cases, a Node dirty-description/grid-attachment runtime check, and a PHP source smoke. The checks are registered through the existing Level 1 `tools/check` entry point.
- **Golden still HOLD:** no PHP/Node canonical Level 1 or WordPress/MariaDB Level 2/Level 3 executed on this source; no ZIP, operator FIELD or complete i18n catalog sync. The new localized description guidance and earlier M2 labels have not been propagated through canonical POT and six PO locales. Rich document editing within the modal is intentionally unsupported until a shared, lossless, accessible Base-backed rich editor contract is reviewed.


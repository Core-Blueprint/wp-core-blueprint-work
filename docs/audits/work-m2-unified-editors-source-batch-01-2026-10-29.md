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
- Work Type: **not implemented in batch 01**. Compact modal and governed status actions are still required.

For advanced editing, the dialog deliberately links to the existing native editor. This is an **intermediate internal source slice**, not the final M2 user promise.

## Next internal batch before milestone build

1. Complete secure Service create/edit via its canonical post and pricing paths, with verified metadata read-back and audit/rollback as needed. Do not invent an ungoverned second service store.
2. Compact Work Type create/edit/activate actions; preserve the one Work Type repository, existing validation and event contracts.
3. Complete richer Work Item / Project fields and customer chooser without corrupting Gutenberg blocks; native editor fallback until proven.
4. Negative-role/nonce/CSRF/stale/concurrency tests; true DOM focus/dismiss/dirty/error tests; repeated reopen after save; Base dark/light and narrow viewport.
5. Six-locale canonical i18n catalog updates; Full Level 1/2/3, deterministic ZIP/SHA, then **one** comprehensive WordPress FIELD acceptance. Do not run operator ZIP per internal commit.

Do not merge Work main, modify Base, run CI/deploy, change schema or remove legacy editor routes under the current authorization.

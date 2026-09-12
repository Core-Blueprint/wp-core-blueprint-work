# Core Blueprint Work — Public Operational API

This document defines the supported server-side PHP contract for sibling integrations that need to read or mutate operational Work Items.

## Product boundary

Work is the operational coordination layer of the Core Blueprint suite. It may connect customer, ticket, contract, commercial-document and automation context to actionable work without becoming authoritative for those sibling domains.

Sibling integrations must use Work public contracts. They must not read or write Work private repositories, post meta or custom tables directly.

## Read contract

`CB\Work\PublicApi\WorkItems` exposes:

- `get( int $work_item_id )`
- `all( int $limit = 100, array $statuses = [] )`
- `get_by_source( string $provider, string $source_type, string $external_id )`
- `source( int $work_item_id )`
- `by_relation( string $provider, string $relation_type, string $external_id )`

A source lookup resolves at most one canonical Work Item. A relation lookup may resolve many Work Items.

## Mutation contract

`CB\Work\PublicApi\WorkItemActions` exposes:

- `create( array $input )`
- `create_from_source( string $provider, string $source_type, string $external_id, array $input )`
- `update( int $work_item_id, array $input )`
- `transition_status( int $work_item_id, string $to )`
- `add_relation( int $work_item_id, string $provider, string $relation_type, string $external_id )`

The mutation contract is a server-side PHP contract, not an HTTP transport. Any REST, AJAX, builder or UI adapter still owns its own nonce/CSRF and transport validation.

Every public mutation requires the current actor to hold `cb_manage_work`. Callers cannot pass an arbitrary `created_by` identity through the public API; Work records the authenticated current WordPress actor.

## Source identity versus relation

These concepts are intentionally different.

### Source identity

A source answers:

> Which external record canonically caused this Work Item to exist?

Examples:

- a Helpdesk ticket explicitly converted into one Work Item;
- an imported external task that must not duplicate on retry;
- an integration event whose replay must resolve the already-created Work Item.

One `(provider, source_type, external_id)` source identity may resolve to only one Work Item. A Work Item may have at most one canonical external source identity.

Manual Work Items do not require a source.

`create_from_source()` is idempotent. A replay returns the already-linked canonical Work Item rather than creating another one.

Source identity is protected by a database uniqueness boundary in `cb_work_item_sources`. A bounded claim prevents normal concurrent workers from both creating the same source-derived Work Item. Stale claims may be taken over after the bounded recovery window.

An internal Work-owned recovery relation is written during creation. If a process stops after canonical Work Item creation but before the source row is attached, a later claimant can recover that already-created Work Item instead of creating another one. The source table remains the authority for external source identity; the internal relation is recovery metadata.

### Relation

A relation answers:

> What external record is this Work Item related to?

Relations are intentionally many-to-many. The same ticket, contract, commercial document or other external resource may be related to multiple Work Items.

The relation table therefore keeps uniqueness only within one Work Item:

`(work_item_id, provider, relation_type, external_id)`

There is deliberately no global uniqueness rule for ordinary relations.

## Failure semantics

Public mutations return either the documented Work projection/result array or `WP_Error`.

Important source outcomes from `create_from_source()` are:

- `created` — this call created the canonical Work Item and attached the source;
- `reused` — the source was already attached to an existing canonical Work Item;
- `recovered` — an earlier interrupted call had already created the Work Item and the current call repaired the source attachment.

A live source claim returns `work_source_busy`. Callers should retry later rather than creating through a different path.

Ambiguous recovery fails closed with `work_source_corrupt`; Work never guesses between multiple candidate Work Items.

## Observability

Public operational mutations reuse the canonical Work lifecycle and governance model.

Relevant lifecycle hooks include:

- `cb_work_work_item_created`
- `cb_work_work_item_updated`
- `cb_work_work_item_status_changed`
- `cb_work_work_item_source_attached`
- `cb_work_work_item_relation_added`

Governance events record Work Item creation/update/status changes plus source attachment and relation creation. Audit context contains operational identifiers and provider/type provenance, not sibling private payloads.

## Integration direction

Adapters should depend on this Work contract, not Work internals.

Examples:

```text
Helpdesk public ticket contract
        ↓
optional adapter
        ↓
Work Public Operational API
        ↓
canonical Work Item
```

For commercial integrations the preferred direction remains:

```text
Work Public Billing API
        ↓
commercial provider adapter
        ↓
provider financial/document truth
        ↓
generic callback to Work
```

The public operational API does not create a dependency on CRM, Helpdesk, Contracts, Invoice & Quotes, a page builder or any other sibling extension.

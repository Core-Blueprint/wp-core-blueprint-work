# Work Data Exchange — Tax Rates consumer proof

## Status

This is the first Work-owned consumer of the Base Data Exchange and Data Mapper public contracts. It is intentionally isolated from F2 billing readiness and does not change the Work schema.

## Ownership

Work remains authoritative for the VAT/tax catalog.

Base owns:

- Data Exchange transport, preview/fingerprint/apply orchestration;
- CSV mechanics;
- Data Mapper schema presentation and mapping mechanics;
- Generic Interoperability discovery.

Work owns:

- VAT field meaning and validation;
- the immutable portable VAT code;
- create/update/status semantics;
- authorization;
- governance/audit events;
- persistence.

## Portable identity

The stable Work VAT `code` is the portable identity. Data Exchange references use:

```text
tax-rate:<code>
```

Database IDs, labels and titles are never used to identify an imported VAT rate. Import fails closed when a supplied code is not already in canonical normalized form.

## Entity schema v1

Portable records contain:

- `code`
- `label`
- `country_code`
- `rate_bp`
- `is_active`
- `valid_from`
- `valid_until`

Internal database IDs and timestamps are excluded.

For Data Mapper imports, `code`, `label`, `rate_bp` and `is_active` are required targets. `country_code`, `valid_from` and `valid_until` are optional. When an optional field is left unmapped, a create uses the Work default while an update preserves the current Work-owned value. An explicitly mapped empty value remains an explicit request to clear an optional/nullable field where the Work domain permits it.

## Mutation path

```text
Base Data Exchange
  -> Work TaxRateEntity
  -> CB\Work\PublicApi\TaxRateActions
  -> Work TaxRates repository
  -> Work Audit
```

The Data Exchange adapter does not write Work storage directly.

## Availability

Registration is fail-soft while the Base Data Exchange Foundation is not present. Work does not add the unmerged Foundation to its hard runtime requirements.

## Project planning bundle v1

Work now also registers the JSON-only `project-bundle` entity for portable Project planning.

The bundle deliberately transports one **internal Project** plus its **active Work Items**. It is a planning portability contract, not a historical backup format.

### Portable identity

Projects and Work Items use Work-owned UUIDv4 portable keys stored through the `cb_work_portable_identities` registry.

The registry enforces:

- one immutable portable key per Work entity/local ID;
- global portable-key uniqueness;
- no title matching;
- no database-ID portability;
- cleanup when the canonical Project or Work Item is permanently deleted.

Portable references use:

```text
project:<uuid>
```

Work Item portable keys remain nested record identity inside the bundle.

### Project bundle schema v1

A project record contains:

- `portable_key`
- `title`
- `description`
- `work_context` — v1 requires `internal`
- `starts_on`
- `due_on`
- `work_items`

Each Work Item contains:

- `portable_key`
- `title`
- `description`
- `priority`
- `estimated_minutes`
- `scheduled_on`
- `due_on`
- `status`

Schema v1 accepts active lifecycle states only: `planned`, `in_progress` and `blocked`.

### Deliberate v1 exclusions

The bundle does not transport:

- CRM customer links;
- WordPress-user assignments;
- local Service IDs;
- local Work Type IDs;
- recurrence rules or occurrence history;
- Time Entries or timers;
- terminal Work history/completion facts;
- billing snapshots or external commercial references;
- source/integration relations.

Those domains need their own portable identity and mapping semantics before being added. Future schema versions may extend the bundle without redefining v1.

### Mutation path

```text
Base Data Exchange
  -> Work ProjectBundleEntity
  -> CB\Work\PublicApi\ProjectActions / WorkItemActions
  -> canonical Work repositories
  -> Work Governance/Audit
```

The adapter never writes Projects or Work Items directly.

### Operator UX

Projects expose:

- **Import project** from the Projects administration screen;
- **Export JSON** from an internal Project Workspace.

Import uses Base's canonical preview/fingerprint/apply orchestration. The default UI mode is `create_only`; `create_update` is available explicitly and resolves only immutable portable identity.

Existing active Work Items that are absent from an update bundle are preserved. The import contract never interprets absence as deletion.

## Scope

VAT rates remain the first mapped CSV consumer. Project bundles are the first Work-owned nested JSON planning contract.

Services and the currently excluded child/history domains must not use titles, database IDs or site GUIDs as portable import identity. They require their own explicit portable-key or mapping design before joining Data Exchange.

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

## Scope

This proof covers Work VAT rates because they already have a stable portable identity. Services, Projects and Work Items must not use titles, database IDs or site GUIDs as portable import identity. They require an explicit immutable portable-key design before becoming normal Data Exchange entities.

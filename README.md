# Core Blueprint Work

Core Blueprint Work is the first-party operational work layer for the Core Blueprint WordPress suite.

## v1 scope

Work owns the service catalog and VAT/tax catalog, projects, work items, recurrence, time tracking, timesheet review, billing-ready reporting and export. CRM remains the authority for customers and customer-specific commercial overrides; Helpdesk remains the authority for tickets.

The extension is builder-agnostic. Public data, query, condition and action contracts are implemented before optional builder adapters. Bricks is the first supported adapter, never a dependency.

## Current implementation

Phase B adds the canonical Work service and VAT authority:

- `cb_work_service` WordPress-native service records;
- hourly, fixed-price and recurring service defaults;
- standard amount/currency and VAT treatment;
- Work-owned VAT rates with immutable historical semantics through activation/deactivation;
- supported read-only PHP contracts under `CB\Work\PublicApi` for sibling integrations.

CRM migration and customer-specific overrides are intentionally deferred to Phase C.

## Requirements

- WordPress 7.0+
- PHP 8.4+
- Core Blueprint Base with a Core API `1.0`-compatible public contract

## Development

The authoritative v1 ownership and phased plan are documented in [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md).

Run the source checks with:

```bash
./tools/check
```

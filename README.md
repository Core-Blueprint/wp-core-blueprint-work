# Core Blueprint Work

Core Blueprint Work is the first-party operational work layer for the Core Blueprint WordPress suite.

## v1 scope

Work owns the service catalog and VAT/tax catalog, projects, work items, recurrence, time tracking, timesheet review, billing-ready reporting and export. CRM remains the authority for customers and customer-specific commercial overrides; Helpdesk remains the authority for tickets.

The extension is builder-agnostic. Public data, query, condition and action contracts are implemented before optional builder adapters. Bricks is the first supported adapter, never a dependency.

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

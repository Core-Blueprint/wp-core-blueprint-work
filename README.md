# Core Blueprint Work

Core Blueprint Work is the first-party operational work layer for the Core Blueprint WordPress suite.

## v1 scope

Work owns the service catalog and VAT/tax catalog, projects, work items, recurrence, time tracking, timesheet review, billing-ready reporting and export. CRM remains the authority for customers and customer-specific commercial overrides; Helpdesk remains the authority for tickets.

The extension is builder-agnostic. Public data, query, condition and action contracts are implemented before optional builder adapters. Bricks is the first supported adapter, never a dependency.

## Current implementation

Through D1.2, Work provides:

- canonical `cb_work_service` service records and Work-owned VAT/tax rates;
- effective pricing resolution with optional customer-agreement providers;
- WordPress-native CPT-backed Projects and Work Items;
- Work Type classification;
- planned / in-progress / completed / skipped / cancelled lifecycle;
- low / normal / high / urgent priorities;
- hourly / fixed / included / non-billable classification kept separate from completion state;
- multiple WordPress-user assignments per Work Item through relational child records;
- generic provider/type/external-ID relations for future sibling integrations;
- a normal top-level **Work** WP Admin menu for operational use;
- settings-only `Core Blueprint → Work` configuration;
- supported read-only PHP contracts under `CB\Work\PublicApi` for sibling integrations.

Recurrence, timers/time entries, refined Today/Overdue/Upcoming views and billing/invoice lifecycle remain later phases.

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

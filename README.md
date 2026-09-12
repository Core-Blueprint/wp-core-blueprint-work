# Core Blueprint Work

Core Blueprint Work is the first-party operational work layer for the Core Blueprint WordPress suite.

## v1 product boundary

Work owns operational work: Services and VAT/tax context, Projects, Work Items, recurrence, time tracking, billing readiness, operational reporting/export and provider-neutral external commercial references.

Work does **not** own customer identity, support tickets, contracts or financial-document truth. Those remain with their authoritative sibling products or external providers.

The permanent ownership and integration rules are documented in [`docs/DOMAIN-BOUNDARIES.md`](docs/DOMAIN-BOUNDARIES.md). Detailed implementation architecture remains in [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md).

The extension is builder-agnostic. Public data, query, condition and governed action contracts come before optional builder adapters. Bricks is the first supported adapter, never a dependency.

## Current implementation

The current `1.0.0-rc1` baseline includes:

- canonical `cb_work_service` Service records and Work-owned VAT/tax rates;
- effective pricing resolution with optional customer-agreement providers;
- WordPress-native CPT-backed Projects and Work Items;
- Work Type classification;
- planned / in-progress / completed / skipped / cancelled Work Item lifecycle;
- low / normal / high / urgent priorities;
- hourly / fixed / included / non-billable classification kept separate from completion state;
- multiple WordPress-user assignments per Work Item through relational child records;
- provider/type/external-ID relations for optional sibling integrations;
- recurring Work Item rules, occurrence ledger, bounded generation and recovery;
- manual Time Entries and a server-authoritative one-active-timer-per-user timer;
- Work Items Table, List, Board/Kanban and Calendar operational views;
- builder-neutral Work frontend/query/condition/action contracts;
- Base Admin Theme integration for Work-owned admin screens.

Still to be completed for the sellable v1 Golden Standard are, among other items:

- fuller Project lifecycle/progress semantics;
- a stronger daily operational Overview;
- public operational mutation seams for sibling integrations;
- provider-neutral billing-readiness snapshots and commercial-reference callbacks;
- billing-ready reporting and CSV/JSON export;
- optional Helpdesk, Invoice & Quotes and Contracts adapters after their required public seams are stable;
- the Bricks adapter and final Golden Standard audit.

Invoice numbering, invoice/credit-note lifecycle, authoritative financial line calculations, payment state and financial-document truth are explicitly outside Work.

## Requirements

- WordPress 7.0+
- PHP 8.4+
- Core Blueprint Base with a Core API `1.0`-compatible public contract

## Development

Run the source checks with:

```bash
./tools/check
```

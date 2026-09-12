# Core Blueprint Work — Domain Boundaries

This document is the canonical product-ownership boundary for Core Blueprint Work `1.0.0-rc1` and later compatible v1 releases.

It intentionally separates **what Work owns** from **what Work may reference or project from another authority**. Optional integrations must preserve these boundaries.

## Core rule

> Work owns operational work and billing readiness. Commercial-document providers own financial-document truth.

Work must remain usable without CRM, Helpdesk, Invoice & Quotes, Contracts, a page builder or any other optional sibling extension.

## Work-owned truth

Work is authoritative for:

- Services and their operational/default pricing context;
- VAT/tax catalog references used as commercial input context;
- Projects;
- Work Items;
- Work Item lifecycle, planning, priority and assignments;
- recurrence rules and generated Work occurrences;
- Time Entries and active timers;
- Work Item billing disposition/classification;
- billing readiness as an operational Work state;
- operational/commercial handoff snapshots;
- operational reporting and export;
- provider-neutral external/source relations;
- provider-neutral external commercial references after a successful handoff.

Work does not become the authority for a sibling domain merely because it stores an external reference or renders a status projection.

## CRM boundary

CRM remains authoritative for:

- Contacts;
- Organizations;
- customer identity and relationship context;
- customer-specific commercial agreements or pricing overrides.

Work may consume documented public CRM contracts for customer selection and pricing-context resolution. Work must never read CRM private repositories or tables.

A Work billing snapshot may record that its resolved pricing context came from a CRM agreement, including stable provenance needed to explain the handoff later. It must not copy CRM into a second customer or agreement domain.

## Helpdesk boundary

Helpdesk remains authoritative for:

- tickets;
- messages/replies;
- ticket attachments;
- support-ticket lifecycle and ticket-specific customer snapshots.

Work may reference a Helpdesk ticket and may create or relate operational Work from a ticket through public contracts. Ticket truth stays in Helpdesk.

No Work integration may depend on Helpdesk private persistence.

## Invoice & Quotes / commercial-provider boundary

Invoice & Quotes is one possible commercial-document provider, not a Work dependency.

A future ERP, accounting product or other invoicing provider must be able to consume the same Work billing-readiness contract without redesigning Work.

### Work owns billing readiness

Billing readiness is an operational Work state. Work may use states such as conceptually:

- not ready;
- ready;
- processed;
- externally linked.

Exact implementation names are an implementation decision for the billing-readiness phase.

Work must not model provider financial-document lifecycle values such as `issued`, `paid` or `credited` as Work-owned truth. Such values may only be displayed as an explicitly external status projection.

### Work handoff snapshot

At the commercial transition, Work must preserve enough immutable historical input to reconstruct what was handed off at that time. The Work-owned snapshot is **operational/commercial input**, not an authoritative financial document.

The snapshot may include, as applicable:

- source Work Item and/or Time Entry references;
- customer reference;
- quantity or duration at handoff time;
- Service reference;
- billing disposition;
- resolved pricing/rate context;
- pricing provenance, such as a Service default or CRM agreement reference;
- VAT/tax input context;
- readiness/handoff timestamp and actor/provenance.

Later changes to Services, Time Entries or CRM agreements must not silently rewrite historical handoff context that has been fixed for commercial processing.

### Commercial provider owns the financial snapshot

The commercial provider remains authoritative for, among other things:

- quote/invoice/credit-note lines;
- exact financial line amounts;
- currency and legal tax treatment on the document;
- rounding;
- document totals;
- invoice/quote numbering;
- document revisions;
- financial fingerprints;
- acceptance/rejection where applicable;
- payment and collection state.

Work must not duplicate this domain.

### External commercial references

After a commercial provider successfully processes Work data, Work may store a provider-neutral reference/projection. The semantic shape should remain generic, for example:

- `provider`;
- `resource_type`;
- `resource_id`;
- `display_reference`;
- optional `status_projection`;
- `linked_at`.

The Work core schema/API must not encode Invoice & Quotes-specific names such as `iq_invoice_id` or assume that the resource is always an invoice.

A status projection is convenience metadata from the external authority. It is not Work-owned financial truth.

### Adapter direction

The preferred integration direction is:

```text
Work Public Billing API
        ↓
commercial provider adapter
        ↓
provider application/domain API
        ↓
provider financial/document truth
        ↓
generic callback/action to Work
        ↓
external commercial reference/projection
```

For Core Blueprint Invoice & Quotes specifically, the optional Work adapter should preferably live on the Invoice & Quotes side when it consumes Work data to create a commercial document. Work must not know how Invoice & Quotes internally creates an invoice.

The concrete Work ↔ Invoice & Quotes write adapter is gated until Invoice & Quotes exposes a source-stable public commercial-document seam.

## Contracts boundary

Contracts remains authoritative for:

- contract lifecycle;
- signing state;
- evidence;
- completion/delivery truth.

Work may later reference Contracts through stable public projections/events and provider-neutral external references. Work must not read Contracts private persistence or recreate signing/evidence state.

The concrete adapter is gated until Contracts exposes a stable sibling-consumer seam.

## Public integration rules

Optional integrations must follow all of these rules:

- no cross-plugin SQL foreign keys;
- no direct reads/writes of sibling private repositories, tables or internal classes;
- public application/API contracts are the only supported mutation boundary;
- external references use provider-neutral provider/type/id semantics;
- integrations fail soft when a sibling plugin is unavailable;
- authorization remains enforced by the authority performing the mutation;
- builder adapters stay thin and never become domain dependencies;
- events/hooks fire only around canonical persisted domain actions, not alternative write paths;
- retries must not create duplicate operational or commercial records where idempotent source identity can be established.

## Builder boundary

Work remains builder-neutral.

Work owns public data, query, condition and governed action contracts. Bricks is the first supported adapter because it is the first-party implementation target, but it is never a dependency or a special case in the Work domain model.

Future builders and AI/automation consumers must be able to use the same public contracts without changes to Work's storage or ownership model.

## Authority summary

```text
Work
→ operational work
→ Services / tax input context
→ Projects / Work Items / recurrence / time
→ billing disposition
→ billing readiness
→ operational/commercial handoff snapshot
→ external commercial reference/projection

CRM
→ customer identity / relationship
→ customer-specific pricing agreements

Helpdesk
→ ticket/message/support lifecycle truth

Invoice & Quotes or another commercial provider
→ quote/invoice/credit-note/payment truth
→ authoritative financial document snapshot

Contracts
→ contract/signing/evidence truth
```

These boundaries are permanent architecture unless a future major-version decision explicitly changes product ownership.

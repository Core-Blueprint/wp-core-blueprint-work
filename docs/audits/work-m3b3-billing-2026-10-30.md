# M3-B3 | Canonical execution context and external invoice registration in Work

Date: 2026-10-30
Repository: `Core-Blueprint/wp-core-blueprint-work`
Branch: `feature/work-m3-operations-foundation-20261030`
Parent: `80f1c9c7f46c003f609e52743f6734021e2667ed`
Classification: **SOURCE IMPLEMENTED CANDIDATE; Golden, WP Field and production HOLD**

## Scope and boundary

M3-B2 already introduced an **Execution status** dialog using `ServiceOperations::period()` and the authoritative, read-only `Periods::resolve()`. This batch adds a separate **Billing registration** action to the same in-place Service Engagement list for customer work. It does **not** infer billing readiness or payment from Recurring Work execution or issue invoices. Internal Engagements explicitly display **Not applicable** without an invoice action.

**Read:** GET `cb-work/v1/workspace/engagements/{id}/billing?month=YYYY-MM` is manager-only and repeats permission checks in the callback. It reads the canonical server-derived Engagement Work Context and `PublicApi\ServiceBilling::coverage()` for the month. The browser receives only `state`, effective period bounds, selected month and a minimal registration `revision`, `invoiced_on` and `external_reference`. CRM/customer identifiers and billing audit actors are **not exposed**. A genuine unavailable/corrupt read fails closed, not converted into an empty/not-recorded invoice.

**Optional terms:** Customer Engagements without applicable coverage may initialize their existing canonical service terms via POST `.../billing/terms` with `frequency`, `anchor_on` and `timing`. These are the existing `PublicApi\ServiceBillingActions::create_terms()` and `ServiceOperations\ServiceBilling::create_terms()` contracts, **not a new terms store**. The domain still refuses duplicates and invalid effective dates. If a selected historical/future month is not covered by already-created terms, the native Service Operations fallback remains the path for advanced review; the canonical writer refuses another set of terms.

**External registration:** For an applicable Customer coverage month, the existing Base Modal exposes `Not recorded` or `Invoiced externally`, date and external reference. POST `.../billing` forwards only `month`, `state`, `invoiced_on`, `reference` and the read `expected_revision` to `PublicApi\ServiceBillingActions::record()`. Canonical InnoDB CAS, append-only correction journal and governance audit event remain intact. A 409/stale conflict or malformed response keeps the modal open and shows an error; it never silently increments/rebases the revision or claims verified payment/invoice issuance. The source does not instantiate an invoicing/payment provider.

**Security and UX:** The endpoints permit GET/POST only, reuse `WorkspaceEngagementsController::can_manage()` at both permission and callback levels, validate direct-call types and dates, reject internal billing writes before reaching the canonical API and do not forward customer/terms IDs from the client. The UI uses only **existing translation strings**, the shared Base Modal, strict response validation, `textContent`, same-origin rendered REST template URLs (supports both pretty and query-style WordPress permalinks) and abortable GETs. The native Service Operations screen remains available for non-parity workflows.

**Architecture:** The new `engagement-billing.js` is imported by `engagements.js` and appended to the existing release-only recursive, content-hashed ESM graph. `src/Admin/Assets.php` requires this source module before enqueue. No Base modification, schema migration, G0/B1 protected renderer, new scheduler, alternative billing engine or changes to M1 Workspace router.

## Tests

Added to canonical `tools/check`:
- `tests/work-m3b3-billing-source-smoke.php`: source-level role/domain/REST/ESM/Base Modal/Golden renderer invariants.
- `tests/work-m3b3-billing-runtime.php`: isolated PHP test of manager access, read projection, customer confidentiality, invalid dates/revisions, canonical CAS receipt and stale error, Internal Work Context segregation and setup terms.
- `tests/work-m3b3-billing-runtime.mjs`: in-place Base Modal browser simulation for canonical registration with exact revision, denied stale result, Internal read-only state, optional terms initialization, invalid API payload and cross-origin refusal.

The isolated **JavaScript runtime PASS** was executed against the source and regression fixture in V8. Source code reviewed for import graph, syntax and canonical API guards. Full `./tools/check`, WP/MariaDB Level 2, deterministic ZIP and sandbox FIELD are **NOT RUN** for this M3 branch; none of the earlier M2 SHA/tests are reusable evidence for this source.

## What remains

M3-B3 is a **source slice** for billing administration, not final parity for advanced billing filters, rule editor/create, invoice reconciliation or all service state lenses. M3-B full parity still needs functional checklist and real WordPress acceptance before legacy UI routes can be retired. Next: **M3-C Time** integration inside Work using unchanged Time CAS/G0 role safeguards, followed by targeted acceptance for M3-B and M3-C and a single canonical M3 Level 1/2/3 + sandbox FIELD round. Work `main`, production, public release and Base changes remain HOLD.

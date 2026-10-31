# M3-B4-B5 — Recurring Work Experience Refinement

Date: 2026-10-31
Repository: Core-Blueprint/wp-core-blueprint-work
Branch: feature/work-m3-operations-foundation-20261030
Status: SOURCE PATCHED / LOCAL VALIDATION AND FIELD ACCEPTANCE PENDING

## Scope and authority
- Work-only responsive filter toolbar: search input + Sort by + Status + Clear. Existing 300ms live-search, URL state and native fallback preserved. The redundant submit button remains in the semantic form for Enter behavior but is visually suppressed in the JS-enhanced recurring view.
- Cards use canonical schedule cadence, server-formatted date and site-time state: pending (cursor before today), upcoming (today/future), complete (no cursor). Unknown or malformed state does not expose private data.
- The recurrence scheduler intentionally catches up overdue occurrences. No dates are silently skipped; we did not touch Scheduler, RecurrenceSchedule, RecurrenceRules, or generated Work Items.
- Overdue activation prompts via the public Base Modal and cannot reach REST without `confirmed:true`; server independently requires explicit confirmation if the canonical cursor is before the current WordPress site date. Normal activation and deactivation remain revision guarded. 409 remains a safe recovery error when browser state becomes stale.
- Editor: description compact (2 rows), End choice No date limit / End date, preview heading responds to historical pending dates; preview still comes from canonical server-side math and stays non-mutating.
- Four new strings tracked in POT and six PO locales. No Base source changes.

## Field acceptance
1. Desktop at ~1600px: Recurring filters fit without broken Sort by label or enormous Search input. At 1120/782/540px transitions remain accessible without horizontal scrolling.
2. Type in Search; results update after debounce without clicking Search. Enter and Clear filters continue to work; URL back/forward and sorting/status stay coherent. No-JS native manager link remains available.
3. Inactive rule with 2026-10-14 cursor shows Pending since, not a false upcoming date. A future cursor shows Next; null cursor shows Complete. Frequency reflects actual interval in translated day/week/month/year form.
4. Pending activation: cancel changes nothing; confirm sends guarded request and may create historical Work Items on a later scheduler run. Test on non-production data only. Re-read the current revision before attempting again after 409.
5. Editor preview marks historical dates and uses Pending occurrences heading; changing a schedule after unlock uses canonical server preview. End selection and Quick Edit preserve canonical fields; Base Save/Cancel remain fixed.
6. Verify light/dark mode, keyboard focus, smaller viewports and visually inspect the reduced action prominence.

## Open
Dirty-state dismissal and initial full-screen default still require Base's documented public modal contract. Do not access private Base internals.

## No merge without explicit user acceptance
Maintain feature branch until Level 1/2, build, and sandbox acceptance are complete.

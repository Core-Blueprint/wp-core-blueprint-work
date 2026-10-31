# M3-B4-B4 — Recurring Work Editor UX field acceptance

Date: 2026-10-31
Repository: Core-Blueprint/wp-core-blueprint-work
Branch: feature/work-m3-operations-foundation-20261030
Status: SOURCE PATCHED / TEST & FIELD ACCEPTANCE PENDING

## Scope
- Base public workspace dialog retained. Only editor body uses Work-scoped geometry, border-box sizing and responsive content width.
- Main section: Work details (full-width title, description), schedule-first cadence and start/end dates, Work context (Project/Service/Type/conditional CRM), Assignment, Advanced settings (create-ahead, due offset, Priority, estimate, billing).
- Complementary aside displays the authoritative non-mutating server Rule Preview. Initial load and changes in schedule fields update it. A stale result cannot overwrite the latest preview.
- Frequency labels are translated through existing gettext IDs, while canonical values remain daily/weekly/monthly/yearly. UI-only End date checkbox has no name and cannot become a persisted REST field.
- End date remains nullable, and locked schedules are disabled without changing the server's revision/lifecycle protections. Existing Quick Edit stays minimal.
- Native WordPress editor remains progressive fallback. No Base modal private APIs, no domain writers, no workflow merge or deployment.

## Verify on WordPress sandbox
1. Open an existing Recurring Work rule, test at desktop and narrow widths, dark and light. No horizontal scrollbar; only Base-owned vertical scroll where content exceeds modal height. Save/Cancel remain fixed.
2. Verify title and description, cadence, dates, context, optional customer, assignees, advanced field values.
3. Toggle End date on/off and save an edited existing rule; ensure never/dated semantics persist and no UI-only field is written. Verify locked schedule cannot be changed.
4. Confirm translated cadence labels (including NL), preview initial GET-of-options + POST non-mutating preview, edits update preview, errors do not freeze the button.
5. Save/create/Quick Edit and revision 409/permission 403; preserved native fallback. On other Work tabs, navigation/modal untouched.
6. Modal dirty-dismiss guarding and initial fullscreen are **upstream Base public-contract questions**, not bypassed by this Work UI patch.

## Next after acceptance
Coordinate the public Modal dirty-state dismissal guard and fullscreen default with Base Luna; follow the public contract without private dialog hooks.

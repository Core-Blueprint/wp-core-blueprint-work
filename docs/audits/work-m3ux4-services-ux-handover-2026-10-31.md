# M3-UX4 — Work Services Navigation & Presentation Handover

Date: 2026-10-31
Repository: Core-Blueprint/wp-core-blueprint-work
Branch: feature/work-m3-operations-foundation-20261030
Status: SOURCE PATCHED / LOCAL CHECKS & FIELD ACCEPTANCE PENDING

## Intent and authority
- Four main Work sections remain Tasks, Projects, Services, Recurring Work.
- Service Operations is a Services-only inline subview with a separate URL parameter `services_view=operations`; Work page and section routing remain authoritative. The native Service Operations URL is the no-JS, missing-Base, and error fallback.
- Time stays in the global top row as a quiet utility with its running indicator.
- Child editors still use Base Modal; the parent Service Operations manager no longer needs an excessively wide and short Base workspace modal.
- No domain, transport, permission, WordPress native renderer, or Base source edits.
- No merge or CI.

## Acceptance
1. On Work / Recurring, clicking the Recurring Work tab alone must **not** open Service Operations. If it does, inspect navigation event propagation and provide console/network evidence.
2. On Work / Services, Services and Service Operations appear as secondary choices. Selecting Service Operations updates the URL without a page refresh. Back/Forward and switching main Work tabs preserve the correct subview.
3. With no services, show a compact empty state, the Add service action, and no repeated external manager link. A REST failure must reveal a native fallback link.
4. Service Operations child creation, billing and recurrence rule actions still use the existing Base Modal and canonical REST writers.
5. Time is accessible on all sections; running HUD/indicator unaffected. No duplicate prominent Service Operations in the top navigation.
6. Buttons and link-shaped buttons are never underlined on hover or focus. Keep visible keyboard outlines; actual editorial links are unaffected. Validate dark/light, keyboard and mobile.

## Base Luna — upstream design-system audit request (not a Base patch)
Audit Base-owned `assets/css/components/buttons.css` and native modal/button presentation for link-shaped buttons (`a.button`, `.cb-core-button`, corresponding scoped variants). In Base-controlled surfaces, ensure hover/focus-visible do not introduce `text-decoration: underline`; preserve focus outlines and ordinary text link behavior. This may affect other extensions and should be corrected upstream only after Base Luna's current Designer Foundation stabilization. Avoid global `a:hover` overrides, hidden cross-plugin styles, or direct Base source changes from Work.

## Open dependency
Base public Modal Foundation does not yet expose a confirmed dirty-state dismissal guard or default fullscreen presentation for the Recurring Work editor. Coordinate separately before UX acceptance of those behaviors; do not use private modal internals.

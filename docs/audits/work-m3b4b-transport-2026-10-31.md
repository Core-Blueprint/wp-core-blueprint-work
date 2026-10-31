# M3-B4-B | Recurring editor transport foundation (2026-10-31)

Source baseline: Work feature `feature/work-m3-operations-foundation-20261030` at `f71867c0347bd8ff6a88c613278999c9aaabd0fb`.

Scope: read-only manager REST detail and authoritative schedule preview, to support a later Base-owned SPA editor without cloning native PHP forms, duplicating scheduling logic or exposing customer identity in recurring list data.

- Detail validates manager capability, Base runtime and exact schema pin; resolves the selected CRM identity only for a privileged edit form. Missing/trashed/unresolvable rules fail closed.
- Preview accepts an explicit JSON schedule and optional rule ID, delegates to the existing RecurrenceSchedule domain and Recurrence::preview_dates. It preserves the canonical occurrence cursor for unchanged schedules and rejects edits to historically locked schedules.
- No REST create/update/activation/trash route is introduced. Native RecurrenceActions remain the only exposed editor/lifecycle until the next controlled B4-B step resolves write conflicts/CAS and Base Modal behavior.
- No UI interception, Base changes, DB migrations, or protected Work renderer changes.
- New isolated source/runtime fixtures enrolled in tools/check. Requires owner-side PHP + full Level 1/2/3 and WordPress sandbox evidence before acceptance.
- UX3-F3 presentation polish intentionally deferred by owner; F2E accepted in owner sandbox. No main merge, native retirement, release, deployment or CI.

# Work transient feedback and Base Toast integration

Date: 2026-10-08
Status: candidate, local and runtime acceptance pending
Branch: `feature/work-toast-notifications-v1`
Based on: Time Golden audit `3c33659cf0b369b2d9fccf0c6576850fcc7aa7e4`

## Policy

- Base's public `@cb-core/toast` Foundation exclusively owns notification presentation,
  semantics, accessibility, stack lifecycle, dismissal and theme integration.
- Work adds one thin `assets/work-toast.js` consumer adapter. No private Base imports,
  no duplicated toast HTML, CSS, animation or persistence.
- Explicit `data-cb-work-toast` markers distinguish ephemeral operation feedback
  from page-level warnings and instructions. PHP still outputs the original notice,
  so content survives JavaScript or module failure.
- Success and info are temporary. Errors and warnings are persistent until dismissed.
  No toast is produced from arbitrary or unknown WordPress notices.
- Pre-paint handoff: a Work-only stylesheet hides transient notices while
  `cb-work-toast-pending` is set by an admin-head bootstrap. The Base Toast
  adapter clears the marker after consuming the PHP notices. A 3-second timeout
  clears it if the module fails, revealing the original notice. With JavaScript
  disabled, no marker is ever set. Hiding with display:none avoids the brief
  WordPress-notice flash and a reserved blank-space layout shift.
- The Work Settings Toast wrapper is explicitly closed to preserve valid DOM.
- The adapter removes one-time query feedback only after successfully showing a toast;
  other URL filter and routing parameters are preserved.
- Use Base's Core presentation on Work-owned admin screens, including
  the provider-specific Work Settings Hub route. Other plugins' Settings Hub
  content is not touched.

## Covered

- Time: server redirects and async Quick Edit and Bulk Edit outcomes.
- Work Items and Work Types: operation redirect feedback.
- Recurring Work: rule operations and generator result; generator failures
  use warning instead of an unconditional success status.
- Work Settings: VAT rate operation feedback in the Base Settings Hub.
- Quick Add: failure feedback. Created notices keep their inline
  `Open it` action link because Base Toast currently supports text only.
- Global Timer HUD: successful stop uses Base Toast when available; the
  existing HUD toast remains as a fallback outside Work-owned screens.
  Active timer form feedback and domain state remain local.

## Still inline on purpose

- Schema/storage availability notices and invalid filter context.
- Messages that contain an actionable link, including Quick Add created.
- Contextual instructions and validations that must remain close to a form.
- No conversion of general WordPress or other extension notices.

## Validation gates

```bash
node tests/work-toast-runtime.js
node tests/work-toast-handoff-runtime.js
php tests/work-toast-contract-smoke.php
node tests/time-quick-edit-submit-runtime.js
node tests/time-bulk-submit-runtime.js
php tests/time-quick-edit-smoke.php
php tests/time-bulk-edit-smoke.php
php tests/global-time-hud-smoke.php
./tools/i18n/check
./tools/check
./tools/build-release
unzip -tqq dist/core-blueprint-work.zip
sha256sum dist/core-blueprint-work.zip
(cd dist && sha256sum -c core-blueprint-work.zip.sha256)
```

## Browser acceptance

1. Time Quick Edit: save without reload, one success toast, no duplicate inline
   notice, persisted seconds correct after manual refresh.
2. Time error/conflict: persistent error toast and unsaved editor remain available.
3. Bulk Edit: success and partial results (warning) show once and do not mask errors.
4. Work Items and Recurring Work: success and failure redirects yield single
   toasts; refresh does not replay the message.
5. Work Settings VAT rate: Core Toast only for selected Work provider.
6. Light/Dark, keyboard dismissal, responsive position, screen-reader announcement,
   reduced-motion and no-JS notice fallback.
7. Quick Add success still retains its inline `Open it` action.
8. Work Items redirected success: no brief WordPress notice or blank-space shift
   before the Base Toast appears. Block the Toast module or disable JavaScript to
   verify the original server notice remains available.

No merge, deployment, publishing or CI/CD is authorized by this candidate.
The Time Golden audit branch and its validated artifact remain unchanged.

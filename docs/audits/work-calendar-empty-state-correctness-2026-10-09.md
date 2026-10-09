# CV-G-001 | Work Calendar empty-state correctness

Date: 2026-10-09  
State: **PATCH CANDIDATE / LOCAL REGRESSIONS AND OPERATOR ACCEPTANCE PENDING**  
Repository: `Core-Blueprint/wp-core-blueprint-work`  
Branch: `fix/work-calendar-empty-state-correctness-v1`  
Base: accepted Calendar Golden `main` `e7d50c704e25af97371efdc835bb5978fa520b4c`

## Reproduced source defect

The operator supplied a WordPress Calendar screenshot for October 2026
showing Work Item date summaries on the 5th, 7th and 8th while an
informational paragraph claimed **"No scheduled work or deadlines this month."**

The month grid and day buttons are server-rendered by
`src/Admin/WorkItemCalendarView.php`. For non-empty day entries it
renders a button with `data-cb-work-calendar-day-open`. The calendar
empty note, however, is injected by `refineCalendar()` inside
`assets/work-items-refinement.js`, not by the query or by the PHP
Calendar view.

**Root cause:** `refineCalendar()` still checked
`calendar.querySelector('.card')` to decide if the month had entries.
The accepted Calendar Golden renderer now uses
`[data-cb-work-calendar-day-open]`, not legacy `.card` markup.
Consequently, the obsolete selector was absent even in populated
months and the JavaScript inserted the false empty-month note.

## Minimal production correction

- The Calendar empty-state guard now checks the actual rendered
  `[data-cb-work-calendar-day-open]` marker within
  `.cb-work-items-calendar`.
- If at least one populated Calendar day is present, the empty-month
  note must not be inserted.
- If there is no day button in the selected month, preserve the
  existing localized empty-month note exactly as before.
- The existing no-duplicate-note guard and view separation remain.
- No PHP queries, repository data access, view state, scheduled/due
  semantics, translations, Base modal, CSS or other Work view behavior
  are modified.

## Regression and coverage

Added `tests/work-calendar-empty-state-runtime.js` and wired it to
the canonical `./tools/check`. It executes the real Work refinement
source inside a dependency-free Node VM with controlled DOM fixtures:

1. **Filled October scenario:** date summaries on three days,
   including the original operator-observed dates, never produce a
   false empty notice.
2. **Genuinely empty selected month:** exactly one translated
   empty note is inserted, and a repeat initialization does not
   duplicate it.
3. **Localization:** the text comes from the canonical localized
   Work assets, not an invented English-only copy.
4. **View isolation:** Table must not receive a Calendar note.
5. **Missing Calendar/table/navigation:** guarded exit without
   a stray empty note.
6. Guard that the real PHP Calendar emits its canonical day trigger
   only for non-empty day entry arrays.

The first scenario would fail against the original `.card` guard,
making the observed screenshot bug a regression test. It does not
pretend to exercise a live browser or WordPress query; existing
Calendar state/renderer smoke and operator WordPress acceptance
remain complementary.

## Operator validation (terminal safe)

Existing local branch setup for the operator is `main` after the
accepted WC-G-001 merge. No `set -e` or `exit` is required.

```bash
cd ~/Downloads/wp-core-blueprint-work

git status --short
git fetch origin --prune
git switch --track origin/fix/work-calendar-empty-state-correctness-v1
git pull --ff-only
git rev-parse HEAD

node --check assets/work-items-refinement.js
node --check tests/work-calendar-empty-state-runtime.js
node tests/work-calendar-empty-state-runtime.js

php tests/d2-calendar-state-smoke.php
php tests/d2-workspace-routing-smoke.php
php tests/work-item-calendar-reorder-smoke.php
php tests/work-calendar-presentation-smoke.php
php tests/work-items-ux-refinement-smoke.php
./tools/i18n/check
./tools/check

./tools/build-release
unzip -tqq dist/core-blueprint-work.zip
sha256sum dist/core-blueprint-work.zip
(cd dist && sha256sum -c core-blueprint-work.zip.sha256)
```

Stop and report errors if any check fails; do not rely on a previously
built ZIP/checksum. Report the exact new SHA-256 from this patch's
validated build.

## WordPress browser acceptance

On coreblueprint.io, test **only after** green local checks and
installing the new ZIP. Prefer WordPress operator runtime as agreed.
Refresh cached assets so the new `work-items-refinement.js` executes.
No need to change live Work data.

- Calendar October 2026, with visible day summaries for October 5/7/8:
  **no** false "No scheduled work..." paragraph.
- Navigate to a month genuinely without scheduled/due Work Items:
  the same translated informational paragraph **appears once** and
  previous/next navigation still works.
- Switch filters, project and month views; verify the notice reflects
  the **visible date entries**, not the global Work Item count.
- Switch Board/Table/List and back: no new empty note or filter regression.
- Modal expand/close, Quick Edit, Time, dark/light Calendar rendering
  remain as already accepted.

## Merge and release governance

Operator explicitly authorized **investigation and patch creation**.
No merge, CI/Actions, distribution publication or deployment is
authorized by that GO. Wait for the operator's tested checksum,
WordPress acceptance and **separate explicit merge GO** before moving
`main` or closing CV-G-001 in Overseer.

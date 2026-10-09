'use strict';

// CV-G-001: execute the real Work Items refinement runtime against DOM
// fixtures that reproduce the operator's filled-October screenshot.
// This test intentionally fails on the pre-fix legacy '.card' selector.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.join(__dirname, '..');
const script = fs.readFileSync(path.join(root, 'assets/work-items-refinement.js'), 'utf8');
const calendarRenderer = fs.readFileSync(path.join(root, 'src/Admin/WorkItemCalendarView.php'), 'utf8');
assert.ok(calendarRenderer.includes('data-cb-work-calendar-day-open'),
    'the fixture must use the canonical calendar day trigger');
assert.ok(calendarRenderer.includes('if ( [] !== $day_entries )'),
    'the day trigger is rendered only when the chosen day has date entries');

function runScenario({ view = 'calendar', populatedDays = 0, hasCalendar = true, hasNavigation = true, strings = {} }) {
    const inserted = [];
    const counts = { legacyCard: 0, dayTriggers: 0 };

    const calendar = {
        querySelector(selector) {
            if (selector === '.card') {
                counts.legacyCard++;
                return null;
            }
            if (selector === '[data-cb-work-calendar-day-open]') {
                counts.dayTriggers++;
                return populatedDays > 0 ? { dataset: { templateId: 'cb-work-calendar-day-2026-10-07' } } : null;
            }
            return null;
        }
    };
    const navigation = {
        insertAdjacentElement(position, node) {
            assert.equal(position, 'afterend');
            inserted.push(node);
        }
    };
    const form = {
        querySelector(selector) {
            return selector === 'input[name="view"]' ? { value: view } : null;
        }
    };
    const page = {
        querySelector(selector) {
            if (selector === '.cb-work-items-filters') return form;
            if (selector === '.cb-work-items-calendar') return hasCalendar ? calendar : null;
            if (selector === '.cb-work-calendar-navigation') return hasNavigation ? navigation : null;
            if (selector === '.cb-work-calendar-empty-note') return inserted[0] ?? null;
            return null;
        },
        querySelectorAll() { return []; }
    };
    const document = {
        readyState: 'complete',
        querySelector(selector) { return selector === '.cb-work-items-page' ? page : null; },
        createElement(tag) {
            return {
                tagName: tag.toUpperCase(),
                className: '',
                textContent: '',
                children: [],
                appendChild(child) { this.children.push(child); }
            };
        }
    };
    const context = { document, window: { cbWorkAdminUx: strings } };
    vm.runInNewContext(script, context, { filename: 'work-items-refinement.js' });
    return { inserted, counts, context };
}

const filledMonth = runScenario({
    // October 2026: Oct 5 has 2, Oct 7 has 17, Oct 8 has 6 date entries.
    populatedDays: 3
});
assert.equal(filledMonth.inserted.length, 0,
    'a Calendar with real visible October Work Item day buttons must not claim no scheduled work');
assert.equal(filledMonth.counts.dayTriggers, 1,
    'the check must use the actual Calendar day marker, not the obsolete card selector');
assert.equal(filledMonth.counts.legacyCard, 0,
    'obsolete card markup must not control the Calendar empty state');

const emptyMonth = runScenario({
    populatedDays: 0,
    strings: { noScheduledThisMonth: 'No scheduled work or deadlines this month.' }
});
assert.equal(emptyMonth.inserted.length, 1,
    'a genuinely empty Calendar month must retain its localized empty notice');
assert.equal(emptyMonth.inserted[0].className, 'description cb-work-calendar-empty-note');
assert.equal(emptyMonth.inserted[0].textContent, 'No scheduled work or deadlines this month.');
vm.runInNewContext(script, emptyMonth.context, { filename: 'work-items-refinement.js' });
assert.equal(emptyMonth.inserted.length, 1,
    'initialization may not duplicate the empty notice');

const filteredEmptyMonth = runScenario({
    populatedDays: 0,
    strings: { noScheduledThisMonth: 'Keine geplanten Arbeiten oder Fristen in diesem Monat.' }
});
assert.equal(filteredEmptyMonth.inserted[0].textContent,
    'Keine geplanten Arbeiten oder Fristen in diesem Monat.',
    'localized copy from canonical Work assets must be respected under filters');

const notCalendar = runScenario({ view: 'table', populatedDays: 0 });
assert.equal(notCalendar.inserted.length, 0,
    'other Work views must not receive a Calendar notice');

const missingCalendar = runScenario({ hasCalendar: false });
const missingNavigation = runScenario({ hasNavigation: false });
assert.equal(missingCalendar.inserted.length, 0);
assert.equal(missingNavigation.inserted.length, 0);

console.log('Work Calendar empty-state runtime passed (populated/empty, localization, idempotency and view isolation).');

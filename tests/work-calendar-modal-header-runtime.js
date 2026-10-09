'use strict';

// Dependency-free runtime regression of the Calendar-only Base modal action
// promotion. Reuse the actual code; do not run a browser-dependent Base import.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../assets/work-calendar.js'), 'utf8');
const styles = fs.readFileSync(path.join(__dirname, '../assets/work-calendar-golden.css'), 'utf8');
assert.ok(source.includes('promoteCalendarClose(body, closeLabel);'));
assert.ok(source.includes("dismissOnly: true"));
assert.ok(source.includes("expandable: true"));
assert.ok(source.includes('reloadAfterMove: false'));
assert.ok(!source.includes('dialog.close('), 'Calendar may not bypass the Base promise/action lifecycle');

class HTMLElement {
    constructor() {
        this.children = [];
        this.attributes = {};
        this.className = '';
        this.title = '';
    }
    setAttribute(name, value) { this.attributes[name] = value; }
    append(...items) { this.children.push(...items); }
    replaceChildren(...items) { this.children = items; }
}
class HTMLButtonElement extends HTMLElement {}
class HTMLDialogElement extends HTMLElement {}
class HTMLTemplateElement extends HTMLElement {}

const dialog = new HTMLDialogElement();
const body = new HTMLElement();
body.closest = selector => selector === 'dialog.cb-core-modal--workspace' ? dialog : null;
const expand = new HTMLButtonElement();
const close = new HTMLButtonElement();
const oldClickHandlers = [];
close.addEventListener = (type, handler) => {
    if (type === 'click') oldClickHandlers.push(handler);
};
let baseDismissed = 0;
close.addEventListener('click', () => { baseDismissed++; });

const actions = new HTMLElement();
actions.querySelectorAll = selector => selector === 'button' ? [close] : [];
actions.remove = () => { actions.removed = true; };
const baseBody = new HTMLElement();
const form = new HTMLElement();
form.querySelector = selector => ({
    '.cb-core-modal__expand-toggle': expand,
    '.cb-core-modal__actions': actions,
    '.cb-core-modal__body': baseBody
})[selector] ?? null;
form.insertBefore = (group, before) => {
    form.group = group;
    form.before = before;
};
dialog.querySelector = selector => selector === '.cb-core-modal__form' ? form : null;
const classes = new Set();
dialog.classList = { add: name => classes.add(name) };

const code = source.replace(/^import\s+.+;\s*$/gm, '');
const context = {
    HTMLElement, HTMLButtonElement, HTMLDialogElement, HTMLTemplateElement,
    document: {
        createElement: () => new HTMLElement(),
        querySelectorAll: () => []
    }
};
vm.runInNewContext(code + '\nthis.promoteCalendarCloseForTest = promoteCalendarClose;', context, {
    filename: 'work-calendar.js'
});
context.promoteCalendarCloseForTest(body, 'Close');

assert.equal(classes.has('cb-work-calendar-modal'), true, 'only the target dialog is scoped');
assert.equal(actions.removed, true, 'footer menu is physically removed');
assert.equal(form.before, baseBody, 'actions are inserted before the scrollable modal body');
assert.deepEqual(form.group.children, [expand, close], 'expand stays left, Close moves right');
assert.equal(close.attributes['aria-label'], 'Close');
assert.equal(close.title, 'Close');
assert.equal(close.children[0].className, 'dashicons dashicons-no-alt');
assert.equal(close.children[0].attributes['aria-hidden'], 'true');
oldClickHandlers[0]();
assert.equal(baseDismissed, 1, 'relocated button retains the original Base dismiss click listener');

const missing = new HTMLElement();
const fallbackDialog = new HTMLDialogElement();
missing.closest = () => fallbackDialog;
fallbackDialog.querySelector = () => ({ querySelector: () => null });
assert.doesNotThrow(() => context.promoteCalendarCloseForTest(missing, 'Close'),
    'unknown future Base markup must keep its original footer rather than crash');

assert.ok(styles.includes('.cb-work-items-page--refined .cb-work-calendar-day__trigger {'));
assert.ok(styles.includes('padding: var(--cb-space-3);'));
assert.ok(styles.includes('border-radius: var(--cb-radius-md);'));
assert.ok(styles.includes('dialog.cb-core-modal.cb-work-calendar-modal .cb-work-calendar-modal__header-actions {'));
assert.ok(styles.includes('dialog.cb-core-modal.cb-work-calendar-modal .cb-work-calendar-modal__close:focus-visible {'));
assert.ok(styles.includes('padding-inline-end: 112px;'), 'modal title must not overlap two header actions');
console.log('Work Calendar modal header runtime passed (reused Base Close, footer removed, expand left, fallback safe).');

'use strict';

// Dependency-free runtime regression for Time Bulk Edit visibility and controls.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../assets/time-entry-bulk-edit.js'), 'utf8');
const listeners = Object.create(null);
const rowState = [];
const rows = Array.from({ length: 3 }, (_, i) => ({
    checked: false,
    matches: selector => selector.includes('[data-cb-work-time-bulk-select]'),
    closest: () => ({
        classList: {
            toggle: (name, enabled) => {
                assert.equal(name, 'is-selected');
                rowState[i] = enabled;
            }
        }
    })
}));
const selectAll = {
    checked: false,
    indeterminate: false,
    disabled: false,
    matches: selector => selector.includes('[data-cb-work-time-bulk-select-all]')
};
const editor = {
    open: true,
    removeAttribute(name) {
        if (name === 'open') this.open = false;
    }
};
const mode = { value: 'keep', matches: selector => selector.includes('[data-cb-work-time-bulk-mode]') };
const target = { value: '0', matches: selector => selector.includes('[data-cb-work-time-bulk-target]') };
const note = { value: '', disabled: false, required: false, matches: selector => selector.includes('[data-cb-work-time-bulk-note]') };
const submit = { disabled: false };
const counter = { textContent: '' };
const form = {
    hidden: true,
    querySelector(selector) {
        return {
            '[data-cb-work-time-bulk-mode]': mode,
            '[data-cb-work-time-bulk-target]': target,
            '[data-cb-work-time-bulk-note]': note,
            '[data-cb-work-time-bulk-submit]': submit,
            '[data-cb-work-time-bulk-count]': counter,
            '[data-cb-work-time-bulk-editor]': editor
        }[selector] ?? null;
    }
};
const list = { querySelector: selector => selector === '[data-cb-work-time-bulk-select-all]' ? selectAll : null };
const host = {
    querySelector(selector) {
        if (selector === '.cb-work-time-entries') return list;
        if (selector === '[data-cb-work-time-bulk-form]') return form;
        return null;
    },
    querySelectorAll(selector) {
        return selector === '[data-cb-work-time-bulk-select]' ? rows : [];
    },
    addEventListener(name, callback) {
        listeners[name] = callback;
    }
};
vm.runInNewContext(source, {
    document: { querySelector: selector => selector === '.cb-work-time-view--entries' ? host : null }
}, { filename: 'time-entry-bulk-edit.js' });

function change(item) {
    listeners.change({ target: item });
}

assert.equal(form.hidden, true, 'zero selected: toolbar stays hidden');
assert.equal(counter.textContent, '0');

rows[0].checked = true;
change(rows[0]);
assert.equal(form.hidden, true, 'one selected: toolbar stays hidden');
assert.equal(counter.textContent, '1');

rows[1].checked = true;
change(rows[1]);
assert.equal(form.hidden, false, 'two selected: toolbar becomes visible');
assert.equal(counter.textContent, '2');
assert.equal(selectAll.indeterminate, true);
assert.deepEqual(rowState, [true, true, false]);

target.value = '42';
change(target);
assert.equal(submit.disabled, false, 'valid bulk change is available for two entries');

editor.open = true;
rows[1].checked = false;
change(rows[1]);
assert.equal(form.hidden, true, 'back to one selected: toolbar hides again');
assert.equal(editor.open, false, 'expanded editor closes when selection is insufficient');
assert.equal(submit.disabled, true, 'one selection cannot trigger a bulk update');
assert.equal(rows[0].checked, true, 'selection is not destructively cleared');

selectAll.checked = true;
change(selectAll);
assert.equal(form.hidden, false, 'Select All reveals toolbar');
assert.equal(counter.textContent, '3');
assert.equal(selectAll.indeterminate, false);
assert.deepEqual(rows.map(row => row.checked), [true, true, true]);

selectAll.checked = false;
change(selectAll);
assert.equal(form.hidden, true, 'deselect all hides toolbar');
assert.equal(counter.textContent, '0');
assert.deepEqual(rows.map(row => row.checked), [false, false, false]);

mode.value = 'append';
change(mode);
assert.equal(note.disabled, false, 'append mode enables note');
assert.equal(note.required, true, 'append mode requires text');

mode.value = 'keep';
change(mode);
assert.equal(note.disabled, true, 'no-change mode disables note');

console.log('Time Bulk Edit visibility runtime passed.');

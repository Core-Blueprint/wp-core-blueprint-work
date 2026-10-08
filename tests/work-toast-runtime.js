'use strict';

// Runtime contract for Work's adapter to the Base public Toast Foundation.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const original = fs.readFileSync(path.join(__dirname, '../assets/work-toast.js'), 'utf8');
assert.match(original, /^import \{ toast \} from '@cb-core\/toast';/m);
const source = original.replace(/^import \{ toast \} from '@cb-core\/toast';/m, 'const toast = __testToast;');

const shown = [];
const visible = [];
const makeNotice = (variant, message, marked = true) => ({
    removed: false,
    matches: selector => marked && selector === '[data-cb-work-toast]',
    getAttribute: name => name === 'data-cb-work-toast' ? variant : null,
    querySelector: selector => selector === 'p' ? { textContent: message } : null,
    textContent: message,
    remove() { this.removed = true; }
});

const success = makeNotice('success', 'Time entry updated.');
const warning = makeNotice('warning', 'Some entries could not be updated.');
const invalid = makeNotice('error', 'The Time entry could not be saved.');
const context = makeNotice('warning', 'Storage upgrade required.', false);
visible.push(success, warning, invalid, context);

const before = 'https://example.test/wp-admin/admin.php?page=core-blueprint-work-time&view=entries&te_source=timer&cb-work-notice=time-updated';
let locationAfter = null;
const window = {
    location: { href: before },
    history: {
        state: null,
        replaceState(state, title, url) { locationAfter = String(url); }
    }
};
const document = {
    querySelectorAll: selector => selector === '[data-cb-work-toast]' ? visible.filter(n => n.matches(selector)) : []
};

vm.runInNewContext(source, {
    window, document, URL,
    __testToast: (message, variant, options) => shown.push({ message, variant, options })
}, { filename: 'work-toast.js' });

assert.equal(shown.length, 3, 'only explicitly marked transient notices become toasts');
assert.deepEqual(shown.map(x => x.variant), ['success', 'warning', 'error']);
assert.equal(shown[0].options.persistent, false);
assert.equal(shown[1].options.persistent, true);
assert.equal(shown[2].options.persistent, true);
assert.equal(context.removed, false, 'persistent page notices remain inline');
assert.ok(success.removed && warning.removed && invalid.removed);
assert.equal(new URL(locationAfter).searchParams.get('cb-work-notice'), null, 'one-time feedback URL is cleaned');
assert.equal(new URL(locationAfter).searchParams.get('te_source'), 'timer', 'filters remain intact');

const asyncNotice = makeNotice('success', 'Additional entry saved.');
assert.equal(window.cbWorkToast.showNotice(asyncNotice), true);
assert.equal(asyncNotice.removed, false, 'async caller owns detached response DOM');
assert.equal(shown[3].message, 'Additional entry saved.');
assert.equal(window.cbWorkToast.showNotice(context), false, 'unmarked context is not promoted');
assert.equal(window.cbWorkToast.showMessage('Network error', 'error', { persistent: true }), true);
assert.equal(shown[4].options.persistent, true);
assert.equal(window.cbWorkToast.showMessage('', 'error'), false, 'empty messages are not announced');
assert.equal(shown.length, 5);

console.log('Work Base Toast integration runtime passed.');

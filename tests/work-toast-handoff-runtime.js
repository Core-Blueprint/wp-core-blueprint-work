'use strict';

// Execute the actual inline head bootstrap emitted by Work's PHP asset setup.
// Verify immediate concealment and timed recovery when the Base module fails.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const php = fs.readFileSync(path.join(__dirname, '../src/Admin/Assets.php'), 'utf8');
const parts = [
    'document.documentElement.classList.add("cb-work-toast-pending");',
    'window.setTimeout(function(){document.documentElement.classList.remove("cb-work-toast-pending");},3000);'
];
for (const part of parts) {
    assert.ok(php.includes("'" + part + "'"), 'head bootstrap must include reviewed code: ' + part);
}

const classes = new Set();
let timeout = null;
const context = {
    document: {
        documentElement: {
            classList: {
                add: name => classes.add(name),
                remove: name => classes.delete(name)
            }
        }
    },
    window: {
        setTimeout(callback, ms) {
            assert.equal(ms, 3000);
            assert.equal(timeout, null, 'single fallback timeout only');
            timeout = callback;
        }
    }
};
vm.runInNewContext(parts.join(''), context, { filename: 'work-toast-head-bootstrap.js' });
assert.equal(classes.has('cb-work-toast-pending'), true,
    'transient server notices must be hidden before first paint');
assert.equal(typeof timeout, 'function', 'failure recovery must be scheduled');

timeout();
assert.equal(classes.has('cb-work-toast-pending'), false,
    'a failed Base Toast import must not hide important notices forever');

console.log('Work Toast pre-paint handoff runtime passed (immediate concealment, timed fallback).');

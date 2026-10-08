'use strict';

// Dependency-free browser-submit regression: WordPress requires an input
// named "action", which can shadow the HTML form's .action URL property.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../assets/time-entry-quick-edit.js'), 'utf8');
const root = 'https://example.test';
const endpoint = root + '/wp-admin/admin-post.php';
const listeners = Object.create(null);
let postedTo = null;
let redirectedTo = null;
let savedNotice = null;
let replaced = false;

const classList = (...initial) => {
    const names = new Set(initial);
    return {
        add: (...added) => added.forEach(name => names.add(name)),
        contains: name => names.has(name)
    };
};
const notice = {
    classList: classList('notice', 'notice-success'),
    setAttribute() {},
    focus() {}
};
const replacement = {
    prepend(node) { savedNotice = node; }
};
let list = {
    dataset: { cbWorkTimeAsyncError: 'Request failed' },
    replaceWith(node) { assert.strictEqual(node, replacement); list = node; replaced = true; }
};
const page = {
    querySelector(selector) {
        return selector === '.cb-work-time-entries' ? replacement
            : selector === '.cb-work-time-page > .notice' ? notice
            : null;
    }
};
const host = {
    querySelector(selector) { return selector === '.cb-work-time-entries' ? list : null; },
    querySelectorAll() { return []; },
    addEventListener(type, callback) { listeners[type] = callback; }
};
const button = { disabled: false };
const form = {
    // Mimic the browser's named form control collision.
    action: { toString: () => '[object HTMLInputElement]' },
    getAttribute(name) { return name === 'action' ? endpoint : null; },
    matches(selector) { return selector === '[data-cb-work-time-quick-edit] form'; },
    reportValidity() { return true; },
    querySelector(selector) { return selector === 'button[type="submit"]' ? button : null; },
    setAttribute() {},
    removeAttribute() {}
};
class FakeFormData {
    constructor(target) { assert.strictEqual(target, form); }
}
const context = {
    document: {
        querySelector: selector => selector === '.cb-work-time-view--entries' ? host : null,
        importNode: node => node
    },
    DOMParser: class {
        parseFromString(html, type) {
            assert.equal(type, 'text/html');
            return page;
        }
    },
    FormData: FakeFormData,
    URL,
    window: {
        location: { href: root + '/wp-admin/admin.php?page=core-blueprint-work-time&view=entries' },
        history: {
            state: null,
            replaceState(state, title, url) { redirectedTo = String(url); }
        }
    },
    async fetch(url, options) {
        postedTo = String(url);
        assert.equal(postedTo, endpoint, 'POST must target admin-post.php, not the shadowing input');
        assert.equal(options.method, 'POST');
        assert.equal(options.credentials, 'same-origin');
        assert.equal(options.redirect, 'follow');
        assert.ok(options.body instanceof FakeFormData);
        return {
            ok: true,
            url: root + '/wp-admin/admin.php?page=core-blueprint-work-time&view=entries&cb-work-notice=time-updated',
            async text() { return '<html></html>'; }
        };
    }
};
vm.runInNewContext(source, context, { filename: 'time-entry-quick-edit.js' });

(async () => {
    let prevented = false;
    await listeners.submit({
        target: form,
        preventDefault() { prevented = true; }
    });
    assert.equal(prevented, true, 'Quick Edit must prevent a full-page form submission');
    assert.equal(postedTo, endpoint, 'the original form action attribute must be used');
    assert.equal(replaced, true, 'successful save must update entries without reloading');
    assert.strictEqual(savedNotice, notice, 'server success notice must be displayed');
    assert.equal(button.disabled, false, 'submit button must be enabled after completion');
    assert.ok(redirectedTo);
    assert.equal(new URL(redirectedTo).searchParams.has('cb-work-notice'), false);
    console.log('Time Quick Edit submit runtime passed.');
})().catch(error => {
    console.error(error);
    process.exitCode = 1;
});

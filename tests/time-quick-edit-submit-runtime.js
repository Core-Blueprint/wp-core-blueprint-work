'use strict';

// Browser-level submit contract without a WordPress DB.
// A WordPress <input name="action"> shadows HTMLFormElement.action.
// The stub must include browser URL.origin and createElement or test failures
// will be hidden by exceptions inside the error-reporting branch.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../assets/time-entry-quick-edit.js'), 'utf8');
const origin = 'https://example.test';
const endpoint = origin + '/wp-admin/admin-post.php';
const listeners = Object.create(null);

let postUrl = '';
let postCount = 0;
let redirectedTo = '';
let serverOutcome = 'time-updated';
let validHttp = true;
let actionUrl = endpoint;
let renderedNotice = null;
let replaced = 0;

const classList = (...initial) => {
    const names = new Set(initial);
    return {
        add: (...values) => values.forEach(name => names.add(name)),
        contains: name => names.has(name)
    };
};
const makeNotice = (type = 'success') => ({
    classList: classList('notice', 'notice-' + type),
    children: [],
    append(node) { this.children.push(node); },
    setAttribute(name, value) { this[name] = value; },
    focus() {}
});
const successNotice = makeNotice();
const conflictNotice = makeNotice('error');
const newList = {
    dataset: { cbWorkTimeAsyncError: 'Request failed' },
    prepend(node) { renderedNotice = node; }
};
let list = {
    dataset: { cbWorkTimeAsyncError: 'Request failed' },
    replaceWith(node) {
        assert.strictEqual(node, newList);
        list = node;
        replaced++;
    }
};
const page = {
    querySelector(selector) {
        if (selector === '.cb-work-time-entries') return newList;
        if (selector === '.cb-work-time-page > .notice') {
            return serverOutcome === 'time-updated' ? successNotice : conflictNotice;
        }
        return null;
    }
};
const host = {
    querySelector(selector) { return selector === '.cb-work-time-entries' ? list : null; },
    querySelectorAll() { return []; },
    addEventListener(type, callback) { listeners[type] = callback; }
};
const button = { disabled: false };
const form = {
    action: { toString: () => '[object HTMLInputElement]' },
    getAttribute(name) { return name === 'action' ? actionUrl : null; },
    matches(selector) { return selector === '[data-cb-work-time-quick-edit] form'; },
    reportValidity() { return true; },
    querySelector(selector) { return selector === 'button[type="submit"]' ? button : null; },
    prepend(node) { renderedNotice = node; },
    setAttribute() {},
    removeAttribute() {}
};
class FakeFormData {
    constructor(target) { assert.strictEqual(target, form); }
}
const context = {
    document: {
        querySelector: selector => selector === '.cb-work-time-view--entries' ? host : null,
        importNode: node => node,
        createElement: () => makeNotice()
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
        location: {
            origin,
            href: origin + '/wp-admin/admin.php?page=core-blueprint-work-time&view=entries'
        },
        history: {
            state: null,
            replaceState(state, title, url) { redirectedTo = String(url); }
        }
    },
    async fetch(url, options) {
        postUrl = String(url);
        postCount++;
        assert.equal(postUrl, endpoint, 'POST must use the real form action');
        assert.equal(options.method, 'POST');
        assert.equal(options.credentials, 'same-origin');
        assert.equal(options.redirect, 'follow');
        assert.ok(options.body instanceof FakeFormData);
        return {
            ok: validHttp,
            url: origin + '/wp-admin/admin.php?page=core-blueprint-work-time&view=entries&cb-work-notice=' + serverOutcome,
            async text() { return '<html></html>'; }
        };
    }
};
vm.runInNewContext(source, context, { filename: 'time-entry-quick-edit.js' });

async function submit() {
    let prevented = false;
    await listeners.submit({
        target: form,
        preventDefault() { prevented = true; }
    });
    assert.equal(prevented, true, 'form must not cause a full page navigation');
    assert.equal(button.disabled, false, 'submit must be re-enabled');
}

(async () => {
    await submit();
    assert.equal(postUrl, endpoint);
    assert.equal(replaced, 1, 'success must refresh the list');
    assert.strictEqual(renderedNotice, successNotice, 'success notice must be displayed');
    assert.ok(redirectedTo, 'history is updated only after a confirmed save');
    assert.equal(new URL(redirectedTo).searchParams.has('cb-work-notice'), false);

    const savedUrl = redirectedTo;
    renderedNotice = null;
    serverOutcome = 'time-conflict';
    await submit();
    assert.equal(replaced, 1, 'conflict must preserve existing form and list');
    assert.strictEqual(renderedNotice, conflictNotice, 'revision conflict must be displayed');
    assert.equal(redirectedTo, savedUrl, 'failed save must not update history');

    renderedNotice = null;
    validHttp = false;
    await submit();
    assert.equal(replaced, 1, 'unexpected HTTP failure must not replace list');
    assert.equal(renderedNotice.classList.contains('notice-error'), true,
        'network/server failures must display a fallback error');
    assert.equal(redirectedTo, savedUrl);

    renderedNotice = null;
    actionUrl = null;
    const before = postCount;
    await submit();
    assert.equal(postCount, before, 'missing form action must not trigger a request');
    assert.equal(renderedNotice.classList.contains('notice-error'), true);

    // With Base's adapter ready, an async failure uses the shared Toast
    // without prepending a second inline copy or losing the unsaved editor.
    renderedNotice = null;
    actionUrl = endpoint;
    validHttp = true;
    serverOutcome = 'time-conflict';
    const toasts = [];
    context.window.cbWorkToast = {
        showNotice(node) { toasts.push(node); return true; },
        showMessage() { return true; }
    };
    await submit();
    assert.strictEqual(toasts[0], conflictNotice, 'Base Toast receives the server error');
    assert.equal(renderedNotice, null, 'no duplicate inline notice is created');
    assert.equal(replaced, 1, 'Toast feedback does not replace an unsaved editor');
    assert.equal(redirectedTo, savedUrl);

    console.log('Time Quick Edit submit runtime passed (success, conflict, HTTP error, missing action, Base Toast).');
})().catch(error => {
    console.error(error);
    process.exitCode = 1;
});

'use strict';

// Exercise the actual bulk-submit listener, including WordPress's shadowing
// hidden input named "action". No browser, network or WordPress DB required.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../assets/time-entry-bulk-edit.js'), 'utf8');
const origin = 'https://example.test';
const endpoint = origin + '/wp-admin/admin-post.php';

const classList = (...initial) => {
    const values = new Set(initial);
    return {
        add: (...names) => names.forEach(name => values.add(name)),
        contains: name => values.has(name),
        toggle: (name, enabled) => enabled ? values.add(name) : values.delete(name)
    };
};

async function scenario(outcome, status = 200, hasAction = true) {
    const handlers = Object.create(null);
    const success = outcome === 'time-bulk-updated' || outcome === 'time-bulk-partial';
    const noticeType = success ? 'success' : 'error';
    const serverNotice = {
        classList: classList('notice', 'notice-' + noticeType),
        setAttribute() {},
        focus() {}
    };
    let feedbackNode = null;
    let postUrl = null;
    let requests = 0;
    let listReplacements = 0;
    let historyUrl = '';
    const rows = Array.from({ length: 2 }, () => ({
        checked: true,
        closest: () => ({ classList: classList() })
    }));
    let activeRows = rows;
    const selectAll = { checked: false, disabled: false, indeterminate: false };
    const replacement = {
        dataset: { cbWorkTimeAsyncError: 'Bulk request failed' },
        querySelector: () => null,
        prepend(node) { feedbackNode = node; }
    };
    let currentList = {
        dataset: { cbWorkTimeAsyncError: 'Bulk request failed' },
        querySelector: selector => selector === '[data-cb-work-time-bulk-select-all]' ? selectAll : null,
        prepend(node) { feedbackNode = node; },
        replaceWith(node) {
            assert.strictEqual(node, replacement);
            currentList = replacement;
            activeRows = [];
            activeForm = freshForm;
            listReplacements++;
        }
    };

    function makeForm() {
        const mode = { value: 'append' };
        const target = { value: '0' };
        const note = { value: 'Audit correction', disabled: false, required: true };
        const button = { disabled: false };
        const count = { textContent: '' };
        const editor = { removeAttribute() {} };
        const map = {
            '[data-cb-work-time-bulk-mode]': mode,
            '[data-cb-work-time-bulk-target]': target,
            '[data-cb-work-time-bulk-note]': note,
            '[data-cb-work-time-bulk-submit]': button,
            '[data-cb-work-time-bulk-count]': count,
            '[data-cb-work-time-bulk-editor]': editor
        };
        return {
            // The named form control masks form.action in real browsers.
            action: { toString: () => '[object HTMLInputElement]' },
            getAttribute: name => name === 'action' && hasAction ? endpoint : null,
            hidden: true,
            querySelector: selector => map[selector] ?? null,
            matches: selector => selector === '[data-cb-work-time-bulk-form]',
            reportValidity: () => true,
            prepend(node) { feedbackNode = node; },
            get submitButton() { return button; }
        };
    }

    const originalForm = makeForm();
    const freshForm = makeForm();
    let activeForm = originalForm;
    const host = {
        querySelector(selector) {
            if (selector === '.cb-work-time-entries') return currentList;
            if (selector === '[data-cb-work-time-bulk-form]') return activeForm;
            return null;
        },
        querySelectorAll(selector) {
            if (selector === '[data-cb-work-time-bulk-select]') return activeRows;
            if (selector === '.cb-work-time-bulk-request-notice') return [];
            return [];
        },
        addEventListener(name, callback) { handlers[name] = callback; }
    };
    const page = {
        querySelector(selector) {
            if (selector === '.cb-work-time-entries') return replacement;
            if (selector === '.cb-work-time-page > .notice') return serverNotice;
            return null;
        }
    };
    class FakeFormData {
        constructor(form) { assert.strictEqual(form, originalForm); }
    }
    const context = {
        document: {
            querySelector: selector => selector === '.cb-work-time-view--entries' ? host : null,
            importNode: node => node,
            createElement: () => ({
                classList: classList(),
                append() {},
                setAttribute() {},
                focus() {}
            })
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
                replaceState(state, title, url) { historyUrl = String(url); }
            }
        },
        async fetch(url, options) {
            requests++;
            postUrl = String(url);
            assert.equal(postUrl, endpoint, 'Bulk Edit must POST to admin-post.php');
            assert.equal(options.method, 'POST');
            assert.equal(options.credentials, 'same-origin');
            assert.equal(options.redirect, 'follow');
            assert.ok(options.body instanceof FakeFormData);
            return {
                ok: status === 200,
                url: origin + '/wp-admin/admin.php?page=core-blueprint-work-time&view=entries&cb-work-notice=' + outcome,
                async text() { return '<html></html>'; }
            };
        }
    };
    vm.runInNewContext(source, context, { filename: 'time-entry-bulk-edit.js' });
    assert.equal(originalForm.hidden, false, 'two selected rows must expose Bulk Edit');
    assert.equal(originalForm.submitButton.disabled, false);

    let prevented = false;
    await handlers.submit({
        target: originalForm,
        preventDefault() { prevented = true; }
    });
    assert.equal(prevented, true, 'Bulk Edit must avoid full-page navigation');
    assert.equal(originalForm.submitButton.disabled, false,
        'the initial controls must recover after a completed request');
    assert.ok(feedbackNode, 'a result or fallback notice must be rendered');

    if (!hasAction) {
        assert.equal(requests, 0, 'missing action URL must not POST');
        assert.equal(listReplacements, 0);
    } else {
        assert.equal(requests, 1);
        assert.equal(postUrl, endpoint);
    }

    if (hasAction && status === 200 && success) {
        assert.equal(listReplacements, 1, 'success or partial update refreshes canonical entries');
        assert.strictEqual(feedbackNode, serverNotice);
        assert.ok(historyUrl, 'successful save updates URL-backed state');
        assert.equal(new URL(historyUrl).searchParams.has('cb-work-notice'), false);
    } else {
        assert.equal(listReplacements, 0, 'failure must retain user selection and form');
        assert.equal(originalForm.hidden, false);
        assert.equal(historyUrl, '', 'failed update must not modify history');
        assert.equal(feedbackNode.classList.contains('notice-error'), true);
    }
}

(async () => {
    await scenario('time-bulk-updated');
    await scenario('time-bulk-partial');
    await scenario('time-bulk-conflict');
    await scenario('time-bulk-invalid', 404);
    await scenario('time-bulk-updated', 200, false);
    console.log('Time Bulk Edit submit runtime passed (success, partial, conflict, HTTP error, missing action).');
})().catch(error => {
    console.error(error);
    process.exitCode = 1;
});

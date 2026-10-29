import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import { createMutationAdapter } from '../assets/js/workspace/canonical-mutations.js';

const oldFormData = globalThis.FormData;
const oldCustomEvent = globalThis.CustomEvent;
globalThis.FormData = class {
	constructor(form) { this.form = form; }
	get(key) { return this.form.fields[key] ?? null; }
};
globalThis.CustomEvent = class {
	constructor(type, options) { this.type = type; this.bubbles = options?.bubbles; }
};
try {
	const capture = {};
	const doc = {
		addEventListener(name, fn, useCapture) { capture[name] = { fn, useCapture }; },
		createElement() { return {
			attrs: {}, setAttribute(name, value) { this.attrs[name] = value; },
			textContent: '', hidden: true,
		}; },
	};
	const destination = {
		dataset: {}, attrs: {},
		addEventListener() {},
		contains() { return false; },
		setAttribute(name, value) { this.attrs[name] = value; },
		removeAttribute(name) { delete this.attrs[name]; },
	};
	const panel = {
		dataset: {
			cbWorkUweClassicEndpoint: 'https://example.test/wp-admin/admin-ajax.php',
			cbWorkUweMutationSaved: 'Saved',
			cbWorkUweMutationFailed: 'Failed',
		},
		ownerDocument: doc, hidden: false,
		insertBefore() {},
	};
	let attempts = 0;
	let refreshes = 0;
	const browser = {
		location: { href: 'https://example.test/wp-admin/admin.php?page=core-blueprint-work-workspace' },
		async fetch(url, options) {
			attempts++;
			assert.equal(url, panel.dataset.cbWorkUweClassicEndpoint);
			assert.equal(options.credentials, 'same-origin');
			assert.equal(options.method, 'POST');
			return { ok: true, status: 200, json: async () => ({
				success: true, data: { updated: 1, attempted: 1, notice: 'work-item-transitioned' },
			}) };
		},
	};
	const adapter = createMutationAdapter(destination, panel, browser, async () => { refreshes++; });
	assert.ok(adapter, 'Canonical mutation adapter is available');
	assert.equal(capture.submit.useCapture, true, 'Detached Calendar forms are intercepted before navigation');
	let removed = false;
	const modal = { replaceChildren() { removed = true; } };
	let saved = 0;
	const form = {
		method: 'post', fields: {
			action: 'cb_work_transition_work_item', _wpnonce: 'private-nonce',
		},
		attrs: {},
		querySelector(selector) {
			const key = selector.match(/name="([^"]+)"/)?.[1];
			return key && this.fields[key] ? { value: this.fields[key] } : null;
		},
		closest(selector) { return selector === '[data-cb-work-uwe-modal]' ? modal : null; },
		dispatchEvent(event) {
			assert.equal(event.type, 'cb-work:calendar-modal-mutation-saved');
			assert.equal(event.bubbles, true);
			saved++;
		},
		setAttribute(name, value) { this.attrs[name] = value; },
		removeAttribute(name) { delete this.attrs[name]; },
	};
	async function fire(target) {
		let prevented = false;
		capture.submit.fn({
			target, defaultPrevented: false,
			preventDefault() { prevented = true; },
		});
		await new Promise((resolve) => setImmediate(resolve));
		return prevented;
	}

	assert.equal(await fire(form), true, 'Workspace Calendar form never navigates to admin-post');
	assert.equal(attempts, 1, 'Existing Work status handler called once');
	assert.equal(saved, 1, 'Modal is notified of confirmed status, so Calendar can refresh after close');
	assert.equal(refreshes, 0, 'Modal must not rehydrate while still open');
	assert.equal(removed, false);
	assert.equal(destination.attrs['aria-busy'], undefined);

	// An unrelated native WordPress form, even with a similar action, is not owned.
	const native = { ...form, closest() { return null; } };
	assert.equal(await fire(native), false);
	assert.equal(attempts, 1, 'Native WordPress forms are left to their existing submit handlers');

	browser.fetch = async () => ({
		ok: false, status: 409, json: async () => ({ success: false, data: { notice: 'conflict' } }),
	});
	assert.equal(await fire(form), true);
	assert.equal(saved, 1, '409 must not announce a successful status change');
	assert.equal(refreshes, 0, '409 retains the current modal');

	browser.fetch = async () => ({
		ok: false, status: 403, json: async () => ({ success: false }),
	});
	assert.equal(await fire(form), true);
	assert.equal(removed, true, 'Permission loss clears detached private modal content');
	assert.equal(refreshes, 1, '403 rechecks the server read permission boundary');
	assert.equal(saved, 1, '403 never announces a status change');
} finally {
	globalThis.FormData = oldFormData;
	globalThis.CustomEvent = oldCustomEvent;
}

// Exercise the ACTUAL classic source for native and dynamically mounted Work.
const shortcuts = fs.readFileSync(new URL('../assets/work-fast-paths.js', import.meta.url), 'utf8');
const listeners = {};
let page = null;
let modalOpen = false;
let focused = 0, selected = 0;
const search = { focus() { focused++; }, select() { selected++; } };
const fakeDocument = {
	readyState: 'complete',
	querySelector(selector) {
		if (selector === 'dialog[open], [role="dialog"][aria-modal="true"]') {
			return modalOpen ? {} : null;
		}
		assert.equal(selector, '.cb-work-items-page');
		return page;
	},
	addEventListener(name, fn) {
		listeners[name] = fn;
	},
};
vm.runInNewContext(shortcuts, { document: fakeDocument });
assert.ok(listeners.keydown, 'Only one global shortcut listener is initialized');
function keydown(target = null) {
	let prevented = false;
	listeners.keydown({
		key: '/', target,
		defaultPrevented: false, ctrlKey: false, metaKey: false, altKey: false,
		preventDefault() { prevented = true; },
	});
	return prevented;
}
assert.equal(keydown(), false, 'Before AJAX mount, no shortcut is enabled');
page = {
	hidden: false,
	closest(selector) {
		return selector === '[data-cb-work-uwe-panel]' ? { hidden: this.hidden }
			: selector === '[data-cb-work-uwe-canonical]' ? { hidden: false } : null;
	},
	querySelector() { return search; },
};
assert.equal(keydown(), true, 'After AJAX mount, / focuses the real Work Items search');
assert.equal(focused, 1);
assert.equal(selected, 1);
assert.equal(keydown({ matches() { return true; } }), false, 'Typing in input never triggers shortcut');
assert.equal(keydown({
	matches() { return false; },
	closest(selector) { return selector.includes('[contenteditable]') ? {} : null; },
}), false, 'Nested contenteditable text never triggers shortcut');
modalOpen = true;
assert.equal(keydown(), false, 'Open Base modal must retain its keyboard focus trap');
modalOpen = false;
page.hidden = true;
assert.equal(keydown(), false, 'Projects/Services never steal shortcut focus');
page.hidden = false;
assert.equal(keydown(), true);
assert.equal(focused, 2, 'Returning to Work Items restores native shortcut');
console.log('Work M1 Calendar modal and fast-path parity: PASS (detached AJAX status, 409, 403, native isolation, / focus).');

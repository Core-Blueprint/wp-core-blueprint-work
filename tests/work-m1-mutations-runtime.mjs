import assert from 'node:assert/strict';
import { mutationAction, mutationResponse, createMutationAdapter, MUTATION_ACTIONS } from '../assets/js/workspace/canonical-mutations.js';

assert.deepEqual(MUTATION_ACTIONS, [
	'cb_work_transition_work_item', 'cb_work_quick_edit_work_item',
	'cb_work_bulk_transition_work_items', 'cb_work_bulk_edit_work_items',
]);
function form(action, nonce = 'valid') {
	const inputs = { action, _wpnonce: nonce };
	return {
		method: 'post',
		inputs,
		querySelector(selector) {
			const key = selector.match(/name="([^"]+)"/)?.[1];
			return key && inputs[key] !== undefined ? { value: inputs[key] } : null;
		},
		setAttribute(key, value) { this[key] = value; },
		removeAttribute(key) { delete this[key]; },
	};
}
assert.equal(mutationAction(form('cb_work_quick_edit_work_item')), 'cb_work_quick_edit_work_item');
assert.equal(mutationAction(form('cb_work_bulk_edit_work_items')), 'cb_work_bulk_edit_work_items');
assert.equal(mutationAction(form('evil_action')), null);
assert.equal(mutationAction(form('cb_work_quick_edit_work_item','')), null);
const readOnly = form('cb_work_quick_edit_work_item');readOnly.method='get';
assert.equal(mutationAction(readOnly), null);
assert.deepEqual(mutationResponse({ success: true, data: { updated: 2, attempted: 3, notice: 'ok' } }),
	{ success: true, updated: 2, attempted: 3, notice: 'ok' });
assert.equal(mutationResponse({ success: false, data: { notice: 'invalid' } }).success, false);
assert.throws(() => mutationResponse({ success: true, data: { updated: 4, attempted: 2 } }), TypeError);
assert.throws(() => mutationResponse({ success: true, data: { updated: 0, attempted: 3 } }), TypeError);
assert.throws(() => mutationResponse({ wrong: true }), TypeError);

const listeners = {};
const destination = {
	dataset: {}, children: new Set(),
	contains(item) { return this.children.has(item); },
	setAttribute(key, value) { this.attrs ??= {}; this.attrs[key] = value; },
	removeAttribute(key) { if (this.attrs) delete this.attrs[key]; },
	addEventListener(name, callback) { listeners[name] = callback; },
};
const inserted = [];
const doc = {
	createElement(tag) { return {
		tag, hidden: true, textContent: '', role: '', attrs: {},
		setAttribute(key, value) { this.attrs[key] = value; },
	}; },
};
const panel = {
	dataset: {
		cbWorkUweClassicEndpoint: 'https://example.test/site/wp-admin/admin-ajax.php',
		cbWorkUweMutationSaved: 'Saved', cbWorkUweMutationSaving: 'Saving…',
		cbWorkUweMutationFailed: 'Update unsuccessful',
	},
	ownerDocument: doc,
	insertBefore(node, target) { assert.equal(target, destination); inserted.push(node); },
};
const browser = {
	location: { href: 'https://example.test/site/wp-admin/admin.php?page=core-blueprint-work-workspace' },
	requests: [],
	fetch: async (href, options) => {
		browser.requests.push({ href, options });
		return { ok: true, json: async () => ({ success: true, data: {
			notice: 'work-item-updated', updated: 2, attempted: 3,
		} }) };
	},
};
let refreshes = 0;
const adapter = createMutationAdapter(destination, panel, browser, async () => { refreshes++; });
assert.ok(adapter);
adapter.ready({ dataset: { cbWorkActionsInitialized: '1' } });
assert.equal(destination.dataset.cbWorkMutationsReady, '1');
adapter.disable();
assert.equal(destination.dataset.cbWorkMutationsReady, '0');

const selectedForm = form('cb_work_bulk_edit_work_items');
destination.children.add(selectedForm);
let blocked = false, submitter = { disabled: false };
function send(formObject) {
	listeners.submit({
		target: formObject,
		submitter,
		preventDefault() { blocked = true; },
	});
}
const OriginalFormData = globalThis.FormData;
class FakeFormData {
	constructor(f) { this.fields = new Map(Object.entries(f.inputs)); }
	get(k) { return this.fields.get(k) ?? null; }
}
globalThis.FormData = FakeFormData;
try {
	send(selectedForm);
	await new Promise((resolve) => setImmediate(resolve));
	assert.equal(blocked, true);
	assert.equal(browser.requests.length, 1);
	assert.equal(browser.requests[0].href, 'https://example.test/site/wp-admin/admin-ajax.php');
	assert.equal(browser.requests[0].options.method, 'POST');
	assert.equal(browser.requests[0].options.credentials, 'same-origin');
	assert.equal(browser.requests[0].options.cache, 'no-store');
	assert.equal(browser.requests[0].options.body.get('action'), 'cb_work_bulk_edit_work_items');
	assert.equal(browser.requests[0].options.body.get('_wpnonce'), 'valid');
	assert.equal(refreshes, 1, 'Refresh only after server confirmation');
	assert.equal(submitter.disabled, false);
	assert.equal(inserted[0].textContent, 'Saved (2/3)');

	browser.fetch = async () => ({ ok: false, json: async () => ({
		success: false, data: { notice: 'work-item-update-invalid' },
	}) });
	send(selectedForm);
	await new Promise((resolve) => setImmediate(resolve));
	assert.equal(refreshes, 1, 'Rejected mutations never rehydrate as success');
	assert.equal(inserted[0].attrs.role, 'alert');
	assert.equal(inserted[0].textContent, 'Update unsuccessful');
	assert.equal(submitter.disabled, false);
	assert.equal(destination.children.has(selectedForm), true, 'User input remains available on rejection');

	browser.fetch = async () => { throw new Error('network'); };
	send(selectedForm);
	await new Promise((resolve) => setImmediate(resolve));
	assert.equal(refreshes, 1);
	assert.equal(inserted[0].textContent, 'Update unsuccessful');
	const suppressed = form('cb_work_quick_edit_work_item');
	destination.children.add(suppressed);
	const preflight = browser.requests.length;
	listeners.submit({ target: suppressed, defaultPrevented: true, preventDefault() { throw new Error('Already cancelled'); } });
	assert.equal(browser.requests.length, preflight, 'Existing bulk validation veto must be respected');
	// A second form must never race the first mutation. A successful write
	// must also refresh the currently selected view when its old form was replaced.
	const parallel = form('cb_work_quick_edit_work_item');
	const second = form('cb_work_transition_work_item');
	destination.children.add(parallel);
	destination.children.add(second);
	let finishMutation;
	let mutationCalls = 0;
	browser.fetch = () => {
		mutationCalls++;
		return new Promise((resolve) => { finishMutation = resolve; });
	};
	send(parallel);
	assert.equal(mutationCalls, 1);
	assert.equal(destination.attrs['aria-busy'], 'true');
	send(second);
	assert.equal(mutationCalls, 1, 'Concurrent mutation must be denied while another is pending');
	assert.equal(inserted[0].textContent, 'Saving…');
	destination.children.delete(parallel);
	finishMutation({ ok: true, json: async () => ({
		success: true, data: { notice: 'work-item-updated', updated: 1, attempted: 1 },
	}) });
	await new Promise((resolve) => setImmediate(resolve));
	assert.equal(refreshes, 2, 'A completed write refreshes the current view even after the previous form is replaced');
	assert.equal(destination.attrs['aria-busy'], undefined);
	assert.equal(inserted[0].textContent, 'Saved');

	// A write permission denial must trigger a new authenticated GET so
	// the canonical renderer can remove cached privileged data on 403.
	browser.fetch = async () => ({
		ok: false, status: 403,
		json: async () => { throw new SyntaxError('Forbidden HTML response'); },
	});
	send(second);
	await new Promise((resolve) => setImmediate(resolve));
	assert.equal(refreshes, 3, '403 write denial rechecks the canonical permission boundary');
	assert.equal(inserted[0].attrs.role, 'alert');
	assert.equal(inserted[0].textContent, 'Update unsuccessful');
	assert.equal(destination.attrs['aria-busy'], undefined);
	assert.equal(submitter.disabled, false);

	const bad = form('not-an-action'); destination.children.add(bad);
	const prev = browser.requests.length;
	blocked = false;
	send(bad);
	assert.equal(blocked, true, 'Unknown POST actions are blocked from native navigation');
	assert.equal(browser.requests.length, prev);
	assert.equal(inserted[0].attrs.role, 'alert');
} finally {
	globalThis.FormData = OriginalFormData;
}
const untrusted = { ...panel, dataset: { cbWorkUweClassicEndpoint: 'https://attacker.test/admin-ajax.php' } };
assert.equal(createMutationAdapter(destination, untrusted, browser, async () => {}), null);
console.log('Work M1 mutation adapter runtime: PASS (allowlist, nonce, partial bulk, single-flight, changed view, failure recovery).');

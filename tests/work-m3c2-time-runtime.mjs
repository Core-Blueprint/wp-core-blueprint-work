import assert from 'node:assert/strict';
import { createTimeEntryDialog } from '../assets/js/workspace/time-entry.js';

function element(tag = 'div') {
	return {
		tagName: tag, children: [], hidden: false, disabled: false, value: '',
		textContent: '', setAttribute(key, value) { this[key] = value; },
		append(...children) { this.children.push(...children); },
		replaceChildren(...children) { this.children = children; },
		addEventListener(type, callback) { (this.listeners ||= {})[type] = callback; },
		checkValidity() { return true; },
		focus() { this.focused = true; },
	};
}
function descendants(node) { return [node, ...(node.children || []).flatMap(descendants)]; }
const root = { ownerDocument: { createElement: element } };
const panel = { dataset: {
	cbWorkM3TimeEntry: 'https://example.test/wp-json/cb-work/v1/workspace/time/entry',
	cbWorkM3TimeSearch: 'https://example.test/wp-json/cb-work/v1/workspace/time/work-items',
	cbWorkM3TimeToday: '2026-10-30',
	cbWorkM3TimeBegin: 'Start', cbWorkM3TimeEnd: 'End',
	cbWorkM3TimeItem: 'Work Item', cbWorkM3TimeSearchLabel: 'Search Work Items',
	cbWorkM3TimeNoteLabel: 'Note', cbWorkM3TimeAdd: 'Add Time Entry',
	cbWorkM3TimeCorrect: 'Correct Time Entry', cbWorkM3TimeSave: 'Save Correction',
	cbWorkM3TimeCancel: 'Cancel', cbWorkM3TimeSelect: 'Select',
	cbWorkM3TimeInvalid: 'Invalid', cbWorkM3TimeConflict: 'Conflict',
	cbWorkM3TimeDenied: 'Forbidden',
} };
let operation = 'create', saved = 0, details = 0, mutation = 0, dialogs = 0;
let lastBody = null;
const sent = [];
const browser = {
	location: { href: 'https://example.test/wp-admin/admin.php' },
	cbCore: { modal: { async show(options) {
		dialogs++;
		lastBody = options.body;
		const all = descendants(options.body);
		const select = all.find((node) => node.tagName === 'select');
		if (select) { select.value = '15'; select.disabled = false; }
		const dates = all.filter((node) => node.type === 'date');
		const times = all.filter((node) => node.type === 'time');
		assert.equal(dates.length, 2);
		assert.equal(times.length, 2);
		assert.ok(times.every((node) => node.step === '1'), 'seconds must survive');
		for (const date of dates) date.value = '2026-10-30';
		times[0].value = '08:00:03';
		times[1].value = '09:01:04';
		const note = all.find((node) => node.tagName === 'textarea');
		note.value = '<img src=x> plain note';
		return options.onConfirm();
	} } },
};
const request = async (url, options = {}) => {
	if (!options.method) {
		details++;
		assert.equal(url, panel.dataset.cbWorkM3TimeEntry + '/42');
		return { id: 42, work_item_id: 15, title: '<script>safe title',
			start_date: '2026-10-30', start_time: '08:00:05',
			end_date: '2026-10-30', end_time: '09:01:06',
			note: 'Existing note', revision: 6 };
	}
	assert.equal(url, panel.dataset.cbWorkM3TimeEntry);
	assert.equal(options.method, 'POST');
	mutation++;
	const payload = JSON.parse(options.body);
	sent.push(payload);
	assert.equal(Object.hasOwn(payload, 'user_id'), false, 'actor cannot be forged');
	if (operation === 'stale') throw Object.assign(new Error('stale'), { status: 409 });
	if (operation === 'denied') throw Object.assign(new Error('denied'), { status: 403 });
	return payload.operation === 'create'
		? { created: true, id: 84, revision: 1 }
		: { updated: true, id: 42, revision: 7 };
};
const editor = createTimeEntryDialog(root, panel, browser, request, () => { saved++; });
assert.ok(editor);
assert.equal(await editor.open(0), true);
assert.equal(sent[0].operation, 'create');
assert.equal(sent[0].work_item_id, 15);
assert.equal(sent[0].start_time, '08:00:03');
operation = 'edit';
assert.equal(await editor.open(42), true);
assert.equal(sent[1].operation, 'update');
assert.equal(sent[1].expected_revision, 6);
assert.equal(Object.hasOwn(sent[1], 'work_item_id'), false, 'immutable correction context');
operation = 'stale';
assert.equal(await editor.open(42), false);
assert.equal(descendants(lastBody).find((node) => node.role === 'alert')?.textContent, 'Conflict');
operation = 'denied';
assert.equal(await editor.open(42), false);
assert.equal(lastBody.children.length, 1, 'authorization loss must conceal sensitive fields');
assert.equal(lastBody.children[0].textContent, 'Forbidden');
assert.deepEqual([dialogs, details, mutation, saved], [4, 3, 4, 2]);
editor.dispose();
assert.equal(await editor.open(0), false, 'teardown stops future dialogs');
const wrongOrigin = { ...panel, dataset: {
	...panel.dataset, cbWorkM3TimeEntry: 'https://foreign.example/wp-json/entry',
} };
assert.equal(createTimeEntryDialog(root, wrongOrigin, browser, request), null);
const withoutBase = { ...browser, cbCore: {} };
assert.equal(createTimeEntryDialog(root, panel, withoutBase, request), null);
console.log('Work M3-C2 Time modal: PASS (create, edit, CAS 409, 403, seconds, teardown, origin).');

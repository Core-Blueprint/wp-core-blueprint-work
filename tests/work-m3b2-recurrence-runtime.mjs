import assert from 'node:assert/strict';
import { createEngagementRuleDialog } from '../assets/js/workspace/engagement-rules.js';

function element(tag = 'div') {
	const listeners = new Map();
	return {
		tag, type: '', value: '', textContent: '', disabled: false, hidden: false,
		dataset: {}, children: [],
		append(...children) { this.children.push(...children); },
		appendChild(child) { this.children.push(child); },
		replaceChildren(...children) { this.children = [...children]; },
		setAttribute(name, value) { this[name] = value; },
		addEventListener(name, handler) { listeners.set(name, handler); },
		removeEventListener(name) { listeners.delete(name); },
		fire(name) { listeners.get(name)?.(); },
	};
}
const panel = element('section');
panel.querySelector = () => null;
panel.dataset = {
	cbWorkM3Endpoint: 'https://example.test/wp-json/cb-work/v1/workspace/engagements',
	cbWorkM3RuleEndpoint: 'https://example.test/wp-json/cb-work/v1/workspace/engagements/__id__/rules',
	cbWorkM3RuleStatuses: JSON.stringify({ not_scheduled: 'Not scheduled', completed: 'Completed' }),
	cbWorkM3RuleMonth: 'Period', cbWorkM3RuleSearch: 'Search',
	cbWorkM3RuleAction: 'Link recurring work',
	cbWorkM3RuleEmpty: 'No recurring Work rules configured.',
	cbWorkM3RuleFrom: 'Effective from', cbWorkM3RulePrev: 'Previous',
	cbWorkM3RuleNext: 'Next', cbWorkM3RuleSave: 'Save',
	cbWorkM3RuleStatus: 'Execution status', cbWorkM3RuleScheduled: 'Scheduled',
	cbWorkM3RuleCompleted: 'Completed', cbWorkM3RuleOverdue: 'Overdue',
	cbWorkM3Error: 'Failed', cbWorkM3Cancel: 'Cancel',
};
const root = {
	ownerDocument: { createElement: element },
	querySelector(selector) { return selector === '[data-cb-work-m3-engagements]' ? panel : null; }
};
const engagement = { id: 9, starts_on: '2026-10-01' };
const calls = [];
let deny = false;
let badStatus = false;
let expectedRule = 101;
let updated = 0;
const read = () => ({
	month: '2026-10', engagement: { id: 9, starts_on: '2026-10-01' },
	linked_total: 0,
	period: { status: badStatus ? 'unknown' : 'not_scheduled', scheduled: 0, completed: 0, overdue: 0 },
	rules: [ { id: expectedRule, title: '<unsafe>Maintenance', frequency: 'monthly', next_on: null } ],
	page: 1, pages: 2,
});
const request = async (url, options) => {
	calls.push({ url, options });
	if (deny) throw new Error('403');
	if (options.method === 'POST') {
		assert.deepEqual(JSON.parse(options.body), { rule_id: expectedRule, effective_from: '2026-10-01' });
		return { linked: true };
	}
	return read();
};
let modalCalls = 0;
const browser = {
	location: { href: 'https://example.test/wp-admin/admin.php?page=core-blueprint-work-workspace' },
	cbCore: { modal: { async show(options) {
		modalCalls++;
		assert.equal(options.title, 'Link recurring work');
		const elements = options.body.children;
		assert.equal(elements[0].textContent, 'Execution status: Not scheduled');
		assert.match(elements[1].textContent, /Scheduled: 0/);
		assert.equal(elements[4].children[1].textContent, '<unsafe>Maintenance');
		assert.equal(elements[6].children[1].textContent, '1 / 2');
		elements[4].value = String(expectedRule);
		assert.equal(elements[7].children[1].value, '2026-10-01');
		const successful = await options.onConfirm();
		assert.equal(successful, true);
		return true;
	} } }
};
const view = createEngagementRuleDialog(root, browser, request, () => { updated++; });
assert.ok(view);
const target = element('button');
assert.equal(await view.open(engagement, target), true);
assert.equal(updated, 1, 'canonical follow-up refresh');
assert.equal(calls.length, 2);
assert.equal(calls[0].options.method, 'GET');
assert.equal(calls[1].options.method, 'POST');
assert.equal(new URL(calls[0].url).origin, 'https://example.test');
assert.equal(new URL(calls[0].url).pathname, '/wp-json/cb-work/v1/workspace/engagements/9/rules');
deny = true;
assert.equal(await view.open(engagement, target), false, 'denied read prevents dialog and mutation');
assert.equal(modalCalls, 1);
deny = false;
badStatus = true;
assert.equal(await view.open(engagement, target), false, 'unmapped domain status fails closed');
assert.equal(modalCalls, 1);
badStatus = false;
const badDialog = createEngagementRuleDialog(root, { ...browser, cbCore: {} }, request);
assert.ok(badDialog);
assert.equal(await badDialog.open(engagement, target), false, 'missing shared Base Modal safe');
assert.equal(calls.filter((call) => call.options.method === 'POST').length, 1,
	'no writes after denied or invalid read');
console.log('Work M3-B2 recurrence modal: PASS (status, context-scoped selection, Base Modal, canonical bind, invalid/403).');

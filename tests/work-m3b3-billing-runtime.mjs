import assert from 'node:assert/strict';
import { createEngagementBillingDialog } from '../assets/js/workspace/engagement-billing.js';

function make(tag = 'div') {
	const handlers = new Map();
	return {
		tag, type: '', value: '', textContent: '', hidden: false,
		dataset: {}, children: [],
		append(...xs) { this.children.push(...xs); },
		appendChild(x) { this.children.push(x); },
		replaceChildren(...xs) { this.children = [...xs]; },
		setAttribute(k, v) { this[k] = v; },
		addEventListener(k, h) { handlers.set(k, h); },
		removeEventListener(k) { handlers.delete(k); },
		fire(k) { return handlers.get(k)?.(); },
	};
}
const panel = make('section'), error = make('p');
panel.querySelector = (s) => s === '[data-cb-work-m3-engagements-error]' ? error : null;
panel.dataset = {
	cbWorkM3BillingEndpoint: 'https://example.test/wp-json/cb-work/v1/workspace/engagements/__id__/billing',
	cbWorkM3BillingTermsEndpoint: 'https://example.test/wp-json/cb-work/v1/workspace/engagements/__id__/billing/terms',
	cbWorkM3BillingAction: 'Billing registration',
	cbWorkM3BillingState: 'Registration', cbWorkM3BillingPeriod: 'Period',
	cbWorkM3BillingDate: 'Invoice date', cbWorkM3BillingRef: 'External reference',
	cbWorkM3BillingFrequency: 'Billing frequency', cbWorkM3BillingTiming: 'Invoice timing',
	cbWorkM3BillingStart: 'Start date', cbWorkM3BillingSave: 'Save',
	cbWorkM3Cancel: 'Cancel', cbWorkM3Error: 'Not saved',
	cbWorkM3BillingStatuses: JSON.stringify({
		not_applicable: 'Not applicable', not_recorded: 'Not recorded',
		invoiced: 'Invoiced externally',
	}),
	cbWorkM3BillingFrequencies: JSON.stringify({ monthly: 'Monthly', weekly: 'Weekly',
		quarterly: 'Quarterly', yearly: 'Yearly' }),
	cbWorkM3BillingTimings: JSON.stringify({ advance: 'In advance', arrears: 'After service' }),
};
const root = {
	ownerDocument: { createElement: make },
	querySelector(s) { return s === '[data-cb-work-m3-engagements]' ? panel : null; },
};
const engagement = { id: 9, context: 'customer', starts_on: '2026-09-01' };
const calls = [];
let scenario = 'record';
let fail = false;
let success = 0;
let opened = 0;
let expectedRevision = 4;
const request = async (url, options) => {
	calls.push({ url, options });
	if (fail && options.method === 'POST') throw new Error('HTTP 409');
	if (options.method === 'POST') {
		const sent = JSON.parse(options.body);
		if (scenario === 'terms') {
			assert.deepEqual(sent, { frequency: 'monthly', timing: 'arrears', anchor_on: '2026-09-01' });
			assert.match(url, /\/billing\/terms/);
			return { terms_id: 33 };
		}
		assert.deepEqual(sent, {
			month: '2026-10', state: 'invoiced', invoiced_on: '2026-10-29',
			reference: 'EXT-100', expected_revision: expectedRevision,
		});
		assert.match(url, /\/billing$/);
		return { outcome: 'saved', revision: expectedRevision + 1 };
	}
	if (scenario === 'bad') return {
		engagement_id: 9, month: '2026-10', work_context: 'customer',
		starts_on: '2026-09-01', state: 'unknown', registration: null,
		period_start: null, period_end_exclusive: null,
	};
	const common = {
		engagement_id: 9, month: '2026-10',
		work_context: scenario === 'internal' ? 'internal' : 'customer',
		starts_on: '2026-09-01',
	};
	if (scenario === 'terms' || scenario === 'internal') return {
		...common, state: 'not_applicable', registration: null,
		period_start: null, period_end_exclusive: null,
	};
	return { ...common, state: 'not_recorded',
		period_start: '2026-10-01', period_end_exclusive: '2026-11-01',
		registration: { revision: expectedRevision, invoiced_on: null, external_reference: '' } };
};
const browser = {
	location: { href: 'https://example.test/wp-admin/admin.php?page=core-blueprint-work-workspace' },
	cbCore: { modal: { async show(options) {
		opened++;
		assert.equal(options.title, 'Billing registration');
		const [month, status, coverage, form] = options.body.children;
		assert.equal(month.children[1].value, '2026-10');
		assert.match(status.textContent, /Not recorded|Not applicable/);
		if (scenario === 'internal') {
			assert.equal(form.children.length, 0);
			assert.equal(await options.onConfirm(), false);
			return false;
		}
		assert.equal(form.children.length, 3);
		if (scenario === 'terms') {
			assert.equal(form.children[0].children[1].value, 'monthly');
			assert.equal(form.children[1].children[1].value, 'arrears');
			assert.equal(form.children[2].children[1].value, '2026-09-01');
		} else {
			assert.equal(form.children[0].children[1].value, 'not_recorded');
			form.children[0].children[1].value = 'invoiced';
			form.children[1].children[1].value = '2026-10-29';
			form.children[2].children[1].value = 'EXT-100';
			assert.equal(coverage.textContent, '2026-10-01 → 2026-11-01');
		}
		const accepted = await options.onConfirm();
		if (scenario === 'stale') {
			assert.equal(accepted, false, 'CAS conflict cannot be reported as saved');
			assert.equal(options.body.children[4].hidden, false);
			return false;
		}
		assert.equal(accepted, true);
		return true;
	} } },
};
const view = createEngagementBillingDialog(root, browser, request, () => { success++; });
assert.ok(view);
assert.equal(await view.open(engagement), true);
assert.equal(success, 1, 'canonical receipt refreshes Engagements');
assert.equal(calls.filter(x => x.options.method === 'POST').length, 1);
assert.equal(calls[0].options.method, 'GET');
assert.equal(new URL(calls[0].url).origin, 'https://example.test');

scenario = 'stale'; fail = true;
assert.equal(await view.open(engagement), false);
assert.equal(success, 1, 'stale write never refreshes as success');
fail = false;
scenario = 'internal';
assert.equal(await view.open(engagement), false, 'internal billing remains read-only');
assert.equal(calls.filter(x => x.options.method === 'POST').length, 2,
	'no invoice write for internal');
scenario = 'terms';
assert.equal(await view.open(engagement), true, 'client can initiate optional terms via canonical API');
assert.equal(success, 2);
scenario = 'bad';
assert.equal(await view.open(engagement), false, 'unknown billing state denied without modal');
assert.equal(opened, 4);
assert.equal(error.hidden, false, 'read failure visible in Engagements shell');
assert.equal(calls.every(x => new URL(x.url).origin === 'https://example.test'), true);
const noModal = createEngagementBillingDialog(root, { ...browser, cbCore: {} }, request);
assert.equal(noModal, null, 'no custom fallback modal');
panel.dataset.cbWorkM3BillingEndpoint = 'https://evil.invalid/exfiltrate/__id__';
scenario = 'record';
assert.equal(await view.open(engagement), false, 'cross-origin REST endpoint rejected');
console.log('Work M3-B3 billing UI: PASS (CAS, read-only internal, terms, safe coverage, Base Modal).');

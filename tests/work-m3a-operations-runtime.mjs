import assert from 'node:assert/strict';
import { mountWorkspaceOperations } from '../assets/js/workspace/operations.js';

function element() {
	return { hidden: true, textContent: '', dataset: {} };
}
const service = element();
const recurrence = element();
const timer = element();
const status = element();
const nav = {
	dataset: {
		cbWorkM3Endpoint: 'https://example.test/wp-json/cb-work/v1/workspace/operations',
		cbWorkM3Nonce: 'rest-secret',
		cbWorkM3Unavailable: 'Unavailable',
		cbWorkM3Active: 'Active',
	},
	querySelector(selector) {
		return {
			'[data-cb-work-m3-count="services"]': service,
			'[data-cb-work-m3-count="recurrence"]': recurrence,
			'[data-cb-work-m3-timer]': timer,
			'[data-cb-work-m3-status]': status,
		}[selector] ?? null;
	}
};
const root = { querySelector(selector) { return selector === '[data-cb-work-m3-operations]' ? nav : null; } };
const calls = [];
const listeners = new Map();
let nextStatus = 200;
let snapshot = { services: { total: 7, active: 3 }, recurrence: { total: 5, active: 2 }, time: { running: true, work_item_id: 42 } };
const browser = {
	location: { href: 'https://example.test/wp-admin/admin.php?page=core-blueprint-work-workspace' },
	async fetch(url, options) {
		calls.push({ url, options });
		return { ok: nextStatus === 200, status: nextStatus, json: async () => snapshot };
	},
	addEventListener(name, handler) { listeners.set(name, handler); },
	removeEventListener(name, handler) { if (listeners.get(name) === handler) listeners.delete(name); }
};
const settle = () => new Promise((resolve) => setTimeout(resolve, 0));
const view = mountWorkspaceOperations(root, browser);
assert.ok(view);
assert.equal(nav.dataset.cbWorkM3Mounted, '1');
await settle();
assert.equal(service.textContent, 'Active 3 / 7');
assert.equal(recurrence.textContent, 'Active 2 / 5');
assert.equal(timer.hidden, false);
assert.equal(service.hidden, false);
assert.equal(status.textContent, '');
assert.equal(calls.length, 1);
assert.equal(calls[0].options.method, 'GET');
assert.equal(calls[0].options.credentials, 'same-origin');
assert.equal(calls[0].options.headers['X-WP-Nonce'], 'rest-secret');
assert.equal(calls[0].options.cache, 'no-store');
assert.equal(mountWorkspaceOperations(root, browser), null);

snapshot = { services: { total: 6, active: 2 }, recurrence: { total: 2, active: 1 }, time: { running: false } };
assert.equal(await view.refresh(), true);
assert.equal(service.textContent, 'Active 2 / 6');
assert.equal(recurrence.textContent, 'Active 1 / 2');
assert.equal(timer.hidden, true);

nextStatus = 403;
assert.equal(await view.refresh(), false);
assert.equal(service.hidden, true);
assert.equal(recurrence.hidden, true);
assert.equal(timer.hidden, true);
assert.equal(status.textContent, 'Unavailable');

nextStatus = 200;
snapshot = { services: { total: '<img>', active: 3 }, recurrence: { total: 5, active: 2 }, time: { running: true } };
assert.equal(await view.refresh(), false);
assert.equal(service.hidden, true);
assert.equal(service.textContent, 'Active 2 / 6', 'unsafe payload never reaches textContent');
snapshot = { services: { total: 0, active: 0 }, recurrence: { total: 0, active: 0 }, time: { running: false } };
assert.equal(await view.refresh(), true);
assert.equal(service.textContent, 'Active 0 / 0');

view.dispose();
assert.equal(nav.dataset.cbWorkM3Mounted, undefined);
assert.equal(listeners.has('focus'), false);
assert.equal(await view.refresh(), false, 'disposed shell cannot issue network read');
const hostile = { ...browser, location: browser.location };
nav.dataset.cbWorkM3Endpoint = 'https://elsewhere.invalid/steal';
assert.equal(mountWorkspaceOperations(root, hostile), null, 'cross-origin endpoint denied');
assert.equal(calls.every(({options}) => options.method === 'GET'), true, 'no write transport');
console.log('Work M3-A Operations shell: PASS (hydration, 403, invalid response, focus cleanup, no writes).');

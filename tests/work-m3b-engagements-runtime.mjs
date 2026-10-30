import assert from 'node:assert/strict';
import { mountWorkspaceEngagements } from '../assets/js/workspace/engagements.js';

function node(tag = 'div') {
	const handlers = new Map();
	return {
		tag, textContent: '', value: '', className: '', name: '', hidden: false,
		disabled: false, dataset: {}, children: [],
		setAttribute(name, value) { this[name] = value; },
		append(...children) { this.children.push(...children); },
		appendChild(child) { this.children.push(child); return child; },
		replaceChildren(...children) { this.children = [...children]; },
		addEventListener(type, handler) { handlers.set(type, handler); },
		removeEventListener(type, handler) { if (handlers.get(type) === handler) handlers.delete(type); },
		fire(type, event = {}) { return handlers.get(type)?.({ button: 0, preventDefault() { this.prevented = true; }, ...event }); },
		countHandlers() { return handlers.size; },
		focus() { this.focused = true; },
	};
}
const list = node('ul'), error = node('p'), empty = node('p'), paging = node();
const output = node('output'), prev = node('button'), next = node('button'), close = node('button'), add = node('button');
const search = node('input'), form = node('form'), link = node('a');
form.querySelector = (selector) => selector === '[name="search"]' ? search : null;
let intercepts = 0;
const oldPrevent = { button: 0, defaultPrevented: false, preventDefault() { intercepts++; } };
const selectors = {
	'[data-cb-work-m3-engagements-filter]': form,
	'[data-cb-work-m3-engagements-list]': list,
	'[data-cb-work-m3-engagements-error]': error,
	'[data-cb-work-m3-engagements-empty]': empty,
	'[data-cb-work-m3-engagements-paging]': paging,
	'[data-cb-work-m3-engagements-page]': output,
	'[data-cb-work-m3-engagements-prev]': prev,
	'[data-cb-work-m3-engagements-next]': next,
	'[data-cb-work-m3-engagements-close]': close,
	'[data-cb-work-m3-engagements-add]': add,
};
const panel = node('section');
panel.hidden = true;
panel.dataset = {
	cbWorkM3Endpoint: 'https://example.test/wp-json/cb-work/v1/workspace/engagements',
	cbWorkM3Projects: 'https://example.test/wp-json/cb-work/v1/workspace/projects',
	cbWorkM3Services: 'https://example.test/wp-json/cb-work/v1/workspace/services',
	cbWorkM3Nonce: 'nonce123',
	cbWorkM3Error: 'Save failed', cbWorkM3Project: 'Project', cbWorkM3Service: 'Service',
	cbWorkM3Start: 'Start date', cbWorkM3Label: 'Label', cbWorkM3Add: 'Add service',
	cbWorkM3Create: 'Create', cbWorkM3Cancel: 'Cancel', cbWorkM3NoProjects: 'No projects',
	cbWorkM3NoServices: 'No services', cbWorkM3ContextInternal: 'Internal',
	cbWorkM3ContextCustomer: 'Customer',
};
panel.querySelector = (selector) => selectors[selector] ?? null;
const nav = { querySelector: (selector) => selector === '[data-cb-work-m3-service-link]' ? link : null };
const root = {
	ownerDocument: { createElement: node },
	querySelector(selector) {
		return selector === '[data-cb-work-m3-operations]' ? nav
			: selector === '[data-cb-work-m3-engagements]' ? panel : null;
	}
};
const responses = [];
const calls = [];
let listings = [{ id: 19, project: '<script>bad</script>', service: 'Maintenance', label: 'Site',
	context: 'customer', starts_on: '2026-10-30', is_active: true }];
let fail = false;
const tick = () => new Promise((resolve) => setTimeout(resolve, 0));

const browser = {
	location: { href: 'https://example.test/wp-admin/admin.php?page=core-blueprint-work-workspace' },
	async fetch(url, options) {
		calls.push({ url, options });
		if (fail) return { ok: false, status: 403 };
		const path = new URL(url).pathname;
		let data;
		if (path.endsWith('/projects')) data = { items: [{ id: 3, title: 'Acme' }] };
		else if (path.endsWith('/services')) data = { items: [{ id: 5, title: 'Maintenance' }] };
		else if (options.method === 'POST') {
			assert.deepEqual(JSON.parse(options.body), {
				project_id: 3, service_id: 5, starts_on: '2026-10-31', label: 'New',
			});
			data = { id: 77 };
			listings = [...listings, { id: 77, project: 'Acme', service: 'Maintenance', label: 'New',
				context: 'internal', starts_on: '2026-10-31', is_active: true }];
		} else data = { items: listings, total: listings.length, page: 1, pages: 1 };
		return { ok: true, status: options.method === 'POST' ? 201 : 200, json: async () => data };
	},
	cbCore: {
		modal: {
			async show(settings) {
				responses.push(settings);
				assert.equal(settings.title, 'Add service');
				await tick(); await tick(); // asynchronous Work catalogs
				const [project, service, date, label] = settings.body.children;
				project.children[1].value = '3'; // select after Work search result
				service.children[1].value = '5';
				date.children[1].value = '2026-10-31';
				label.children[1].value = 'New';
				const accepted = await settings.onConfirm();
				assert.equal(accepted, true);
				return true;
			},
		}
	}
};

let summaryUpdates = 0;
const view = mountWorkspaceEngagements(root, browser, () => { summaryUpdates++; });
assert.ok(view);
assert.equal(panel.dataset.cbWorkM3EngagementsMounted, '1');
assert.equal(mountWorkspaceEngagements(root, browser), null, 'idempotent mount');
link.fire('click', oldPrevent);
await tick(); await tick();
assert.equal(intercepts, 1, 'plain click remains in Workspace');
assert.equal(panel.hidden, false);
assert.equal(add.hidden, false);
assert.equal(list.children.length, 1);
assert.equal(list.children[0].children[0].textContent, '<script>bad</script> / Maintenance / Site', 'unsafe text is never interpolated as HTML');
assert.equal(calls[0].options.headers['X-WP-Nonce'], 'nonce123');
assert.equal(calls[0].options.credentials, 'same-origin');
assert.equal(calls[0].options.method, 'GET');

add.fire('click');
await tick(); await tick(); await tick();
assert.equal(responses.length, 1, 'uses shared Base Modal');
assert.equal(calls.filter((call) => call.options.method === 'POST').length, 1, 'one canonical create request');
assert.equal(list.children.length, 2, 'new Engagement appears without reload');
assert.equal(summaryUpdates, 1, 'existing Operations summary refreshed after canonical create');

fail = true;
assert.equal(await view.refresh(1), false);
assert.equal(error.hidden, false);
assert.equal(list.hidden, true, 'no stale rows on 403');
fail = false;
listings = [{ ...listings[0], context: '<img>' }];
assert.equal(await view.refresh(1), false, 'invalid context does not become customer');
assert.equal(error.hidden, false);
listings = [];
assert.equal(await view.refresh(1), true);
assert.equal(empty.hidden, false);

close.fire('click');
assert.equal(panel.hidden, true);
assert.equal(link.focused, true);
view.dispose();
assert.equal(panel.dataset.cbWorkM3EngagementsMounted, undefined);
assert.equal(link.countHandlers(), 0);
assert.equal(await view.refresh(1), false);

const noModal = { ...browser, cbCore: {} };
assert.equal(mountWorkspaceEngagements(root, noModal), null, 'native link remains usable without Base Modal');
panel.dataset.cbWorkM3Endpoint = 'https://evil.example/collect';
assert.equal(mountWorkspaceEngagements(root, browser), null, 'cross-origin service API denied');
assert.equal(calls.every((call) => new URL(call.url).origin === 'https://example.test'), true);
console.log('Work M3-B1 Engagements UI: PASS (live list/create, Base Modal, nonce, 403, stale data and native fallback).');

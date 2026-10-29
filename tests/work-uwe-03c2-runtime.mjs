import assert from 'node:assert/strict';
import {
	CANONICAL_VIEWS, readCanonicalView, canonicalViewUrl,
	canonicalViewParameters, createCanonicalViewMount,
} from '../assets/js/workspace/canonical-mount.js';

const base = 'https://example.test/site/wp-admin/admin.php?page=core-blueprint-work-workspace';
assert.deepEqual(CANONICAL_VIEWS, ['table', 'list', 'kanban', 'calendar']);
assert.equal(readCanonicalView(base), 'table');
assert.equal(readCanonicalView(base + '&view=invalid', 'kanban'), 'kanban');
assert.equal(readCanonicalView(base + '&view=calendar'), 'calendar');
assert.throws(() => canonicalViewUrl(base, 'unsupported'), TypeError);
const target = canonicalViewUrl(base + '&section=work-items&uwe_page=3&search=test', 'list');
assert.equal(target, '/site/wp-admin/admin.php?page=core-blueprint-work-workspace&section=work-items&search=test&view=list');
const targetUrl = new URL(target, base);
assert.equal(targetUrl.searchParams.get('page'), 'core-blueprint-work-workspace');
assert.equal(targetUrl.searchParams.get('uwe_page'), null);
assert.equal(targetUrl.searchParams.get('view'), 'list');

const params = canonicalViewParameters(base + '&view=calendar&search=Agenda&status=planned&uwe_page=4&sort=title',
	'cb_work_uwe_render_view', 'nonce-value', 'table');
assert.equal(params.get('action'), 'cb_work_uwe_render_view');
assert.equal(params.get('nonce'), 'nonce-value');
assert.equal(params.get('view'), 'calendar');
assert.equal(params.get('s'), 'Agenda');
assert.equal(params.get('paged'), '4');
assert.equal(params.get('page'), null, 'WordPress routing page must not leak into the AJAX query');
assert.equal(params.get('status'), 'planned');
assert.equal(params.get('sort'), 'title');

function element(hidden = false) {
	return {
		hidden, dataset: {}, attributes: new Map(), classList: {
			items: new Set(),
			toggle(name, value) { if (value) this.items.add(name); else this.items.delete(name); },
		},
		setAttribute(name, value) { this.attributes.set(name, String(value)); },
		removeAttribute(name) { this.attributes.delete(name); },
		querySelector: () => null,
	};
}
const canonical = element();
canonical.querySelector = (selector) => selector === 'script, iframe, object, embed' ? null
	: selector === '.cb-work-calendar' ? element() : null;
globalThis.DOMParser = class {
	parseFromString(html, mime) {
		assert.equal(mime, 'text/html');
		assert.ok(html.includes('cb-work-items-page'));
		return { querySelector(selector) {
			return selector === '.cb-work-items-page' ? canonical : null;
		} };
	}
};
let onClick;
const viewLinks = Object.fromEntries(CANONICAL_VIEWS.map((view) => {
	const link = element();
	link.dataset.cbWorkUweView = view;
	return [view, link];
}));
const switcher = element();
switcher.dataset.cbWorkUweDefaultView = 'table';
switcher.querySelectorAll = () => Object.values(viewLinks);
switcher.querySelector = () => ({
	textContent: 'Table',
});
switcher.addEventListener = (name, handler) => { assert.equal(name, 'click'); onClick = handler; };
const destination = element(true);
destination.replaceChildren = (...nodes) => { destination.children = nodes; };
const heading = element(false);
const controls = element(true), list = element(false), empty = element(true), pagination = element(true);
const feedback = element(true), error = element(true);
const selectors = {
	'[data-cb-work-uwe-canonical]': destination,
	'[data-cb-work-uwe-view-switcher]': switcher,
	'[data-cb-work-uwe-heading]': heading,
	'[data-cb-work-uwe-controls]': controls,
	'[data-cb-work-uwe-list]': list,
	'[data-cb-work-uwe-empty]': empty,
	'[data-cb-work-uwe-pagination]': pagination,
	'[data-cb-work-uwe-feedback]': feedback,
	'[data-cb-work-uwe-error]': error,
};
const panel = element();
panel.dataset = {
	cbWorkUweClassicEndpoint: 'https://example.test/site/wp-admin/admin-ajax.php',
	cbWorkUweClassicAction: 'cb_work_uwe_render_view',
	cbWorkUweClassicNonce: 'private-nonce',
};
panel.querySelector = (selector) => selectors[selector] || null;
panel.ownerDocument = { importNode: (node, deep) => {
	assert.equal(deep, true);
	return node;
} };
const browser = {
	location: { href: base },
	history: { pushState(_state, _title, target) { browser.location.href = new URL(target, base).href; } },
	fetch: async (url, options) => {
		browser.lastRequest = { url, options };
		const view = new URL(url).searchParams.get('view');
		return { ok: true, json: async () => ({
			success: true, data: { view, html: '<div class="cb-work-items-page"></div>' },
		}) };
	},
};
let navigations = 0;
const mount = createCanonicalViewMount(panel, browser, () => { navigations++; return mount.refresh(); });
assert.ok(mount);
await mount.refresh();
assert.equal(new URL(browser.lastRequest.url).searchParams.get('action'), 'cb_work_uwe_render_view');
assert.equal(browser.lastRequest.options.method, 'GET');
assert.equal(browser.lastRequest.options.credentials, 'same-origin');
assert.equal(browser.lastRequest.options.cache, 'no-store');
assert.equal(destination.hidden, false);
assert.equal(heading.hidden, true);
assert.equal(list.hidden, true);
assert.equal(feedback.hidden, true);
assert.equal(panel.attributes.has('aria-busy'), false);

let prevented = false;
onClick({
	target: { closest: () => viewLinks.calendar },
	button: 0,
	ctrlKey: false, metaKey: false, shiftKey: false, altKey: false,
	defaultPrevented: false,
	preventDefault() { prevented = true; },
});
assert.equal(prevented, true);
assert.equal(navigations, 1);
assert.equal(new URL(browser.location.href).searchParams.get('view'), 'calendar');
await Promise.resolve();
await Promise.resolve();
await Promise.resolve();
assert.equal(new URL(browser.lastRequest.url).searchParams.get('view'), 'calendar');
assert.equal(viewLinks.calendar.attributes.get('aria-current'), 'page');

browser.fetch = async () => { throw new Error('network offline'); };
// Mount binds browser.fetch at creation? It accesses the live property, so fallback works.
await mount.refresh();
assert.equal(destination.hidden, true);
assert.equal(heading.hidden, false);
assert.equal(list.hidden, false);
assert.equal(empty.hidden, true);
assert.equal(controls.hidden, true);
assert.equal(error.hidden, false);
assert.equal(panel.attributes.has('aria-busy'), false);
mount.abort();
console.log('UWE-03C.2 runtime: PASS (view routing, AJAX security, mounting, error fallback).');

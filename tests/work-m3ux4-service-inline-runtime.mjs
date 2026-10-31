import assert from 'node:assert/strict';
import { mountWorkspaceEngagements } from '../assets/js/workspace/engagements.js';

// Realistic Services DOM: the navigation link is no longer in global Operations.
// This exercises the inline manager, URL history, empty state, REST failure,
// and native progressive fallback, not the older Base-modal host.
function element(tag = 'div') {
	const events = new Map();
	const classes = new Set();
	return {
		tag, hidden: false, textContent: '', value: '', dataset: {},
		children: [], parentNode: null,
		classList: {
			add(name) { classes.add(name); },
			remove(name) { classes.delete(name); },
			contains(name) { return classes.has(name); },
			toggle(name, force) {
				if (force === true) classes.add(name);
				else if (force === false) classes.delete(name);
				else if (classes.has(name)) classes.delete(name);
				else classes.add(name);
				return classes.has(name);
			},
		},
		setAttribute(name, value) { this[name] = value; },
		removeAttribute(name) { delete this[name]; },
		appendChild(node) {
			if (node.parentNode) node.parentNode.removeChild(node);
			this.children.push(node); node.parentNode = this; return node;
		},
		removeChild(node) {
			const position = this.children.indexOf(node);
			if (position >= 0) this.children.splice(position, 1);
			node.parentNode = null;
		},
		insertBefore(node, before) {
			if (node.parentNode) node.parentNode.removeChild(node);
			const position = before ? this.children.indexOf(before) : -1;
			this.children.splice(position < 0 ? this.children.length : position, 0, node);
			node.parentNode = this; return node;
		},
		get nextSibling() {
			if (!this.parentNode) return null;
			return this.parentNode.children[this.parentNode.children.indexOf(this) + 1] || null;
		},
		replaceChildren(...nodes) { this.children = []; for (const node of nodes) this.appendChild(node); },
		addEventListener(type, callback) { events.set(type, callback); },
		removeEventListener(type, callback) { if (events.get(type) === callback) events.delete(type); },
		click() { return events.get('click')?.({ button:0, defaultPrevented:false, ctrlKey:false, metaKey:false, altKey:false, shiftKey:false, preventDefault() { this.prevented = true; } }); },
		focus() { this.focused = true; },
	};
}
const section = element('section');
const container = section.appendChild(element());
const listButton = section.appendChild(element('button'));
listButton.hidden = true;
const link = element('a');
const nav = element('nav');
const nativeParent = element('div');
const panel = nativeParent.appendChild(element('section'));
panel.hidden = true;
panel.dataset = {
	cbWorkM3Endpoint: 'https://example.test/wp-json/cb-work/v1/workspace/engagements',
	cbWorkM3Projects: 'https://example.test/wp-json/cb-work/v1/workspace/projects',
	cbWorkM3Services: 'https://example.test/wp-json/cb-work/v1/workspace/services',
	cbWorkM3Nonce: 'nonce', cbWorkM3NoServices:'No services found.',
	cbWorkM3EmptySearch:'No matching services.',
};
const form = element('form'), search = element('input');
form.querySelector = (selector) => selector === '[name="search"]' ? search : null;
const list = element('ul'), error = element('p'), empty = element('p'),
	paging = element(), output = element('output'), previous = element('button'),
	next = element('button'), close = element('button'), add = element('button');
const fields = {
	'[data-cb-work-m3-engagements-filter]':form,
	'[data-cb-work-m3-engagements-list]':list,
	'[data-cb-work-m3-engagements-error]':error,
	'[data-cb-work-m3-engagements-empty]':empty,
	'[data-cb-work-m3-engagements-paging]':paging,
	'[data-cb-work-m3-engagements-page]':output,
	'[data-cb-work-m3-engagements-prev]':previous,
	'[data-cb-work-m3-engagements-next]':next,
	'[data-cb-work-m3-engagements-close]':close,
	'[data-cb-work-m3-engagements-add]':add,
};
panel.querySelector = (selector) => fields[selector] ?? null;
section.querySelector = (selector) =>
	selector === '[data-cb-work-m3-services-inline]' ? container
		: selector === '[data-cb-work-m3-service-list]' ? listButton : null;
const root = {
	ownerDocument: { createElement: element },
	querySelector(selector) {
		return {
			'[data-cb-work-m3-operations]':nav,
			'[data-cb-work-m3-engagements]':panel,
			'[data-cb-work-m3-service-link]':link,
			'[data-cb-work-uwe-panel="services"]':section,
		}[selector] ?? null;
	},
};
const baseUrl = 'https://example.test/wp-admin/admin.php?page=core-blueprint-work-workspace&section=services';
const events = new Map(), history = [];
let denied = false, modalCount = 0, getCount = 0;
const browser = {
	location: {href:baseUrl},
	history: {
		pushState(state, title, url) {
			history.push(url);
			browser.location.href = new URL(url, browser.location.href).href;
		},
	},
	addEventListener(type, fn) { events.set(type, fn); },
	removeEventListener(type, fn) { if (events.get(type) === fn) events.delete(type); },
	async fetch(url, options) {
		getCount++;
		assert.equal(new URL(url).origin, 'https://example.test');
		assert.equal(options.headers['X-WP-Nonce'], 'nonce');
		return denied ? {ok:false,status:403} : {
			ok:true, status:200,
			json:async () => ({items:[],total:0,page:1,pages:1}),
		};
	},
	cbCore: {modal: {show() {modalCount++; return Promise.resolve(false);}}},
};
const settle = () => new Promise(resolve => setTimeout(resolve,0));
const view = mountWorkspaceEngagements(root,browser);
assert.ok(view,'Services inline manager mounts with contextual link');
assert.equal(panel.parentNode,container,'same live panel moves into the Services subview');
assert.equal(container.hidden,true);
assert.equal(panel.hidden,true);
assert.equal(listButton.hidden,false,'secondary Services navigation is progressively enhanced');
assert.equal(modalCount,0);
link.click(); await settle();
assert.equal(new URL(browser.location.href).searchParams.get('services_view'),'operations');
assert.equal(section.classList.contains('is-operations-open'),true);
assert.equal(container.hidden,false);
assert.equal(panel.hidden,false);
assert.equal(empty.hidden,false);
assert.equal(empty.textContent,'No services found.');
assert.equal(add.hidden,false);
assert.equal(getCount,1);
assert.equal(modalCount,0,'opening Service Operations never launches a wide Base modal');
search.value = 'absent';
assert.equal(await view.refresh(1),true);
assert.equal(empty.textContent,'No matching services.','search-specific empty state');
denied = true;
assert.equal(await view.refresh(1),false);
assert.equal(error.hidden,false);
assert.equal(panel.classList.contains('is-error'),true,'native fallback may be revealed after transport failure');
assert.equal(list.hidden,true,'no stale items after permission loss');
listButton.click();
assert.equal(new URL(browser.location.href).searchParams.has('services_view'),false);
assert.equal(panel.hidden,true);
assert.equal(listButton.focused,undefined);
browser.location.href = new URL(history[0],baseUrl).href;
events.get('popstate')();
await settle();
assert.equal(panel.hidden,false,'Back restores the Services operations view');
view.sync('recurring');
assert.equal(panel.hidden,true,'other Work section hides the manager');
view.sync('services'); await settle();
assert.equal(panel.hidden,false,'return to Services restores selected subview');
view.dispose();
assert.equal(panel.parentNode,nativeParent,'live native panel restored on teardown');
assert.equal(panel.dataset.cbWorkM3EngagementsMounted,undefined);
console.log('Work M3-UX4 inline Services integration: PASS (panel placement, URL/Back, empty/error, modal avoidance, teardown).');

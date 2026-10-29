import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const dir = new URL('../assets/', import.meta.url);
const source = fs.readFileSync(new URL('work-items-reorder.js', dir), 'utf8')
	.replace(/^import .*;\s*$/gm, '').replace(/^export \{[^}]+\};\s*$/gm, '');

class Element {
	constructor() {
		this.dataset = {};
		this.hidden = true;
		this.listeners = {};
		this.attributes = new Map();
	}
	addEventListener(name, callback, options) {
		this.listeners[name] ??= [];
		this.listeners[name].push({ callback, options });
	}
	querySelector() { return null; }
	querySelectorAll() { return []; }
	closest() { return null; }
	setAttribute(name, value) { this.attributes.set(name, value); }
}
class Button extends Element {}
class Table extends Element {}
class Input extends Element {}

const documentListeners = new Map();
const document = {
	querySelectorAll: () => [],
	addEventListener(name, callback, options) {
		documentListeners.set(name, [...(documentListeners.get(name) || []), { callback, options }]);
	},
	getElementById() { return null; },
};

const sandbox = {
	document, window: { cbCore: { reorder: { enhance() {} } }, location: { reload() {} } },
	HTMLElement: Element, HTMLButtonElement: Button, HTMLTableElement: Table,
	HTMLInputElement: Input, HTMLSelectElement: Element, Node: Element,
	AbortController, CustomEvent: class {}, Set, WeakMap, FormData,
};
vm.runInNewContext(source, sandbox, { filename: 'work-items-reorder.js' });
assert.ok(documentListeners.get('cb-work:canonical-view-mounted')?.length);
assert.ok(documentListeners.get('cb-work:canonical-view-unmount')?.length);

const toggle = new Button();
const panel = new Element();
const root = new Element();
root.querySelector = (selector) => selector === '[data-cb-work-list-display-toggle]' ? toggle
	: selector === '[data-cb-work-list-display-panel]' ? panel : null;
root.querySelectorAll = () => [];
root.closest = () => null;
const fragment = { querySelectorAll(selector) {
	return selector === '[data-cb-work-list-display]' ? [root] : [];
} };
const fire = (name) => {
	for (const listener of documentListeners.get(name) || []) {
		listener.callback({ detail: { root: fragment } });
	}
};

fire('cb-work:canonical-view-mounted');
assert.equal(root.dataset.cbWorkListDisplayMounted, '1');
assert.equal(toggle.listeners.click.length, 1);
assert.equal(documentListeners.get('click')?.length, 1);
const firstDocumentListener = documentListeners.get('click')[0];
assert.ok(firstDocumentListener.options?.signal);
assert.equal(firstDocumentListener.options.signal.aborted, false);

fire('cb-work:canonical-view-mounted');
assert.equal(toggle.listeners.click.length, 1, 'The same fragment must not register duplicate handlers.');
assert.equal(documentListeners.get('click')?.length, 1, 'Repeated mount must not add a second document listener.');

fire('cb-work:canonical-view-unmount');
assert.equal(firstDocumentListener.options.signal.aborted, true, 'Unmount aborts the document listener.');
assert.equal(root.dataset.cbWorkListDisplayMounted, undefined);

const replacement = new Element();
const replacementToggle = new Button();
const replacementPanel = new Element();
replacement.querySelector = (selector) => selector === '[data-cb-work-list-display-toggle]' ? replacementToggle
	: selector === '[data-cb-work-list-display-panel]' ? replacementPanel : null;
fragment.querySelectorAll = (selector) => selector === '[data-cb-work-list-display]' ? [replacement] : [];
fire('cb-work:canonical-view-mounted');
assert.equal(replacementToggle.listeners.click.length, 1);
assert.equal(documentListeners.get('click').length, 2);
assert.equal(documentListeners.get('click')[1].options.signal.aborted, false);

const prefSource = fs.readFileSync(new URL('work-view-preferences.js', dir), 'utf8')
	.replace(/^import .*;\s*$/gm, '');
const prefHandlers = new Map();
const documentForView = {
	querySelectorAll() { return []; },
	addEventListener(name, callback) { prefHandlers.set(name, callback); },
};
vm.runInNewContext(prefSource, { document: documentForView }, { filename: 'work-view-preferences.js' });
assert.ok(prefHandlers.has('cb-work:canonical-view-mounted'));
const prefTrigger = { dataset: {}, attached: 0, addEventListener() { this.attached++; } };
const prefFragment = { querySelectorAll(selector) {
	return selector === '[data-cb-work-view-preferences-open]' ? [prefTrigger] : [];
} };
prefHandlers.get('cb-work:canonical-view-mounted')({ detail: { root: prefFragment } });
prefHandlers.get('cb-work:canonical-view-mounted')({ detail: { root: prefFragment } });
assert.equal(prefTrigger.attached, 1, 'View preferences trigger must mount once per fragment.');
console.log('Work M1 preference lifecycle: PASS (mount, repeat mount, abort cleanup, replacement, modal trigger).');

import assert from 'node:assert/strict';
import { createOperationDialog } from '../assets/js/workspace/operation-dialog.js';

// Isolated native DOM semantics, including reparenting and Base promise close.
class Node {
	constructor(tag, doc) {
		this.tag = tag;
		this.ownerDocument = doc;
		this.parentNode = null;
		this.children = [];
		this.hidden = false;
		this.listeners = new Map();
	}
	setAttribute() {}
	addEventListener(name, fn) { this.listeners.set(name, fn); }
	removeEventListener(name) { this.listeners.delete(name); }
	removeChild(node) {
		const index = this.children.indexOf(node);
		assert.ok(index >= 0);
		this.children.splice(index, 1);
		node.parentNode = null;
		return node;
	}
	appendChild(node) {
		node.parentNode?.removeChild(node);
		this.children.push(node);
		node.parentNode = this;
		return node;
	}
	insertBefore(node, sibling) {
		node.parentNode?.removeChild(node);
		const index = sibling == null ? this.children.length : this.children.indexOf(sibling);
		assert.ok(index >= 0);
		this.children.splice(index, 0, node);
		node.parentNode = this;
		return node;
	}
	get nextSibling() {
		if (!this.parentNode) return null;
		return this.parentNode.children[this.parentNode.children.indexOf(this) + 1] || null;
	}
	remove() { this.parentNode?.removeChild(this); }
	closest(selector) { return selector === 'dialog' && openDialogs === 1 ? dialog : null; }
}
const doc = { createElement(tag) { return new Node(tag, doc); } };
const parent = doc.createElement('main');
const panel = parent.appendChild(doc.createElement('section'));
let openDialogs = 0, maxDialogs = 0, closed = null, settings = null;
const form = {
	requestSubmit() {
		void Promise.resolve(settings.onConfirm()).then(() => {
			openDialogs--;
			closed(true);
		});
	},
};
const dialog = { querySelector(selector) { return selector === 'form' ? form : null; } };
const browser = {
	requestAnimationFrame(callback) { queueMicrotask(callback); },
	cbCore: { modal: {
		show(config) {
			settings = config;
			openDialogs++;
			maxDialogs = Math.max(maxDialogs, openDialogs);
			assert.equal(config.size, 'workspace');
			assert.equal(config.expandable, true);
			assert.equal(config.dismissOnly, true);
			return new Promise((resolve) => { closed = resolve; });
		},
	} },
};
const refreshes = [];
const host = createOperationDialog(panel, browser, { title: 'Time', onOpen() { refreshes.push(true); } });
assert.ok(host);
const first = host.open();
assert.equal(openDialogs, 1);
assert.equal(host.isOpen(), true);
assert.equal(panel.hidden, false);
assert.equal(panel.parentNode, settings.body);

let nestedFormStopped = false;
panel.listeners.get('submit')({ stopPropagation() { nestedFormStopped = true; } });
assert.equal(nestedFormStopped, true, 'inner form does not bubble into Base close');

let childStarted = false, finishChild;
assert.equal(host.transition(() => new Promise((resolve) => {
	childStarted = true;
	assert.equal(openDialogs, 0, 'parent Base dialog settled before child starts');
	finishChild = resolve;
})), true);
assert.equal(host.transition(() => {}), false, 'two pending child transitions denied');
await new Promise((resolve) => setTimeout(resolve, 0));
assert.equal(childStarted, true);
assert.equal(panel.parentNode, parent, 'same original panel restored after modal');
assert.equal(panel.hidden, true, 'parent list hidden during child modal');
finishChild();
await new Promise((resolve) => setTimeout(resolve, 0));
assert.equal(openDialogs, 1, 'list reopens after child completes');
assert.deepEqual(refreshes, [true, true], 'authoritative list refresh on resume');
assert.equal(maxDialogs, 1, 'never two concurrent Base dialogs');
assert.equal(host.dismiss(), true);
await new Promise((resolve) => setTimeout(resolve, 0));
await first;
assert.equal(openDialogs, 0);
assert.equal(panel.parentNode, parent);
assert.equal(panel.hidden, true);
const disposeWhileOpen = host.open();
assert.equal(openDialogs, 1, 'host can reopen after standard dismissal');
host.dispose();
await disposeWhileOpen;
assert.equal(openDialogs, 0, 'active host disposal settles Base');
assert.equal(panel.listeners.has('submit'), false);
assert.equal(parent.children.length, 1, 'no orphan placeholder after disposal');
assert.equal(createOperationDialog({}, { cbCore: {} }), null, 'Base unavailable: native fallback');
console.log('Work M3-UX2 Base host runtime: PASS (single modal, child handoff, submit isolation, DOM restoration, fallback).');

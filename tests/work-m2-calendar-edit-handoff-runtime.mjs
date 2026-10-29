import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

const source = readFileSync(new URL('../assets/js/workspace/editors.js', import.meta.url), 'utf8');
const stripped = source.replace(/^import .+;$/gm, '').replace(/^export /gm, '')
	+ '\n;globalThis.exports = { mountUnifiedEditors };';
const context = { URL, URLSearchParams, Set, Map, Number, Object };
runInNewContext(stripped, context);
const { mountUnifiedEditors } = context.exports;

const listeners = new Map();
const frames = [];
let dismissClicks = 0;
let fetches = 0;
let prevented = 0;
let stopped = 0;
const document = {
	addEventListener(type, cb) { listeners.set(type, cb); },
};
const root = {
	dataset: { cbWorkM2Endpoint: '/wp-admin/admin-ajax.php', cbWorkM2Nonce: 'test' },
	ownerDocument: document,
	addEventListener() {},
	querySelector() { return { id: 'work-item-panel' }; },
	isConnected: true,
};
const dialogListeners = {};
const dialog = {
	open: true,
	querySelector() { return dismiss; },
	addEventListener(type, cb) { dialogListeners[type] = cb; },
};
const dismiss = {
	disabled: false,
	click() {
		dismissClicks += 1;
		dialog.open = false;
		dialogListeners.close?.();
	},
};
const body = {
	dataset: { cbWorkUweOwner: 'work-item-panel' },
	isConnected: true,
	closest() { return dialog; },
};
const link = {
	href: 'https://example.test/wp-admin/post.php?post=27&action=edit',
	target: '',
	hasAttribute() { return false; },
	closest(selector) {
		if (selector.startsWith('.cb-work-day-modal')) return body;
		if (selector.includes('.cb-work-board__title')) return {};
		return null;
	},
};
const target = { closest(selector) { return selector === 'a[href]' ? link : null; } };
const browser = {
	cbCore: { modal: { show() { throw new Error('Editor modal opened before day dialog closed.'); } } },
	location: { href: 'https://example.test/wp-admin/admin.php?page=core-blueprint-work-workspace' },
	fetch() { fetches += 1; throw new Error('GET must wait until Base closes day dialog.'); },
	requestAnimationFrame(callback) { frames.push(callback); },
};
mountUnifiedEditors(root, browser, () => {});
const onClick = listeners.get('click');
assert.equal(typeof onClick, 'function');
function clickEvent(overrides = {}) {
	return {
		target, defaultPrevented: false, button: 0, ctrlKey: false,
		shiftKey: false, altKey: false, metaKey: false,
		preventDefault() { prevented += 1; },
		stopPropagation() { stopped += 1; },
		...overrides,
	};
}
body.dataset.cbWorkUweOwner = 'different-panel';
onClick(clickEvent());
assert.equal(dismissClicks, 0, 'A dialog from another workspace must be ignored.');
body.dataset.cbWorkUweOwner = 'work-item-panel';
onClick(clickEvent({ ctrlKey: true }));
assert.equal(dismissClicks, 0, 'Modified open-in-new-tab intent must be honored.');
onClick(clickEvent());
assert.equal(prevented, 1);
assert.equal(stopped, 1);
assert.equal(dismissClicks, 1, 'The Base Calendar close button must be activated.');
assert.equal(frames.length, 1, 'Editor opening must wait for Base modal cleanup.');
assert.equal(fetches, 0, 'Never fetch or open Work editor inside the Calendar modal.');
assert.match(source, /body\.dataset\.cbWorkUweOwner !== panel\.id/);
assert.match(source, /dialog\.addEventListener\('close'/);
console.log('M2 Calendar to Work editor Base modal handoff: PASS.');

import assert from 'node:assert/strict';
import { createWorkspaceRouter, readSection, sectionUrl, SECTIONS } from '../assets/js/workspace/router.js';

const base = 'https://example.test/wp-admin/admin.php?page=core-blueprint-work-workspace';
assert.deepEqual(SECTIONS, ['work-items', 'projects', 'services']);
assert.equal(readSection(base), 'work-items');
assert.equal(readSection(base + '&section=projects'), 'projects');
assert.equal(readSection(base + '&section=unknown'), 'work-items');
assert.equal(readSection(base + '&section=services'), 'services');
assert.equal(sectionUrl(base + '&filter=active#panel', 'projects'), '/wp-admin/admin.php?page=core-blueprint-work-workspace&filter=active&section=projects#panel');
assert.equal(sectionUrl(base + '&section=services', 'work-items'), '/wp-admin/admin.php?page=core-blueprint-work-workspace');
assert.throws(() => sectionUrl(base, 'invalid'), TypeError);

const listeners = new Map();
const browser = {
	location: { href: base },
	scrollY: 0,
	addEventListener(name, listener) { listeners.set(name, listener); },
	removeEventListener(name, listener) { if (listeners.get(name) === listener) listeners.delete(name); },
	history: {
		pushState(_state, _title, href) {
			browser.location.href = new URL(href, browser.location.href).href;
		}
	}
};
const events = [];
const router = createWorkspaceRouter(browser, (section, detail) => events.push({ section, ...detail }));
assert.equal(router.section(), 'work-items');
assert.equal(router.navigate('work-items'), false);
assert.equal(router.navigate('invalid'), false);
assert.equal(router.navigate('projects'), true);
assert.equal(router.section(), 'projects');
assert.equal(readSection(browser.location.href), 'projects');
assert.deepEqual(events.at(-1), { section: 'projects', source: 'push', restoreY: 0 });

browser.scrollY = 275;
assert.equal(router.navigate('services'), true);
browser.scrollY = 14;
browser.location.href = base + '&section=projects';
listeners.get('popstate')();
assert.equal(router.section(), 'projects');
assert.deepEqual(events.at(-1), { section: 'projects', source: 'popstate', restoreY: 275 });

browser.location.href = base;
listeners.get('popstate')();
assert.equal(router.section(), 'work-items');
router.dispose();
assert.equal(listeners.has('popstate'), false);
console.log('UWE-03A router runtime: PASS (URL, navigation, scroll state, popstate, teardown)');

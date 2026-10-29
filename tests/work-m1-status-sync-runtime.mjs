import assert from 'node:assert/strict';
import { createCanonicalStatusSync, canonicalViewParameters } from '../assets/js/workspace/canonical-mount.js';

function clock() {
	const scheduled = new Map();
	let id = 0;
	return {
		scheduled,
		setTimeout(callback, ms) { const key = ++id; scheduled.set(key, { callback, ms }); return key; },
		clearTimeout(key) { scheduled.delete(key); },
		fire() {
			const callbacks = [...scheduled.values()];
			scheduled.clear();
			for (const { callback } of callbacks) callback();
		},
	};
}

const browser = clock();
let refreshed = 0;
const sync = createCanonicalStatusSync(browser, () => { refreshed++; }, 600);

sync.request();
assert.equal(browser.scheduled.size, 1, 'One canonical refresh is scheduled');
assert.equal([...browser.scheduled.values()][0].ms, 600);
sync.request();
sync.request();
assert.equal(browser.scheduled.size, 1, 'Rapid status changes coalesce to one GET');
browser.fire();
assert.equal(refreshed, 1, 'Only one authoritative rehydration happens');
assert.equal(browser.scheduled.size, 0);

sync.request();
sync.cancel();
assert.equal(browser.scheduled.size, 0, 'Changing view aborts stale status refresh');
browser.fire();
assert.equal(refreshed, 1, 'Cancelled refresh must not run');
sync.request();
browser.fire();
assert.equal(refreshed, 2, 'Scheduler remains usable after cancellation');
sync.cancel();
assert.equal(browser.scheduled.size, 0);

const base = 'https://example.test/wp-admin/admin.php?page=core-blueprint-work-workspace&view=kanban&status=active&project_id=12&uwe_page=2';
const params = canonicalViewParameters(base, 'cb_work_uwe_render_view', 'private-nonce', 'table');
assert.equal(params.get('action'), 'cb_work_uwe_render_view');
assert.equal(params.get('nonce'), 'private-nonce');
assert.equal(params.get('view'), 'kanban');
assert.equal(params.get('status'), 'active');
assert.equal(params.get('project_id'), '12');
assert.equal(params.get('paged'), '2');
assert.equal(params.get('page'), null, 'WordPress routing page cannot leak into AJAX GET');

console.log('Work M1 cross-view status sync runtime: PASS (debounce, cancellation, rehydration, canonical filters).');

import assert from 'node:assert/strict';
import { isServiceOperationsView, serviceOperationsUrl } from '../assets/js/workspace/engagements.js';

const base = 'https://example.test/wp-admin/admin.php?page=core-blueprint-work-workspace&section=services&search=maintenance&uwe_page=2#work';
const opened = serviceOperationsUrl(base, true);
assert.equal(opened, '/wp-admin/admin.php?page=core-blueprint-work-workspace&section=services&search=maintenance&uwe_page=2&services_view=operations#work');
assert.equal(isServiceOperationsView(new URL(opened, base).href), true);
assert.equal(serviceOperationsUrl(new URL(opened, base).href, true), opened, 'reopening is idempotent');
const closed = serviceOperationsUrl(new URL(opened, base).href, false);
assert.equal(closed, '/wp-admin/admin.php?page=core-blueprint-work-workspace&section=services&search=maintenance&uwe_page=2#work');
assert.equal(isServiceOperationsView(new URL(closed, base).href), false);
assert.equal(isServiceOperationsView(base), false);
assert.equal(isServiceOperationsView('https://example.test/wp-admin/admin.php?section=projects&services_view=operations'), false, 'other section cannot show Operations');
assert.equal(isServiceOperationsView('https://example.test/wp-admin/admin.php?section=services&services_view=invalid'), false);
assert.throws(() => serviceOperationsUrl('https://example.test/wp-admin/admin.php?section=projects', true), TypeError, 'no cross-section open');
assert.equal(serviceOperationsUrl('https://example.test/wp-admin/admin.php?section=projects&services_view=operations', false), '/wp-admin/admin.php?section=projects');
console.log('Work M3-UX4 Service Operations URL state: PASS (revisit, direct link, history, section isolation, WordPress page).');

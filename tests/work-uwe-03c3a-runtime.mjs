import assert from 'node:assert/strict';
import {
	calendarMonthUrl, canonicalViewParameters, canonicalViewUrl,
} from '../assets/js/workspace/canonical-mount.js';

const workspace = 'https://example.test/site/wp-admin/admin.php?page=core-blueprint-work-workspace&section=work-items&view=calendar&uwe_page=3&sort=due#calendar';
const native = 'https://example.test/site/wp-admin/admin.php?page=core-blueprint-work-items&view=calendar&calendar_month=2026-11';
const url = calendarMonthUrl(workspace, native);
assert.ok(url, 'Native calendar month link must be mapped into Workspace routing');
const result = new URL(url, workspace);
assert.equal(result.searchParams.get('page'), 'core-blueprint-work-workspace');
assert.equal(result.searchParams.getAll('page').length, 1);
assert.equal(result.searchParams.get('section'), 'work-items');
assert.equal(result.searchParams.get('view'), 'calendar');
assert.equal(result.searchParams.get('calendar_month'), '2026-11');
assert.equal(result.searchParams.get('uwe_page'), null);
assert.equal(result.searchParams.get('sort'), 'due');
assert.equal(result.hash, '#calendar');

const ajax = canonicalViewParameters(result.href, 'cb_work_uwe_render_view', 'nonce-token', 'calendar');
assert.equal(ajax.get('view'), 'calendar');
assert.equal(ajax.get('calendar_month'), '2026-11');
assert.equal(ajax.get('page'), null);
assert.equal(ajax.get('action'), 'cb_work_uwe_render_view');
assert.equal(ajax.get('nonce'), 'nonce-token');

assert.equal(calendarMonthUrl(workspace, 'https://attacker.example/wp-admin/admin.php?calendar_month=2026-10'), null);
assert.equal(calendarMonthUrl(workspace, native.replace('2026-11', '2026-13')), null);
assert.equal(calendarMonthUrl(workspace, native.replace('2026-11', '2026-00')), null);
assert.equal(calendarMonthUrl(workspace, native.replace('2026-11', '2040-1')), null);
assert.equal(calendarMonthUrl(workspace, native.replace('calendar_month=2026-11', 'other=2026-11')), null);
assert.throws(() => canonicalViewUrl(workspace, 'board'), TypeError);
console.log('UWE-03C.3A lifecycle runtime: PASS (calendar month routing, WP admin page, AJAX filter, cross-origin and invalid dates).');

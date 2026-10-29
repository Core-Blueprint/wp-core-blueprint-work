import assert from 'node:assert/strict';
import {
	FILTER_FIELDS, canonicalFilterUrl, canonicalLinkUrl, snapshotWorkState, restoreWorkState,
} from '../assets/js/workspace/canonical-filters.js';

const base = 'https://example.test/site/wp-admin/admin.php?page=core-blueprint-work-workspace&section=work-items&view=table&search=old&priority=high&uwe_page=3#work';
const asUrl = (path) => new URL(path, base);
const native = 'https://example.test/site/wp-admin/admin.php?page=core-blueprint-work-items';

assert.ok(FILTER_FIELDS.includes('search'));
assert.ok(FILTER_FIELDS.includes('work_type_id'));
assert.ok(FILTER_FIELDS.includes('calendar_month'));

const filtered = canonicalFilterUrl(base, new URLSearchParams([
	['page', 'core-blueprint-work-items'],
	['view', 'kanban'],
	['s', 'Website maintenance'],
	['status', 'active'],
	['project_id', '12'],
	['service_id', '5'],
	['work_type_id', '0'],
	['assignee_id', '9'],
	['priority', 'normal'],
	['sort', 'due'],
]), 'table');
const url = asUrl(filtered);
assert.equal(url.searchParams.get('page'), 'core-blueprint-work-workspace');
assert.equal(url.searchParams.getAll('page').length, 1);
assert.equal(url.searchParams.get('section'), 'work-items');
assert.equal(url.searchParams.get('view'), 'kanban');
assert.equal(url.searchParams.get('search'), 'Website maintenance');
assert.equal(url.searchParams.get('status'), 'active');
assert.equal(url.searchParams.get('project_id'), '12');
assert.equal(url.searchParams.get('service_id'), '5');
assert.equal(url.searchParams.get('work_type_id'), null);
assert.equal(url.searchParams.get('assignee_id'), '9');
assert.equal(url.searchParams.get('sort'), 'due');
assert.equal(url.searchParams.get('uwe_page'), null, 'New filters reset paging');
assert.equal(url.searchParams.get('priority'), 'normal', 'Old filters must be replaced');
assert.equal(url.hash, '#work');

const preset = asUrl(canonicalLinkUrl(base, native + '&view=kanban&status=blocked&assignee_id=4', 'table'));
assert.equal(preset.searchParams.get('status'), 'blocked');
assert.equal(preset.searchParams.get('assignee_id'), '4');
assert.equal(preset.searchParams.get('search'), null, 'A preset replaces the old search');
assert.equal(preset.searchParams.get('page'), 'core-blueprint-work-workspace');

const page = asUrl(canonicalLinkUrl(base, native + '&view=table&s=ops&paged=8&project_id=3', 'table'));
assert.equal(page.searchParams.get('search'), 'ops');
assert.equal(page.searchParams.get('uwe_page'), '8');
assert.equal(page.searchParams.get('project_id'), '3');

const advanced = base.replace('#work', '&customer=crm%3A22&work_type_id=33&calendar_month=2026-10&billing=not_applicable#work');
const saved = snapshotWorkState(advanced);
const projectsUrl = new URL(restoreWorkState(advanced), advanced);
projectsUrl.searchParams.set('section', 'projects');
assert.equal(projectsUrl.searchParams.get('page'), 'core-blueprint-work-workspace');
assert.equal(projectsUrl.searchParams.get('customer'), null, 'Private Work filter must not leak into Projects');
assert.equal(projectsUrl.searchParams.get('calendar_month'), null);
const restored = asUrl(restoreWorkState(projectsUrl.href, saved));
assert.equal(restored.searchParams.get('customer'), 'crm:22');
assert.equal(restored.searchParams.get('work_type_id'), '33');
assert.equal(restored.searchParams.get('calendar_month'), '2026-10');
assert.equal(restored.searchParams.get('billing'), 'not_applicable');
assert.equal(restored.searchParams.get('search'), 'old');
assert.equal(restored.searchParams.get('view'), 'table');
assert.equal(restored.searchParams.get('uwe_page'), '3');
assert.equal(restored.searchParams.get('page'), 'core-blueprint-work-workspace');
assert.equal(restored.searchParams.getAll('page').length, 1);

const cleared = asUrl(canonicalLinkUrl(base, native + '&view=table', 'table'));
assert.equal(cleared.searchParams.get('search'), null);
assert.equal(cleared.searchParams.get('priority'), null);
assert.equal(cleared.searchParams.get('page'), 'core-blueprint-work-workspace');

assert.equal(canonicalLinkUrl(base, 'https://external.example/site/wp-admin/admin.php?page=core-blueprint-work-items'), null);
assert.equal(canonicalLinkUrl(base, '/site/wp-admin/post.php?page=core-blueprint-work-items'), null);
assert.equal(canonicalLinkUrl(base, '/site/wp-admin/admin.php?page=core-blueprint-work-items&action=delete'), null);
assert.equal(canonicalLinkUrl(base, '/site/wp-admin/admin.php?page=core-blueprint-workspace'), null);
assert.equal(asUrl(canonicalLinkUrl(base, '/site/wp-admin/admin.php?page=core-blueprint-work-items&view=../evil', 'table')).searchParams.get('view'), 'table');
assert.equal(asUrl(canonicalFilterUrl(base, new URLSearchParams([['s','<b>plain text</b>']]))).searchParams.get('search'), '<b>plain text</b>',
	'Search remains inert URL text; server and DOM own escaping');

console.log('Work M1 canonical filter router: PASS (search, filters, presets, paging, WP reserved route, unsafe links).');

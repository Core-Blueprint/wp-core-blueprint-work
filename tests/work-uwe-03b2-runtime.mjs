import assert from 'node:assert/strict';
import {
	apiParameters, dataStateUrl, defaultDataState, readDataState,
} from '../assets/js/workspace/state.js';
import { createDataClient } from '../assets/js/workspace/data-client.js';

const base = 'https://example.test/site/wp-admin/admin.php?page=core-blueprint-work-workspace';
const original = base + '&section=work-items&search=sample&sort=due&status=blocked&page=3&per_page=10#table';
assert.deepEqual(readDataState(original, 'work-items'), {
	search: 'sample', sort: 'due', status: 'blocked', page: 3, per_page: 10,
});
assert.deepEqual(readDataState(base + '&page=-4&per_page=900&sort=invalid&status=invalid', 'work-items'),
	defaultDataState('work-items'));
assert.equal(readDataState(base + '&sort=due&status=blocked', 'projects').sort, 'title');
assert.equal(readDataState(base + '&sort=due&status=blocked', 'services').status, '');
assert.equal(readDataState(base + '&search=' + 'a'.repeat(190), 'projects').search.length, 160);
const rewritten = dataStateUrl(original, 'work-items', {
	...defaultDataState('work-items'), search: 'Audit & Service', status: 'planned', page: 2,
});
assert.equal(rewritten, '/site/wp-admin/admin.php?page=core-blueprint-work-workspace&section=work-items&search=Audit+%26+Service&status=planned&page=2#table');
assert.equal(dataStateUrl(base + '&section=projects&status=blocked&per_page=40', 'projects', defaultDataState('projects')),
	'/site/wp-admin/admin.php?page=core-blueprint-work-workspace&section=projects');
assert.equal(apiParameters({ ...defaultDataState('work-items'), status: 'planned' }).get('status'), 'planned');

let observed = null;
const valid = { section: 'work-items', items: [{ id: 9, title: '<script>alert(1)</script>' }], page: 1, total: 1, pages: 1 };
const client = createDataClient({
	baseHref: base,
	fetcher: async (url, options) => {
		observed = { url, options };
		return { ok: true, json: async () => valid };
	},
});
const body = await client.load('https://example.test/site/wp-json/cb-work/v1/workspace/work-items', 'private-nonce',
	apiParameters(defaultDataState('work-items')));
assert.deepEqual(body, valid);
assert.equal(observed.options.method, 'GET');
assert.equal(observed.options.credentials, 'same-origin');
assert.equal(observed.options.headers['X-WP-Nonce'], 'private-nonce');
assert.equal(new URL(observed.url).searchParams.get('per_page'), '25');
assert.equal(new URL(observed.url).searchParams.has('nonce'), false);

let release;
const requests = [];
const concurrent = createDataClient({
	baseHref: base,
	fetcher: (url, options) => {
		requests.push({ url, options });
		return new Promise((resolve) => {
			if (requests.length === 1) release = resolve;
			else resolve({ ok: true, json: async () => valid });
		});
	},
});
const oldRequest = concurrent.load(base, 'nonce', apiParameters(defaultDataState('work-items')));
const freshRequest = concurrent.load(base, 'nonce', apiParameters(defaultDataState('work-items')));
assert.equal(requests[0].options.signal.aborted, true);
assert.equal(requests[1].options.signal.aborted, false);
release({ ok: true, json: async () => valid });
await assert.rejects(oldRequest, { name: 'AbortError' });
assert.deepEqual(await freshRequest, valid);
concurrent.abort();
assert.equal(requests[1].options.signal.aborted, true);

const unauthorized = createDataClient({ baseHref: base, fetcher: async () => ({ ok: false, status: 403 }) });
await assert.rejects(unauthorized.load(base, 'nonce', apiParameters(defaultDataState('work-items'))),
	(error) => error.status === 403);
const invalid = createDataClient({ baseHref: base, fetcher: async () => ({ ok: true, json: async () => ({ items: [] }) }) });
await assert.rejects(invalid.load(base, 'nonce', apiParameters(defaultDataState('work-items'))), TypeError);
console.log('UWE-03B.2 runtime: PASS (URL state, query params, GET/nonce, cancellation, 403, payload validation).');

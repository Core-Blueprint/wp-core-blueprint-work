import assert from 'node:assert/strict';
import { mountWorkspaceTime } from '../assets/js/workspace/time.js';

function el(tag = 'div') {
	const listeners = new Map();
	return {
		tag, textContent: '', value: '', hidden: false, disabled: false, dataset: {},
		children: [], name: '', className: '',
		setAttribute(k, v) { this[k] = v; },
		append(...xs) { this.children.push(...xs); },
		appendChild(x) { this.children.push(x); return x; },
		replaceChildren(...xs) { this.children = [...xs]; },
		addEventListener(k, fn) { listeners.set(k, fn); },
		removeEventListener(k) { listeners.delete(k); },
		fire(k, event = {}) {
			return listeners.get(k)?.({ button: 0, preventDefault() { this.cancelled = true; }, ...event });
		},
		countHandlers() { return listeners.size; },
		focus() { this.focused = true; },
	};
}
const link = el('a'), panel = el('section'), close = el('button');
const error = el('p'), activeZone = el(), activeLabel = el(), stopButton = el('button');
const form = el('form'), search = el('input'), select = el('select'), note = el('textarea');
const start = el('button'), entries = el('ul'), paging = el();
const prev = el('button'), next = el('button'), pageLabel = el('output');
const formNodes = { '[name="search"]': search, '[name="work_item_id"]': select, '[name="note"]': note };
form.querySelector = (sel) => formNodes[sel] ?? null;
const panelNodes = {
	'[data-cb-work-m3-time-close]': close,
	'[data-cb-work-m3-time-error-message]': error,
	'[data-cb-work-m3-time-active-zone]': activeZone,
	'[data-cb-work-m3-time-active-label]': activeLabel,
	'[data-cb-work-m3-time-stop]': stopButton,
	'[data-cb-work-m3-time-start-form]': form,
	'[data-cb-work-m3-time-start]': start,
	'[data-cb-work-m3-time-entries]': entries,
	'[data-cb-work-m3-time-paging]': paging,
	'[data-cb-work-m3-time-prev]': prev,
	'[data-cb-work-m3-time-next]': next,
	'[data-cb-work-m3-time-page]': pageLabel,
};
panel.querySelector = (sel) => panelNodes[sel] ?? null;
panel.dataset = {
	cbWorkM3TimeEndpoint: 'https://example.test/wp-json/cb-work/v1/workspace/time',
	cbWorkM3TimeSearch: 'https://example.test/wp-json/cb-work/v1/workspace/time/work-items',
	cbWorkM3TimeTimer: 'https://example.test/wp-json/cb-work/v1/workspace/time/timer',
	cbWorkM3TimeNonce: 'secret', cbWorkM3TimeSelect: 'Select a Work Item',
	cbWorkM3TimeActive: 'Active timer', cbWorkM3TimeError: 'Timer failed',
};
panel.hidden = true;
const nav = { querySelector(sel) { return sel === '[data-cb-work-m3-time-link]' ? link : null; } };
const root = {
	ownerDocument: { createElement: el },
	querySelector(sel) {
		return sel === '[data-cb-work-m3-operations]' ? nav
			: sel === '[data-cb-work-m3-time]' ? panel : null;
	},
};
let running = null, fail = false, brokenRead = false;
let refreshed = 0, hudEvents = 0, calls = [], mutationCalls = 0;
const browser = {
	location: { href: 'https://example.test/wp-admin/admin.php?page=core-blueprint-work-workspace' },
	dispatchEvent(event) { assert.equal(event.type, 'cb-work-time:changed'); hudEvents++; },
	async fetch(url, opts) {
		calls.push({ url, opts });
		assert.equal(opts.headers['X-WP-Nonce'], 'secret');
		assert.equal(opts.credentials, 'same-origin');
		if (fail) return { ok: false, status: 403 };
		const u = new URL(url);
		let data;
		if (u.pathname.endsWith('/work-items')) data = {
			items: [{ id: 15, label: '<svg/onload=alert(1)>', meta: '#15' }],
		};
		else if (u.pathname.endsWith('/timer')) {
			mutationCalls++;
			const payload = JSON.parse(opts.body);
			if (payload.operation === 'start') {
				assert.deepEqual(payload, { operation: 'start', work_item_id: 15, note: 'Site maintenance' });
				running = { entry_id: 91, work_item_id: 15,
					title: '<svg>Maintenance</svg>', note: 'Site maintenance',
					started_at: '2026-10-30 08:00:00' };
				data = { started: true, entry_id: 91 };
			} else {
				assert.deepEqual(payload, { operation: 'stop' });
				running = null;
				data = { stopped: true, entry_id: 91 };
			}
		} else {
			data = brokenRead ? { garbage: true } : {
				actor_id: 7, timer: running,
				entries: [{ id: 42, work_item_id: 15, title: '<img src=x>',
					started_at: '2026-10-30 08:00:00', ended_at: '2026-10-30 09:00:00',
					duration_seconds: 3600, source: 'timer', revision: 2 }],
				page: 1, pages: 1, total: 1,
			};
		}
		return { ok: true, status: 200, json: async () => data };
	},
};
const tick = () => new Promise((resolve) => setTimeout(resolve, 0));
let clicks = 0;
const api = mountWorkspaceTime(root, browser, () => { refreshed++; });
assert.ok(api);
assert.equal(panel.dataset.cbWorkM3TimeMounted, '1');
assert.equal(mountWorkspaceTime(root, browser), null, 'idempotent mount');
link.fire('click', { preventDefault() { clicks++; } });
await tick();
assert.equal(clicks, 1, 'unmodified click intercepted');
assert.equal(panel.hidden, false);
assert.equal(entries.children.length, 1);
assert.equal(entries.children[0].children[0].textContent, '<img src=x>', 'no dangerous HTML insertion');
assert.equal(form.hidden, false);
assert.equal(activeZone.hidden, true);
search.value = 'Website';
assert.equal(await api.lookup(), true, 'bounded picker search');
assert.equal(select.children[1].textContent, '<svg/onload=alert(1)>', 'search label text not HTML');
assert.equal(select.disabled, false);
select.value = '15';
note.value = 'Site maintenance';
assert.equal(await api.mutate('start'), true);
assert.equal(running?.work_item_id, 15);
assert.equal(activeZone.hidden, false);
assert.equal(form.hidden, true);
assert.equal(hudEvents, 1);
assert.equal(refreshed, 1);
assert.equal(await api.mutate('stop'), true);
assert.equal(activeZone.hidden, true);
assert.equal(form.hidden, false);
assert.equal(hudEvents, 2);
assert.equal(mutationCalls, 2);

fail = true;
assert.equal(await api.refresh(), false);
assert.equal(error.hidden, false, '403 hides stale Time state');
assert.equal(entries.children.length, 0);
fail = false;
brokenRead = true;
assert.equal(await api.refresh(), false, 'untrusted read refused');
brokenRead = false;
assert.equal(await api.refresh(), true, 'retry recovers');
close.fire('click');
assert.equal(panel.hidden, true);
assert.equal(link.focused, true);
api.dispose();
assert.equal(panel.dataset.cbWorkM3TimeMounted, undefined);
assert.equal(link.countHandlers(), 0);
assert.equal(await api.refresh(), false, 'disposed app does not fetch');
panel.dataset.cbWorkM3TimeTimer = 'https://attacker.example/track';
assert.equal(mountWorkspaceTime(root, browser), null, 'cross-origin mutation endpoint rejected');
console.log('Work M3-C1 Time runtime: PASS (Time SPA, timer start/stop, actor reads, picker, 403, HUD, fallback).');

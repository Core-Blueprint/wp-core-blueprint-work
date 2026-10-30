import { createWorkspaceRouter, SECTIONS } from './router.js';
import { apiParameters, dataStateUrl, defaultDataState, readDataState } from './state.js';
import { createDataClient } from './data-client.js';
import { createCanonicalViewMount } from './canonical-mount.js';
import { restoreWorkState, snapshotWorkState } from './canonical-filters.js';
import { mountUnifiedEditors } from './editors.js';
import { mountWorkspaceOperations } from './operations.js';
import { mountWorkspaceEngagements } from './engagements.js';
import { mountWorkspaceTime } from './time.js';

/**
 * UWE-03B.2: progressively enhance the FIELD-stable server view.
 * All content is rendered using textContent (no HTML interpolation).
 */
export function mountWorkspace(root, browser = window) {
	if (!root || root.dataset.cbWorkUweMounted === '1') return null;

	const tabs = new Map();
	const panels = new Map();
	root.querySelectorAll('[data-cb-work-uwe-tab]').forEach((tab) => {
		tabs.set(tab.dataset.cbWorkUweTab, tab);
	});
	root.querySelectorAll('[data-cb-work-uwe-panel]').forEach((panel) => {
		panels.set(panel.dataset.cbWorkUwePanel, panel);
	});
	if (!SECTIONS.every((section) => tabs.has(section) && panels.has(section))) return null;

	root.dataset.cbWorkUweMounted = '1';
	const announcement = root.querySelector('[data-cb-work-uwe-status]');
	const nonce = root.dataset.cbWorkUweNonce || '';
	const memory = new Map(SECTIONS.map((section) => [section, defaultDataState(section)]));
	let activeSection = new URL(browser.location.href).searchParams.get('section');
	if (!SECTIONS.includes(activeSection)) activeSection = 'work-items';
	memory.set(activeSection, readDataState(browser.location.href, activeSection));
	let workSnapshot = activeSection === 'work-items'
		? snapshotWorkState(browser.location.href) : new URLSearchParams();

	const fetcher = typeof browser.fetch === 'function' ? browser.fetch.bind(browser) : null;
	const client = fetcher ? createDataClient({ fetcher, baseHref: browser.location.href }) : null;
	const timers = new Map();
	let refreshSequence = 0;
	const document = root.ownerDocument;
	const canonicalMount = createCanonicalViewMount(
		panels.get('work-items'),
		browser,
		() => refresh('work-items')
	);

	function panelControls(section) {
		const panel = panels.get(section);
		const form = panel.querySelector('[data-cb-work-uwe-controls]');
		return { panel, form, search: form?.elements.namedItem('search'), sort: form?.elements.namedItem('sort'),
			status: form?.elements.namedItem('status') };
	}

	function syncForm(section) {
		const { form, search, sort, status } = panelControls(section);
		if (!form) return;
		const state = readDataState(browser.location.href, section);
		if (search) search.value = state.search;
		if (sort) sort.value = state.sort;
		if (status) status.value = state.status;
	}

	function createRecord(record, section) {
		const row = document.createElement('li');
		row.className = 'cb-work-uwe__record';
		const title = document.createElement('span');
		title.className = 'cb-work-uwe__record-title';
		title.textContent = String(record?.title ?? '');
		if (['projects', 'services'].includes(section) && Number(record?.id) > 0) {
			const button = document.createElement('button');
			button.type = 'button';
			button.className = 'cb-work-uwe__record-title--button';
			button.dataset.cbWorkM2Edit = section === 'projects' ? 'project' : 'service';
			button.dataset.cbWorkM2Id = String(record.id);
			button.textContent = title.textContent;
			title.replaceWith(button);
			row.appendChild(button);
			return row;
		}
		row.appendChild(title);
		return row;
	}

	function updateResults(section, result) {
		const panel = panels.get(section);
		const list = panel.querySelector('[data-cb-work-uwe-list]');
		const empty = panel.querySelector('[data-cb-work-uwe-empty]');
		const pages = panel.querySelector('[data-cb-work-uwe-pagination]');
		const previous = panel.querySelector('[data-cb-work-uwe-prev]');
		const next = panel.querySelector('[data-cb-work-uwe-next]');
		const pageLabel = panel.querySelector('[data-cb-work-uwe-page-label]');
		const count = panel.querySelector('[data-cb-work-uwe-count]');
		if (!list || !empty || !pages || !previous || !next || !pageLabel) return;
		const rows = result.items.map((record) => createRecord(record, section));
		list.replaceChildren(...rows);
		list.hidden = rows.length === 0;
		empty.hidden = rows.length > 0;
		pages.hidden = result.pages < 2;
		previous.disabled = result.page <= 1;
		next.disabled = result.page >= result.pages;
		pageLabel.textContent = (pageLabel.dataset.template || '%1$d / %2$d')
			.replace('%1$d', String(result.page)).replace('%2$d', String(result.pages));
		if (count) count.textContent = String(result.total);
		panel.dataset.cbWorkUwePages = String(result.pages);
	}

	async function refresh(section) {
		if (section === 'work-items' && canonicalMount) {
			workSnapshot = snapshotWorkState(browser.location.href);
			memory.set(section, readDataState(browser.location.href, section));
			await canonicalMount.refresh(browser.location.href);
			return;
		}
		const panel = panels.get(section);
		const endpoint = panel.dataset.cbWorkUweEndpoint;
		if (!client || !nonce || !endpoint) return; // No JavaScript/API: keep SSR fallback.
		const form = panel.querySelector('[data-cb-work-uwe-controls]');
		if (form) form.hidden = false;
		const state = readDataState(browser.location.href, section);
		const sequence = ++refreshSequence;
		memory.set(section, state);
		syncForm(section);

		const feedback = panel.querySelector('[data-cb-work-uwe-feedback]');
		const error = panel.querySelector('[data-cb-work-uwe-error]');
		if (error) error.hidden = true;
		if (feedback) {
			feedback.textContent = tabs.get(section).textContent.trim();
			feedback.hidden = false;
		}
		panel.setAttribute('aria-busy', 'true');
		try {
			const result = await client.load(endpoint, nonce, apiParameters(state));
			if (activeSection !== section || sequence !== refreshSequence) return;
			if (result.section !== section) throw new TypeError('Workspace section mismatch');
			updateResults(section, result);
		} catch (failure) {
			if (failure?.name === 'AbortError' || activeSection !== section || sequence !== refreshSequence) return;
			if (error) error.hidden = false;
		} finally {
			if (activeSection === section && sequence === refreshSequence) {
				panel.removeAttribute('aria-busy');
				if (feedback) feedback.hidden = true;
			}
		}
	}

	function showSection(section, detail = null) {
		if (section !== activeSection && activeSection === 'work-items') {
			canonicalMount?.abort();
		}
		if (section !== activeSection) {
			if (detail?.source === 'push') {
				if (activeSection === 'work-items') {
					workSnapshot = snapshotWorkState(browser.location.href);
				} else {
					memory.set(activeSection, readDataState(browser.location.href, activeSection));
				}
				const withoutWork = restoreWorkState(browser.location.href);
				const destination = section === 'work-items'
					? restoreWorkState(new URL(withoutWork, browser.location.href).href, workSnapshot)
					: dataStateUrl(new URL(withoutWork, browser.location.href).href, section, memory.get(section));
				browser.history.replaceState(browser.history.state, '', destination);
			}
			activeSection = section;
		}
		for (const key of SECTIONS) {
			const selected = key === section;
			const tab = tabs.get(key);
			tab.setAttribute('aria-selected', String(selected));
			tab.tabIndex = selected ? 0 : -1;
			tab.classList.toggle('is-active', selected);
			panels.get(key).hidden = !selected;
		}
		if (detail) {
			if (announcement) announcement.textContent = tabs.get(section).textContent.trim();
			const focused = document.activeElement;
			if (focused && root.contains(focused)) {
				const panel = focused.closest('[data-cb-work-uwe-panel]');
				if (panel && panel.hidden) tabs.get(section).focus();
			}
			if (typeof browser.requestAnimationFrame === 'function' && typeof browser.scrollTo === 'function') {
				browser.requestAnimationFrame(() => browser.scrollTo({ top: detail.restoreY, behavior: 'instant' }));
			}
		}
		if (detail?.source !== 'popstate') void refresh(section);
	}

	const router = createWorkspaceRouter(browser, showSection);
	showSection(router.section());
	mountUnifiedEditors(root, browser, refresh);
	const operationsSummary = mountWorkspaceOperations(root, browser);
	mountWorkspaceEngagements(root, browser, () => operationsSummary?.refresh());
	mountWorkspaceTime(root, browser, () => operationsSummary?.refresh());

	function updateState(section, nextState) {
		if (activeSection !== section) return;
		const nextUrl = dataStateUrl(browser.location.href, section, nextState);
		const current = new URL(browser.location.href);
		if (nextUrl === current.pathname + current.search + current.hash) return;
		browser.history.pushState({ cbWorkUweData: true }, '', nextUrl);
		void refresh(section);
	}

	function collectForm(section) {
		const controls = panelControls(section);
		const current = readDataState(browser.location.href, section);
		return { ...current, search: controls.search?.value ?? '', sort: controls.sort?.value ?? current.sort,
			status: controls.status?.value ?? '', page: 1 };
	}

	function clearTimer(section) {
		if (timers.has(section)) browser.clearTimeout(timers.get(section));
		timers.delete(section);
	}

	for (const [section, tab] of tabs) {
		tab.addEventListener('click', (event) => {
			if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
			event.preventDefault();
			clearTimer(activeSection);
			router.navigate(section);
		});
		tab.addEventListener('keydown', (event) => {
			const index = SECTIONS.indexOf(section);
			let next = null;
			switch (event.key) {
				case 'ArrowRight': next = SECTIONS[(index + 1) % SECTIONS.length]; break;
				case 'ArrowLeft': next = SECTIONS[(index + SECTIONS.length - 1) % SECTIONS.length]; break;
				case 'Home': next = SECTIONS[0]; break;
				case 'End': next = SECTIONS[SECTIONS.length - 1]; break;
				default: return;
			}
			event.preventDefault();
			clearTimer(activeSection);
			tabs.get(next).focus();
			router.navigate(next);
		});
	}

	for (const section of SECTIONS) {
		const { panel, form, search, sort, status } = panelControls(section);
		if (!form) continue;
		form.addEventListener('submit', (event) => {
			event.preventDefault();
			clearTimer(section);
			updateState(section, collectForm(section));
		});
		if (search) search.addEventListener('input', () => {
			clearTimer(section);
			timers.set(section, browser.setTimeout(() => {
				timers.delete(section);
				updateState(section, collectForm(section));
			}, 300));
		});
		for (const field of [sort, status]) {
			if (field) field.addEventListener('change', () => {
				clearTimer(section);
				updateState(section, collectForm(section));
			});
		}
		panel.querySelector('[data-cb-work-uwe-clear]')?.addEventListener('click', () => {
			clearTimer(section);
			updateState(section, defaultDataState(section));
		});
		panel.querySelector('[data-cb-work-uwe-retry]')?.addEventListener('click', () => {
			if (activeSection === section) void refresh(section);
		});
		for (const [control, delta] of [['[data-cb-work-uwe-prev]', -1], ['[data-cb-work-uwe-next]', 1]]) {
			panel.querySelector(control)?.addEventListener('click', () => {
				const current = readDataState(browser.location.href, section);
				const maxPage = Number(panel.dataset.cbWorkUwePages || 1);
				const page = current.page + delta;
				if (page < 1 || page > maxPage) return;
				updateState(section, { ...current, page });
			});
		}
	}

	// createWorkspaceRouter is subscribed first and updates the selected tab.
	browser.addEventListener('popstate', () => {
		clearTimer(activeSection);
		memory.set(activeSection, readDataState(browser.location.href, activeSection));
		void refresh(activeSection);
	});
	return router;
}

function boot() {
	document.querySelectorAll('[data-cb-work-uwe]').forEach((root) => mountWorkspace(root));
}
if (typeof document !== 'undefined') {
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot, { once: true });
	} else {
		boot();
	}
}

/**
 * UWE-03C.2: mount the *canonical* Work Items HTML returned by UWE-03C.1.
 *
 * This is a visual view-integration stage. Golden action lifecycle is reconciled
 * in UWE-03C.3, so the native Work Items screen remains available by link.
 * No second Table/List/Board/Calendar renderer is implemented here.
 */
import { canonicalFilterUrl, canonicalLinkUrl } from './canonical-filters.js';
import { createMutationAdapter } from './canonical-mutations.js';

export const CANONICAL_VIEWS = Object.freeze(['table', 'list', 'kanban', 'calendar']);

export function readCanonicalView(href, fallback = 'table') {
	const requested = new URL(href).searchParams.get('view');
	return CANONICAL_VIEWS.includes(requested) ? requested
		: (CANONICAL_VIEWS.includes(fallback) ? fallback : 'table');
}

export function canonicalViewUrl(href, view) {
	if (!CANONICAL_VIEWS.includes(view)) throw new TypeError('Unknown canonical Work Items view');
	const url = new URL(href);
	url.searchParams.set('view', view);
	url.searchParams.delete('uwe_page');
	return url.pathname + url.search + url.hash;
}

/**
 * Convert the existing Calendar month links to the persistent Work route.
 * The native renderer still produces the original links for no-JS fallback.
 */
export function calendarMonthUrl(href, target) {
	const current = new URL(href);
	const requested = new URL(target, href);
	const month = requested.searchParams.get('calendar_month');
	if (requested.origin !== current.origin || !/^\d{4}-(0[1-9]|1[0-2])$/.test(month ?? '')) return null;
	current.searchParams.set('view', 'calendar');
	current.searchParams.set('calendar_month', month);
	current.searchParams.delete('uwe_page');
	return current.pathname + current.search + current.hash;
}

export function canonicalViewParameters(href, action, nonce, fallback = 'table') {
	const url = new URL(href);
	const query = new URLSearchParams();
	query.set('action', action);
	query.set('nonce', nonce);
	query.set('view', readCanonicalView(href, fallback));
	const allowed = [
		['search', 's'], ['status', 'status'], ['sort', 'sort'],
		['priority', 'priority'], ['project_id', 'project_id'],
		['service_id', 'service_id'], ['work_type_id', 'work_type_id'],
		['assignee_id', 'assignee_id'], ['customer', 'customer'],
		['billing', 'billing'], ['work_context', 'work_context'],
		['calendar_month', 'calendar_month'],
		['scheduled_from', 'scheduled_from'], ['scheduled_to', 'scheduled_to'],
		['due_from', 'due_from'], ['due_to', 'due_to'],
	];
	for (const [from, to] of allowed) {
		const value = url.searchParams.get(from);
		if (value) query.set(to, value);
	}
	const page = url.searchParams.get('uwe_page');
	if (page && /^\d{1,6}$/.test(page) && Number(page) >= 1 && Number(page) <= 100000) {
		query.set('paged', page);
	}
	return query;
}

/**
 * Debounce canonical rehydration until Base reorder and Base modal lifecycle
 * have settled. Each view switch cancels pending work and performs its own
 * authoritative GET read instead.
 */
export function createCanonicalStatusSync(browser, callback, delay = 600) {
	let timer = null;
	return {
		request() {
			if (timer !== null) browser.clearTimeout(timer);
			timer = browser.setTimeout(() => {
				timer = null;
				callback();
			}, delay);
		},
		cancel() {
			if (timer !== null) browser.clearTimeout(timer);
			timer = null;
		},
	};
}

/** Reapply native disclosure state across canonical HTML replacements. */
export function syncAdvancedFilters(root, expanded) {
	const form = root?.querySelector?.('form.cb-work-items-filters');
	const advanced = form?.querySelector?.('#cb-work-more-filters');
	const toggle = form?.querySelector?.('.cb-work-more-filters-toggle');
	if (!advanced || !toggle) return false;
	advanced.hidden = !expanded;
	toggle.setAttribute('aria-expanded', String(expanded));
	return true;
}

export function createCanonicalViewMount(panel, browser, notify) {
	const destination = panel?.querySelector('[data-cb-work-uwe-canonical]');
	const switcher = panel?.querySelector('[data-cb-work-uwe-view-switcher]');
	const endpoint = panel?.dataset.cbWorkUweClassicEndpoint;
	const action = panel?.dataset.cbWorkUweClassicAction;
	const nonce = panel?.dataset.cbWorkUweClassicNonce;
	if (!destination || !switcher || !endpoint || !action || !nonce || typeof browser.fetch !== 'function') {
		return null;
	}
	const fallbackView = switcher.dataset.cbWorkUweDefaultView || 'table';
	const oldHeading = panel.querySelector('[data-cb-work-uwe-heading]');
	const fallback = [
		panel.querySelector('[data-cb-work-uwe-controls]'),
		panel.querySelector('[data-cb-work-uwe-list]'),
		panel.querySelector('[data-cb-work-uwe-empty]'),
		panel.querySelector('[data-cb-work-uwe-pagination]'),
	];
	const originalHidden = fallback.map((node) => node?.hidden ?? null);
	const feedback = panel.querySelector('[data-cb-work-uwe-feedback]');
	const error = panel.querySelector('[data-cb-work-uwe-error]');
	let current = null;
	let serial = 0;
	const document = panel.ownerDocument;
	const mutationAdapter = createMutationAdapter(destination, panel, browser, () => refresh());
	const statusSync = createCanonicalStatusSync(browser, () => {
		if (!panel.hidden) void refresh();
	});
	let searchTimer = null;
	let advancedOpen = false;

	function navigateFilters(url) {
		if (!url) return;
		const currentUrl = new URL(browser.location.href);
		if (url === currentUrl.pathname + currentUrl.search + currentUrl.hash) return;
		browser.history.pushState({ cbWorkM1Filters: true }, '', url);
		void notify();
	}

	function submitFilters(form) {
		if (searchTimer !== null) {
			browser.clearTimeout(searchTimer);
			searchTimer = null;
		}
		const view = readCanonicalView(browser.location.href, fallbackView);
		navigateFilters(canonicalFilterUrl(browser.location.href, new FormData(form), view));
	}

	destination.addEventListener('cb-work:canonical-status-committed', (event) => {
		if (event.target && destination.contains(event.target)) statusSync.request();
	});

	destination.addEventListener('cb-work:canonical-preferences-saved', (event) => {
		if (!event?.target || destination.contains(event.target)) void refresh();
	});

	destination.addEventListener('submit', (event) => {
		const form = event.target;
		if (!form?.matches?.('form.cb-work-items-filters')) return;
		event.preventDefault();
		submitFilters(form);
	});

	destination.addEventListener('change', (event) => {
		const control = event.target;
		const form = control?.closest?.('form.cb-work-items-filters');
		if (!form || control?.name === 's') return;
		submitFilters(form);
	});

	destination.addEventListener('keydown', (event) => {
		if (event.key !== 'Escape') return;
		const advanced = event.target?.closest?.('#cb-work-more-filters');
		if (!advanced || advanced.hidden) return;
		event.preventDefault();
		advancedOpen = false;
		advanced.hidden = true;
		const toggle = advanced.closest('form.cb-work-items-filters')
			?.querySelector('.cb-work-more-filters-toggle');
		toggle?.setAttribute('aria-expanded', 'false');
		toggle?.focus?.({ preventScroll: true });
	});

	destination.addEventListener('input', (event) => {
		if (event.target?.name !== 's') return;
		const form = event.target.closest?.('form.cb-work-items-filters');
		if (!form) return;
		if (searchTimer !== null) browser.clearTimeout(searchTimer);
		searchTimer = browser.setTimeout(() => {
			searchTimer = null;
			submitFilters(form);
		}, 300);
	});

	destination.addEventListener('click', (event) => {
		const toggle = event.target?.closest?.('.cb-work-more-filters-toggle');
		if (toggle && event.button === 0) {
			const form = toggle.closest('form.cb-work-items-filters');
			const advanced = form?.querySelector('#cb-work-more-filters');
			if (!advanced) return;
			event.preventDefault();
			advancedOpen = advanced.hidden;
			advanced.hidden = !advancedOpen;
			toggle.setAttribute('aria-expanded', String(advancedOpen));
			if (advancedOpen) {
				advanced.querySelector('input, select, button, [tabindex]:not([tabindex="-1"])')
					?.focus?.({ preventScroll: true });
			}
			return;
		}
		const link = event.target?.closest?.('a');
		if (!link || event.defaultPrevented || event.button !== 0
			|| event.ctrlKey || event.metaKey || event.shiftKey || event.altKey
			|| link.target === '_blank' || link.hasAttribute('download')) return;
		// Calendar month links have their own strict month controller below.
		if (link.matches('.cb-work-calendar-navigation__actions a')) return;
		const url = canonicalLinkUrl(browser.location.href, link.href,
			readCanonicalView(browser.location.href, fallbackView));
		if (!url) return;
		event.preventDefault();
		navigateFilters(url);
	});

	destination.addEventListener('click', (event) => {
		const anchor = event.target?.closest?.('.cb-work-calendar-navigation__actions a');
		if (!anchor || event.defaultPrevented || event.button !== 0
			|| event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
		const url = calendarMonthUrl(browser.location.href, anchor.href);
		if (!url) return;
		event.preventDefault();
		browser.history.pushState({ cbWorkUweCalendarMonth: true }, '', url);
		void refresh();
	});

	function navigate(view) {
		const url = canonicalViewUrl(browser.location.href, view);
		const existing = new URL(browser.location.href);
		if (url === existing.pathname + existing.search + existing.hash) return;
		browser.history.pushState({ cbWorkUweView: view }, '', url);
		void notify();
	}

	switcher.addEventListener('click', (event) => {
		const link = event.target?.closest?.('[data-cb-work-uwe-view]');
		if (!link || event.defaultPrevented || event.button !== 0
			|| event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
		const view = link.dataset.cbWorkUweView;
		if (!CANONICAL_VIEWS.includes(view)) return;
		event.preventDefault();
		navigate(view);
	});

	function syncPreferredOrder(canonical) {
		const inner = canonical.querySelector('.cb-work-view-switcher');
		const legacy = switcher.querySelector('.cb-work-uwe__legacy-link');
		if (!inner || !legacy) return;
		const links = new Map([...switcher.querySelectorAll('[data-cb-work-uwe-view]')].map((link) => [link.dataset.cbWorkUweView, link]));
		for (const option of inner.querySelectorAll('a.cb-work-view-switcher__option')) {
			const view = new URL(option.href, browser.location.href).searchParams.get('view');
			const link = links.get(view);
			if (link) switcher.insertBefore(link, legacy);
		}
	}

	function markActive(view) {
		switcher.querySelectorAll('[data-cb-work-uwe-view]').forEach((link) => {
			const selected = link.dataset.cbWorkUweView === view;
			link.classList.toggle('is-active', selected);
			if (selected) link.setAttribute('aria-current', 'page');
			else link.removeAttribute('aria-current');
		});
	}

	function showFallback() {
		mutationAdapter?.disable();
		destination.hidden = true;
		if (oldHeading) oldHeading.hidden = false;
		fallback.forEach((node, index) => { if (node) node.hidden = originalHidden[index]; });
	}

	async function refresh(href = browser.location.href) {
		statusSync.cancel();
		serial += 1;
		const ticket = serial;
		if (current) current.abort();
		const controller = new AbortController();
		current = controller;
		const view = readCanonicalView(href, fallbackView);
		mutationAdapter?.disable();
		markActive(view);
		const url = new URL(endpoint, href);
		for (const [key, value] of canonicalViewParameters(href, action, nonce, fallbackView)) {
			url.searchParams.set(key, value);
		}
		if (error) error.hidden = true;
		if (feedback) {
			feedback.hidden = false;
			feedback.textContent = switcher.querySelector('.is-active')?.textContent.trim() || '';
		}
		panel.setAttribute('aria-busy', 'true');

		try {
			const response = await browser.fetch(url.href, {
				method: 'GET',
				credentials: 'same-origin',
				cache: 'no-store',
				headers: { 'Accept': 'application/json' },
				signal: controller.signal,
			});
			if (ticket !== serial || controller.signal.aborted) return;
			if (!response.ok) throw new Error('Canonical Work Items HTTP ' + response.status);
			const payload = await response.json();
			if (ticket !== serial || controller.signal.aborted) return;
			if (payload?.success !== true || payload?.data?.view !== view
				|| typeof payload?.data?.html !== 'string') {
				throw new TypeError('Invalid canonical Work Items response');
			}
			const parsed = new DOMParser().parseFromString(payload.data.html, 'text/html');
			const canonical = parsed.querySelector('.cb-work-items-page');
			const marker = {
				table: '.cb-work-items-table',
				list: '.cb-work-items-list',
				kanban: '.cb-work-items-kanban',
				calendar: '.cb-work-calendar',
			}[view];
			// Empty table/list views can legitimately have no row renderer.
			if (!canonical || (view === 'calendar' && !canonical.querySelector(marker))) {
				throw new TypeError('Missing canonical Work Items content');
			}
			if (canonical.querySelector('script, iframe, object, embed')) {
				throw new TypeError('Unexpected active content in canonical Work Items HTML');
			}
			syncPreferredOrder(canonical);
			const inserted = document.importNode(canonical, true);
			syncAdvancedFilters(inserted, advancedOpen);
			const outgoing = destination.firstElementChild;
			const activeInput = document.activeElement;
			const focusedField = activeInput?.closest?.('form.cb-work-items-filters')
				&& outgoing?.contains?.(activeInput) && activeInput.name ? activeInput.name : null;
			const focusedCardId = !focusedField && outgoing?.contains?.(activeInput)
				? activeInput?.closest?.('[data-cb-core-reorder-item]')?.dataset?.cbCoreReorderItem : null;
			const cursor = focusedField === 's' && Number.isInteger(activeInput.selectionStart)
				? [activeInput.selectionStart, activeInput.selectionEnd] : null;
			if (outgoing && typeof document.dispatchEvent === 'function' && typeof CustomEvent === 'function') {
				document.dispatchEvent(new CustomEvent('cb-work:canonical-view-unmount', {
					detail: { root: outgoing },
				}));
			}
			destination.replaceChildren(inserted);
			destination.hidden = false;
			// Modules own their existing handlers. Mount merely announces
			// the exact imported fragment; it never duplicates interactions.
			if (typeof document.dispatchEvent === 'function' && typeof CustomEvent === 'function') {
				document.dispatchEvent(new CustomEvent('cb-work:canonical-view-mounted', {
					detail: { root: inserted, view },
				}));
			}
			mutationAdapter?.ready(inserted);
			if (focusedField) {
				const field = inserted.querySelector('form.cb-work-items-filters')?.elements?.namedItem?.(focusedField);
				if (field && typeof field.focus === 'function') {
					field.focus({ preventScroll: true });
					if (cursor && typeof field.setSelectionRange === 'function') {
						field.setSelectionRange(cursor[0], cursor[1]);
					}
				}
			}
			if (focusedCardId) {
				const card = [...inserted.querySelectorAll('[data-cb-core-reorder-item]')]
					.find((item) => item.dataset.cbCoreReorderItem === focusedCardId);
				const focusTarget = card?.querySelector('a, button, [tabindex]') || card;
				if (typeof focusTarget?.focus === 'function') {
					focusTarget.focus({ preventScroll: true });
				} else {
					switcher.querySelector('.is-active')?.focus?.({ preventScroll: true });
				}
			}
			if (oldHeading) oldHeading.hidden = true;
			for (const node of fallback) {
				if (node) node.hidden = true;
			}
		} catch (failure) {
			if (ticket !== serial || controller.signal.aborted || failure?.name === 'AbortError') return;
			// The fallback is always available and never replaced on a failed load.
			showFallback();
			if (error) error.hidden = false;
		} finally {
			if (ticket === serial) {
				panel.removeAttribute('aria-busy');
				if (feedback) feedback.hidden = true;
			}
		}
	}

	return { refresh, abort() {
		statusSync.cancel();
		if (searchTimer !== null) browser.clearTimeout(searchTimer);
		searchTimer = null;
		serial += 1;
		mutationAdapter?.disable();
		current?.abort();
		panel.removeAttribute('aria-busy');
		if (feedback) feedback.hidden = true;
	} };
}

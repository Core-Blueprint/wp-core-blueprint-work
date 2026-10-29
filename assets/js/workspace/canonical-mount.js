/**
 * UWE-03C.2: mount the *canonical* Work Items HTML returned by UWE-03C.1.
 *
 * This is a visual view-integration stage. Golden action lifecycle is reconciled
 * in UWE-03C.3, so the native Work Items screen remains available by link.
 * No second Table/List/Board/Calendar renderer is implemented here.
 */
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

	function markActive(view) {
		switcher.querySelectorAll('[data-cb-work-uwe-view]').forEach((link) => {
			const selected = link.dataset.cbWorkUweView === view;
			link.classList.toggle('is-active', selected);
			if (selected) link.setAttribute('aria-current', 'page');
			else link.removeAttribute('aria-current');
		});
	}

	function showFallback() {
		destination.hidden = true;
		if (oldHeading) oldHeading.hidden = false;
		fallback.forEach((node, index) => { if (node) node.hidden = originalHidden[index]; });
	}

	async function refresh(href = browser.location.href) {
		serial += 1;
		const ticket = serial;
		if (current) current.abort();
		const controller = new AbortController();
		current = controller;
		const view = readCanonicalView(href, fallbackView);
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
			destination.replaceChildren(document.importNode(canonical, true));
			destination.hidden = false;
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
		serial += 1;
		current?.abort();
		panel.removeAttribute('aria-busy');
		if (feedback) feedback.hidden = true;
	} };
}

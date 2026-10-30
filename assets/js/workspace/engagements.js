/**
 * M3-B1. In-place Engagement list and basic creation, with native advanced
 * Service Operations as a real fallback. Uses only existing Work APIs.
 */
export function mountWorkspaceEngagements(root, browser = window, onCreated = null) {
	const nav = root?.querySelector?.('[data-cb-work-m3-operations]');
	const panel = root?.querySelector?.('[data-cb-work-m3-engagements]');
	const link = nav?.querySelector?.('[data-cb-work-m3-service-link]');
	const fetcher = typeof browser.fetch === 'function' ? browser.fetch.bind(browser) : null;
	const modal = browser.cbCore?.modal;
	if (!nav || !panel || !link || !fetcher || !modal?.show || panel.dataset.cbWorkM3EngagementsMounted === '1') return null;

	const endpoint = panel.dataset.cbWorkM3Endpoint;
	const projectsEndpoint = panel.dataset.cbWorkM3Projects;
	const servicesEndpoint = panel.dataset.cbWorkM3Services;
	const nonce = panel.dataset.cbWorkM3Nonce;
	if (!nonce || ![endpoint, projectsEndpoint, servicesEndpoint].every((value) => {
		try { return new URL(value, browser.location.href).origin === new URL(browser.location.href).origin; }
		catch { return false; }
	})) return null;

	const document = root.ownerDocument;
	const form = panel.querySelector('[data-cb-work-m3-engagements-filter]');
	const list = panel.querySelector('[data-cb-work-m3-engagements-list]');
	const error = panel.querySelector('[data-cb-work-m3-engagements-error]');
	const empty = panel.querySelector('[data-cb-work-m3-engagements-empty]');
	const paging = panel.querySelector('[data-cb-work-m3-engagements-paging]');
	const pageLabel = panel.querySelector('[data-cb-work-m3-engagements-page]');
	const prev = panel.querySelector('[data-cb-work-m3-engagements-prev]');
	const next = panel.querySelector('[data-cb-work-m3-engagements-next]');
	const close = panel.querySelector('[data-cb-work-m3-engagements-close]');
	const add = panel.querySelector('[data-cb-work-m3-engagements-add]');
	const search = form?.querySelector('[name="search"]');
	if ([form, list, error, empty, paging, pageLabel, prev, next, close, add, search].some((node) => !node)) return null;

	let disposed = false;
	let page = 1;
	let pages = 1;
	let version = 0;
	let active = null;
	let dialogOpen = false;
	panel.dataset.cbWorkM3EngagementsMounted = '1';

	function clear() {
		list.replaceChildren();
		list.hidden = true;
		empty.hidden = true;
		paging.hidden = true;
		add.hidden = true;
		error.hidden = true;
	}
	function showError() {
		clear();
		error.hidden = false;
	}
	function el(tag, text = '', className = '') {
		const element = document.createElement(tag);
		element.textContent = text;
		if (className) element.className = className;
		return element;
	}
	function validPage(data) {
		return data && Array.isArray(data.items) && data.items.length <= 25 &&
			Number.isSafeInteger(data.total) && data.total >= 0 &&
			Number.isSafeInteger(data.page) && data.page > 0 &&
			Number.isSafeInteger(data.pages) && data.pages >= 1 &&
			data.items.every((item) => Number.isSafeInteger(item.id) && item.id > 0 &&
				typeof item.project === 'string' && typeof item.service === 'string' &&
				typeof item.label === 'string' && ['internal', 'customer'].includes(item.context) &&
				/^\d{4}-\d{2}-\d{2}$/.test(item.starts_on) && typeof item.is_active === 'boolean');
	}
	async function request(url, options = {}) {
		const target = new URL(url, browser.location.href);
		if (target.origin !== new URL(browser.location.href).origin) throw new TypeError('Cross-origin Work request');
		const response = await fetcher(target.href, {
			credentials: 'same-origin', cache: 'no-store',
			headers: { Accept: 'application/json', 'X-WP-Nonce': nonce, ...(options.body ? { 'Content-Type': 'application/json' } : {}) },
			...options,
		});
		if (!response.ok) {
			const failure = new Error('Work engagement request failed');
			failure.status = response.status;
			throw failure;
		}
		return response.json();
	}
	function paint(data) {
		if (!validPage(data)) throw new TypeError('Invalid engagements payload');
		list.replaceChildren(...data.items.map((item) => {
			const row = el('li', '', 'cb-work-m3-engagements__record');
			const title = el('strong', [item.project, item.service, item.label].filter(Boolean).join(' / '));
			const detail = el('span', [
				item.context === 'internal' ? panel.dataset.cbWorkM3ContextInternal : panel.dataset.cbWorkM3ContextCustomer,
				item.starts_on,
			].filter(Boolean).join(' · '), 'cb-work-m3-engagements__meta');
			row.append(title, detail);
			return row;
		}));
		page = data.page;
		pages = data.pages;
		list.hidden = data.items.length === 0;
		empty.hidden = data.items.length !== 0;
		pageLabel.textContent = String(page) + ' / ' + String(pages);
		paging.hidden = pages < 2;
		prev.disabled = page <= 1;
		next.disabled = page >= pages;
		error.hidden = true;
		add.hidden = false;
	}
	async function refresh(requested = page) {
		if (disposed) return false;
		active?.abort();
		const ticket = ++version;
		active = new AbortController();
		const url = new URL(endpoint, browser.location.href);
		url.searchParams.set('page', String(requested));
		const query = String(search.value || '').trim().slice(0, 160);
		if (query) url.searchParams.set('search', query);
		try {
			const data = await request(url.href, { method: 'GET', signal: active.signal });
			if (disposed || ticket !== version) return false;
			paint(data);
			return true;
		} catch (reason) {
			if (disposed || ticket !== version || reason?.name === 'AbortError') return false;
			showError();
			return false;
		} finally {
			if (ticket === version) active = null;
		}
	}
	function inputRow(label, name, type) {
		const wrapper = el('label', '', 'cb-work-m3-engagements__field');
		wrapper.appendChild(el('span', label));
		const input = document.createElement('input');
		input.name = name;
		input.type = type;
		if (type === 'search') input.maxLength = 160;
		wrapper.appendChild(input);
		return { wrapper, input };
	}
	function pickRow(title, name, errorText) {
		const wrapper = el('div', '', 'cb-work-m3-engagements__field');
		const searchRow = inputRow(title, name + '_search', 'search');
		const select = document.createElement('select');
		select.name = name;
		select.required = true;
		select.setAttribute('aria-label', title);
		const unavailable = el('p', errorText);
		unavailable.hidden = true;
		wrapper.append(searchRow.wrapper, select, unavailable);
		return { wrapper, search: searchRow.input, select, unavailable };
	}
	async function openCreate() {
		if (dialogOpen || disposed || !browser.cbCore?.modal?.show) return;
		dialogOpen = true;
		const body = el('div', '', 'cb-work-m3-engagements__form');
		const project = pickRow(panel.dataset.cbWorkM3Project, 'project_id', panel.dataset.cbWorkM3NoProjects);
		const service = pickRow(panel.dataset.cbWorkM3Service, 'service_id', panel.dataset.cbWorkM3NoServices);
		const date = inputRow(panel.dataset.cbWorkM3Start, 'starts_on', 'date');
		const label = inputRow(panel.dataset.cbWorkM3Label, 'label', 'text');
		date.input.required = true;
		label.input.maxLength = 190;
		const message = el('p', '', 'cb-work-m3-engagements__message');
		message.hidden = true;
		body.append(project.wrapper, service.wrapper, date.wrapper, label.wrapper, message);

		let closed = false;
		const loads = [];
		async function options(field, endpointUrl) {
			if (closed) return;
			const index = loads.indexOf(field.controller);
			if (index !== -1) loads.splice(index, 1);
			field.controller?.abort();
			field.controller = new AbortController();
			const controller = field.controller;
			loads.push(controller);
			const url = new URL(endpointUrl, browser.location.href);
			url.searchParams.set('page', '1');
			url.searchParams.set('per_page', '100');
			const term = field.search.value.trim().slice(0, 160);
			if (term) url.searchParams.set('search', term);
			field.select.disabled = true;
			field.unavailable.hidden = true;
			try {
				const result = await request(url.href, { method: 'GET', signal: controller.signal });
				if (closed || field.controller !== controller) return;
				if (!Array.isArray(result.items) || !result.items.every((item) => Number.isSafeInteger(item.id) && item.id > 0 && typeof item.title === 'string')) throw new TypeError('Invalid Work catalog');
				field.select.replaceChildren();
				const choose = document.createElement('option');
				choose.value = '';
				choose.textContent = field === project ? panel.dataset.cbWorkM3Project : panel.dataset.cbWorkM3Service;
				field.select.appendChild(choose);
				for (const item of result.items) {
					const option = document.createElement('option');
					option.value = String(item.id);
					option.textContent = item.title;
					field.select.appendChild(option);
				}
				field.select.disabled = false;
				field.unavailable.hidden = result.items.length > 0;
			} catch (reason) {
				if (closed || field.controller !== controller || reason?.name === 'AbortError') return;
				field.select.replaceChildren();
				field.select.disabled = true;
				field.unavailable.hidden = false;
			}
		}
		const timerIds = [];
		for (const [field, url] of [[project, projectsEndpoint], [service, servicesEndpoint]]) {
			const handler = () => {
				clearTimeout(field.timeout);
				field.timeout = setTimeout(() => void options(field, url), 250);
				timerIds.push(field.timeout);
			};
			field.search.addEventListener('input', handler);
			void options(field, url);
		}
		let created = false;
		try {
			const outcome = await browser.cbCore.modal.show({
				title: panel.dataset.cbWorkM3Add, body,
				confirmLabel: panel.dataset.cbWorkM3Create,
				cancelLabel: panel.dataset.cbWorkM3Cancel,
				initialFocus: '[name="project_id_search"]',
				onConfirm: async () => {
					const projectId = Number(project.select.value);
					const serviceId = Number(service.select.value);
					const startsOn = date.input.value;
					if (project.select.disabled || service.select.disabled ||
						!Number.isSafeInteger(projectId) || projectId <= 0 ||
						!Number.isSafeInteger(serviceId) || serviceId <= 0 ||
						!/^\d{4}-\d{2}-\d{2}$/.test(startsOn)) {
						message.textContent = panel.dataset.cbWorkM3Error;
						message.hidden = false;
						return false;
					}
					try {
						const payload = await request(endpoint, {
							method: 'POST',
							body: JSON.stringify({
								project_id: projectId, service_id: serviceId,
								starts_on: startsOn, label: label.input.value.slice(0, 190),
							}),
						});
						if (!Number.isSafeInteger(payload?.id) || payload.id <= 0) throw new TypeError('Invalid creation receipt');
						created = true;
						return true;
					} catch {
						message.textContent = panel.dataset.cbWorkM3Error;
						message.hidden = false;
						return false;
					}
				},
			});
			if (outcome === true && created && !disposed) {
				await refresh(1);
				if (typeof onCreated === 'function') void onCreated();
			}
		} finally {
			closed = true;
			for (const controller of loads) controller.abort();
			for (const id of timerIds) clearTimeout(id);
			dialogOpen = false;
		}
	}
	function clickLink(event) {
		if (disposed || event.defaultPrevented || event.button !== 0 ||
			event.metaKey || event.ctrlKey || event.altKey || event.shiftKey) return;
		event.preventDefault();
		panel.hidden = false;
		void refresh(1);
	}
	function closePanel() {
		active?.abort();
		++version;
		panel.hidden = true;
		link.focus?.();
	}
	function submit(event) { event.preventDefault(); void refresh(1); }
	function previous() { if (page > 1) void refresh(page - 1); }
	function following() { if (page < pages) void refresh(page + 1); }
	function createClick() { void openCreate(); }

	link.addEventListener('click', clickLink);
	close.addEventListener('click', closePanel);
	form.addEventListener('submit', submit);
	prev.addEventListener('click', previous);
	next.addEventListener('click', following);
	add.addEventListener('click', createClick);

	return {
		refresh,
		dispose() {
			disposed = true;
			active?.abort();
			++version;
			panel.hidden = true;
			link.removeEventListener('click', clickLink);
			close.removeEventListener('click', closePanel);
			form.removeEventListener('submit', submit);
			prev.removeEventListener('click', previous);
			next.removeEventListener('click', following);
			add.removeEventListener('click', createClick);
			delete panel.dataset.cbWorkM3EngagementsMounted;
		}
	};
}

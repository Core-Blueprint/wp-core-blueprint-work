/**
 * M3-C1 Time manager surface. Native Time remains authoritative for advanced
 * manual/CAS edits and time-only roles; this module never caches domain state.
 */
export function mountWorkspaceTime(root, browser = window, onChanged = null) {
	const nav = root?.querySelector?.('[data-cb-work-m3-operations]');
	const link = nav?.querySelector?.('[data-cb-work-m3-time-link]');
	const panel = root?.querySelector?.('[data-cb-work-m3-time]');
	const fetcher = typeof browser.fetch === 'function' ? browser.fetch.bind(browser) : null;
	if (!nav || !link || !panel || !fetcher || panel.dataset.cbWorkM3TimeMounted === '1') return null;
	const base = panel.dataset.cbWorkM3TimeEndpoint;
	const searchUrl = panel.dataset.cbWorkM3TimeSearch;
	const timerUrl = panel.dataset.cbWorkM3TimeTimer;
	const nonce = panel.dataset.cbWorkM3TimeNonce;
	if (!nonce || ![base, searchUrl, timerUrl].every((raw) => {
		try { return new URL(raw, browser.location.href).origin === new URL(browser.location.href).origin; }
		catch { return false; }
	})) return null;

	const q = (selector) => panel.querySelector(selector);
	const close = q('[data-cb-work-m3-time-close]');
	const error = q('[data-cb-work-m3-time-error-message]');
	const activeZone = q('[data-cb-work-m3-time-active-zone]');
	const activeLabel = q('[data-cb-work-m3-time-active-label]');
	const stopButton = q('[data-cb-work-m3-time-stop]');
	const form = q('[data-cb-work-m3-time-start-form]');
	const search = form?.querySelector('[name="search"]');
	const select = form?.querySelector('[name="work_item_id"]');
	const note = form?.querySelector('[name="note"]');
	const startButton = q('[data-cb-work-m3-time-start]');
	const entries = q('[data-cb-work-m3-time-entries]');
	const pager = q('[data-cb-work-m3-time-paging]');
	const previous = q('[data-cb-work-m3-time-prev]');
	const next = q('[data-cb-work-m3-time-next]');
	const pageLabel = q('[data-cb-work-m3-time-page]');
	if ([close, error, activeZone, activeLabel, stopButton, form, search,
		select, note, startButton, entries, pager, previous, next, pageLabel].some((x) => !x)) return null;

	panel.dataset.cbWorkM3TimeMounted = '1';
	let destroyed = false, busy = false, page = 1, pages = 1, epoch = 0, searchEpoch = 0;
	let controller = null, searchController = null, searchTimeout = null;
	const doc = root.ownerDocument;
	const element = (tag, content = '') => {
		const result = doc.createElement(tag);
		result.textContent = content;
		return result;
	};

	async function request(url, options = {}) {
		const target = new URL(url, browser.location.href);
		if (target.origin !== new URL(browser.location.href).origin) throw new TypeError('Foreign Time endpoint');
		const response = await fetcher(target.href, {
			method: 'GET', credentials: 'same-origin', cache: 'no-store',
			headers: { 'X-WP-Nonce': nonce, Accept: 'application/json',
				...(options.body ? { 'Content-Type': 'application/json' } : {}) },
			...options,
		});
		if (!response.ok) throw new TypeError('Time request failed');
		return response.json();
	}
	const validTimer = (x) => x === null || (x && Number.isSafeInteger(x.entry_id) && x.entry_id > 0
		&& Number.isSafeInteger(x.work_item_id) && x.work_item_id > 0
		&& typeof x.title === 'string' && typeof x.started_at === 'string' && typeof x.note === 'string');
	const validEntry = (x) => x && Number.isSafeInteger(x.id) && x.id > 0
		&& Number.isSafeInteger(x.work_item_id) && x.work_item_id > 0
		&& typeof x.title === 'string' && typeof x.started_at === 'string'
		&& typeof x.ended_at === 'string' && Number.isSafeInteger(x.duration_seconds)
		&& x.duration_seconds >= 0 && ['manual', 'timer'].includes(x.source)
		&& Number.isSafeInteger(x.revision) && x.revision >= 1;
	function render(data) {
		if (!data || !validTimer(data.timer) || !Array.isArray(data.entries) || data.entries.length > 10
			|| !data.entries.every(validEntry) || !Number.isSafeInteger(data.actor_id) || data.actor_id < 1
			|| !Number.isSafeInteger(data.page) || data.page < 1
			|| !Number.isSafeInteger(data.pages) || data.pages < 1
			|| !Number.isSafeInteger(data.total) || data.total < 0) throw new TypeError('Invalid Time read');

		page = data.page; pages = data.pages;
		activeZone.hidden = !data.timer;
		form.hidden = !!data.timer;
		activeLabel.textContent = data.timer
			? [panel.dataset.cbWorkM3TimeActive, data.timer.title, data.timer.started_at].join(' · ') : '';
		entries.replaceChildren(...data.entries.map((record) => {
			const li = element('li');
			const heading = element('strong', record.title);
			const info = element('span', [
				record.started_at, record.ended_at, String(record.duration_seconds) + ' s'
			].join(' · '));
			li.append(heading, info);
			return li;
		}));
		pager.hidden = pages < 2;
		pageLabel.textContent = String(page) + ' / ' + String(pages);
		previous.disabled = page <= 1;
		next.disabled = page >= pages;
		error.hidden = true;
	}
	function fail() {
		activeZone.hidden = true;
		form.hidden = true;
		entries.replaceChildren();
		pager.hidden = true;
		error.hidden = false;
	}
	async function refresh(requested = page) {
		if (destroyed) return false;
		controller?.abort();
		controller = new AbortController();
		const ticket = ++epoch;
		const url = new URL(base, browser.location.href);
		url.searchParams.set('page', String(requested));
		try {
			const data = await request(url.href, { signal: controller.signal });
			if (destroyed || ticket !== epoch) return false;
			render(data);
			return true;
		} catch (reason) {
			if (destroyed || ticket !== epoch || reason?.name === 'AbortError') return false;
			fail(); return false;
		} finally {
			if (ticket === epoch) controller = null;
		}
	}
	async function lookup() {
		if (destroyed || busy) return false;
		searchController?.abort();
		searchController = new AbortController();
		const ticket = ++searchEpoch;
		const query = search.value.trim().slice(0, 80);
		select.disabled = true;
		select.replaceChildren();
		if (query.length < 2) return false;
		const url = new URL(searchUrl, browser.location.href);
		url.searchParams.set('search', query);
		try {
			const data = await request(url.href, { signal: searchController.signal });
			if (destroyed || ticket !== searchEpoch) return false;
			if (!Array.isArray(data?.items) || data.items.length > 20 ||
				!data.items.every((x) => Number.isSafeInteger(x.id) && x.id > 0 && typeof x.label === 'string')) {
				throw new TypeError('Invalid Work Item picker');
			}
			const choose = element('option', panel.dataset.cbWorkM3TimeSelect);
			choose.value = '';
			select.append(choose, ...data.items.map((x) => {
				const option = element('option', x.label);
				option.value = String(x.id);
				return option;
			}));
			select.disabled = data.items.length === 0;
			return true;
		} catch (reason) {
			if (destroyed || ticket !== searchEpoch || reason?.name === 'AbortError') return false;
			select.replaceChildren();
			select.disabled = true;
			error.hidden = false;
			return false;
		} finally {
			if (ticket === searchEpoch) searchController = null;
		}
	}
	async function mutate(operation) {
		if (destroyed || busy) return false;
		const payload = { operation };
		if (operation === 'start') {
			const id = Number(select.value);
			if (select.disabled || !Number.isSafeInteger(id) || id < 1) { error.hidden = false; return false; }
			payload.work_item_id = id;
			payload.note = note.value.slice(0, 4000);
		}
		busy = true;
		stopButton.disabled = true;
		startButton.disabled = true;
		try {
			const receipt = await request(timerUrl, {
				method: 'POST', body: JSON.stringify(payload),
			});
			if ((operation === 'start' && receipt?.started !== true)
				|| (operation === 'stop' && receipt?.stopped !== true)
				|| !Number.isSafeInteger(receipt?.entry_id) || receipt.entry_id < 1) {
				throw new TypeError('Invalid Time mutation receipt');
			}
			// Wake the *existing* global Time HUD. It will query its own
			// canonical endpoint rather than consuming a second state store.
			browser.dispatchEvent?.(new Event('cb-work-time:changed'));
			if (typeof onChanged === 'function') await onChanged();
			await refresh(1);
			return true;
		} catch {
			error.hidden = false;
			// Do not claim a successful timer transition if the receipt is
			// denied/uncertain. Resync from the server once possible.
			void refresh(page);
			return false;
		} finally {
			busy = false;
			stopButton.disabled = false;
			startButton.disabled = false;
		}
	}
	function onLink(event) {
		if (destroyed || event.defaultPrevented || event.button !== 0 ||
			event.metaKey || event.ctrlKey || event.altKey || event.shiftKey) return;
		event.preventDefault();
		panel.hidden = false;
		void refresh(1);
	}
	function onClose() {
		controller?.abort(); searchController?.abort(); ++epoch; ++searchEpoch;
		panel.hidden = true; link.focus?.();
	}
	function onSearch() {
		clearTimeout(searchTimeout);
		searchTimeout = setTimeout(() => { void lookup(); }, 250);
	}
	function onSubmit(event) { event.preventDefault(); void mutate('start'); }
	function onStop() { void mutate('stop'); }
	function onPrevious() { if (page > 1) void refresh(page - 1); }
	function onNext() { if (page < pages) void refresh(page + 1); }

	link.addEventListener('click', onLink);
	close.addEventListener('click', onClose);
	search.addEventListener('input', onSearch);
	form.addEventListener('submit', onSubmit);
	stopButton.addEventListener('click', onStop);
	previous.addEventListener('click', onPrevious);
	next.addEventListener('click', onNext);
	return {
		refresh, lookup, mutate,
		dispose() {
			destroyed = true; ++epoch; ++searchEpoch;
			controller?.abort(); searchController?.abort();
			clearTimeout(searchTimeout); panel.hidden = true;
			link.removeEventListener('click', onLink);
			close.removeEventListener('click', onClose);
			search.removeEventListener('input', onSearch);
			form.removeEventListener('submit', onSubmit);
			stopButton.removeEventListener('click', onStop);
			previous.removeEventListener('click', onPrevious);
			next.removeEventListener('click', onNext);
			delete panel.dataset.cbWorkM3TimeMounted;
		}
	};
}

/**
 * M3-A operations read surface. The canonical Service Operations, Recurrence
 * and Time handlers remain the only writers; Work only consumes a bounded
 * permission-checked snapshot and never mirrors mutable domain state.
 */
export function mountWorkspaceOperations(root, browser = window) {
	const nav = root?.querySelector?.('[data-cb-work-m3-operations]');
	if (!nav || nav.dataset.cbWorkM3Mounted === '1') return null;
	const fetcher = typeof browser.fetch === 'function' ? browser.fetch.bind(browser) : null;
	const endpoint = nav.dataset.cbWorkM3Endpoint;
	const nonce = nav.dataset.cbWorkM3Nonce;
	if (!fetcher || !endpoint || !nonce) return null;
	let url;
	try {
		url = new URL(endpoint, browser.location.href);
		if (url.origin !== new URL(browser.location.href).origin) return null;
	} catch {
		return null;
	}
	const services = nav.querySelector('[data-cb-work-m3-count="services"]');
	const recurrence = nav.querySelector('[data-cb-work-m3-count="recurrence"]');
	const timer = nav.querySelector('[data-cb-work-m3-timer]');
	const status = nav.querySelector('[data-cb-work-m3-status]');
	if (!services || !recurrence || !timer || !status) return null;

	nav.dataset.cbWorkM3Mounted = '1';
	let disposed = false;
	let sequence = 0;
	let lastSuccess = 0;
	let controller = null;

	function validCount(value) {
		return Number.isSafeInteger(value) && value >= 0;
	}
	function display(payload) {
		if (!payload || !validCount(payload.services?.total) ||
			!validCount(payload.services?.active) ||
			!validCount(payload.recurrence?.total) ||
			!validCount(payload.recurrence?.active) ||
			typeof payload.time?.running !== 'boolean') {
			throw new TypeError('Invalid Work operations snapshot');
		}
		services.textContent = nav.dataset.cbWorkM3Active + ' ' + String(payload.services.active) + ' / ' + String(payload.services.total);
		recurrence.textContent = nav.dataset.cbWorkM3Active + ' ' + String(payload.recurrence.active) + ' / ' + String(payload.recurrence.total);
		services.hidden = false;
		recurrence.hidden = false;
		timer.hidden = !payload.time.running;
		status.textContent = '';
	}
	async function refresh() {
		if (disposed) return false;
		const ticket = ++sequence;
		controller?.abort();
		controller = new AbortController();
		try {
			const response = await fetcher(url.href, {
				method: 'GET',
				credentials: 'same-origin',
				cache: 'no-store',
				headers: { 'X-WP-Nonce': nonce, Accept: 'application/json' },
				signal: controller.signal,
			});
			if (disposed || ticket !== sequence) return false;
			if (!response.ok) throw new TypeError('Work operations request denied');
			const payload = await response.json();
			if (disposed || ticket !== sequence) return false;
			display(payload);
			lastSuccess = Date.now();
			return true;
		} catch (error) {
			if (disposed || ticket !== sequence || error?.name === 'AbortError') return false;
			// Keep the real native links usable. Never show stale summary values.
			services.hidden = true;
			recurrence.hidden = true;
			timer.hidden = true;
			status.textContent = nav.dataset.cbWorkM3Unavailable || '';
			return false;
		} finally {
			if (ticket === sequence) controller = null;
		}
	}
	function onFocus() {
		if (!disposed && Date.now() - lastSuccess >= 30000) void refresh();
	}
	browser.addEventListener?.('focus', onFocus);
	void refresh();

	return {
		refresh,
		dispose() {
			disposed = true;
			sequence++;
			controller?.abort();
			browser.removeEventListener?.('focus', onFocus);
			delete nav.dataset.cbWorkM3Mounted;
		}
	};
}

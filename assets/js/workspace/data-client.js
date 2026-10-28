/**
 * UWE-03B.2: private WordPress REST consumer. GET only, nonce in header,
 * no caching of privileged responses outside the current view session.
 */
export function createDataClient({ fetcher = globalThis.fetch.bind(globalThis), makeController = () => new AbortController(), baseHref = globalThis.location.href } = {}) {
	let current = null;
	let sequence = 0;

	return {
		async load(endpoint, nonce, params) {
			if (current) current.abort();
			const controller = makeController();
			current = controller;
			const requestId = ++sequence;
			const url = new URL(endpoint, baseHref);
			for (const [key, value] of params) url.searchParams.set(key, value);
			const response = await fetcher(url.href, {
				method: 'GET',
				credentials: 'same-origin',
				headers: {
					'Accept': 'application/json',
					'X-WP-Nonce': nonce,
				},
				signal: controller.signal,
			});
			if (requestId !== sequence || controller.signal.aborted) {
				const stale = new Error('Outdated workspace response');
				stale.name = 'AbortError';
				throw stale;
			}
			if (!response.ok) {
				const failure = new Error('Workspace REST HTTP ' + response.status);
				failure.status = response.status;
				throw failure;
			}
			const payload = await response.json();
			if (requestId !== sequence || controller.signal.aborted) {
				const stale = new Error('Outdated workspace response');
				stale.name = 'AbortError';
				throw stale;
			}
			if (!payload || typeof payload.section !== 'string' || !Array.isArray(payload.items)
				|| !Number.isInteger(payload.page) || !Number.isInteger(payload.pages)
				|| !Number.isInteger(payload.total) || payload.total < 0) {
				throw new TypeError('Invalid workspace read response');
			}
			return payload;
		},
		abort() {
			++sequence;
			if (current) current.abort();
			current = null;
		},
	};
}

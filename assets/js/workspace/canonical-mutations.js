/**
 * Work M1: ajax transport for the EXACT existing guarded OperationalActions
 * admin-post handlers. The server shares action handlers and repository code.
 * No second Work Item mutation implementation lives in the browser.
 */
export const MUTATION_ACTIONS = Object.freeze([
	'cb_work_transition_work_item',
	'cb_work_quick_edit_work_item',
	'cb_work_bulk_transition_work_items',
	'cb_work_bulk_edit_work_items',
]);

export function mutationAction(form) {
	if (!form || typeof form.querySelector !== 'function') return null;
	const action = form.querySelector('input[name="action"]')?.value ?? '';
	const nonce = form.querySelector('input[name="_wpnonce"]')?.value ?? '';
	const method = String(form.method || '').toLowerCase();
	return method === 'post' && MUTATION_ACTIONS.includes(action) && nonce ? action : null;
}

export function mutationResponse(payload) {
	if (!payload || typeof payload !== 'object' || typeof payload.success !== 'boolean') {
		throw new TypeError('Invalid Work Item mutation response');
	}
	if (!payload.success) return { success: false, data: payload.data ?? null };
	const updated = Number(payload.data?.updated);
	const attempted = Number(payload.data?.attempted);
	if (!Number.isInteger(updated) || updated < 1 || !Number.isInteger(attempted)
		|| attempted < updated || attempted > 100) {
		throw new TypeError('Invalid Work Item mutation count');
	}
	return { success: true, updated, attempted, notice: String(payload.data.notice || '') };
}

export function createMutationAdapter(destination, panel, browser, refresh) {
	const endpointText = panel?.dataset.cbWorkUweClassicEndpoint || '';
	if (!destination || !endpointText || typeof browser.fetch !== 'function'
		|| typeof refresh !== 'function') return null;
	const endpoint = new URL(endpointText, browser.location.href);
	const current = new URL(browser.location.href);
	if (endpoint.origin !== current.origin || !endpoint.pathname.endsWith('/admin-ajax.php')) return null;
	const inFlight = new WeakSet();
	const document = panel.ownerDocument;
	let status = null;
	let mutationPending = false;

	function announce(message, error = false) {
		if (!status) {
			status = document.createElement('p');
			status.setAttribute('data-cb-work-uwe-mutation-status', '');
			status.setAttribute('role', 'status');
			status.setAttribute('aria-live', 'polite');
			panel.insertBefore(status, destination);
		}
		status.hidden = false;
		status.setAttribute('role', error ? 'alert' : 'status');
		status.textContent = message;
	}

	async function handle(event) {
		if (event.defaultPrevented) return;
		const form = event.target;
		if (!form || !destination.contains(form) || String(form.method || '').toLowerCase() !== 'post') return;
		// Fail closed: unexpected native admin-post actions must never navigate
		// away from the persistent Work app or bypass its controlled transport.
		event.preventDefault();
		const action = mutationAction(form);
		if (!action) {
			announce(panel.dataset.cbWorkUweMutationFailed || 'Work Item update failed.', true);
			return;
		}
		if (inFlight.has(form)) return;
		if (mutationPending) {
			announce(panel.dataset.cbWorkUweMutationSaving || 'Saving…');
			return;
		}
		mutationPending = true;
		inFlight.add(form);
		destination.setAttribute('aria-busy', 'true');
		const submit = event.submitter;
		if (submit && 'disabled' in submit) submit.disabled = true;
		form.setAttribute('aria-busy', 'true');
		try {
			announce(panel.dataset.cbWorkUweMutationSaving || 'Saving…');
			const body = new FormData(form);
			if (body.get('action') !== action) throw new TypeError('Work Item mutation action mismatch');
			const response = await browser.fetch(endpoint.href, {
				method: 'POST',
				credentials: 'same-origin',
				cache: 'no-store',
				headers: { Accept: 'application/json' },
				body,
			});
			const payload = await response.json().catch(() => null);
			const result = mutationResponse(payload);
			if (!response.ok || !result.success) {
				announce(panel.dataset.cbWorkUweMutationFailed || 'Work Item update failed.', true);
				return;
			}
			const message = panel.dataset.cbWorkUweMutationSaved || 'Saved';
			announce(result.attempted === result.updated
				? message : message + ' (' + result.updated + '/' + result.attempted + ')');
			// Do not perform an optimistic mutation: only server-confirmed
			// success causes the canonical renderer to rehydrate the view.
			// The user may have changed views during the mutation. Rehydrate
			// the CURRENT view rather than silently keeping stale state.
			if (!panel.hidden) await refresh();
		} catch {
			// Network, malformed response and authorization failures keep the
			// current form/selection available for correction and retry.
			announce(panel.dataset.cbWorkUweMutationFailed || 'Work Item update failed.', true);
		} finally {
			mutationPending = false;
			destination.removeAttribute('aria-busy');
			inFlight.delete(form);
			form.removeAttribute('aria-busy');
			if (submit && 'disabled' in submit && destination.contains(form)) {
				submit.disabled = false;
			}
		}
	}
	destination.addEventListener('submit', (event) => { void handle(event); });
	return { ready(root) {
		// Only enable previously hidden mutation controls after both the
		// canonical UI handlers AND secure submission transport are mounted.
		destination.dataset.cbWorkMutationsReady =
			root?.dataset?.cbWorkActionsInitialized === '1' ? '1' : '0';
	}, disable() { destination.dataset.cbWorkMutationsReady = '0'; } };
}

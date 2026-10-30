/**
 * M3-UX2: host an existing Work Operations panel in the public Base Modal.
 *
 * The parent workspace dialog is always settled before an existing child
 * editor calls Base Modal. We use Base's documented onConfirm and the native
 * form.requestSubmit(), not private Base modal internals or dialog.close().
 * No alternate persistence, API or modal engine is introduced.
 */
export function createOperationDialog(panel, browser, { title, onOpen } = {}) {
	const modal = browser?.cbCore?.modal;
	const parent = panel?.parentNode;
	const doc = panel?.ownerDocument;
	if (typeof modal?.show !== 'function' || !parent?.insertBefore ||
		!doc?.createElement || typeof panel.closest !== 'function') return null;

	const marker = doc.createElement('span');
	marker.hidden = true;
	marker.setAttribute('aria-hidden', 'true');
	parent.insertBefore(marker, panel);

	let disposed = false;
	let active = false;
	let pending = null;
	let requestedClose = false;
	const waitForBaseCleanup = () => new Promise((resolve) => {
		if (typeof browser.requestAnimationFrame === 'function') browser.requestAnimationFrame(resolve);
		else setTimeout(resolve, 0);
	});

	// Native filter/timer forms live inside the Base dialog body; their submit
	// events must not bubble into Base's parent dialog confirmation form.
	const stopNestedSubmit = (event) => event.stopPropagation();
	panel.addEventListener('submit', stopNestedSubmit);

	function submitHost() {
		const form = panel.closest('dialog')?.querySelector('form');
		if (!form || typeof form.requestSubmit !== 'function') return false;
		form.requestSubmit();
		return true;
	}

	async function open() {
		if (disposed || active) return false;
		active = true;
		requestedClose = false;
		const body = doc.createElement('div');
		body.className = 'cb-work-unified cb-work-ux2-dialog';
		body.appendChild(panel);
		panel.hidden = false;
		let failed = false;
		try {
			if (typeof onOpen === 'function') onOpen();
			await modal.show({
				title, body, size: 'workspace', expandable: true,
				dismissOnly: true, onConfirm: async () => true,
			});
		} catch {
			failed = true;
		} finally {
			// Restore the *same* live panel and its event handlers; do not
			// clone it or let Base cleanup destroy Work's mounted UI.
			parent.insertBefore(panel, marker.nextSibling);
			panel.hidden = true;
			active = false;
			if (disposed) marker.remove();
		}
		const next = pending;
		pending = null;
		if (disposed || requestedClose || failed || !next) return !failed;
		await waitForBaseCleanup();
		try {
			await next();
		} finally {
			// Return to the same manager list after a child editor finishes.
			// Base's child modal resolves before this call, never nested.
			if (!disposed && !requestedClose) {
				await waitForBaseCleanup();
				void open();
			}
		}
		return true;
	}

	function transition(action) {
		if (disposed || typeof action !== 'function') return false;
		if (!active) {
			void action();
			return true;
		}
		if (pending) return false;
		pending = action;
		if (!submitHost()) {
			pending = null;
			return false;
		}
		return true;
	}

	function dismiss() {
		if (!active) return false;
		requestedClose = true;
		pending = null;
		return submitHost();
	}

	function dispose() {
		disposed = true;
		pending = null;
		if (active) dismiss();
		else marker.remove();
		panel.removeEventListener('submit', stopNestedSubmit);
	}

	return { open, transition, dismiss, isOpen: () => active, dispose };
}

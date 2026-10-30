/**
 * M3-B3 operator-attested external billing, using canonical server coverage
 * and CAS. Never emits provider invoices or invents financial state.
 */
export function createEngagementBillingDialog(root, browser, request, onUpdated = null) {
	const panel = root?.querySelector?.('[data-cb-work-m3-engagements]');
	const document = root?.ownerDocument;
	const modal = browser.cbCore?.modal;
	if (!panel || !document || !modal?.show) return null;

	let labels, frequencies, timings;
	try {
		labels = JSON.parse(panel.dataset.cbWorkM3BillingStatuses || '{}');
		frequencies = JSON.parse(panel.dataset.cbWorkM3BillingFrequencies || '{}');
		timings = JSON.parse(panel.dataset.cbWorkM3BillingTimings || '{}');
		if (!['not_applicable', 'not_recorded', 'invoiced'].every((s) => typeof labels[s] === 'string')
			|| !['weekly', 'monthly', 'quarterly', 'yearly'].every((s) => typeof frequencies[s] === 'string')
			|| !['advance', 'arrears'].every((s) => typeof timings[s] === 'string')) return null;
	} catch { return null; }

	const make = (tag, content = '') => {
		const node = document.createElement(tag);
		node.textContent = content;
		return node;
	};
	const selector = (name, options) => {
		const element = make('select');
		element.setAttribute('aria-label', name);
		for (const [key, value] of Object.entries(options)) {
			const option = make('option', value);
			option.value = key;
			element.appendChild(option);
		}
		return element;
	};
	const row = (title, input) => {
		const wrapper = make('label');
		wrapper.className = 'cb-work-m3-billing__field';
		wrapper.appendChild(make('span', title));
		wrapper.appendChild(input);
		return wrapper;
	};
	const date = (name) => {
		const input = make('input');
		input.type = name;
		return input;
	};
	let busy = false;
	function urlFor(id, terms = false) {
		if (!Number.isSafeInteger(id) || id <= 0) throw new TypeError('Invalid engagement');
		const raw = terms ? panel.dataset.cbWorkM3BillingTermsEndpoint : panel.dataset.cbWorkM3BillingEndpoint;
		if (!raw || !raw.includes('__id__')) throw new TypeError('Invalid billing endpoint');
		const url = new URL(raw.replace('__id__', String(id)), browser.location.href);
		if (url.origin !== new URL(browser.location.href).origin) throw new TypeError('Cross-origin Work billing request');
		return url;
	}
	function valid(data, id) {
		if (!data || data.engagement_id !== id || !/^\d{4}-(0[1-9]|1[0-2])$/.test(data.month)
			|| !['internal', 'customer'].includes(data.work_context)
			|| !['not_applicable', 'not_recorded', 'invoiced'].includes(data.state)
			|| !/^\d{4}-\d{2}-\d{2}$/.test(data.starts_on)
			|| (data.work_context === 'internal' && data.state !== 'not_applicable')) return false;
		if (data.state === 'not_applicable') {
			return data.registration === null && data.period_start === null && data.period_end_exclusive === null;
		}
		if (data.work_context !== 'customer'
			|| !/^\d{4}-\d{2}-\d{2}$/.test(data.period_start)
			|| !/^\d{4}-\d{2}-\d{2}$/.test(data.period_end_exclusive)
			|| data.period_end_exclusive <= data.period_start) return false;
		const reg = data.registration;
		return reg === null || (
			Number.isSafeInteger(reg.revision) && reg.revision >= 1
			&& (reg.invoiced_on === null || /^\d{4}-\d{2}-\d{2}$/.test(reg.invoiced_on))
			&& typeof reg.external_reference === 'string' && reg.external_reference.length <= 190
		);
	}
	async function open(engagement, focusButton = null) {
		if (busy || !modal?.show || !engagement || !Number.isSafeInteger(engagement.id)) return false;
		busy = true;
		const id = engagement.id;
		let closed = false, loading = null, seq = 0, snapshot = null, changed = false;
		const body = make('div');
		body.className = 'cb-work-m3-billing';
		const month = date('month');
		const status = make('p');
		status.setAttribute('role', 'status');
		const windowLabel = make('p');
		const form = make('div');
		form.className = 'cb-work-m3-billing__form';
		const error = make('p');
		error.setAttribute('role', 'alert');
		error.hidden = true;
		body.append(row(panel.dataset.cbWorkM3BillingPeriod, month), status, windowLabel, form, error);
		let inputs = null;

		function clearError() { error.hidden = true; error.textContent = ''; }
		function showError() { error.textContent = panel.dataset.cbWorkM3Error; error.hidden = false; }
		function render(data) {
			snapshot = data;
			month.value = data.month;
			clearError();
			status.textContent = panel.dataset.cbWorkM3BillingState + ': ' + labels[data.state];
			windowLabel.textContent = data.period_start && data.period_end_exclusive
				? data.period_start + ' → ' + data.period_end_exclusive : '';
			form.replaceChildren();
			inputs = null;
			if (data.work_context === 'internal') return;
			if (data.state === 'not_applicable') {
				const frequency = selector(panel.dataset.cbWorkM3BillingFrequency, frequencies);
				frequency.value = 'monthly';
				const timing = selector(panel.dataset.cbWorkM3BillingTiming, timings);
				timing.value = 'arrears';
				const anchor = date('date');
				anchor.required = true;
				anchor.min = data.starts_on;
				anchor.value = data.starts_on;
				form.append(row(panel.dataset.cbWorkM3BillingFrequency, frequency),
					row(panel.dataset.cbWorkM3BillingTiming, timing),
					row(panel.dataset.cbWorkM3BillingStart, anchor));
				inputs = { kind: 'terms', frequency, timing, anchor };
				return;
			}
			const state = selector(panel.dataset.cbWorkM3BillingState, {
				not_recorded: labels.not_recorded, invoiced: labels.invoiced,
			});
			state.value = data.state;
			const invoiceDate = date('date');
			invoiceDate.value = data.registration?.invoiced_on || '';
			const reference = date('text');
			reference.maxLength = 190;
			reference.value = data.registration?.external_reference || '';
			form.append(row(panel.dataset.cbWorkM3BillingState, state),
				row(panel.dataset.cbWorkM3BillingDate, invoiceDate),
				row(panel.dataset.cbWorkM3BillingRef, reference));
			inputs = { kind: 'record', state, invoiceDate, reference };
		}
		async function load(monthValue = '') {
			if (closed) return false;
			loading?.abort();
			loading = new AbortController();
			const ticket = ++seq, controller = loading;
			snapshot = null;
			inputs = null;
			form.replaceChildren();
			windowLabel.textContent = '';
			status.textContent = '';
			clearError();
			try {
				const url = urlFor(id);
				if (monthValue) url.searchParams.set('month', monthValue);
				const data = await request(url.href, { method: 'GET', signal: controller.signal });
				if (closed || ticket !== seq) return false;
				if (!valid(data, id)) throw new TypeError('Invalid billing coverage');
				render(data);
				return true;
			} catch (reason) {
				if (!closed && ticket === seq && reason?.name !== 'AbortError') showError();
				return false;
			} finally {
				if (ticket === seq) loading = null;
			}
		}
		const onMonth = () => { if (!closed && /^\d{4}-\d{2}$/.test(month.value)) void load(month.value); };
		month.addEventListener('change', onMonth);
		try {
			if (!await load()) {
				panel.querySelector?.('[data-cb-work-m3-engagements-error]')?.removeAttribute?.('hidden');
				return false;
			}
			const accepted = await modal.show({
				title: panel.dataset.cbWorkM3BillingAction,
				body,
				confirmLabel: panel.dataset.cbWorkM3BillingSave,
				cancelLabel: panel.dataset.cbWorkM3Cancel,
				initialFocus: 'input[type="month"]',
				onConfirm: async () => {
					if (closed || !snapshot || !inputs || snapshot.engagement_id !== id) {
						showError();
						return false;
					}
					if (inputs.kind === 'terms') {
						const frequency = inputs.frequency.value, timing = inputs.timing.value, anchor = inputs.anchor.value;
						if (!Object.hasOwn(frequencies, frequency) || !Object.hasOwn(timings, timing)
							|| !/^\d{4}-\d{2}-\d{2}$/.test(anchor) || anchor < snapshot.starts_on) {
							showError();
							return false;
						}
						try {
							const created = await request(urlFor(id, true).href, {
								method: 'POST',
								body: JSON.stringify({ frequency, timing, anchor_on: anchor }),
							});
							if (!Number.isSafeInteger(created?.terms_id) || created.terms_id <= 0) throw new TypeError('Invalid terms receipt');
							changed = true;
							return true;
						} catch { showError(); return false; }
					}
					if (inputs.kind !== 'record') return false;
					const state = inputs.state.value;
					const invoicedOn = inputs.invoiceDate.value;
					const reference = inputs.reference.value;
					if (!['not_recorded', 'invoiced'].includes(state)
						|| (invoicedOn && !/^\d{4}-\d{2}-\d{2}$/.test(invoicedOn))
						|| reference.length > 190) { showError(); return false; }
					const expectedRevision = snapshot.registration?.revision ?? 0;
					try {
						const updated = await request(urlFor(id).href, {
							method: 'POST',
							body: JSON.stringify({
								month: snapshot.month, state,
								invoiced_on: invoicedOn, reference,
								expected_revision: expectedRevision,
							}),
						});
						if (!['saved', 'reused'].includes(updated?.outcome)
							|| !Number.isSafeInteger(updated.revision) || updated.revision < 1) {
							throw new TypeError('Invalid billing receipt');
						}
						changed = true;
						return true;
					} catch {
						// A stale CAS write MUST stay visible and cannot be
						// rebased silently over another operator's changes.
						showError();
						return false;
					}
				},
			});
			if (accepted === true && changed && !closed) {
				if (typeof onUpdated === 'function') await onUpdated(id, focusButton);
				return true;
			}
			return false;
		} finally {
			closed = true;
			++seq;
			loading?.abort();
			month.removeEventListener('change', onMonth);
			busy = false;
		}
	}
	return { open };
}

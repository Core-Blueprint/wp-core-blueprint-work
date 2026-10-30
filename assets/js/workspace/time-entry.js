/**
 * M3-C2: Base Modal edits only the manager's own Time entries.
 * WordPress local time, CAS and audit remain authoritative on the server.
 */
export function createTimeEntryDialog(root, panel, browser, request, onSaved) {
	const modal = browser.cbCore?.modal;
	const doc = root.ownerDocument;
	const endpoint = panel.dataset.cbWorkM3TimeEntry;
	const searchUrl = panel.dataset.cbWorkM3TimeSearch;
	if (!modal?.show || !endpoint || !searchUrl) return null;
	try {
		if ([endpoint, searchUrl].some((url) =>
			new URL(url, browser.location.href).origin !== new URL(browser.location.href).origin)) return null;
	} catch { return null; }

	let opening = false, disposed = false;
	const make = (tag, text = '') => {
		const el = doc.createElement(tag);
		el.textContent = text;
		return el;
	};
	const field = (label, type, value = '') => {
		const input = make('input');
		input.type = type;
		input.value = value;
		input.required = type !== 'search';
		if (type === 'time') input.step = '1';
		if (type === 'search') { input.maxLength = 80; input.autocomplete = 'off'; }
		const row = make('label');
		row.className = 'cb-work-m3-time-editor__field';
		row.append(make('span', label), input);
		return { row, input };
	};
	const validDetail = (data, id) => data && data.id === id
		&& Number.isSafeInteger(data.revision) && data.revision >= 1
		&& Number.isSafeInteger(data.work_item_id) && data.work_item_id > 0
		&& typeof data.title === 'string' && typeof data.note === 'string'
		&& data.note.length <= 4000
		&& ['start_date', 'end_date'].every((key) => /^\d{4}-\d{2}-\d{2}$/.test(data[key]))
		&& ['start_time', 'end_time'].every((key) => /^\d{2}:\d{2}:\d{2}$/.test(data[key]));

	async function open(id = 0, trigger = null) {
		if (opening || disposed || !Number.isSafeInteger(id) || id < 0) return false;
		opening = true;
		let closed = false, pickerAbort = null, pickerTimer = null, pickerSeq = 0;
		try {
			let detail = null;
			if (id > 0) {
				try { detail = await request(endpoint + '/' + String(id)); }
				catch { return false; }
				if (disposed || !validDetail(detail, id)) return false;
			}

			const body = make('div');
			body.className = 'cb-work-m3-time-editor';
			const alert = make('p');
			alert.hidden = true;
			alert.setAttribute('role', 'alert');
			const note = make('textarea');
			note.rows = 3;
			note.maxLength = 4000;
			note.value = detail?.note || '';
			const noteRow = make('label');
			noteRow.className = 'cb-work-m3-time-editor__field';
			noteRow.append(make('span', panel.dataset.cbWorkM3TimeNoteLabel), note);
			let itemSelect = null;
			if (detail) {
				body.append(make('strong', panel.dataset.cbWorkM3TimeItem),
					make('p', detail.title));
			} else {
				const searchField = field(panel.dataset.cbWorkM3TimeSearchLabel, 'search');
				const itemSearch = searchField.input;
				itemSelect = make('select');
				itemSelect.disabled = true;
				itemSelect.required = true;
				itemSelect.setAttribute('aria-label', panel.dataset.cbWorkM3TimeItem);
				const itemRow = make('label');
				itemRow.className = 'cb-work-m3-time-editor__field';
				itemRow.append(make('span', panel.dataset.cbWorkM3TimeItem), itemSelect);
				body.append(searchField.row, itemRow);
				const lookup = async () => {
					pickerAbort?.abort();
					pickerAbort = new AbortController();
					const current = pickerAbort;
					const ticket = ++pickerSeq;
					const term = itemSearch.value.trim().slice(0, 80);
					itemSelect.replaceChildren();
					itemSelect.disabled = true;
					if (term.length < 2) return;
					const url = new URL(searchUrl, browser.location.href);
					url.searchParams.set('search', term);
					try {
						const data = await request(url.href, { signal: current.signal });
						if (disposed || closed || ticket !== pickerSeq) return;
						if (!Array.isArray(data?.items) || data.items.length > 20
							|| !data.items.every((x) => Number.isSafeInteger(x.id) && x.id > 0 && typeof x.label === 'string')) {
							throw new TypeError('Invalid Work Item picker');
						}
						const choose = make('option', panel.dataset.cbWorkM3TimeSelect);
						choose.value = '';
						itemSelect.append(choose, ...data.items.map((x) => {
							const option = make('option', x.label);
							option.value = String(x.id);
							return option;
						}));
						itemSelect.disabled = data.items.length === 0;
					} catch (error) {
						if (disposed || closed || ticket !== pickerSeq || error?.name === 'AbortError') return;
						alert.textContent = panel.dataset.cbWorkM3TimeInvalid;
						alert.hidden = false;
					}
				};
				itemSearch.addEventListener('input', () => {
					clearTimeout(pickerTimer);
					pickerAbort?.abort();
					++pickerSeq;
					itemSelect.replaceChildren();
					itemSelect.disabled = true;
					pickerTimer = setTimeout(() => { void lookup(); }, 250);
				});
			}

			const today = /^\d{4}-\d{2}-\d{2}$/.test(panel.dataset.cbWorkM3TimeToday)
				? panel.dataset.cbWorkM3TimeToday : '';
			const startDate = field(panel.dataset.cbWorkM3TimeBegin, 'date', detail?.start_date || today);
			const startTime = field(panel.dataset.cbWorkM3TimeBegin, 'time', detail?.start_time || '');
			const endDate = field(panel.dataset.cbWorkM3TimeEnd, 'date', detail?.end_date || today);
			const endTime = field(panel.dataset.cbWorkM3TimeEnd, 'time', detail?.end_time || '');
			const range = make('div');
			range.className = 'cb-work-m3-time-editor__range';
			range.append(startDate.row, startTime.row, endDate.row, endTime.row);
			body.append(range, noteRow, alert);
			let saved = false, stale = false;
			const result = await modal.show({
				title: detail ? panel.dataset.cbWorkM3TimeCorrect : panel.dataset.cbWorkM3TimeAdd,
				body, confirmLabel: detail ? panel.dataset.cbWorkM3TimeSave : panel.dataset.cbWorkM3TimeAdd,
				cancelLabel: panel.dataset.cbWorkM3TimeCancel,
				initialFocus: detail ? 'input[type="date"]' : 'input[type="search"]',
				onConfirm: async () => {
					if (disposed || closed || stale) return false;
					const item = detail?.work_item_id || Number(itemSelect?.value);
					const controls = [startDate.input, startTime.input, endDate.input, endTime.input];
					if (!Number.isSafeInteger(item) || item <= 0 ||
						controls.some((control) => !control.value || !control.checkValidity())
						|| note.value.length > 4000) {
						alert.textContent = panel.dataset.cbWorkM3TimeInvalid;
						alert.hidden = false;
						return false;
					}
					const payload = {
						operation: detail ? 'update' : 'create',
						start_date: startDate.input.value, start_time: startTime.input.value,
						end_date: endDate.input.value, end_time: endTime.input.value, note: note.value,
					};
					if (detail) {
						payload.id = detail.id;
						payload.expected_revision = detail.revision;
					} else payload.work_item_id = item;
					try {
						const receipt = await request(endpoint, { method: 'POST', body: JSON.stringify(payload) });
						if (!Number.isSafeInteger(receipt?.id) || receipt.id <= 0
							|| !Number.isSafeInteger(receipt?.revision) || receipt.revision < 1
							|| (detail && (receipt.id !== detail.id || receipt.updated !== true
								|| receipt.revision <= detail.revision))
							|| (!detail && (receipt.created !== true || receipt.revision !== 1))) {
							throw new TypeError('Invalid Time entry receipt');
						}
						saved = true;
						return true;
					} catch (error) {
						if (error?.status === 409) stale = true; // No silent CAS rebase.
						if (error?.status === 403) body.replaceChildren(alert);
						alert.textContent = error?.status === 409 ? panel.dataset.cbWorkM3TimeConflict
							: error?.status === 403 ? panel.dataset.cbWorkM3TimeDenied
							: panel.dataset.cbWorkM3TimeInvalid;
						alert.hidden = false;
						return false;
					}
				},
			});
			if (result === true && saved && !disposed) {
				await onSaved?.();
				return true;
			}
			return false;
		} finally {
			closed = true;
			pickerAbort?.abort();
			clearTimeout(pickerTimer);
			opening = false;
			trigger?.focus?.();
		}
	}
	return { open, dispose() { disposed = true; } };
}

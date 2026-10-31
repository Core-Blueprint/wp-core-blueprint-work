/**
 * M3-B4-B3: Recurring Work SPA. Base owns modal + Object Picker UI;
 * REST and RecurrenceRules own all domain validation and writes.
 */
function el(doc, tag, className = '', text = null) {
	const node = doc.createElement(tag);
	if (className) node.className = className;
	if (text !== null) node.textContent = String(text);
	return node;
}

function control(doc, label, key, value, type = 'text', config = {}) {
	const wrap = el(doc, 'label', 'cb-work-recurring__field');
	const caption = el(doc, 'span', '', label);
	const input = el(doc, type === 'textarea' ? 'textarea' : 'input');
	if (type !== 'textarea') input.type = type;
	input.name = key;
	input.value = String(value ?? '');
	if (config.required) input.required = true;
	if (config.maxLength) input.maxLength = config.maxLength;
	if (config.min !== undefined) input.min = String(config.min);
	if (config.max !== undefined) input.max = String(config.max);
	if (type === 'textarea') input.rows = 4;
	wrap.append(caption, input);
	return wrap;
}

function selection(doc, label, key, value, choices, blank = null) {
	const wrap = el(doc, 'label', 'cb-work-recurring__field');
	const select = el(doc, 'select');
	select.name = key;
	if (blank !== null) {
		const option = el(doc, 'option', '', blank);
		option.value = '0';
		select.append(option);
	}
	for (const choice of choices) {
		const option = el(doc, 'option', '', choice.title);
		option.value = String(choice.id);
		select.append(option);
	}
	select.value = String(value ?? (blank === null ? '' : 0));
	wrap.append(el(doc, 'span', '', label), select);
	return wrap;
}

// The publicly documented Base Object Picker selected/nonce/search contract.
function picker(doc, label, key, items, nonce, action, multiple) {
	const wrapper = el(doc, 'div', 'cb-work-recurring__field cb-work-recurring__picker');
	wrapper.append(el(doc, 'span', '', label));
	const outer = el(doc, 'div', 'cb-core-object-picker');
	outer.setAttribute('data-cb-core-object-picker', '');
	outer.dataset.searchAction = action;
	outer.dataset.searchNonce = nonce;
	outer.dataset.searchContext = '{}';
	outer.dataset.selected = JSON.stringify(items);
	outer.dataset.multiple = multiple ? '1' : '0';
	outer.dataset.emptyMessage = 'No results found.';
	const input = el(doc, 'input', 'regular-text code cb-core-object-picker__input');
	input.type = 'text';
	input.name = key;
	input.setAttribute('data-cb-core-object-picker-input', '');
	input.value = items.map((x) => String(x.id)).join(',');
	const enhanced = el(doc, 'div', 'cb-core-object-picker__enhanced');
	enhanced.hidden = true;
	enhanced.setAttribute('data-cb-core-object-picker-enhanced', '');
	const selected = el(doc, 'div', 'cb-core-object-picker__selected');
	selected.setAttribute('data-cb-core-object-picker-selected', '');
	const search = el(doc, 'input', 'regular-text cb-core-object-picker__search');
	search.type = 'search';
	search.setAttribute('data-cb-core-object-picker-search', '');
	search.setAttribute('aria-label', label);
	const results = el(doc, 'div', 'cb-core-object-picker__results');
	results.hidden = true;
	results.setAttribute('data-cb-core-object-picker-results', '');
	enhanced.append(selected, search, results);
	outer.append(input, enhanced);
	wrapper.append(outer);
	return { wrapper, input };
}

function section(doc, title, content) {
	const block = el(doc, 'section', 'cb-work-recurring__section');
	block.append(el(doc, 'h3', '', title), content);
	return block;
}

function readChanges(form, original, quick = false) {
	const fields = {};
	for (const field of form.querySelectorAll('input[name], select[name], textarea[name]')) {
		if (field.disabled) continue;
		if (!field.reportValidity()) return null;
		const key = field.name;
		let value = field.value;
		if (['project_id','service_id','work_type_id','estimated_minutes','interval_count','create_ahead_days','due_offset_days'].includes(key)) {
			value = Number(value);
			if (!Number.isSafeInteger(value) || value < 0) return null;
		} else if (key === 'assigned_user_ids') {
			value = value.trim() ? value.split(',').map(Number) : [];
			if (value.some((number) => !Number.isSafeInteger(number) || number < 1)) return null;
		}
		if (!original || JSON.stringify(value) !== JSON.stringify(original[key])) fields[key] = value;
	}
	return fields;
}

/**
 * Read only the schedule fields for preview. No new writable field or client
 * scheduler is introduced: the canonical REST preview owns all date math.
 */
export function schedulePreviewFields(form) {
	const select = (key) => form.querySelector('[name="' + key + '"]');
	const frequency = select('frequency');
	const interval = select('interval_count');
	const start = select('start_on');
	const end = select('end_on');
	if (!frequency || !interval || !start || !end ||
		![frequency, interval, start, end].every((field) => field.checkValidity())) return null;
	const intervalCount = Number(interval.value);
	if (!Number.isSafeInteger(intervalCount) || intervalCount < 1 || intervalCount > 999 ||
		!/^\\d{4}-\\d{2}-\\d{2}$/.test(start.value)) return null;
	return {
		frequency: frequency.value,
		interval_count: intervalCount,
		start_on: start.value,
		end_on: end.value || null,
	};
}

function formView(doc, root, item, options, quick = false, pickerAvailable = false) {
	const panel = root.querySelector('[data-cb-work-uwe-panel="recurring"]');
	const labels = panel.dataset;
	const body = el(doc, 'div', 'cb-work-recurring');
	const form = el(doc, 'div', 'cb-work-recurring__form');
	const notice = el(doc, 'p', 'cb-work-recurring__message');
	notice.setAttribute('role', 'alert');
	notice.hidden = true;
	const original = {};
	const capture = () => {
		for (const field of form.querySelectorAll('input[name],select[name],textarea[name]')) {
			if (field.disabled) continue;
			const key = field.name;
			const isInt = ['project_id','service_id','work_type_id','estimated_minutes','interval_count','create_ahead_days','due_offset_days'].includes(key);
			original[key] = key === 'assigned_user_ids' ? (field.value ? field.value.split(',').map(Number) : [])
				: isInt ? Number(field.value) : field.value;
		}
	};
	const field = (label, key, value, type, config) => control(doc, label, key, value, type, config);
	const priorityChoices = ['low','normal','high','urgent'].map((x) => ({
		id: x, title: root.dataset['cbWorkM2Priority' + x[0].toUpperCase() + x.slice(1)] || x,
	}));
	const title = field(root.dataset.cbWorkM2Title || 'Title', 'title', item.title || '', 'text', {required:true, maxLength:200});
	const priority = selection(doc, root.dataset.cbWorkM2Priority || 'Priority', 'priority', item.priority || 'normal', priorityChoices);
	if (quick) {
		form.append(title, priority);
		capture();
		form.append(notice); body.append(form);
		return {body, form, notice, original};
	}

	const layout = el(doc, 'div', 'cb-work-recurring__layout');
	const main = el(doc, 'div', 'cb-work-recurring__main');
	const aside = el(doc, 'aside', 'cb-work-recurring__aside');
	const identity = el(doc, 'div', 'cb-work-recurring__stack');
	identity.append(title, field(root.dataset.cbWorkM2DescriptionLabel || 'Description',
		'description', item.description || '', 'textarea', {maxLength:65535}));
	main.append(section(doc, labels.cbWorkRecurringDetails || 'Work details', identity));

	const scheduling = el(doc, 'div', 'cb-work-recurring__schedule');
	const cadence = el(doc, 'div', 'cb-work-recurring__cadence');
	const every = field(labels.cbWorkRecurringEvery || 'Every', 'interval_count',
		item.interval_count ?? 1, 'number', {required:true,min:1,max:999});
	const frequency = selection(doc, labels.cbWorkRecurringFrequency || 'Frequency', 'frequency',
		item.frequency || 'weekly', ['daily','weekly','monthly','yearly'].map((name) => ({
			id: name, title: labels['cbWorkRecurringUnit' + name[0].toUpperCase() + name.slice(1)] || name,
		})));
	cadence.append(every, frequency);
	scheduling.append(cadence);

	const dates = el(doc, 'div', 'cb-work-recurring__grid');
	const starts = field(root.dataset.cbWorkM2Start || 'Start date', 'start_on',
		item.start_on || options.today, 'date', {required:true});
	const endGroup = el(doc, 'div', 'cb-work-recurring__end-group');
	const endToggle = el(doc, 'label', 'cb-work-recurring__end-toggle');
	const endCheck = el(doc, 'input');
	endCheck.type = 'checkbox'; // UI-only; NEVER included in canonical REST writes.
	endCheck.checked = !!item.end_on;
	const endCaption = el(doc, 'span', '', labels.cbWorkRecurringEndDate || 'End date');
	endToggle.append(endCheck, endCaption);
	const endDate = field(labels.cbWorkRecurringEndDate || 'End date', 'end_on',
		item.end_on || '', 'date');
	endDate.classList.add('cb-work-recurring__end-date');
	const endInput = endDate.querySelector('input');
	endInput.setAttribute('aria-label', labels.cbWorkRecurringEndDate || 'End date');
	const noEnd = el(doc, 'span', 'cb-work-recurring__no-end', labels.cbWorkRecurringNoLimit || 'No date limit');
	let previousEnd = endInput.value;
	const syncEnd = () => {
		const enabled = endCheck.checked;
		endDate.hidden = !enabled;
		noEnd.hidden = enabled;
		endInput.required = enabled;
		if (!enabled) {
			if (endInput.value) previousEnd = endInput.value;
			endInput.value = '';
		} else if (!endInput.value) endInput.value = previousEnd;
	};
	endCheck.addEventListener('change', syncEnd);
	endGroup.append(endToggle, endDate, noEnd);
	syncEnd();
	dates.append(starts, endGroup);
	scheduling.append(dates);
	if (item.schedule_locked) {
		for (const key of ['frequency','interval_count','start_on','end_on']) {
			scheduling.querySelector('[name="' + key + '"]').disabled = true;
		}
		endCheck.disabled = true;
		scheduling.append(el(doc, 'p', 'description', labels.cbWorkRecurringLock || 'Schedule locked by history.'));
	}
	main.append(section(doc, labels.cbWorkRecurringSchedule || 'Schedule', scheduling));

	const contextGrid = el(doc, 'div', 'cb-work-recurring__grid');
	const context = selection(doc, root.dataset.cbWorkM2Context || 'Work context', 'work_context',
		item.work_context || 'internal', [
			{id:'internal',title:root.dataset.cbWorkM2Internal || 'Internal'},
			{id:'customer',title:root.dataset.cbWorkM2CustomerWork || 'Customer'},
		]);
	const project = selection(doc, root.dataset.cbWorkM2Project || 'Project', 'project_id',
		item.project_id || 0, options.projects || [], root.dataset.cbWorkM2NoProject || 'No Project');
	contextGrid.append(context, project,
		selection(doc, root.dataset.cbWorkM2Service || 'Service', 'service_id',
			item.service_id || 0, options.services || [], root.dataset.cbWorkM2NoService || 'No service'),
		selection(doc, root.dataset.cbWorkM2Type || 'Work Type', 'work_type_id',
			item.work_type_id || 0, options.work_types || [], root.dataset.cbWorkM2NoType || 'No type'));
	if (pickerAvailable && options.customer_enabled) {
		const customer = picker(doc, root.dataset.cbWorkM2Customer || 'Customer', 'customer_object_id',
			item.customer ? [item.customer] : [], options.picker_nonce, 'cb_work_search_customers', false);
		contextGrid.append(customer.wrapper);
		const syncCustomer = () => {
			const inherited = Number(project.querySelector('select').value) > 0;
			const enabled = !inherited && context.querySelector('select').value === 'customer';
			context.querySelector('select').disabled = inherited;
			customer.wrapper.hidden = !enabled;
			customer.input.disabled = !enabled;
		};
		form.addEventListener('change', syncCustomer);
		syncCustomer();
	}
	main.append(section(doc, labels.cbWorkRecurringContext || 'Work context', contextGrid));
	if (pickerAvailable) {
		const assignees = picker(doc, root.dataset.cbWorkM2Assignees || 'Assignees',
			'assigned_user_ids', item.assignees || [], options.picker_nonce, 'cb_work_search_users', true);
		main.append(section(doc, labels.cbWorkRecurringAssignment || 'Assignment', assignees.wrapper));
	}

	const advanced = el(doc, 'details', 'cb-work-recurring__advanced');
	advanced.append(el(doc, 'summary', '', labels.cbWorkRecurringAdvanced || 'Show advanced settings'));
	const advancedGrid = el(doc, 'div', 'cb-work-recurring__grid');
	advancedGrid.append(
		field(labels.cbWorkRecurringCreateAhead || 'Create ahead', 'create_ahead_days',
			item.create_ahead_days ?? 14, 'number', {min:0,max:3650}),
		field(labels.cbWorkRecurringDueOffset || 'Due offset', 'due_offset_days',
			item.due_offset_days ?? 0, 'number', {min:0,max:3650}),
		priority, field(root.dataset.cbWorkM2Estimate || 'Estimated minutes', 'estimated_minutes',
			item.estimated_minutes ?? 0, 'number', {min:0,max:2147483647}),
		selection(doc, root.dataset.cbWorkM2Billing || 'Billing', 'billing_disposition',
			item.billing_disposition || '', [
				{id:'', title:root.dataset.cbWorkM2Unclassified || 'Not classified'},
				{id:'hourly',title:root.dataset.cbWorkM2Hourly || 'Hourly'},
				{id:'fixed',title:root.dataset.cbWorkM2Fixed || 'Fixed'},
				{id:'included',title:root.dataset.cbWorkM2Included || 'Included'},
				{id:'non_billable',title:root.dataset.cbWorkM2NonBillable || 'Non-billable'},
			]));
	advanced.append(advancedGrid);
	main.append(advanced);
	const preview = el(doc, 'div', 'cb-work-recurring__preview');
	preview.setAttribute('aria-live', 'polite');
	preview.setAttribute('aria-atomic', 'true');
	const previewButton = el(doc, 'button', 'button cb-work-recurring__preview-button',
		labels.cbWorkRecurringPreview || 'Rule Preview');
	previewButton.type = 'button';
	previewButton.dataset.cbWorkRecurringPreview = '1';
	const previewCard = section(doc, labels.cbWorkRecurringUpcoming || 'Upcoming occurrences (preview)', preview);
	previewCard.classList.add('cb-work-recurring__preview-card');
	previewCard.append(previewButton, el(doc, 'p', 'description',
		labels.cbWorkRecurringPreviewHint || 'Dates are a preview only while this rule is inactive.'));
	aside.append(previewCard);

	layout.append(main, aside);
	form.append(layout);
	capture();
	form.append(notice); body.append(form);
	return {body, form, notice, original, preview, previewButton};
}

export function mountRecurringWorkspace(root, browser, refresh) {
	const panel = root?.querySelector?.('[data-cb-work-uwe-panel="recurring"]');
	if (!panel || panel.dataset.cbWorkRecurringMounted === '1') return;
	panel.dataset.cbWorkRecurringMounted = '1';
	const base = panel.dataset.cbWorkUweEndpoint;
	const nonce = root.dataset.cbWorkUweNonce;
	if (!base || !nonce || typeof browser.fetch !== 'function') return;
	const doc = root.ownerDocument;
	let dialogActive = false;
	let pending = false;
	const urlFor = (suffix) => base.replace(/\/$/, '') + suffix;
	const errorBox = el(doc, 'p', 'cb-work-recurring__workspace-error');
	errorBox.setAttribute('role', 'alert');
	errorBox.hidden = true;
	panel.querySelector('[data-cb-work-uwe-feedback]')?.after(errorBox);

	async function request(path, method = 'GET', body = null) {
		const response = await browser.fetch(urlFor(path), {
			method, credentials: 'same-origin', headers: {
				'X-WP-Nonce': nonce,
				...(body ? {'Content-Type':'application/json'} : {}),
			},
			...(body ? {body: JSON.stringify(body)} : {}),
		});
		const json = await response.json().catch(() => null);
		if (!response.ok || !json || typeof json !== 'object') {
			const error = new Error(json?.message || 'Recurring Work request failed.');
			error.status = response.status;
			throw error;
		}
		return json;
	}
	function showError(message) {
		errorBox.textContent = message;
		errorBox.hidden = false;
	}
	async function open(id, quick = false, fallback = null) {
		if (dialogActive || !browser.cbCore?.modal?.show) return;
		dialogActive = true;
		let item;
		let options;
		try {
			if (id > 0) {
				item = await request('/rules/' + encodeURIComponent(String(id)));
			}
			if (!quick) {
				options = await request('/options?rule_id=' + encodeURIComponent(String(id)));
			}
		} catch (error) {
			dialogActive = false;
			showError(error.message);
			// Preserve the native editor as an explicit fallback, but never
			// navigate automatically from a failed REST read.
			return;
		}
		item ||= {id:0,title:'',priority:'normal',work_context:'internal'};
		options ||= {};
		const view = formView(doc, root, item, options, quick, !!browser.cbCore?.objectPicker?.init);
		const {body, form, notice, original, preview, previewButton} = view;
		const inform = (message) => {
			notice.textContent = message; notice.hidden = false;
		};
		let previewTimer = null;
		let previewVersion = 0;
		let previewClosed = false;
		const stopPreviewTimer = () => {
			if (previewTimer !== null) browser.clearTimeout?.(previewTimer);
			previewTimer = null;
		};
		async function loadPreview() {
			if (!preview || previewClosed || pending) return;
			const ticket = ++previewVersion;
			const fields = schedulePreviewFields(form);
			if (!fields) {
				previewButton.disabled = false;
				preview.setAttribute('aria-busy', 'false');
				preview.replaceChildren(el(doc, 'p', 'description',
					panel.dataset.cbWorkRecurringPreviewInvalid || 'Check the schedule fields.'));
				return;
			}
			previewButton.disabled = true;
			preview.setAttribute('aria-busy', 'true');
			preview.replaceChildren(el(doc, 'p', 'description',
				panel.dataset.cbWorkRecurringPreviewLoading || 'Building server preview…'));
			try {
				const data = await request('/preview', 'POST', {rule_id: id, ...fields});
				if (previewClosed || ticket !== previewVersion) return;
				preview.replaceChildren();
				const dates = el(doc, 'ul', 'cb-work-recurring__dates');
				if (!Array.isArray(data.dates)) throw new TypeError('Invalid recurrence preview');
				for (const value of data.dates) {
					if (typeof value?.label !== 'string') throw new TypeError('Invalid recurrence preview');
					dates.append(el(doc, 'li', '', value.label));
				}
				if (data.dates.length) preview.append(dates);
				else preview.append(el(doc, 'p', 'description',
					panel.dataset.cbWorkRecurringPreviewEmpty || 'not scheduled yet'));
				notice.hidden = true;
			} catch (error) {
				if (previewClosed || ticket !== previewVersion) return;
				preview.replaceChildren(el(doc, 'p', 'cb-work-recurring__message',
					error.status === 409 ? panel.dataset.cbWorkRecurringStale : error.message));
			} finally {
				if (!previewClosed && ticket === previewVersion) {
					previewButton.disabled = false;
					preview.setAttribute('aria-busy', 'false');
				}
			}
		}
		function schedulePreview() {
			if (previewClosed) return;
			++previewVersion; // stale response is invalid immediately on input.
			stopPreviewTimer();
			previewTimer = browser.setTimeout?.(() => {
				previewTimer = null;
				void loadPreview();
			}, 350) ?? null;
		}
		if (previewButton) {
			previewButton.addEventListener('click', () => {
				stopPreviewTimer();
				void loadPreview();
			});
			for (const name of ['input', 'change']) form.addEventListener(name, (event) => {
				if (['frequency','interval_count','start_on','end_on'].includes(event.target?.name)
					|| (event.type === 'change' && event.target?.type === 'checkbox')) schedulePreview();
			});
		}

		try {
			const promise = browser.cbCore.modal.show({
				title: quick ? panel.dataset.cbWorkRecurringQuick : id ? panel.dataset.cbWorkRecurringEditLabel : panel.dataset.cbWorkRecurringAddLabel,
				body, size: quick ? 'default' : 'workspace', expandable: !quick,
				initialFocus: '[name="title"]',
				confirmLabel: root.dataset.cbWorkM2Save || 'Save',
				cancelLabel: root.dataset.cbWorkM2Cancel || 'Cancel',
				onConfirm: async () => {
					if (pending) return false;
					const fields = readChanges(form, id > 0 ? original : null, quick);
					if (!fields) { inform('Check the highlighted fields.'); return false; }
					pending = true;
					notice.hidden = true;
					try {
						if (!id) await request('/rules', 'POST', {fields});
						else if (Object.keys(fields).length) await request('/rules/' + id, 'PATCH', {
							revision: item.revision, fields,
						});
						return true;
					} catch (error) {
						inform(error.status === 409 ? panel.dataset.cbWorkRecurringStale
							: error.status === 403 ? (root.dataset.cbWorkM2Denied || 'Permission denied.')
							: error.message);
						return false;
					} finally { pending = false; }
				},
			});
			if (browser.cbCore?.objectPicker?.init) {
				browser.setTimeout?.(() => browser.cbCore.objectPicker.init(body), 0);
			}
			if (previewButton) {
				previewTimer = browser.setTimeout?.(() => {
					previewTimer = null;
					void loadPreview();
				}, 0) ?? null;
			}
			const saved = await promise;
			if (saved === true) void refresh('recurring');
		} finally {
			previewClosed = true;
			++previewVersion;
			stopPreviewTimer();
			dialogActive = false;
		}
	}

	async function lifecycle(id, revision, action, trigger) {
		if (dialogActive || pending || !browser.cbCore?.modal?.show) return;
		if (action === 'quick') { await open(id, true); return; }
		if (['trash','delete_permanently'].includes(action)) {
			dialogActive = true;
			let confirmed = false;
			try {
				confirmed = await browser.cbCore.modal.show({
					title: action === 'trash' ? panel.dataset.cbWorkRecurringTrash : panel.dataset.cbWorkRecurringDelete,
					body: action === 'trash' ? panel.dataset.cbWorkRecurringTrashConfirm
						: panel.dataset.cbWorkRecurringDeleteConfirm,
					confirmLabel: action === 'trash' ? panel.dataset.cbWorkRecurringTrash : panel.dataset.cbWorkRecurringDelete,
					confirmVariant: 'danger',
					...(action === 'delete_permanently' ? {
						confirmCheck: {label:panel.dataset.cbWorkRecurringDeleteAck},
					} : {}),
				});
			} finally { dialogActive = false; }
			if (!confirmed) return;
		}
		pending = true;
		if (trigger) trigger.disabled = true;
		errorBox.hidden = true;
		try {
			await request('/rules/' + id + '/lifecycle', 'POST', {
				revision, action, ...(action === 'delete_permanently' ? {confirmed:true} : {}),
			});
			await refresh('recurring');
		} catch (error) {
			showError(error.status === 409 ? 'The rule changed or this action is not allowed. Refresh the list and try again.' : error.message);
		} finally {
			pending = false;
			if (trigger) trigger.disabled = false;
		}
	}
	root.addEventListener('click', (event) => {
		if (event.defaultPrevented || event.button !== 0 || event.ctrlKey ||
			event.metaKey || event.altKey || event.shiftKey) return;
		const target = event.target?.closest?.(
			'[data-cb-work-recurring-add], [data-cb-work-recurring-open], [data-cb-work-recurring-action], [data-cb-work-recurring-view]'
		);
		if (!target || !panel.contains(target) || !browser.cbCore?.modal?.show) return;
		if (target.hasAttribute('data-cb-work-recurring-view')) {
			event.preventDefault();
			const url = new URL(browser.location.href);
			if (url.searchParams.get('recurring_view') === 'trash') url.searchParams.delete('recurring_view');
			else url.searchParams.set('recurring_view', 'trash');
			url.searchParams.delete('uwe_page');
			browser.history.pushState({cbWorkRecurringView:true}, '', url.pathname + url.search + url.hash);
			errorBox.hidden = true;
			void refresh('recurring');
			return;
		}
		if (dialogActive || pending) {event.preventDefault(); return;}
		if (target.hasAttribute('data-cb-work-recurring-add')) {
			event.preventDefault(); void open(0); return;
		}
		if (target.hasAttribute('data-cb-work-recurring-open')) {
			event.preventDefault(); void open(Number(target.dataset.cbWorkRecurringOpen), false, target); return;
		}
		if (target.hasAttribute('data-cb-work-recurring-action')) {
			event.preventDefault();
			void lifecycle(Number(target.dataset.ruleId), Number(target.dataset.revision),
				target.dataset.cbWorkRecurringAction, target);
		}
	});
}

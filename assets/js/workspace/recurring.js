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

function formView(doc, root, item, options, quick = false, pickerAvailable = false) {
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
	const basics = el(doc, 'div', 'cb-work-recurring__grid');
	basics.append(title, field(root.dataset.cbWorkM2DescriptionLabel || 'Description',
		'description', item.description || '', 'textarea', {maxLength:65535}));
	const context = selection(doc, root.dataset.cbWorkM2Context || 'Work context', 'work_context',
		item.work_context || 'internal', [
			{id:'internal',title:root.dataset.cbWorkM2Internal || 'Internal'},
			{id:'customer',title:root.dataset.cbWorkM2CustomerWork || 'Customer'},
		]);
	const project = selection(doc, root.dataset.cbWorkM2Project || 'Project', 'project_id',
		item.project_id || 0, options.projects || [], root.dataset.cbWorkM2NoProject || 'No Project');
	basics.append(context, project);
	basics.append(selection(doc, root.dataset.cbWorkM2Service || 'Service', 'service_id',
		item.service_id || 0, options.services || [], root.dataset.cbWorkM2NoService || 'No service'));
	basics.append(selection(doc, root.dataset.cbWorkM2Type || 'Work Type', 'work_type_id',
		item.work_type_id || 0, options.work_types || [], root.dataset.cbWorkM2NoType || 'No type'));
	form.append(section(doc, 'Work details', basics));
	if (pickerAvailable && options.customer_enabled) {
		const customer = picker(doc, root.dataset.cbWorkM2Customer || 'Customer', 'customer_object_id',
			item.customer ? [item.customer] : [], options.picker_nonce, 'cb_work_search_customers', false);
		basics.append(customer.wrapper);
		const sync = () => {
			const inherited = Number(project.querySelector('select').value) > 0;
			const enabled = !inherited && context.querySelector('select').value === 'customer';
			context.querySelector('select').disabled = inherited;
			customer.wrapper.hidden = !enabled;
			customer.input.disabled = !enabled;
		};
		form.addEventListener('change', sync);
		sync();
	}
	const scheduling = el(doc, 'div', 'cb-work-recurring__grid');
	const frequency = selection(doc, 'Frequency', 'frequency', item.frequency || 'weekly',
		['daily','weekly','monthly','yearly'].map((x) => ({id:x,title:x})));
	scheduling.append(frequency,
		field('Every', 'interval_count', item.interval_count ?? 1, 'number', {required:true,min:1,max:999}),
		field('Start date', 'start_on', item.start_on || options.today, 'date', {required:true}),
		field('End date', 'end_on', item.end_on || '', 'date'),
		field('Create ahead (days)', 'create_ahead_days', item.create_ahead_days ?? 14, 'number', {min:0,max:3650}),
		field('Due offset (days)', 'due_offset_days', item.due_offset_days ?? 0, 'number', {min:0,max:3650}));
	if (item.schedule_locked) {
		for (const key of ['frequency','interval_count','start_on','end_on']) {
			scheduling.querySelector('[name="' + key + '"]').disabled = true;
		}
		scheduling.append(el(doc, 'p', 'description', 'Schedule is locked because Work Items have already been generated.'));
	}
	const preview = el(doc, 'div', 'cb-work-recurring__preview');
	preview.setAttribute('aria-live', 'polite');
	const previewButton = el(doc, 'button', 'button', 'Preview dates');
	previewButton.type = 'button';
	previewButton.dataset.cbWorkRecurringPreview = '1';
	const scheduleBlock = section(doc, 'Planning', scheduling);
	scheduleBlock.append(previewButton, preview);
	form.append(scheduleBlock);
	if (pickerAvailable) {
		const assignees = picker(doc, root.dataset.cbWorkM2Assignees || 'Assignees',
			'assigned_user_ids', item.assignees || [], options.picker_nonce, 'cb_work_search_users', true);
		const box = el(doc, 'div', 'cb-work-recurring__grid');
		box.append(assignees.wrapper);
		form.append(section(doc, 'Assignments', box));
	}
	const advanced = el(doc, 'details', 'cb-work-recurring__advanced');
	advanced.append(el(doc, 'summary', '', 'Advanced'));
	const advancedGrid = el(doc, 'div', 'cb-work-recurring__grid');
	advancedGrid.append(priority, field(root.dataset.cbWorkM2Estimate || 'Estimated minutes', 'estimated_minutes',
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
	form.append(advanced);
	const hint = el(doc, 'p', 'description', 'Closing this dialog without saving discards unsaved edits.');
	form.append(hint);
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
		if (previewButton) previewButton.addEventListener('click', async () => {
			if (pending) return;
			const fields = readChanges(form, null);
			if (!fields) { inform('Check the schedule fields.'); return; }
			previewButton.disabled = true;
			try {
				const data = await request('/preview', 'POST', {
					rule_id: id,
					frequency: fields.frequency ?? item.frequency,
					interval_count: fields.interval_count ?? item.interval_count,
					start_on: fields.start_on ?? item.start_on,
					end_on: fields.end_on ?? item.end_on ?? null,
				});
				preview.replaceChildren();
				const dates = el(doc, 'ul', 'cb-work-recurring__dates');
				for (const value of data.dates || []) dates.append(el(doc, 'li', '', value.label));
				preview.append(dates);
				notice.hidden = true;
			} catch (error) { inform(error.status === 409 ? 'The saved schedule is locked.' : error.message); }
			finally { previewButton.disabled = false; }
		});
		try {
			const promise = browser.cbCore.modal.show({
				title: (id ? (quick ? 'Quick Edit' : 'Edit recurring Work') : 'Add recurring Work'),
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
						inform(error.status === 409 ? 'This rule changed or its schedule is locked. Reopen it to review the latest version.'
							: error.status === 403 ? 'You no longer have permission to save this rule.'
							: error.message);
						return false;
					} finally { pending = false; }
				},
			});
			if (browser.cbCore?.objectPicker?.init) {
				browser.setTimeout?.(() => browser.cbCore.objectPicker.init(body), 0);
			}
			const saved = await promise;
			if (saved === true) void refresh('recurring');
		} finally {
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
					title: action === 'trash' ? 'Move rule to Trash?' : 'Delete rule permanently?',
					body: action === 'trash' ? 'The rule will be paused and its history retained.'
						: 'Permanent deletion is allowed only when there is no generation history.',
					confirmLabel: action === 'trash' ? 'Move to Trash' : 'Delete permanently',
					confirmVariant: 'danger',
					...(action === 'delete_permanently' ? {
						confirmCheck: {label:'I understand that this cannot be undone.'},
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

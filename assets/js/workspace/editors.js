import '@cb-core/modal';
import '@cb-core/object-picker';

/**
 * M2 coherent editor slice for Work Items and Projects.
 *
 * All writes go through one guarded Work endpoint backed by the existing
 * public action contracts. The modal itself belongs to Base; no custom
 * dialog, focus trap, retry engine, or second persistence mechanism.
 */
const SUPPORTED = new Set(['work-item', 'project', 'service']);

export function nativeWorkItemTarget(href, baseHref) {
	const url = new URL(href, baseHref);
	const base = new URL(baseHref);
	if (url.origin !== base.origin) return null;
	const path = url.pathname.split('/').pop();
	if (path === 'post-new.php' && url.searchParams.get('post_type') === 'cb_work_item') {
		return { entity: 'work-item', id: 0 };
	}
	if (path === 'post.php' && url.searchParams.get('action') === 'edit') {
		const id = url.searchParams.get('post');
		if (id && /^[1-9]\d*$/.test(id) && Number.isSafeInteger(Number(id))) {
			return { entity: 'work-item', id: Number(id) };
		}
	}
	return null;
}

function field(document, label, key, value, type = 'text') {
	const wrapper = document.createElement('label');
	wrapper.className = 'cb-work-m2__field';
	const name = document.createElement('span');
	name.textContent = label;
	const input = document.createElement('input');
	input.name = key;
	input.type = type;
	input.value = String(value ?? '');
	if (key === 'title') {
		input.required = true;
		input.maxLength = 200;
	}
	if (key === 'estimated_minutes') {
		input.min = '0';
		input.max = '2147483647';
	}
	wrapper.append(name, input);
	return wrapper;
}

function select(document, label, key, selected, options, emptyLabel) {
	const wrapper = document.createElement('label');
	wrapper.className = 'cb-work-m2__field';
	const name = document.createElement('span');
	name.textContent = label;
	const control = document.createElement('select');
	control.name = key;
	if (emptyLabel) {
		const empty = document.createElement('option');
		empty.value = '0';
		empty.textContent = emptyLabel;
		control.appendChild(empty);
	}
	for (const option of options) {
		const item = document.createElement('option');
		item.value = String(option.id);
		item.textContent = String(option.title);
		control.appendChild(item);
	}
	control.value = String(selected ?? (emptyLabel ? 0 : ''));
	wrapper.append(name, control);
	return wrapper;
}

function objectPicker(document, root, label, name, multiple, config, action, placeholder, emptyMessage) {
	const wrap = document.createElement('div');
	wrap.className = 'cb-work-m2__field cb-work-m2__field--picker';
	const caption = document.createElement('span');
	caption.className = 'cb-work-m2__field-caption';
	caption.textContent = label;
	const picker = document.createElement('div');
	picker.className = 'cb-core-object-picker';
	picker.setAttribute('data-cb-core-object-picker', '');
	picker.dataset.searchAction = action;
	picker.dataset.searchNonce = String(config.nonce || '');
	picker.dataset.searchContext = '{}';
	picker.dataset.selected = JSON.stringify(config.selected || []);
	picker.dataset.multiple = multiple ? '1' : '0';
	picker.dataset.emptyMessage = emptyMessage;
	const input = document.createElement('input');
	input.type = 'text';
	input.name = name;
	input.className = 'regular-text code cb-core-object-picker__input';
	input.setAttribute('data-cb-core-object-picker-input', '');
	input.value = (config.selected || []).map((item) => String(item.id)).join(',');
	const enhanced = document.createElement('div');
	enhanced.className = 'cb-core-object-picker__enhanced';
	enhanced.hidden = true;
	enhanced.setAttribute('data-cb-core-object-picker-enhanced', '');
	const selected = document.createElement('div');
	selected.className = 'cb-core-object-picker__selected';
	selected.setAttribute('data-cb-core-object-picker-selected', '');
	const searchLabel = document.createElement('label');
	searchLabel.className = 'screen-reader-text';
	searchLabel.textContent = label;
	const search = document.createElement('input');
	search.type = 'search';
	search.className = 'regular-text cb-core-object-picker__search';
	search.placeholder = placeholder;
	search.autocomplete = 'off';
	search.setAttribute('data-cb-core-object-picker-search', '');
	const searchId = 'cb-work-m2-search-' + name + '-' + String(root.dataset.cbWorkM2Instance || '1');
	search.id = searchId;
	searchLabel.htmlFor = searchId;
	const results = document.createElement('div');
	results.className = 'cb-core-object-picker__results';
	results.hidden = true;
	results.setAttribute('data-cb-core-object-picker-results', '');
	enhanced.append(selected, searchLabel, search, results);
	picker.append(input, enhanced);
	wrap.append(caption, picker);
	return { wrap, input };
}

function buildEditor(document, root, result, pickerAvailable) {
	const item = result.item;
	const entity = result.entity;
	const body = document.createElement('div');
	body.className = 'cb-work-m2';
	const form = document.createElement('div');
	form.className = 'cb-work-m2__form';
	const explanation = document.createElement('p');
	explanation.className = 'description';
	explanation.textContent = root.dataset.cbWorkM2Description || '';
	form.appendChild(explanation);
	form.appendChild(field(document, root.dataset.cbWorkM2Title || 'Title', 'title', item.title));
	const grid = document.createElement('div');
	grid.className = 'cb-work-m2__grid';
	if (entity === 'work-item') {
		grid.appendChild(select(document, root.dataset.cbWorkM2Project || 'Project', 'project_id',
			item.project_id, result.projects || [], root.dataset.cbWorkM2NoProject || 'No Project'));
		grid.appendChild(select(document, root.dataset.cbWorkM2Service || 'Service', 'service_id',
			item.service_id, result.services || [], root.dataset.cbWorkM2NoService || 'No service'));
		grid.appendChild(select(document, root.dataset.cbWorkM2Type || 'Work Type', 'work_type_id',
			item.work_type_id, result.work_types || [], root.dataset.cbWorkM2NoType || 'No Work Type'));
		grid.appendChild(select(document, root.dataset.cbWorkM2Priority || 'Priority', 'priority',
			item.priority, ['low', 'normal', 'high', 'urgent'].map((value) => ({
				id: value, title: root.dataset['cbWorkM2Priority' + value[0].toUpperCase() + value.slice(1)] || value,
			})), null));
		grid.appendChild(select(document, root.dataset.cbWorkM2Billing || 'Billing', 'billing_disposition',
			item.billing_disposition ?? 'non_billable', [
				{ id: '', title: root.dataset.cbWorkM2Unclassified || 'Not classified' },
				{ id: 'hourly', title: root.dataset.cbWorkM2Hourly || 'Hourly' },
				{ id: 'fixed', title: root.dataset.cbWorkM2Fixed || 'Fixed price' },
				{ id: 'included', title: root.dataset.cbWorkM2Included || 'Included' },
				{ id: 'non_billable', title: root.dataset.cbWorkM2NonBillable || 'Non-billable' },
			], null));
		grid.appendChild(field(document, root.dataset.cbWorkM2Estimate || 'Estimate (minutes)',
			'estimated_minutes', item.estimated_minutes, 'number'));
		grid.appendChild(field(document, root.dataset.cbWorkM2Scheduled || 'Scheduled',
			'scheduled_on', item.scheduled_on, 'date'));
	} else if (entity === 'project') {
		grid.appendChild(field(document, root.dataset.cbWorkM2Start || 'Start',
			'starts_on', item.starts_on, 'date'));
		grid.appendChild(field(document, root.dataset.cbWorkM2Due || 'Due',
			'due_on', item.due_on, 'date'));
	} else if (entity === 'service') {
		const price = item.pricing || {};
		grid.appendChild(select(document, root.dataset.cbWorkM2PricingModel || 'Pricing model',
			'pricing_model', price.pricing_model || 'fixed', [
				{ id: 'hourly', title: root.dataset.cbWorkM2Hourly || 'Hourly' },
				{ id: 'fixed', title: root.dataset.cbWorkM2Fixed || 'Fixed price' },
				{ id: 'recurring', title: root.dataset.cbWorkM2Recurring || 'Recurring' },
			], null));
		const periods = select(document, root.dataset.cbWorkM2RecurringPeriod || 'Recurring period',
			'recurring_period', price.recurring_period || 'monthly', [
				{ id: 'weekly', title: root.dataset.cbWorkM2Weekly || 'Weekly' },
				{ id: 'monthly', title: root.dataset.cbWorkM2Monthly || 'Monthly' },
				{ id: 'quarterly', title: root.dataset.cbWorkM2Quarterly || 'Quarterly' },
				{ id: 'yearly', title: root.dataset.cbWorkM2Yearly || 'Yearly' },
			], null);
		grid.appendChild(periods);
		grid.appendChild(field(document, root.dataset.cbWorkM2Price || 'Standard price',
			'amount', item.amount_display || ''));
		grid.appendChild(field(document, root.dataset.cbWorkM2Currency || 'Currency',
			'currency', price.currency || 'EUR'));
		grid.appendChild(select(document, root.dataset.cbWorkM2TaxMode || 'VAT',
			'tax_mode', price.tax_mode || 'exclusive', [
				{ id: 'exclusive', title: root.dataset.cbWorkM2Exclusive || 'Excluding VAT' },
				{ id: 'inclusive', title: root.dataset.cbWorkM2Inclusive || 'Including VAT' },
				{ id: 'exempt', title: root.dataset.cbWorkM2Exempt || 'VAT exempt / no VAT' },
			], null));
		const tax = select(document, root.dataset.cbWorkM2TaxRate || 'VAT rate',
			'tax_rate_id', price.tax_rate_id || 0, result.tax_rates || [],
			root.dataset.cbWorkM2NoRate || 'No VAT rate');
		grid.appendChild(tax);
		const syncServiceFields = () => {
			const recurring = form.querySelector('[name="pricing_model"]').value === 'recurring';
			periods.hidden = !recurring;
			periods.querySelector('select').disabled = !recurring;
			const taxable = form.querySelector('[name="tax_mode"]').value !== 'exempt';
			tax.hidden = !taxable;
			tax.querySelector('select').disabled = !taxable;
		};
		form.addEventListener('change', syncServiceFields);
		// Run after the controls enter the form DOM.
		form.appendChild(grid);
		syncServiceFields();
	} else {
		form.appendChild(grid);
	}
	// Work Item and Project grids previously existed only as detached nodes.
	// Always attach the editable controls before opening the Base dialog.
	if (entity === 'work-item' || entity === 'project') {
		form.appendChild(grid);
	}
	const descriptionInfo = result.description_editor || { mode: 'advanced', value: '' };
	if (descriptionInfo.mode === 'plain') {
		const description = document.createElement('label');
		description.className = 'cb-work-m2__field cb-work-m2__description';
		const caption = document.createElement('span');
		caption.textContent = root.dataset.cbWorkM2DescriptionLabel || 'Description';
		const textarea = document.createElement('textarea');
		textarea.name = 'description';
		textarea.rows = 6;
		textarea.maxLength = 16000;
		textarea.value = String(descriptionInfo.value ?? '');
		textarea.dataset.cbWorkM2Initial = textarea.value;
		const hint = document.createElement('small');
		hint.className = 'description';
		hint.textContent = root.dataset.cbWorkM2PlainDescription || 'Plain text only. Use the advanced editor for formatting.';
		description.append(caption, textarea, hint);
		form.appendChild(description);
	} else {
		const richNotice = document.createElement('p');
		richNotice.className = 'description cb-work-m2__rich-notice';
		richNotice.textContent = root.dataset.cbWorkM2RichDescription
			|| 'This record contains rich content. Open the advanced editor to change its description.';
		form.appendChild(richNotice);
	}
	// Work-owned customer identity and assignments use the existing Base
	// Object Picker plus canonical Work search actions. Never expose arbitrary
	// provider/type/id text inputs as a substitute for the CRM resolver.
	if (pickerAvailable && (entity === 'project' || entity === 'work-item')) {
		const configs = result.pickers || {};
		const customer = configs.customer || {};
		const projectControl = entity === 'work-item' ? form.querySelector('[name="project_id"]') : null;
		if (customer.enabled && (Number(item.id) === 0 || item.work_context === 'internal' || item.work_context === 'customer')) {
			const context = select(document, root.dataset.cbWorkM2Context || 'Work context',
				'work_context', item.work_context || 'internal', [
					{ id: 'internal', title: root.dataset.cbWorkM2Internal || 'Internal' },
					{ id: 'customer', title: root.dataset.cbWorkM2CustomerWork || 'Customer' },
				], null);
			const identity = objectPicker(document, root, root.dataset.cbWorkM2Customer || 'Customer',
				'customer', false, customer, 'cb_work_search_customers',
				root.dataset.cbWorkM2CustomerSearch || 'Search contacts and organizations…',
				root.dataset.cbWorkM2CustomerEmpty || 'No matching CRM customers found.');
			form.append(context, identity.wrap);
			const syncCustomer = () => {
				const inherited = projectControl && Number(projectControl.value) > 0;
				const selectedContext = context.querySelector('select');
				selectedContext.disabled = !!inherited;
				const active = !inherited && selectedContext.value === 'customer';
				identity.wrap.hidden = !active;
				identity.input.disabled = !active;
			};
			form.addEventListener('change', syncCustomer);
			syncCustomer();
		}
		if (entity === 'work-item' && configs.assignees) {
			const assignees = objectPicker(document, root,
				root.dataset.cbWorkM2Assignees || 'Assignees',
				'assigned_user_ids', true, configs.assignees, 'cb_work_search_users',
				root.dataset.cbWorkM2UserSearch || 'Search WordPress users…',
				root.dataset.cbWorkM2UserEmpty || 'No matching users found.');
			form.appendChild(assignees.wrap);
		}
	}
	if (Number(item.id) > 0) {
		const advanced = document.createElement('a');
		advanced.className = 'cb-work-m2__advanced';
		advanced.href = new URL('post.php?post=' + encodeURIComponent(String(item.id)) + '&action=edit',
			root.dataset.cbWorkM2AdminUrl).href;
		advanced.target = '_blank';
		advanced.rel = 'noopener noreferrer';
		advanced.textContent = root.dataset.cbWorkM2Advanced || 'Open advanced editor';
		form.appendChild(advanced);
	}
	const message = document.createElement('p');
	message.className = 'cb-work-m2__message';
	message.setAttribute('role', 'alert');
	message.hidden = true;
	form.appendChild(message);
	body.appendChild(form);
	return { body, form, message };
}

function readFields(form) {
	const controls = [...form.querySelectorAll('input[name], select[name], textarea[name]')]
		.filter((control) => !control.disabled && !(
			control.name === 'description' && control.value === control.dataset.cbWorkM2Initial
		));
	for (const control of controls) {
		if (typeof control.reportValidity === 'function' && !control.reportValidity()) return null;
	}
	return Object.fromEntries(controls.map((input) => [input.name, input.value]));
}

async function request(root, browser, parameters, body = null) {
	const endpoint = root.dataset.cbWorkM2Endpoint;
	const nonce = root.dataset.cbWorkM2Nonce;
	const url = new URL(endpoint, browser.location.href);
	const fetcher = browser.fetch.bind(browser);
	let options;
	if (body) {
		const payload = new FormData();
		payload.set('action', 'cb_work_uwe_editor');
		payload.set('nonce', nonce);
		for (const [key, value] of Object.entries(parameters)) payload.set(key, String(value));
		for (const [key, value] of Object.entries(body.fields)) {
			payload.set('fields[' + key + ']', String(value));
		}
		payload.set('expected_updated_at', String(body.expected_updated_at || ''));
		payload.set('expected_version', String(body.expected_version || ''));
		options = { method: 'POST', credentials: 'same-origin', body: payload };
	} else {
		url.search = new URLSearchParams({
			action: 'cb_work_uwe_editor', nonce,
			entity: String(parameters.entity), id: String(parameters.id),
		}).toString();
		options = { method: 'GET', credentials: 'same-origin' };
	}
	const response = await fetcher(url.href, options);
	const json = await response.json().catch(() => null);
	if (!response.ok || json?.success !== true || !json?.data?.item) {
		const err = new Error(String(json?.data?.code || 'work_editor_unavailable'));
		err.status = response.status;
		throw err;
	}
	return json.data;
}

export function mountUnifiedEditors(root, browser, refresh) {
	if (!root || root.dataset.cbWorkM2Mounted === '1') return;
	if (!root.dataset.cbWorkM2Endpoint || !root.dataset.cbWorkM2Nonce) return;
	root.dataset.cbWorkM2Mounted = '1';
	const document = root.ownerDocument;
	let opening = false;

	async function open(entity, id, trigger) {
		if (!SUPPORTED.has(entity) || opening) return;
		const modal = browser.cbCore?.modal;
		if (!modal?.show) return;
		opening = true;
		let data;
		try {
			data = await request(root, browser, { entity, id });
		} catch (_) {
			opening = false;
			// Fail closed: leave the original WordPress editor link accessible.
			if (trigger?.href) browser.location.assign(trigger.href);
			return;
		}
		opening = false;
		const { body, form, message } = buildEditor(document, root, data,
			!!browser.cbCore?.objectPicker?.init);
		const ownerSection = entity === 'project' ? 'projects' : entity === 'service' ? 'services' : 'work-items';
		const owner = root.querySelector('[data-cb-work-uwe-panel="' + ownerSection + '"]');
		if (owner?.id) {
			body.setAttribute('data-cb-work-uwe-modal', '');
			body.dataset.cbWorkUweOwner = owner.id;
		}
		const noun = entity === 'project'
			? (root.dataset.cbWorkM2Project || 'Project')
			: entity === 'service' ? (root.dataset.cbWorkM2Service || 'Service')
			: (root.dataset.cbWorkM2WorkItem || 'Work Item');
		const title = (id > 0 ? root.dataset.cbWorkM2Edit || 'Edit' : root.dataset.cbWorkM2Add || 'Add') + ' ' + noun;
		const modalPromise = modal.show({
			title, body, size: 'workspace', expandable: true,
			confirmLabel: root.dataset.cbWorkM2Save || 'Save',
			cancelLabel: root.dataset.cbWorkM2Cancel || 'Cancel',
			initialFocus: '[name="title"]',
			onConfirm: async () => {
				const fields = readFields(form);
				if (!fields) return false;
				message.hidden = true;
				try {
					await request(root, browser, { entity, id }, {
						fields, expected_updated_at: data.item.updated_at, expected_version: data.item.version,
					});
					return true;
				} catch (error) {
					if (error.status === 401 || error.status === 403) {
						const text = root.dataset.cbWorkM2Denied || 'Your session no longer permits this edit. Close the editor.';
						form.replaceChildren();
						body.replaceChildren(message);
						message.textContent = text;
						message.hidden = false;
						return false;
					}
					message.textContent = error.status === 409
						? (root.dataset.cbWorkM2Stale || 'The record changed. Close and reopen it.')
						: (root.dataset.cbWorkM2Error || 'Could not save. Review the fields and try again.');
					message.hidden = false;
					return false;
				}
			},
		});
		// Modal body is attached by Base. Initialize Base's public picker API
		// on that dynamic subtree, never a second Work picker implementation.
		if (browser.cbCore?.objectPicker?.init) {
			browser.setTimeout(() => browser.cbCore.objectPicker.init(body), 0);
		}
		const result = await modalPromise;
		if (result === true) {
			void refresh(entity === 'project' ? 'projects' : entity === 'service' ? 'services' : 'work-items');
		}
	}


	async function postWorkType(action, nonce, data) {
		const payload = new FormData();
		payload.set('action', action);
		payload.set('_wpnonce', nonce);
		for (const [key, value] of Object.entries(data)) payload.set(key, String(value));
		const response = await browser.fetch(root.dataset.cbWorkM2Endpoint, {
			method: 'POST', body: payload, credentials: 'same-origin',
		});
		const json = await response.json().catch(() => null);
		if (!response.ok || json?.success !== true) {
			throw new Error(String(json?.data?.notice || 'work_type_update_invalid'));
		}
	}

	async function openWorkTypes() {
		const modal = browser.cbCore?.modal;
		if (!modal?.show || opening) return;
		opening = true;
		let data;
		try {
			data = await request(root, browser, { entity: 'work-type', id: 0 });
		} catch (_) {
			opening = false;
			return;
		}
		opening = false;
		const body = document.createElement('div');
		body.className = 'cb-work-m2 cb-work-m2--types';
		const owner = root.querySelector('[data-cb-work-uwe-panel="work-items"]');
		if (owner?.id) {
			body.setAttribute('data-cb-work-uwe-modal', '');
			body.dataset.cbWorkUweOwner = owner.id;
		}
		const intro = document.createElement('p');
		intro.className = 'description';
		intro.textContent = root.dataset.cbWorkM2TypesHelp || '';
		body.appendChild(intro);
		const list = document.createElement('ul');
		list.className = 'cb-work-m2__type-list';
		const feedback = document.createElement('p');
		feedback.setAttribute('role', 'status');
		feedback.setAttribute('aria-live', 'polite');
		feedback.hidden = true;
		let changed = false;
		for (const type of data.types || []) {
			const item = document.createElement('li');
			const name = document.createElement('span');
			name.textContent = String(type.label);
			const toggle = document.createElement('button');
			toggle.type = 'button';
			toggle.className = 'button button-small';
			toggle.textContent = type.active
				? (root.dataset.cbWorkM2Deactivate || 'Deactivate')
				: (root.dataset.cbWorkM2Activate || 'Activate');
			toggle.setAttribute('aria-label', toggle.textContent + ' ' + String(type.label));
			toggle.addEventListener('click', async () => {
				toggle.disabled = true;
				feedback.hidden = true;
				try {
					await postWorkType('cb_work_toggle_work_type', type.nonce, {
						work_type_id: type.id, active: type.active ? '0' : '1',
					});
					type.active = !type.active;
					changed = true;
					toggle.textContent = type.active
						? (root.dataset.cbWorkM2Deactivate || 'Deactivate')
						: (root.dataset.cbWorkM2Activate || 'Activate');
					toggle.setAttribute('aria-label', toggle.textContent + ' ' + String(type.label));
				} catch (_) {
					feedback.textContent = root.dataset.cbWorkM2Error || 'Could not save.';
					feedback.hidden = false;
				} finally {
					toggle.disabled = false;
				}
			});
			item.append(name, toggle);
			list.appendChild(item);
		}
		body.appendChild(list);
		const form = document.createElement('div');
		form.className = 'cb-work-m2__form';
		const label = field(document, root.dataset.cbWorkM2Name || 'Name', 'label', '');
		label.querySelector('input').required = true;
		const code = field(document, root.dataset.cbWorkM2Code || 'Code', 'code', '');
		code.querySelector('input').maxLength = 64;
		form.append(label, code);
		body.append(form, feedback);
		const result = await modal.show({
			title: root.dataset.cbWorkM2ManageTypes || 'Work Types',
			body,
			size: 'default',
			initialFocus: '[name="label"]',
			confirmLabel: root.dataset.cbWorkM2Create || 'Create',
			cancelLabel: root.dataset.cbWorkM2Close || 'Close',
			onConfirm: async () => {
				if (!label.querySelector('input').reportValidity()) return false;
				feedback.hidden = true;
				try {
					await postWorkType('cb_work_create_work_type', data.create_nonce, {
						'work_type[label]': label.querySelector('input').value,
						'work_type[code]': code.querySelector('input').value,
					});
					changed = true;
					return true;
				} catch (_) {
					feedback.textContent = root.dataset.cbWorkM2Error || 'Could not save.';
					feedback.hidden = false;
					return false;
				}
			},
		});
		if (changed || result === true) void refresh('work-items');
	}


	// Calendar's Base day dialog is intentionally detached from the Work
	// panel. Close it through Base's existing dismiss action before starting
	// an item edit; never stack two workspace dialogs or bypass Base focus.
	document.addEventListener('click', (event) => {
		if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.shiftKey
			|| event.altKey || event.metaKey) return;
		const link = event.target?.closest?.('a[href]');
		if (!link || link.target === '_blank' || link.hasAttribute('download')) return;
		const body = link.closest('.cb-work-day-modal[data-cb-work-uwe-modal]');
		const panel = root.querySelector('[data-cb-work-uwe-panel="work-items"]');
		if (!body || !panel?.id || body.dataset.cbWorkUweOwner !== panel.id) return;
		if (!link.closest('.cb-work-board__title, .cb-work-day-modal__closed')) return;
		const target = nativeWorkItemTarget(link.href, browser.location.href);
		if (!target || !browser.cbCore?.modal?.show) return;
		const dialog = body.closest('dialog.cb-work-calendar-modal');
		const dismiss = dialog?.querySelector('.cb-work-calendar-modal__close');
		if (!dismiss || !dialog.open || dismiss.disabled) return;
		event.preventDefault();
		event.stopPropagation();
		dialog.addEventListener('close', () => {
			// Base removes the dialog and restores focus in an animation frame.
			// Open the editor only after that cleanup, not inside the day modal.
			const schedule = typeof browser.requestAnimationFrame === 'function'
				? browser.requestAnimationFrame.bind(browser)
				: (callback) => browser.setTimeout(callback, 16);
			schedule(() => schedule(() => {
				if (root.isConnected && !body.isConnected) {
					void open(target.entity, target.id, link);
				}
			}));
		}, { once: true });
		dismiss.click();
	}, true);

	root.addEventListener('click', (event) => {
		if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.shiftKey
			|| event.altKey || event.metaKey) return;
		const typesTrigger = event.target?.closest?.('[data-cb-work-m2-types]');
		if (typesTrigger) {
			if (!browser.cbCore?.modal?.show) return;
			event.preventDefault();
			event.stopPropagation();
			void openWorkTypes();
			return;
		}
		let trigger = event.target?.closest?.('[data-cb-work-m2-add], [data-cb-work-m2-edit]');
		let entity, id;
		if (trigger) {
			entity = trigger.dataset.cbWorkM2Add || trigger.dataset.cbWorkM2Edit;
			id = Number(trigger.dataset.cbWorkM2Id || 0);
		} else {
			const link = event.target?.closest?.('a[href]');
			if (!link || link.target === '_blank' || link.hasAttribute('download')
				|| !link.closest('[data-cb-work-uwe-canonical]')) return;
			// Intercept only the canonical Work Item title links, not unrelated
			// Project, Calendar, filter, menu or administration destinations.
			if (!link.closest('.cb-work-item-cell__title, .cb-work-list-item__title, .cb-work-list-item__detail-actions, .cb-work-board__title')) return;
			const destination = nativeWorkItemTarget(link.href, browser.location.href);
			if (!destination) return;
			trigger = link;
			entity = destination.entity;
			id = destination.id;
		}
		if (!SUPPORTED.has(entity) || !Number.isSafeInteger(id) || id < 0) return;
		if (!browser.cbCore?.modal?.show) return;
		event.preventDefault();
		event.stopPropagation();
		void open(entity, id, trigger);
	}, true);
}

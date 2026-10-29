import '@cb-core/modal';

/**
 * M2 coherent editor slice for Work Items and Projects.
 *
 * All writes go through one guarded Work endpoint backed by the existing
 * public action contracts. The modal itself belongs to Base; no custom
 * dialog, focus trap, retry engine, or second persistence mechanism.
 */
const SUPPORTED = new Set(['work-item', 'project']);

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

function buildEditor(document, root, result) {
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
		grid.appendChild(field(document, root.dataset.cbWorkM2Estimate || 'Estimate (minutes)',
			'estimated_minutes', item.estimated_minutes, 'number'));
		grid.appendChild(field(document, root.dataset.cbWorkM2Scheduled || 'Scheduled',
			'scheduled_on', item.scheduled_on, 'date'));
	} else {
		grid.appendChild(field(document, root.dataset.cbWorkM2Start || 'Start',
			'starts_on', item.starts_on, 'date'));
	}
	grid.appendChild(field(document, root.dataset.cbWorkM2Due || 'Due', 'due_on', item.due_on, 'date'));
	form.appendChild(grid);
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
	const controls = [...form.querySelectorAll('input[name], select[name], textarea[name]')];
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
		const { body, form, message } = buildEditor(document, root, data);
		const owner = root.querySelector('[data-cb-work-uwe-panel="' + (entity === 'project' ? 'projects' : 'work-items') + '"]');
		if (owner?.id) {
			body.setAttribute('data-cb-work-uwe-modal', '');
			body.dataset.cbWorkUweOwner = owner.id;
		}
		const noun = entity === 'project'
			? (root.dataset.cbWorkM2Project || 'Project')
			: (root.dataset.cbWorkM2WorkItem || 'Work Item');
		const title = (id > 0 ? root.dataset.cbWorkM2Edit || 'Edit' : root.dataset.cbWorkM2Add || 'Add') + ' ' + noun;
		const result = await modal.show({
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
						fields, expected_updated_at: data.item.updated_at,
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
		if (result === true) {
			void refresh(entity === 'project' ? 'projects' : 'work-items');
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
			if (!link.closest('.cb-work-item-cell__title, .cb-work-list-item__title, .cb-work-board__title')) return;
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

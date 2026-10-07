import '@cb-core/modal';
import '@cb-core/reorder';

const policyFromBody = (root) => {
	const order = [...root.querySelectorAll('[data-cb-core-reorder-item]')]
		.map((item) => String(item.dataset.cbCoreReorderItem || ''))
		.filter(Boolean);
	const selected = root.querySelector('input[name="cb-work-default-view"]:checked');
	return {
		default_view: selected instanceof HTMLInputElement ? String(selected.value || '') : '',
		order,
	};
};

const applyDefaults = (root) => {
	const order = String(root.dataset.defaultOrder || '')
		.split(',')
		.map((value) => value.trim())
		.filter(Boolean);
	const list = root.querySelector('[data-cb-core-reorder-list="views"]');
	if (list instanceof HTMLElement) {
		order.forEach((view) => {
			const item = [...list.querySelectorAll('[data-cb-core-reorder-item]')]
				.find((candidate) => String(candidate.dataset.cbCoreReorderItem || '') === view);
			if (item instanceof HTMLElement) list.appendChild(item);
		});
	}
	const defaultView = String(root.dataset.defaultView || 'table');
	const radio = [...root.querySelectorAll('input[name="cb-work-default-view"]')]
		.find((candidate) => candidate instanceof HTMLInputElement && String(candidate.value || '') === defaultView);
	if (radio instanceof HTMLInputElement) radio.checked = true;
};

const persist = async (root, policy) => {
	const status = root.querySelector('[data-cb-work-view-preferences-status]');
	if (status) status.textContent = root.dataset.saving || 'Saving…';

	const body = new FormData();
	body.set('action', root.dataset.action || '');
	body.set('nonce', root.dataset.nonce || '');
	body.set('policy', JSON.stringify(policy));

	const response = await fetch(root.dataset.ajaxUrl || '', {
		method: 'POST',
		credentials: 'same-origin',
		body,
	});
	const payload = await response.json().catch(() => null);
	if (!response.ok || !payload?.success || !payload?.data?.policy) {
		const message = payload?.data?.message || root.dataset.error || 'The Work Item view preferences could not be saved.';
		if (status) status.textContent = message;
		throw new Error(message);
	}
	if (status) status.textContent = root.dataset.saved || 'Saved';
	return payload.data.policy;
};

const openPreferences = async (trigger) => {
	const templateId = String(trigger.dataset.templateId || '');
	const template = document.getElementById(templateId);
	const modal = window.cbCore?.modal;
	const reorder = window.cbCore?.reorder;
	if (!(template instanceof HTMLTemplateElement) || !modal?.show || !reorder?.enhance) return;

	const body = template.content.firstElementChild?.cloneNode(true);
	if (!(body instanceof HTMLElement)) return;

	const reorderRoot = body.querySelector('[data-cb-core-reorder]');
	if (!(reorderRoot instanceof HTMLElement)) return;
	reorder.enhance(reorderRoot);

	body.querySelector('[data-cb-work-view-preferences-reset]')?.addEventListener('click', () => {
		applyDefaults(body);
	});

	const result = await modal.show({
		title: String(trigger.dataset.modalTitle || ''),
		body,
		confirmLabel: trigger.dataset.saveLabel || 'Save changes',
		cancelLabel: trigger.dataset.cancelLabel || 'Cancel',
		size: 'wide',
		initialFocus: '[data-cb-core-reorder-handle]',
		onConfirm: async () => {
			await persist(body, policyFromBody(body));
			return true;
		},
	});

	if (result === true) window.location.reload();
};

document.querySelectorAll('[data-cb-work-view-preferences-open]').forEach((trigger) => {
	trigger.addEventListener('click', () => {
		void openPreferences(trigger);
	});
});

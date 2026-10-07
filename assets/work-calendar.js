import '@cb-core/modal';
import { enhanceStatusBoard } from '@cb-work/work-items-reorder';

const reorderItem = (root, itemId) => [...root.querySelectorAll('[data-cb-core-reorder-item]')]
	.find((item) => String(item.dataset.cbCoreReorderItem || '') === String(itemId || '')) || null;

const reorderList = (root, listId) => [...root.querySelectorAll('[data-cb-core-reorder-list]')]
	.find((list) => String(list.dataset.cbCoreReorderList || '') === String(listId || '')) || null;

const refreshTemplateCounts = (template) => {
	template.content.querySelectorAll('[data-cb-work-status-lane]').forEach((lane) => {
		const list = lane.querySelector('[data-cb-core-reorder-list]');
		const count = list ? list.querySelectorAll('[data-cb-core-reorder-item]').length : 0;
		const counter = lane.querySelector('.count');
		const empty = lane.querySelector('[data-cb-work-board-empty]');
		if (counter) counter.textContent = '(' + String(count) + ')';
		if (empty) empty.hidden = count > 0;
	});
};

const syncTemplateMove = (template, move) => {
	const card = reorderItem(template.content, move.itemId);
	const target = reorderList(template.content, move.targetStatus);
	if (!(card instanceof HTMLElement) || !(target instanceof HTMLElement)) return;

	target.append(card);
	card.dataset.cbWorkStatus = String(move.result?.status || move.targetStatus || '');
	card.dataset.cbWorkAllowedStatuses = Array.isArray(move.result?.allowed_statuses)
		? move.result.allowed_statuses.join(',')
		: '';
	refreshTemplateCounts(template);
};

const openDayModal = (trigger) => {
	const calendar = trigger.closest('[data-cb-work-calendar]');
	const templateId = String(trigger.dataset.templateId || '');
	const template = document.getElementById(templateId);
	const modal = window.cbCore?.modal;

	if (!(template instanceof HTMLTemplateElement) || !modal?.show) return;

	const body = template.content.firstElementChild?.cloneNode(true);
	if (!(body instanceof HTMLElement)) return;

	const pending = modal.show({
		title: String(trigger.dataset.modalTitle || ''),
		body,
		dismissOnly: true,
		confirmLabel: String(calendar?.dataset.closeLabel || 'Close'),
		size: 'wide',
	});

	const board = body.querySelector('[data-cb-work-board-reorder]');
	if (board instanceof HTMLElement) {
		enhanceStatusBoard(board, {
			reloadAfterMove: false,
			onPersistedMove: (move) => syncTemplateMove(template, move),
		});
	}

	void pending;
};

document.querySelectorAll('[data-cb-work-calendar-day-open]').forEach((trigger) => {
	trigger.addEventListener('click', () => openDayModal(trigger));
});

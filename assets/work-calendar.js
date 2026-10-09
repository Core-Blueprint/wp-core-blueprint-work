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

/**
 * Calendar-only presentation: move Base's existing dismiss action next
 * to the workspace expand/restore control. Keep the actual Base button and
 * its click handler so resolution, Escape, and focus restoration are owned
 * by Base. Fail gracefully if a future Base version changes the markup.
 */
const promoteCalendarClose = (body, closeLabel) => {
	const dialog = body.closest('dialog.cb-core-modal--workspace');
	const form = dialog?.querySelector('.cb-core-modal__form');
	const expand = form?.querySelector('.cb-core-modal__expand-toggle');
	const actions = form?.querySelector('.cb-core-modal__actions');
	const buttons = actions?.querySelectorAll('button');
	if (!(dialog instanceof HTMLDialogElement) || !(form instanceof HTMLElement)
		|| !(expand instanceof HTMLButtonElement) || buttons?.length !== 1) return;

	const close = buttons[0];
	if (!(close instanceof HTMLButtonElement)) return;

	const controls = document.createElement('div');
	controls.className = 'cb-work-calendar-modal__header-actions';

	// Reuse Base's working Close action, not a second manual dialog.close().
	// Keep the Close name visible to assistive technology.
	close.className = 'cb-work-calendar-modal__close';
	close.setAttribute('aria-label', closeLabel);
	close.title = closeLabel;
	const icon = document.createElement('span');
	icon.className = 'dashicons dashicons-no-alt';
	icon.setAttribute('aria-hidden', 'true');
	close.replaceChildren(icon);

	controls.append(expand, close);
	form.insertBefore(controls, form.querySelector('.cb-core-modal__body'));
	actions.remove();
	dialog.classList.add('cb-work-calendar-modal');
};

const openDayModal = (trigger) => {
	const calendar = trigger.closest('[data-cb-work-calendar]');
	const templateId = String(trigger.dataset.templateId || '');
	const template = document.getElementById(templateId);
	const modal = window.cbCore?.modal;

	if (!(template instanceof HTMLTemplateElement) || !modal?.show) return;

	const body = template.content.firstElementChild?.cloneNode(true);
	if (!(body instanceof HTMLElement)) return;

	const closeLabel = String(calendar?.dataset.closeLabel || 'Close');
	const pending = modal.show({
		title: String(trigger.dataset.modalTitle || ''),
		body,
		dismissOnly: true,
		confirmLabel: closeLabel,
		size: 'workspace',
		expandable: true,
	});
	promoteCalendarClose(body, closeLabel);

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

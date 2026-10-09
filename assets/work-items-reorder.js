import '@cb-core/reorder';

const displayPanelFor = (root) => {
	const id = String(root.dataset.displayPanelId || '');
	return id ? document.getElementById(id) : null;
};

const policyFromPanel = (root) => {
	const items = [...root.querySelectorAll('[data-cb-core-reorder-item]')];
	const displayPanel = displayPanelFor(root);
	const density = displayPanel?.querySelector('[data-cb-work-table-density][aria-checked="true"]');
	const alternating = displayPanel?.querySelector('[data-cb-work-table-alternating]');
	return {
		order: items.map((item) => String(item.dataset.cbCoreReorderItem || '')).filter(Boolean),
		hidden: items
			.filter((item) => {
				const checkbox = item.querySelector('[data-cb-work-column-visible]');
				return checkbox instanceof HTMLInputElement && !checkbox.checked;
			})
			.map((item) => String(item.dataset.cbCoreReorderItem || ''))
			.filter(Boolean),
		density: density instanceof HTMLElement ? String(density.dataset.cbWorkTableDensity || 'compact') : 'compact',
		alternating_rows: alternating instanceof HTMLInputElement ? alternating.checked : true,
	};
};

const columnCell = (row, columnId) => [...row.children].find(
	(cell) => cell instanceof HTMLElement && cell.dataset.cbWorkColumn === columnId
);

const applyTablePolicy = (table, policy) => {
	if (!(table instanceof HTMLTableElement)) return;
	const hidden = new Set(policy.hidden || []);
	const density = ['compact', 'normal', 'spacious'].includes(String(policy.density || ''))
		? String(policy.density)
		: 'compact';
	table.dataset.cbWorkDensity = density;
	table.dataset.cbWorkAlternating = policy.alternating_rows === false ? '0' : '1';

	const rows = table.querySelectorAll('tr');
	rows.forEach((row) => {
		(policy.order || []).forEach((columnId) => {
			const cell = columnCell(row, columnId);
			if (!cell) return;
			cell.hidden = hidden.has(columnId);
			row.appendChild(cell);
		});
	});
};

const syncDisplayControls = (root, policy) => {
	const panel = displayPanelFor(root);
	if (!panel) return;
	const density = ['compact', 'normal', 'spacious'].includes(String(policy.density || ''))
		? String(policy.density)
		: 'compact';
	panel.querySelectorAll('[data-cb-work-table-density]').forEach((button) => {
		button.setAttribute('aria-checked', String(button.dataset.cbWorkTableDensity || '') === density ? 'true' : 'false');
	});
	const alternating = panel.querySelector('[data-cb-work-table-alternating]');
	if (alternating instanceof HTMLInputElement) {
		alternating.checked = policy.alternating_rows !== false;
	}
};

const persist = async (root, operation, policy = null) => {
	const status = root.querySelector('[data-cb-work-table-preferences-status]');
	if (status) status.textContent = root.dataset.saving || 'Saving…';

	const body = new FormData();
	body.set('action', root.dataset.action || '');
	body.set('nonce', root.dataset.nonce || '');
	body.set('operation', operation);
	if (policy) body.set('policy', JSON.stringify(policy));

	const response = await fetch(root.dataset.ajaxUrl || '', {
		method: 'POST',
		credentials: 'same-origin',
		body,
	});
	const payload = await response.json().catch(() => null);
	if (!response.ok || !payload?.success) {
		if (status) status.textContent = payload?.data?.message || root.dataset.error || 'Could not save preferences.';
		throw new Error(payload?.data?.message || 'Work Item table preference request failed.');
	}
	if (status) status.textContent = root.dataset.saved || 'Saved';
	return payload.data?.policy || policy;
};

const initTablePreferences = (root) => {
	const table = root.querySelector('[data-cb-work-items-table]');
	const panel = root.querySelector('[data-cb-work-table-columns-panel]');
	const toggle = panel?.id
		? document.querySelector('[data-cb-work-table-columns-toggle][aria-controls="' + panel.id + '"]')
		: null;
	const reset = root.querySelector('[data-cb-work-table-columns-reset]');
	const reorderRoot = root.querySelector('[data-cb-core-reorder]');
	const reorder = window.cbCore?.reorder;
	const displayPanel = displayPanelFor(root);
	const displayToggle = displayPanel?.id
		? document.querySelector('[data-cb-work-table-display-toggle][aria-controls="' + displayPanel.id + '"]')
		: null;
	const displayWrapper = displayToggle?.closest('.cb-work-table-display') || null;

	if (!(table instanceof HTMLTableElement) || !panel || !toggle || !reorderRoot || !reorder?.enhance) return;

	let currentPolicy = policyFromPanel(root);

	const controller = reorder.enhance(reorderRoot, {
		async onMove() {
			const policy = policyFromPanel(root);
			applyTablePolicy(table, policy);
			try {
				currentPolicy = await persist(root, 'save', policy);
				syncDisplayControls(root, currentPolicy);
			} catch (error) {
				applyTablePolicy(table, currentPolicy);
				syncDisplayControls(root, currentPolicy);
				throw error;
			}
		},
	});

	reorderRoot.addEventListener('cb:reorder:error', () => {
		applyTablePolicy(table, policyFromPanel(root));
	});

	root.querySelectorAll('[data-cb-work-column-visible]').forEach((checkbox) => {
		if (!(checkbox instanceof HTMLInputElement) || checkbox.disabled) return;
		checkbox.addEventListener('change', async () => {
			const policy = policyFromPanel(root);
			applyTablePolicy(table, policy);
			try {
				currentPolicy = await persist(root, 'save', policy);
				syncDisplayControls(root, currentPolicy);
			} catch {
				applyTablePolicy(table, currentPolicy);
				syncDisplayControls(root, currentPolicy);
				checkbox.checked = !currentPolicy.hidden?.includes(String(checkbox.value || ''));
			}
		});
	});

	toggle.addEventListener('click', () => {
		const opening = panel.hidden;
		panel.hidden = !opening;
		toggle.setAttribute('aria-expanded', opening ? 'true' : 'false');
		if (opening && displayPanel && displayToggle) {
			displayPanel.hidden = true;
			displayToggle.setAttribute('aria-expanded', 'false');
		}
		if (opening) {
			panel.querySelector('[data-cb-core-reorder-handle]')?.focus({ preventScroll: true });
		}
	});

	panel.addEventListener('keydown', (event) => {
		if (event.key !== 'Escape') return;
		panel.hidden = true;
		toggle.setAttribute('aria-expanded', 'false');
		toggle.focus();
	});

	if (displayPanel && displayToggle) {
		const closeDisplay = (restoreFocus = false) => {
			if (displayPanel.hidden) return;
			displayPanel.hidden = true;
			displayToggle.setAttribute('aria-expanded', 'false');
			if (restoreFocus) displayToggle.focus({ preventScroll: true });
		};

		displayToggle.addEventListener('click', () => {
			const opening = displayPanel.hidden;
			displayPanel.hidden = !opening;
			displayToggle.setAttribute('aria-expanded', opening ? 'true' : 'false');
			if (opening) {
				panel.hidden = true;
				toggle.setAttribute('aria-expanded', 'false');
				displayPanel.querySelector('[data-cb-work-table-density][aria-checked="true"]')?.focus({ preventScroll: true });
			}
		});

		displayPanel.querySelectorAll('[data-cb-work-table-density]').forEach((button) => {
			button.addEventListener('click', async () => {
				displayPanel.querySelectorAll('[data-cb-work-table-density]').forEach((candidate) => {
					candidate.setAttribute('aria-checked', candidate === button ? 'true' : 'false');
				});
				const policy = policyFromPanel(root);
				applyTablePolicy(table, policy);
				try {
					currentPolicy = await persist(root, 'save', policy);
					syncDisplayControls(root, currentPolicy);
				} catch {
					applyTablePolicy(table, currentPolicy);
					syncDisplayControls(root, currentPolicy);
				}
			});
		});

		const alternating = displayPanel.querySelector('[data-cb-work-table-alternating]');
		alternating?.addEventListener('change', async () => {
			const policy = policyFromPanel(root);
			applyTablePolicy(table, policy);
			try {
				currentPolicy = await persist(root, 'save', policy);
				syncDisplayControls(root, currentPolicy);
			} catch {
				applyTablePolicy(table, currentPolicy);
				syncDisplayControls(root, currentPolicy);
			}
		});

		document.addEventListener('click', (event) => {
			if (
				displayPanel.hidden
				|| !(event.target instanceof Node)
				|| displayWrapper?.contains(event.target)
			) return;
			closeDisplay();
		});

		displayPanel.addEventListener('keydown', (event) => {
			if (event.key !== 'Escape') return;
			event.preventDefault();
			closeDisplay(true);
		});
	}

	reset?.addEventListener('click', async () => {
		try {
			await persist(root, 'reset');
			window.location.reload();
		} catch {
			// Status copy is handled by persist(); keep the current valid UI state.
		}
	});

	currentPolicy = policyFromPanel(root);
	syncDisplayControls(root, currentPolicy);
	applyTablePolicy(table, currentPolicy);
	void controller;
};

document.querySelectorAll('[data-cb-work-table-preferences]').forEach(initTablePreferences);


const listDisplayPolicy = (root) => {
	const group = root.querySelector('[data-cb-work-list-group][aria-checked="true"]');
	const order = root.querySelector('[data-cb-work-list-project-order][aria-checked="true"]');
	return {
		group_by: group instanceof HTMLElement ? String(group.dataset.cbWorkListGroup || 'project') : 'project',
		project_order: order instanceof HTMLElement ? String(order.dataset.cbWorkListProjectOrder || 'asc') : 'asc',
	};
};

const syncListDisplay = (root, policy) => {
	root.querySelectorAll('[data-cb-work-list-group]').forEach((button) => {
		button.setAttribute(
			'aria-checked',
			String(button.dataset.cbWorkListGroup || '') === String(policy.group_by || 'project') ? 'true' : 'false'
		);
	});
	root.querySelectorAll('[data-cb-work-list-project-order]').forEach((button) => {
		button.setAttribute(
			'aria-checked',
			String(button.dataset.cbWorkListProjectOrder || '') === String(policy.project_order || 'asc') ? 'true' : 'false'
		);
	});
	const orderSection = root.querySelector('[data-cb-work-list-project-order-section]');
	if (orderSection instanceof HTMLElement) {
		orderSection.hidden = String(policy.group_by || 'project') !== 'project';
	}
};

const persistListDisplay = async (root, policy) => {
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
		throw new Error(payload?.data?.message || root.dataset.error || 'List display preferences could not be saved.');
	}
	return payload.data.policy;
};

const initListDisplay = (root) => {
	const toggle = root.querySelector('[data-cb-work-list-display-toggle]');
	const panel = root.querySelector('[data-cb-work-list-display-panel]');
	const groupButtons = [...root.querySelectorAll('[data-cb-work-list-group]')];
	const orderButtons = [...root.querySelectorAll('[data-cb-work-list-project-order]')];

	if (!(toggle instanceof HTMLButtonElement) || !(panel instanceof HTMLElement)) return;

	let currentPolicy = listDisplayPolicy(root);

	const saveSelection = async (buttons, selected) => {
		buttons.forEach((button) => {
			button.setAttribute('aria-checked', button === selected ? 'true' : 'false');
		});
		syncListDisplay(root, listDisplayPolicy(root));
		try {
			currentPolicy = await persistListDisplay(root, listDisplayPolicy(root));
			syncListDisplay(root, currentPolicy);
			window.location.reload();
		} catch (error) {
			syncListDisplay(root, currentPolicy);
			window.alert(error instanceof Error ? error.message : root.dataset.error || '');
		}
	};

	groupButtons.forEach((button) => {
		button.addEventListener('click', () => {
			void saveSelection(groupButtons, button);
		});
	});
	orderButtons.forEach((button) => {
		button.addEventListener('click', () => {
			void saveSelection(orderButtons, button);
		});
	});

	const close = (restoreFocus = false) => {
		if (panel.hidden) return;
		panel.hidden = true;
		toggle.setAttribute('aria-expanded', 'false');
		if (restoreFocus) toggle.focus({ preventScroll: true });
	};

	toggle.addEventListener('click', () => {
		const opening = panel.hidden;
		panel.hidden = !opening;
		toggle.setAttribute('aria-expanded', opening ? 'true' : 'false');
		if (opening) {
			panel.querySelector('[aria-checked="true"]')?.focus({ preventScroll: true });
		}
	});

	document.addEventListener('click', (event) => {
		if (panel.hidden || !(event.target instanceof Node) || root.contains(event.target)) return;
		close();
	});

	panel.addEventListener('keydown', (event) => {
		if (event.key !== 'Escape') return;
		event.preventDefault();
		close(true);
	});

	syncListDisplay(root, currentPolicy);
};

document.querySelectorAll('[data-cb-work-list-display]').forEach(initListDisplay);


const boardCardByReorderId = (root, itemId) => [...root.querySelectorAll('[data-cb-core-reorder-item]')]
	.find((item) => String(item.dataset.cbCoreReorderItem || '') === itemId) || null;

const allowedStatuses = (card) => new Set(
	String(card?.dataset?.cbWorkAllowedStatuses || '')
		.split(',')
		.map((value) => value.trim())
		.filter(Boolean)
);

const refreshBoardCounts = (root) => {
	root.querySelectorAll('[data-cb-work-status-lane]').forEach((lane) => {
		const list = lane.querySelector('[data-cb-core-reorder-list]');
		const count = list ? list.querySelectorAll('[data-cb-core-reorder-item]').length : 0;
		const counter = lane.querySelector('.count');
		const empty = lane.querySelector('[data-cb-work-board-empty]');
		if (counter) counter.textContent = '(' + String(count) + ')';
		if (empty) empty.hidden = count > 0;
	});
};

const persistBoardTransition = async (root, card, targetStatus) => {
	const body = new FormData();
	body.set('action', root.dataset.action || '');
	body.set('nonce', root.dataset.nonce || '');
	body.set('work_item_id', card.dataset.cbWorkItemId || '');
	body.set('status', targetStatus);
	body.set('expected_status', card.dataset.cbWorkStatus || '');

	const response = await fetch(root.dataset.ajaxUrl || '', {
		method: 'POST',
		credentials: 'same-origin',
		body,
	});
	const payload = await response.json().catch(() => null);
	if (!response.ok || !payload?.success) {
		throw new Error(payload?.data?.message || root.dataset.error || 'Work Item status update failed.');
	}
	return payload.data || {};
};

const enhanceStatusBoard = (root, options = {}) => {
	const reloadAfterMove = options.reloadAfterMove !== false;
	const reorder = window.cbCore?.reorder;
	if (!reorder?.enhance) return;

	reorder.enhance(root, {
		crossList: true,

		canMove(move) {
			if (move.from.listId === move.to.listId) return false;
			const card = boardCardByReorderId(root, move.itemId);
			return Boolean(card && allowedStatuses(card).has(move.to.listId));
		},

		async onMove(move) {
			const card = boardCardByReorderId(root, move.itemId);
			if (!card) return false;

			const result = await persistBoardTransition(root, card, move.to.listId);
			card.dataset.cbWorkStatus = String(result.status || move.to.listId);
			card.dataset.cbWorkAllowedStatuses = Array.isArray(result.allowed_statuses)
				? result.allowed_statuses.join(',')
				: '';
			refreshBoardCounts(root);
			if (typeof options.onPersistedMove === 'function') {
				options.onPersistedMove({
					itemId: move.itemId,
					fromStatus: move.from.listId,
					targetStatus: move.to.listId,
					result,
				});
			}
			if (reloadAfterMove) window.location.reload();
			return true;
		},
	});

	root.addEventListener('cb:reorder:error', () => {
		refreshBoardCounts(root);
	});

	root.addEventListener('cb:reorder:change', () => {
		refreshBoardCounts(root);
	});

	refreshBoardCounts(root);
};

document.querySelectorAll('[data-cb-work-board-reorder]').forEach((root) => enhanceStatusBoard(root));


export { enhanceStatusBoard };

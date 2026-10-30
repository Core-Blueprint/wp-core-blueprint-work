/**
 * M3-B2 scoped rule/period dialog. All business decisions remain with the
 * canonical Engagements/Periods/RecurrenceRules APIs. No scheduler calls.
 */
export function createEngagementRuleDialog(root, browser, request, afterBinding = null) {
	const panel = root.querySelector('[data-cb-work-m3-engagements]');
	const doc = root.ownerDocument;
	const endpoint = panel?.dataset.cbWorkM3RuleEndpoint;
	if (!panel || !endpoint) return null;
	let labels;
	try { labels = JSON.parse(panel.dataset.cbWorkM3RuleStatuses || '{}'); }
	catch { return null; }
	const elt = (tag, text = '') => {
		const item = doc.createElement(tag);
		item.textContent = text;
		return item;
	};
	let busy = false;
	function restUrl(id) {
		if (!Number.isSafeInteger(id) || id <= 0) throw new TypeError('Invalid engagement');
		if (!endpoint || !endpoint.includes('__id__')) throw new TypeError('Invalid Work REST template');
		const url = new URL(endpoint.replace('__id__', String(id)), browser.location.href);
		if (url.origin !== new URL(browser.location.href).origin) throw new TypeError('Cross-origin Work REST');
		return url;
	}
	async function open(engagement, button) {
		if (busy || !browser.cbCore?.modal?.show) return false;
		busy = true;
		const url = restUrl(engagement.id);
		let closed = false;
		let seq = 0;
		let pending = null;
		let result = null;
		let page = 1;
		let pages = 1;

		const body = elt('div');
		body.className = 'cb-work-m3-rules';
		const status = elt('p');
		status.setAttribute('role', 'status');
		const totals = elt('p');
		const monthRow = elt('label');
		monthRow.appendChild(elt('span', panel.dataset.cbWorkM3RuleMonth));
		const month = elt('input');
		month.type = 'month';
		monthRow.appendChild(month);
		const searchRow = elt('label');
		searchRow.appendChild(elt('span', panel.dataset.cbWorkM3RuleSearch));
		const search = elt('input');
		search.type = 'search';
		search.maxLength = 160;
		searchRow.appendChild(search);
		const rule = elt('select');
		rule.setAttribute('aria-label', panel.dataset.cbWorkM3RuleAction);
		rule.required = true;
		const empty = elt('p', panel.dataset.cbWorkM3RuleEmpty);
		empty.hidden = true;
		const pagination = elt('div');
		pagination.className = 'cb-work-m3-rules__pages';
		const prev = elt('button', panel.dataset.cbWorkM3RulePrev);
		prev.type = 'button';
		const indicator = elt('output');
		const next = elt('button', panel.dataset.cbWorkM3RuleNext);
		next.type = 'button';
		pagination.append(prev, indicator, next);
		const fromRow = elt('label');
		fromRow.appendChild(elt('span', panel.dataset.cbWorkM3RuleFrom));
		const from = elt('input');
		from.type = 'date';
		from.required = true;
		from.value = engagement.starts_on || '';
		from.min = engagement.starts_on || '';
		fromRow.appendChild(from);
		const error = elt('p');
		error.setAttribute('role', 'alert');
		error.hidden = true;
		body.append(status, totals, monthRow, searchRow, rule, empty, pagination, fromRow, error);

		function fail() {
			result = null;
			rule.replaceChildren();
			rule.disabled = true;
			status.textContent = '';
			totals.textContent = '';
			empty.hidden = true;
			pagination.hidden = true;
			error.textContent = panel.dataset.cbWorkM3Error;
			error.hidden = false;
		}
		function validResponse(data) {
			return data && /^\d{4}-(0[1-9]|1[0-2])$/.test(data.month) &&
				data.engagement?.id === engagement.id &&
				typeof data.engagement.starts_on === 'string' &&
				Number.isSafeInteger(data.linked_total) && data.linked_total >= 0 &&
				Number.isSafeInteger(data.page) && data.page >= 1 &&
				Number.isSafeInteger(data.pages) && data.pages >= 1 &&
				Array.isArray(data.rules) && data.rules.length <= 25 &&
				data.rules.every((item) => Number.isSafeInteger(item.id) && item.id > 0 &&
					typeof item.title === 'string' && typeof item.frequency === 'string') &&
				typeof data.period?.status === 'string' &&
				['scheduled', 'completed', 'overdue'].every((key) =>
					Number.isSafeInteger(data.period[key]) && data.period[key] >= 0);
		}
		async function load(targetPage = page) {
			if (closed) return false;
			pending?.abort();
			const ticket = ++seq;
			pending = new AbortController();
			const target = restUrl(engagement.id);
			target.searchParams.set('page', String(targetPage));
			if (month.value) target.searchParams.set('month', month.value);
			if (search.value.trim()) target.searchParams.set('search', search.value.trim().slice(0, 160));
			rule.disabled = true;
			error.hidden = true;
			try {
				const data = await request(target.href, { method: 'GET', signal: pending.signal });
				if (closed || ticket !== seq) return false;
				if (!validResponse(data)) throw new TypeError('Invalid Work recurrence status');
				result = data;
				page = data.page;
				pages = data.pages;
				month.value = data.month;
				const label = labels[data.period.status];
				if (typeof label !== 'string' || !label) throw new TypeError('Unknown execution status');
				status.textContent = panel.dataset.cbWorkM3RuleStatus + ': ' + label;
				totals.textContent = [
					panel.dataset.cbWorkM3RuleScheduled + ': ' + data.period.scheduled,
					panel.dataset.cbWorkM3RuleCompleted + ': ' + data.period.completed,
					panel.dataset.cbWorkM3RuleOverdue + ': ' + data.period.overdue,
				].join(' · ');
				rule.replaceChildren();
				const choose = elt('option', panel.dataset.cbWorkM3RuleAction);
				choose.value = '';
				rule.appendChild(choose);
				for (const item of data.rules) {
					const option = elt('option', item.title);
					option.value = String(item.id);
					rule.appendChild(option);
				}
				rule.disabled = data.rules.length === 0;
				empty.hidden = data.rules.length !== 0 || page < pages;
				pagination.hidden = pages <= 1;
				indicator.textContent = page + ' / ' + pages;
				prev.disabled = page <= 1;
				next.disabled = page >= pages;
				if (!from.value) from.value = data.engagement.starts_on;
				error.hidden = true;
				return true;
			} catch (e) {
				if (!closed && ticket === seq && e?.name !== 'AbortError') fail();
				return false;
			} finally {
				if (ticket === seq) pending = null;
			}
		}
		let typed = null;
		const onSearch = () => {
			clearTimeout(typed);
			typed = setTimeout(() => void load(1), 250);
		};
		search.addEventListener('input', onSearch);
		month.addEventListener('change', () => void load(1));
		prev.addEventListener('click', () => { if (page > 1) void load(page - 1); });
		next.addEventListener('click', () => { if (page < pages) void load(page + 1); });
		try {
			// Load exact canonical execution state before offering a binding.
			if (!await load(1)) {
				const panelError = panel.querySelector('[data-cb-work-m3-engagements-error]');
				if (panelError) panelError.hidden = false;
				return false;
			}
			let linked = false;
			const accepted = await browser.cbCore.modal.show({
				title: panel.dataset.cbWorkM3RuleAction,
				body, confirmLabel: panel.dataset.cbWorkM3RuleSave,
				cancelLabel: panel.dataset.cbWorkM3Cancel,
				initialFocus: 'select',
				onConfirm: async () => {
					const ruleId = Number(rule.value);
					if (!result || rule.disabled || !result.rules.some((candidate) => candidate.id === ruleId) ||
						!/^\d{4}-\d{2}-\d{2}$/.test(from.value) ||
						from.value < engagement.starts_on) {
						error.textContent = panel.dataset.cbWorkM3Error;
						error.hidden = false;
						return false;
					}
					// Do not assume a successful link from a successful HTTP response
					// with a wrong or incomplete receipt.
					try {
						const reply = await request(restUrl(engagement.id).href, {
							method: 'POST',
							body: JSON.stringify({ rule_id: ruleId, effective_from: from.value }),
						});
						if (reply?.linked !== true) throw new TypeError('Invalid rule binding receipt');
						linked = true;
						return true;
					} catch {
						error.textContent = panel.dataset.cbWorkM3Error;
						error.hidden = false;
						return false;
					}
				},
			});
			if (accepted === true && linked && !closed) {
				if (typeof afterBinding === 'function') await afterBinding(engagement.id, button);
				return true;
			}
			return false;
		} finally {
			closed = true;
			++seq;
			pending?.abort();
			clearTimeout(typed);
			busy = false;
		}
	}
	return { open };
}

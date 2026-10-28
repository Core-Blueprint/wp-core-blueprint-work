/**
 * UWE-03B.2: shareable, bounded client query state.
 * Reserves the WordPress admin `page` route and uses `uwe_page` for workspace pagination.
 * Preserves unrelated WordPress/admin query arguments and hash fragments.
 */
export const DATA_KEYS = Object.freeze(['search', 'sort', 'status', 'uwe_page', 'per_page']);
const SORTS = Object.freeze(['workload', 'due', 'scheduled', 'updated', 'title']);
const STATUSES = Object.freeze(['planned', 'in_progress', 'blocked', 'completed', 'skipped', 'cancelled']);

export function defaultSort(section) {
	return section === 'work-items' ? 'workload' : 'title';
}

export function defaultDataState(section) {
	return { search: '', sort: defaultSort(section), status: '', page: 1, per_page: 25 };
}

function boundedInteger(raw, min, max, fallback) {
	if (!/^\d+$/.test(String(raw ?? ''))) return fallback;
	const value = Number(raw);
	return Number.isSafeInteger(value) && value >= min && value <= max ? value : fallback;
}

export function readDataState(href, section) {
	const params = new URL(href).searchParams;
	const defaults = defaultDataState(section);
	const sort = params.get('sort');
	const status = params.get('status');
	return {
		search: (params.get('search') ?? '').trim().slice(0, 160),
		sort: SORTS.includes(sort) && (section === 'work-items' || ['title', 'updated'].includes(sort))
			? sort : defaults.sort,
		status: section === 'work-items' && STATUSES.includes(status) ? status : '',
		page: boundedInteger(params.get('uwe_page'), 1, 100000, 1),
		per_page: boundedInteger(params.get('per_page'), 1, 100, 25),
	};
}

export function dataStateUrl(href, section, input) {
	const url = new URL(href);
	for (const key of DATA_KEYS) url.searchParams.delete(key);
	const state = { ...defaultDataState(section), ...input };
	const search = String(state.search ?? '').trim().slice(0, 160);
	if (search) url.searchParams.set('search', search);
	if (SORTS.includes(state.sort) && state.sort !== defaultSort(section)
		&& (section === 'work-items' || ['title', 'updated'].includes(state.sort))) {
		url.searchParams.set('sort', state.sort);
	}
	if (section === 'work-items' && STATUSES.includes(state.status)) url.searchParams.set('status', state.status);
	if (Number.isSafeInteger(state.page) && state.page > 1 && state.page <= 100000) {
		url.searchParams.set('uwe_page', String(state.page));
	}
	if (Number.isSafeInteger(state.per_page) && state.per_page !== 25
		&& state.per_page >= 1 && state.per_page <= 100) {
		url.searchParams.set('per_page', String(state.per_page));
	}
	return url.pathname + url.search + url.hash;
}

export function apiParameters(state) {
	const parameters = new URLSearchParams();
	parameters.set('page', String(state.page));
	parameters.set('per_page', String(state.per_page));
	if (state.search) parameters.set('search', state.search);
	if (state.sort) parameters.set('sort', state.sort);
	if (state.status) parameters.set('status', state.status);
	return parameters;
}

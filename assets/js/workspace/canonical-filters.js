/**
 * Work M1: route canonical Work Item filter/search/paging navigation through
 * the persistent Unified Workspace, never WordPress's native admin page.
 *
 * These functions are pure state adapters; the source of truth for supported
 * filters, query semantics and HTML stays WorkItemViewState / Operations.
 */
export const FILTER_FIELDS = Object.freeze([
	'search', 'status', 'priority', 'project_id', 'service_id',
	'work_type_id', 'assignee_id', 'billing', 'work_context', 'customer',
	'calendar_month', 'scheduled_from', 'scheduled_to', 'due_from', 'due_to', 'sort',
]);
const ROUTE_FIELDS = Object.freeze([...FILTER_FIELDS, 'view', 'uwe_page']);
export const WORK_QUERY_FIELDS = ROUTE_FIELDS;

export function snapshotWorkState(href) {
	const source = new URL(href).searchParams;
	const saved = new URLSearchParams();
	for (const field of WORK_QUERY_FIELDS) {
		for (const value of source.getAll(field)) saved.append(field, value);
	}
	return saved;
}

export function restoreWorkState(href, snapshot = new URLSearchParams()) {
	const url = new URL(href);
	for (const field of WORK_QUERY_FIELDS) url.searchParams.delete(field);
	for (const [field, value] of snapshot) {
		if (WORK_QUERY_FIELDS.includes(field)) url.searchParams.set(field, value);
	}
	return url.pathname + url.search + url.hash;
}

const CANONICAL_FIELD_MAP = Object.freeze({ s: 'search', paged: 'uwe_page' });
const NATIVE_PAGE = 'core-blueprint-work-items';
const VALID_VIEWS = Object.freeze(['table', 'list', 'kanban', 'calendar']);

function meaningful(key, value) {
	if (!value || value === '0') return false;
	if (key === 'uwe_page' && (!/^\d{1,6}$/.test(value) || +value <= 1 || +value > 100000)) return false;
	return true;
}
function applyFilters(baseHref, fields, fallbackView = 'table') {
	const url = new URL(baseHref);
	for (const key of ROUTE_FIELDS) url.searchParams.delete(key);
	url.searchParams.set('section', 'work-items');
	const view = fields.get('view');
	url.searchParams.set('view', VALID_VIEWS.includes(view) ? view : fallbackView);
	for (const key of FILTER_FIELDS) {
		const value = fields.get(key);
		if (value !== null && meaningful(key, value)) url.searchParams.set(key, value.slice(0, 191));
	}
	const page = fields.get('uwe_page');
	if (meaningful('uwe_page', page)) url.searchParams.set('uwe_page', page);
	return url.pathname + url.search + url.hash;
}

/**
 * Convert the original native Work Items GET form. WordPress's reserved
 * "page" input is dropped. Called with a FormData or URLSearchParams object.
 */
export function canonicalFilterUrl(baseHref, entries, fallbackView = 'table') {
	const fields = new URLSearchParams();
	for (const [rawKey, rawValue] of entries) {
		const key = CANONICAL_FIELD_MAP[rawKey] || rawKey;
		if (!ROUTE_FIELDS.includes(key) || key === 'uwe_page') continue;
		if (typeof rawValue === 'string') fields.set(key, rawValue.trim());
	}
	return applyFilters(baseHref, fields, fallbackView);
}

/**
 * Convert a link produced by WorkItemViewState::query_args. The native href
 * must point at the same WordPress admin.php origin and Work Items page;
 * arbitrary external/local admin links and mutations are never intercepted.
 */
export function canonicalLinkUrl(baseHref, nativeHref, fallbackView = 'table') {
	const current = new URL(baseHref);
	const native = new URL(nativeHref, current);
	if (native.origin !== current.origin
		|| native.pathname !== current.pathname
		|| native.searchParams.get('page') !== NATIVE_PAGE
		|| native.searchParams.has('action')) return null;
	const fields = new URLSearchParams();
	for (const [rawKey, value] of native.searchParams) {
		const key = CANONICAL_FIELD_MAP[rawKey] || rawKey;
		if (ROUTE_FIELDS.includes(key)) fields.set(key, value);
	}
	return applyFilters(baseHref, fields, fallbackView);
}

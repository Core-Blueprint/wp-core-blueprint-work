/**
 * UWE-03A: Work-only URL and navigation state. No network writes or framework.
 * The browser URL remains the source of truth for the selected section.
 */
export const SECTIONS = Object.freeze([ 'work-items', 'projects', 'services', 'recurring' ]);

export function readSection(href) {
	const requested = new URL(href).searchParams.get('section');
	return SECTIONS.includes(requested) ? requested : 'work-items';
}

export function sectionUrl(href, section) {
	if (!SECTIONS.includes(section)) {
		throw new TypeError('Unknown Work workspace section');
	}
	const url = new URL(href);
	if (section === 'work-items') {
		url.searchParams.delete('section');
	} else {
		url.searchParams.set('section', section);
	}
	return url.pathname + url.search + url.hash;
}

export function createWorkspaceRouter(browser, notify) {
	let current = readSection(browser.location.href);
	const scrollPositions = new Map();

	function transition(next, source) {
		if (next === current) return false;
		scrollPositions.set(current, Math.max(0, Number(browser.scrollY) || 0));
		const restoreY = scrollPositions.get(next) ?? 0;
		current = next;
		notify(next, { source, restoreY });
		return true;
	}

	function onPopState() {
		transition(readSection(browser.location.href), 'popstate');
	}

	browser.addEventListener('popstate', onPopState);

	return {
		section: () => current,
		navigate(next) {
			if (!SECTIONS.includes(next) || next === current) return false;
			browser.history.pushState({ cbWorkUweSection: next }, '', sectionUrl(browser.location.href, next));
			return transition(next, 'push');
		},
		dispose() {
			browser.removeEventListener('popstate', onPopState);
		}
	};
}

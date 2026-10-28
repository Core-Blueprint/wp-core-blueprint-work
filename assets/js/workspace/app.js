import { createWorkspaceRouter, SECTIONS } from './router.js';

/** Progressive enhancement: server-rendered links keep working without JS. */
export function mountWorkspace(root, browser = window) {
	if (!root || root.dataset.cbWorkUweMounted === '1') return null;

	const tabs = new Map();
	const panels = new Map();
	root.querySelectorAll('[data-cb-work-uwe-tab]').forEach((tab) => {
		tabs.set(tab.dataset.cbWorkUweTab, tab);
	});
	root.querySelectorAll('[data-cb-work-uwe-panel]').forEach((panel) => {
		panels.set(panel.dataset.cbWorkUwePanel, panel);
	});
	if (!SECTIONS.every((section) => tabs.has(section) && panels.has(section))) return null;
	root.dataset.cbWorkUweMounted = '1';

	const announcement = root.querySelector('[data-cb-work-uwe-status]');
	const render = (section, detail = null) => {
		for (const key of SECTIONS) {
			const selected = key === section;
			const tab = tabs.get(key);
			tab.setAttribute('aria-selected', String(selected));
			tab.tabIndex = selected ? 0 : -1;
			tab.classList.toggle('is-active', selected);
			panels.get(key).hidden = !selected;
		}
		if (detail) {
			if (announcement) announcement.textContent = tabs.get(section).textContent.trim();
			const focused = root.ownerDocument.activeElement;
			if (focused && root.contains(focused)) {
				const hiddenPanel = focused.closest('[data-cb-work-uwe-panel]');
				if (hiddenPanel && hiddenPanel.hidden) tabs.get(section).focus();
			}
			if (typeof browser.requestAnimationFrame === 'function' && typeof browser.scrollTo === 'function') {
				browser.requestAnimationFrame(() => browser.scrollTo({ top: detail.restoreY, behavior: 'instant' }));
			}
		}
	};
	const router = createWorkspaceRouter(browser, render);
	render(router.section());

	for (const [section, tab] of tabs) {
		tab.addEventListener('click', (event) => {
			if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
			event.preventDefault();
			router.navigate(section);
		});
		tab.addEventListener('keydown', (event) => {
			const index = SECTIONS.indexOf(section);
			let next = null;
			switch (event.key) {
				case 'ArrowRight': next = SECTIONS[(index + 1) % SECTIONS.length]; break;
				case 'ArrowLeft': next = SECTIONS[(index + SECTIONS.length - 1) % SECTIONS.length]; break;
				case 'Home': next = SECTIONS[0]; break;
				case 'End': next = SECTIONS[SECTIONS.length - 1]; break;
				default: return;
			}
			event.preventDefault();
			tabs.get(next).focus();
			router.navigate(next);
		});
	}

	return router;
}

function boot() {
	document.querySelectorAll('[data-cb-work-uwe]').forEach((root) => mountWorkspace(root));
}
if (typeof document !== 'undefined') {
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot, { once: true });
	} else {
		boot();
	}
}

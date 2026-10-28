(() => {
	'use strict';

	// Progressive enhancement for the native WordPress Project list.
	// The original title, Add Project button and list table remain authoritative.
	const mount = () => {
		const config = window.cbWorkProjectsList;
		const wrap = document.querySelector('.wrap');
		const heading = wrap?.querySelector('h1.wp-heading-inline');
		if (!config || !wrap || !heading) {
			return;
		}

		// Keep the established Import action available independently of the
		// optional header layout. No import link is shown without the provider.
		if (config.importUrl && config.importLabel && !wrap.querySelector('[data-cb-work-project-import-action]')) {
			const action = document.createElement('a');
			action.className = 'page-title-action';
			action.href = config.importUrl;
			action.textContent = config.importLabel;
			action.dataset.cbWorkProjectImportAction = 'true';

			const addProject = wrap.querySelector('a.page-title-action');
			if (addProject) {
				addProject.insertAdjacentElement('afterend', action);
			} else {
				heading.insertAdjacentElement('afterend', action);
			}
		}

		// If the WordPress heading structure changes, leave the native shell
		// functional. Never hide it or replace its buttons with duplicates.
		if (wrap.querySelector('[data-cb-work-projects-header]')) {
			return;
		}
		const boundary = wrap.querySelector('hr.wp-header-end');
		if (!boundary || !config.description) {
			return;
		}

		const nativeActions = [];
		for (let sibling = heading.nextElementSibling; sibling && sibling !== boundary; sibling = sibling.nextElementSibling) {
			if (sibling.matches('a.page-title-action')) {
				nativeActions.push(sibling);
			}
		}

		const header = document.createElement('header');
		header.className = 'cb-work-projects-list__header';
		header.dataset.cbWorkProjectsHeader = 'true';

		const copy = document.createElement('div');
		copy.className = 'cb-work-projects-list__heading';
		const description = document.createElement('p');
		description.className = 'description';
		description.textContent = config.description;

		const actions = document.createElement('div');
		actions.className = 'cb-work-projects-list__actions';

		wrap.insertBefore(header, heading);
		header.appendChild(copy);
		copy.appendChild(heading);
		copy.appendChild(description);
		for (const action of nativeActions) {
			actions.appendChild(action);
		}
		if (nativeActions.length) {
			header.appendChild(actions);
		}
	};

	if ('loading' === document.readyState) {
		document.addEventListener('DOMContentLoaded', mount, { once: true });
	} else {
		mount();
	}
})();

(() => {
	'use strict';

	const mount = () => {
		const config = window.cbWorkProjectsList;
		if (!config || !config.importUrl || !config.importLabel) {
			return;
		}

		const wrap = document.querySelector('.wrap');
		const heading = wrap?.querySelector('h1.wp-heading-inline');
		if (!wrap || !heading || wrap.querySelector('[data-cb-work-project-import-action]')) {
			return;
		}

		const action = document.createElement('a');
		action.className = 'page-title-action';
		action.href = config.importUrl;
		action.textContent = config.importLabel;
		action.dataset.cbWorkProjectImportAction = 'true';

		const addProject = wrap.querySelector('a.page-title-action');
		if (addProject) {
			addProject.insertAdjacentElement('afterend', action);
			return;
		}

		heading.insertAdjacentElement('afterend', action);
	};

	if ('loading' === document.readyState) {
		document.addEventListener('DOMContentLoaded', mount, { once: true });
		return;
	}

	mount();
})();

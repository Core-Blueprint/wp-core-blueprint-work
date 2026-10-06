(() => {
	'use strict';

	const config = window.cbWorkProjectDataExchange;
	const form = document.querySelector('[data-cb-work-project-import]');
	if (!config || !form) {
		return;
	}

	const fileInput = form.querySelector('input[name="file"]');
	const modeInput = form.querySelector('select[name="mode"]');
	const previewButton = form.querySelector('[data-cb-work-project-preview]');
	const applyButton = form.querySelector('[data-cb-work-project-apply]');
	const result = document.querySelector('[data-cb-work-project-import-result]');
	let fingerprint = '';

	const escapeHtml = (value) => String(value)
		.replaceAll('&', '&amp;')
		.replaceAll('<', '&lt;')
		.replaceAll('>', '&gt;')
		.replaceAll('"', '&quot;')
		.replaceAll("'", '&#039;');

	const displayValue = (value) => {
		const normalized = String(value ?? '').trim();
		return normalized || '—';
	};

	const humanize = (value) => {
		const normalized = String(value ?? '').trim().replaceAll('_', ' ');
		return normalized ? normalized.replace(/\b\w/g, (character) => character.toUpperCase()) : '—';
	};

	const reset = () => {
		fingerprint = '';
		applyButton.disabled = true;
		result.innerHTML = '';
	};

	const payload = (action) => {
		const data = new FormData();
		data.append('action', action);
		data.append('nonce', config.nonce);
		data.append('mode', modeInput.value);
		if (fileInput.files && fileInput.files[0]) {
			data.append('file', fileInput.files[0]);
		}
		if (fingerprint) {
			data.append('fingerprint', fingerprint);
		}
		return data;
	};

	const request = async (action) => {
		const response = await fetch(config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: payload(action),
		});
		const body = await response.json();
		if (!response.ok || !body || !body.success) {
			throw new Error(body?.data?.validation?.message || config.labels.failed);
		}
		return body.data.validation;
	};

	const showPreview = (validation) => {
		const counts = validation.counts || {};
		const warnings = Array.isArray(validation.warnings) ? validation.warnings : [];
		const errors = Array.isArray(validation.errors) ? validation.errors : [];
		const rows = [
			[config.labels.projectsCreate, counts.projects_create || 0],
			[config.labels.projectsUpdate, counts.projects_update || 0],
			[config.labels.projectsSkip, counts.projects_skip || 0],
			[config.labels.workItems, counts.work_items || 0],
		];
		let html = '<div class="notice notice-' + (validation.valid ? 'success' : 'error') + ' inline"><p><strong>' + escapeHtml(validation.message || '') + '</strong></p></div>';
		html += '<table class="widefat striped" style="max-width:720px"><tbody>';
		rows.forEach(([label, value]) => {
			html += '<tr><th>' + escapeHtml(label) + '</th><td>' + escapeHtml(value) + '</td></tr>';
		});
		html += '</tbody></table>';
		if (warnings.length) {
			html += '<ul>';
			warnings.forEach((warning) => { html += '<li>' + escapeHtml(warning) + '</li>'; });
			html += '</ul>';
		}
		if (errors.length) {
			html += '<ul>';
			errors.forEach((error) => { html += '<li>' + escapeHtml(error.message || error.code || '') + '</li>'; });
			html += '</ul>';
		}

		const review = validation.review || {};
		const project = review.project && typeof review.project === 'object' ? review.project : null;
		const workItems = Array.isArray(review.work_items) ? review.work_items : [];
		if (validation.valid && project) {
			html += '<h2>' + escapeHtml(config.labels.importReview) + '</h2>';
			html += '<h3>' + escapeHtml(config.labels.project) + '</h3>';
			html += '<table class="widefat striped" style="max-width:920px"><tbody>';
			html += '<tr><th>' + escapeHtml(config.labels.project) + '</th><td><strong>' + escapeHtml(displayValue(project.title)) + '</strong></td></tr>';
			html += '<tr><th>' + escapeHtml(config.labels.context) + '</th><td>' + escapeHtml(humanize(project.work_context)) + '</td></tr>';
			html += '<tr><th>' + escapeHtml(config.labels.startDate) + '</th><td>' + escapeHtml(displayValue(project.starts_on)) + '</td></tr>';
			html += '<tr><th>' + escapeHtml(config.labels.dueDate) + '</th><td>' + escapeHtml(displayValue(project.due_on)) + '</td></tr>';
			html += '</tbody></table>';

			html += '<h3>' + escapeHtml(config.labels.workItems) + ' (' + escapeHtml(workItems.length) + ')</h3>';
			html += '<table class="widefat striped"><thead><tr>';
			html += '<th>' + escapeHtml(config.labels.workItem) + '</th>';
			html += '<th>' + escapeHtml(config.labels.status) + '</th>';
			html += '<th>' + escapeHtml(config.labels.priority) + '</th>';
			html += '<th>' + escapeHtml(config.labels.scheduled) + '</th>';
			html += '<th>' + escapeHtml(config.labels.dueDate) + '</th>';
			html += '<th>' + escapeHtml(config.labels.estimate) + '</th>';
			html += '</tr></thead><tbody>';
			workItems.forEach((item) => {
				html += '<tr>';
				html += '<td><strong>' + escapeHtml(displayValue(item.title)) + '</strong></td>';
				html += '<td>' + escapeHtml(humanize(item.status)) + '</td>';
				html += '<td>' + escapeHtml(humanize(item.priority)) + '</td>';
				html += '<td>' + escapeHtml(displayValue(item.scheduled_on)) + '</td>';
				html += '<td>' + escapeHtml(displayValue(item.due_on)) + '</td>';
				html += '<td>' + escapeHtml(item.estimated_minutes || 0) + ' ' + escapeHtml(config.labels.minutes) + '</td>';
				html += '</tr>';
			});
			html += '</tbody></table>';
		}
		result.innerHTML = html;
	};

	form.addEventListener('submit', async (event) => {
		event.preventDefault();
		reset();
		previewButton.disabled = true;
		result.innerHTML = '<p>' + escapeHtml(config.labels.validating) + '</p>';
		try {
			const validation = await request(config.actions.preview);
			showPreview(validation);
			fingerprint = validation.valid ? (validation.fingerprint || '') : '';
			applyButton.disabled = !fingerprint;
		} catch (error) {
			result.innerHTML = '<div class="notice notice-error inline"><p>' + escapeHtml(error.message || config.labels.failed) + '</p></div>';
		} finally {
			previewButton.disabled = false;
		}
	});

	applyButton.addEventListener('click', async () => {
		if (!fingerprint) {
			return;
		}
		previewButton.disabled = true;
		applyButton.disabled = true;
		result.innerHTML = '<p>' + escapeHtml(config.labels.applying) + '</p>';
		try {
			const validation = await request(config.actions.apply);
			result.innerHTML = '<div class="notice notice-success inline"><p><strong>' + escapeHtml(validation.message || '') + '</strong></p></div>';
			if (validation.url) {
				window.location.assign(validation.url);
			}
		} catch (error) {
			fingerprint = '';
			result.innerHTML = '<div class="notice notice-error inline"><p>' + escapeHtml(error.message || config.labels.failed) + '</p></div>';
			previewButton.disabled = false;
			applyButton.disabled = true;
		}
	});

	fileInput.addEventListener('change', reset);
	modeInput.addEventListener('change', reset);
})();

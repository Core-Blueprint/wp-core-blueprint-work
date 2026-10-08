/* Recurring Work admin workspace: read-only canonical preview and governed quick edit. */
(() => {
    'use strict';

    const config = window.cbWorkRecurrenceUi;
    if (!config || typeof config.ajaxUrl !== 'string') return;

    document.querySelectorAll('form').forEach((form) => {
        const activate = form.querySelector('[data-cb-work-confirm]');
        if (!activate) return;
        form.addEventListener('submit', (event) => {
            if (!window.confirm(activate.textContent.trim() + '?')) event.preventDefault();
        });
    });

    document.querySelectorAll('[data-cb-quick-edit-trigger]').forEach((trigger) => {
        const row = trigger.closest('tr');
        const editor = row?.nextElementSibling;
        if (!editor?.matches('[data-cb-quick-edit-row]')) return;
        trigger.addEventListener('click', () => {
            editor.hidden = !editor.hidden;
            trigger.setAttribute('aria-expanded', String(!editor.hidden));
            if (!editor.hidden) editor.querySelector('input[name="title"]')?.focus();
        });
        editor.querySelector('[data-cb-quick-edit-cancel]')?.addEventListener('click', () => {
            editor.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
            trigger.focus();
        });
        const form = editor.querySelector('[data-cb-quick-edit-form]');
        form?.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!form.reportValidity()) return;
            const button = form.querySelector('[type="submit"]');
            const status = form.querySelector('[data-cb-quick-edit-status]');
            if (button) button.disabled = true;
            if (status) status.textContent = '';
            try {
                const body = new FormData(form);
                const response = await fetch(config.ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body
                });
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.data?.message || config.error);
                const label = row.querySelector('[data-cb-rule-title]');
                if (label) label.textContent = result.data.title;
                editor.hidden = true;
                trigger.setAttribute('aria-expanded', 'false');
                trigger.focus();
            } catch (error) {
                if (status) status.textContent = error.message || config.error;
            } finally {
                if (button) button.disabled = false;
            }
        });
    });

    const editor = document.querySelector('[data-cb-recurrence-editor]');
    const preview = document.querySelector('[data-cb-recurrence-preview]');
    if (!editor || !preview) return;

    const advanced = editor.querySelectorAll('[data-cb-work-advanced]');
    const advancedToggle = editor.querySelector('[data-cb-work-advanced-toggle]');
    if (advancedToggle) {
        let expanded = advancedToggle.getAttribute('aria-expanded') === 'true';
        const syncAdvanced = () => {
            advanced.forEach((row) => { row.hidden = !expanded; });
            advancedToggle.setAttribute('aria-expanded', String(expanded));
        };
        advancedToggle.addEventListener('click', () => {
            expanded = !expanded;
            syncAdvanced();
        });
        syncAdvanced();
    }

    const value = (name) => editor.elements.namedItem('recurrence[' + name + ']')?.value || '';
    const project = editor.elements.namedItem('recurrence[project_id]');
    const title = preview.querySelector('[data-cb-preview-title]');
    const projectLabel = preview.querySelector('[data-cb-preview-project]');
    const estimate = preview.querySelector('[data-cb-preview-estimate]');
    const dates = preview.querySelector('[data-cb-preview-dates]');
    let pending = 0;
    let requestId = 0;

    const updateSummary = () => {
        if (title) title.textContent = value('title') || '—';
        if (estimate) estimate.textContent = value('estimated_minutes') || '0';
        if (projectLabel && project) {
            projectLabel.textContent = project.options[project.selectedIndex]?.textContent?.trim() || '—';
        }
    };

    const refreshDates = async () => {
        const current = ++requestId;
        if (dates) dates.textContent = config.loading;
        const body = new FormData(editor);
        body.set('action', 'cb_work_preview_recurrence_rule');
        body.set('_ajax_nonce', config.previewNonce);
        body.set('rule_id', editor.dataset.ruleId || '0');
        try {
            const response = await fetch(config.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body
            });
            const result = await response.json();
            if (current !== requestId) return;
            if (!response.ok || !result.success) throw new Error(result.data?.message || config.error);
            if (dates) {
                dates.replaceChildren();
                const items = result.data?.dates || [];
                items.forEach((item) => {
                    const row = document.createElement('li');
                    row.textContent = item.label;
                    dates.appendChild(row);
                });
                if (!items.length) dates.textContent = '—';
            }
        } catch (error) {
            if (current === requestId && dates) dates.textContent = error.message || config.error;
        }
    };
    const onChange = () => {
        updateSummary();
        window.clearTimeout(pending);
        pending = window.setTimeout(refreshDates, 350);
    };
    editor.addEventListener('input', onChange);
    editor.addEventListener('change', onChange);
    updateSummary();
})();

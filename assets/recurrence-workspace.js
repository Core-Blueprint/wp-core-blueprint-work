/* Recurring Work admin workspace: read-only canonical preview and governed quick edit. */
(() => {
    'use strict';

    const config = window.cbWorkRecurrenceUi;
    if (!config || typeof config.ajaxUrl !== 'string') return;

    document.querySelectorAll('form').forEach((form) => {
        if (form.matches('[data-cb-inline-toggle], [data-cb-editor-toggle]')) return;
        const activate = form.querySelector('[data-cb-work-confirm]');
        if (!activate) return;
        form.addEventListener('submit', (event) => {
            if (!window.confirm(activate.textContent.trim() + '?')) event.preventDefault();
        });
    });

    document.querySelectorAll('[data-cb-inline-toggle]').forEach((form) => {
        const row = form.closest('[data-cb-rule-row]');
        const button = form.querySelector('button[type="submit"]');
        const activeInput = form.querySelector('input[name="active"]');
        const badge = row?.querySelector('[data-cb-rule-status]');
        if (!row || !button || !activeInput || !badge) return;
        const status = document.createElement('span');
        status.setAttribute('role', 'status');
        status.className = 'cb-work-recurrence-inline-status';
        form.after(status);
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const activating = activeInput.value === '1';
            if (activating && !window.confirm(button.textContent.trim() + '?')) return;
            const data = new FormData(form);
            data.set('action', 'cb_work_toggle_recurrence_rule_inline');
            button.disabled = true;
            status.textContent = '';
            try {
                const response = await fetch(config.ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: data
                });
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.data?.message || config.error);
                badge.textContent = result.data.status;
                badge.classList.toggle('cb-work-recurrence-status--active', result.data.active);
                badge.classList.toggle('cb-work-recurrence-status--inactive', !result.data.active);
                button.textContent = result.data.action;
                activeInput.value = result.data.active ? '0' : '1';
                if (result.data.active) button.removeAttribute('data-cb-work-confirm');
                else button.setAttribute('data-cb-work-confirm', '');
                const filter = new URLSearchParams(window.location.search).get('cb_status');
                if (filter === 'active' || filter === 'inactive') window.location.reload();
            } catch (error) {
                status.textContent = error.message || config.error;
            } finally {
                button.disabled = false;
            }
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
                if (new URLSearchParams(window.location.search).get('cb_sort') === 'title') {
                    window.location.reload();
                    return;
                }
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

    const editorToggle = document.querySelector('[data-cb-editor-toggle]');
    const editForm = document.querySelector('[data-cb-recurrence-editor]');
    if (editorToggle && editForm) {
        const button = editorToggle.querySelector('[type="submit"]');
        const activeInput = editorToggle.querySelector('input[name="active"]');
        const feedback = document.createElement('span');
        feedback.setAttribute('role', 'status');
        editorToggle.appendChild(feedback);
        const unsavedNotice = document.querySelector('[data-cb-recurrence-unsaved]');
        const saveButton = document.querySelector('[form="cb-work-recurrence-editor-form"][type="submit"]');
        let dirty = false;
        const markDirty = () => {
            dirty = true;
            if (unsavedNotice) unsavedNotice.hidden = false;
        };
        editForm.addEventListener('input', markDirty);
        editForm.addEventListener('change', markDirty);
        editorToggle.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (dirty) {
                feedback.textContent = config.saveFirst;
                saveButton?.focus();
                return;
            }
            if (!button || !activeInput) return;
            if (activeInput.value === '1' && !window.confirm(button.textContent.trim() + '?')) return;
            const body = new FormData(editorToggle);
            body.set('action', 'cb_work_toggle_recurrence_rule_inline');
            button.disabled = true;
            feedback.textContent = '';
            try {
                const response = await fetch(config.ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body
                });
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.data?.message || config.error);
                document.querySelectorAll('.cb-work-recurrence-editor .cb-work-recurrence-status').forEach((badge) => {
                    badge.textContent = result.data.status;
                    badge.classList.toggle('cb-work-recurrence-status--active', result.data.active);
                    badge.classList.toggle('cb-work-recurrence-status--inactive', !result.data.active);
                });
                button.textContent = result.data.action;
                activeInput.value = result.data.active ? '0' : '1';
                if (result.data.active) button.removeAttribute('data-cb-work-confirm');
                else button.setAttribute('data-cb-work-confirm', '');
            } catch (error) {
                feedback.textContent = error.message || config.error;
            } finally {
                button.disabled = false;
            }
        });
    }

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
    const intervalField = editor.elements.namedItem('recurrence[interval_count]');
    const unitsField = editor.elements.namedItem('recurrence[frequency]');
    const syncUnits = () => {
        if (!intervalField || !unitsField) return;
        const plural = Number(intervalField.value) !== 1;
        Array.from(unitsField.options).forEach((option) => {
            option.textContent = plural ? option.dataset.cbUnitPlural : option.dataset.cbUnitSingular;
        });
    };
    const project = editor.elements.namedItem('recurrence[project_id]');
    const title = preview.querySelector('[data-cb-preview-title]');
    const projectLabel = preview.querySelector('[data-cb-preview-project]');
    const estimate = preview.querySelector('[data-cb-preview-estimate]');
    const schedule = preview.querySelector('[data-cb-preview-schedule]');
    const due = preview.querySelector('[data-cb-preview-due]');
    const dates = preview.querySelector('[data-cb-preview-dates]');
    let pending = 0;
    let requestId = 0;
    const scheduleNames = new Set([
        'recurrence[frequency]',
        'recurrence[interval_count]',
        'recurrence[start_on]',
        'recurrence[end_on]'
    ]);

    const updateSummary = () => {
        syncUnits();
        if (title) title.textContent = value('title') || '—';
        if (estimate) estimate.textContent = value('estimated_minutes') || '0';
        const frequency = unitsField?.selectedOptions?.[0];
        if (schedule && intervalField && frequency) {
            schedule.textContent = config.every + ' ' + (intervalField.value || '1') + ' ' + frequency.textContent.trim();
        }
        if (due) due.textContent = (value('due_offset_days') || '0') + ' ' + config.dueSuffix;
        if (projectLabel && project) {
            projectLabel.textContent = project.options[project.selectedIndex]?.textContent?.trim() || '—';
        }
    };

    const refreshDates = async () => {
        const current = ++requestId;
        if (dates) dates.setAttribute('aria-busy', 'true');
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
        } finally {
            if (current === requestId && dates) dates.removeAttribute('aria-busy');
        }
    };
    const onChange = (event) => {
        updateSummary();
        if (!scheduleNames.has(event.target?.name)) return;
        window.clearTimeout(pending);
        pending = window.setTimeout(refreshDates, 350);
    };
    editor.addEventListener('input', onChange);
    editor.addEventListener('change', onChange);
    updateSummary();
})();

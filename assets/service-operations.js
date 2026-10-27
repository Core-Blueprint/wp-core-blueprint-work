'use strict';
(() => {
    const config = window.cbWorkServiceOperations;
    if (!config) return;
    // Keep the deep-link usable without JavaScript; enhance it by revealing
    // the native details element and focusing the first required selector.
    document.addEventListener('click', event => {
        const trigger = event.target.closest?.('[data-cb-so-open]');
        if (!trigger) return;
        const details = document.getElementById('cb-work-so-add');
        if (!details) return;
        event.preventDefault();
        details.open = true;
        details.scrollIntoView?.({block: 'start', behavior: 'smooth'});
        details.querySelector('select[name="project_id"]')?.focus({preventScroll: true});
    });
    // Project context is authoritative server-side; the client only adjusts
    // the optional billing controls to avoid asking Internal users for invoices.
    function updateServiceOptions(form) {
        const project = form.querySelector('[data-cb-so-project-context]');
        const service = form.querySelector('[data-cb-so-service-id]');
        const rulePicker = form.querySelector('[data-cb-so-rule-picker]');
        if (!project || !service || !rulePicker) return;
        const projectId = project.value || '';
        const serviceId = service.value || '';
        const context = project.selectedOptions?.[0]?.dataset?.context || '';
        for (const option of rulePicker.options) {
            if (!option.value) continue;
            const compatible = !!projectId && !!serviceId
                && option.dataset?.project === projectId
                && option.dataset?.service === serviceId
                && option.dataset?.context === context;
            option.disabled = !compatible;
            option.hidden = !compatible;
            if (!compatible && option.selected) rulePicker.value = '0';
        }
    }
    function updateBillingVisibility(project) {
        const form = project.closest('form');
        const fields = form?.querySelector('[data-cb-so-billing-fields]');
        if (!fields) return;
        const context = project.selectedOptions?.[0]?.dataset?.context || '';
        fields.hidden = context === 'internal';
        const checkbox = fields.querySelector('[name="billing_enabled"]');
        if (checkbox) checkbox.disabled = context === 'internal';
        if (form) updateServiceOptions(form);
    }
    document.querySelectorAll?.('[data-cb-so-project-context]').forEach(updateBillingVisibility);
    document.addEventListener('change', event => {
        const project = event.target.closest?.('[data-cb-so-project-context]');
        if (project) updateBillingVisibility(project);
        else if (event.target.closest?.('[data-cb-so-service-id]')) {
            const form = event.target.closest('form');
            if (form) updateServiceOptions(form);
        }
    });
    document.addEventListener('submit', async event => {
        const form = event.target.closest('form[data-cb-so-invoice]');
        if (!form || !window.fetch) return;
        event.preventDefault();
        const button = form.querySelector('button[type="submit"]');
        const feedback = form.querySelector('[data-cb-so-feedback]');
        if (button.disabled) return;
        button.disabled = true;
        feedback.textContent = '';
        try {
            const body = new FormData(form);
            body.set('action', 'cb_work_service_invoice_ajax');
            const response = await fetch(config.ajaxUrl, {
                method: 'POST', credentials: 'same-origin', body, headers: {Accept: 'application/json'}
            });
            const result = await response.json();
            if (!response.ok || !result.success || !result.data?.registration) {
                throw new Error(result.data?.code || 'request_failed');
            }
            const registration = result.data.registration;
            form.elements.revision.value = String(registration.revision);
            const badge = form.closest('td')?.querySelector('[data-cb-so-billing-label]');
            if (badge) {
                const previous = badge.textContent === config.invoiced ? 'invoiced' : 'not_recorded';
                badge.textContent = registration.state === 'invoiced' ? config.invoiced : config.notRecorded;
                if (previous !== registration.state) {
                    for (const [state, delta] of [[previous, -1], [registration.state, 1]]) {
                        const counter = document.querySelector('[data-cb-so-billing-count="' + state + '"]');
                        if (counter) counter.textContent = String(Math.max(0, Number(counter.textContent) + delta));
                    }
                }
            }
            feedback.textContent = config.saved;
        } catch (error) {
            feedback.textContent = config.error + (error.message ? ' (' + error.message + ')' : '');
        } finally {
            button.disabled = false;
        }
    });
})();
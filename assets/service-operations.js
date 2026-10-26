'use strict';
(() => {
    const config = window.cbWorkServiceOperations;
    if (!config) return;
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
            if (badge) badge.textContent = registration.state === 'invoiced' ? config.invoiced : config.notRecorded;
            feedback.textContent = config.saved;
        } catch (error) {
            feedback.textContent = config.error + (error.message ? ' (' + error.message + ')' : '');
        } finally {
            button.disabled = false;
        }
    });
})();
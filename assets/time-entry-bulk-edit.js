/* T2-C: progressive Time Entries bulk-edit enhancement; server remains authoritative. */
(() => {
    'use strict';

    const host = document.querySelector('.cb-work-time-view--entries');
    if (!host) return;

    let busy = false;
    const list = () => host.querySelector('.cb-work-time-entries');
    const form = () => host.querySelector('[data-cb-work-time-bulk-form]');
    const rowBoxes = () => Array.from(host.querySelectorAll('[data-cb-work-time-bulk-select]'));
    const feedback = () => list()?.dataset.cbWorkTimeAsyncError || 'An error occurred. Please try again.';

    function controls() {
        const bulk = form();
        if (!bulk) return;
        const boxes = rowBoxes();
        const selected = boxes.filter(box => box.checked);
        const selectAll = list().querySelector('[data-cb-work-time-bulk-select-all]');
        const mode = bulk.querySelector('[data-cb-work-time-bulk-mode]');
        const target = bulk.querySelector('[data-cb-work-time-bulk-target]');
        const note = bulk.querySelector('[data-cb-work-time-bulk-note]');
        const submit = bulk.querySelector('[data-cb-work-time-bulk-submit]');
        const counter = bulk.querySelector('[data-cb-work-time-bulk-count]');

        if (selectAll) {
            selectAll.disabled = boxes.length === 0;
            selectAll.checked = boxes.length > 0 && selected.length === boxes.length;
            selectAll.indeterminate = selected.length > 0 && selected.length < boxes.length;
        }
        if (counter) counter.textContent = String(selected.length);
        boxes.forEach(box => box.closest('tr')?.classList.toggle('is-selected', box.checked));

        // Match the Work Items selection-driven bulk toolbar. Time Entries
        // deliberately requires at least two selected entries for Bulk Edit.
        bulk.hidden = selected.length < 2;
        if (bulk.hidden) {
            bulk.querySelector('[data-cb-work-time-bulk-editor]')?.removeAttribute('open');
        }

        const hasNoteChange = mode?.value !== 'keep';
        const needsNoteText = mode?.value === 'append' || mode?.value === 'replace';
        if (note) {
            note.disabled = !needsNoteText;
            note.required = needsNoteText;
        }
        const hasTargetChange = target && target.value !== '0';
        if (submit) {
            submit.disabled = busy || selected.length === 0
                || (!hasTargetChange && !hasNoteChange)
                || (needsNoteText && !note?.value.trim());
        }
    }

    function notice(source = null) {
        host.querySelectorAll('.cb-work-time-bulk-request-notice').forEach(node => node.remove());
        const bulk = form();
        // After a successful save, the replacement list has no selection and
        // hides the bulk form. Keep the success/partial notice visible above it.
        const container = bulk && !bulk.hidden ? bulk : list();
        if (!container) return;
        const div = source ? document.importNode(source, true) : document.createElement('div');
        div.classList.add('cb-work-time-bulk-request-notice');
        if (!source) {
            div.classList.add('notice', 'notice-error', 'inline');
            const p = document.createElement('p');
            p.textContent = feedback();
            div.append(p);
        }
        div.setAttribute('role', div.classList.contains('notice-error') ? 'alert' : 'status');
        div.tabIndex = -1;
        container.prepend(div);
        div.focus({ preventScroll: true });
    }

    // Fallback without JS: the <noscript> override reveals the ordinary POST form.
    // With JS enabled, the form can save without navigation and refresh the canonical list.
    controls();

    host.addEventListener('change', event => {
        const target = event.target;
        if (target.matches('[data-cb-work-time-bulk-select-all]')) {
            rowBoxes().forEach(box => { box.checked = target.checked; });
        }
        if (target.matches('[data-cb-work-time-bulk-select], [data-cb-work-time-bulk-select-all], [data-cb-work-time-bulk-mode], [data-cb-work-time-bulk-target]')) {
            controls();
        }
    });
    host.addEventListener('input', event => {
        if (event.target.matches('[data-cb-work-time-bulk-note]')) controls();
    });

    host.addEventListener('submit', async event => {
        const bulk = event.target;
        if (!bulk.matches('[data-cb-work-time-bulk-form]')) return;
        event.preventDefault();
        if (busy) return;
        controls();
        const submit = bulk.querySelector('[data-cb-work-time-bulk-submit]');
        if (!submit || submit.disabled || !bulk.reportValidity()) return;

        busy = true;
        controls();
        try {
            const response = await fetch(bulk.action, {
                method: 'POST',
                credentials: 'same-origin',
                redirect: 'follow',
                body: new FormData(bulk)
            });
            const url = new URL(response.url, window.location.href);
            if (!response.ok || url.origin !== window.location.origin
                || !url.pathname.endsWith('/admin.php')
                || url.searchParams.get('view') !== 'entries') {
                throw new Error('Unexpected bulk edit response');
            }
            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            const updatedList = page.querySelector('.cb-work-time-entries');
            const serverNotice = page.querySelector('.cb-work-time-page > .notice');
            const outcome = url.searchParams.get('cb-work-notice');
            if (!updatedList || !serverNotice || !outcome) throw new Error('Bulk update outcome missing');

            if (outcome === 'time-bulk-updated' || outcome === 'time-bulk-partial') {
                const current = list();
                if (!current) throw new Error('Time Entries list missing');
                current.replaceWith(document.importNode(updatedList, true));
                controls();
                notice(serverNotice);
                url.searchParams.delete('cb-work-notice');
                url.searchParams.delete('te_updated');
                url.searchParams.delete('te_failed');
                url.searchParams.delete('te_edit');
                window.history.replaceState(window.history.state, '', url.href);
            } else {
                // Failed preflight: selection and input remain available to correct.
                notice(serverNotice);
            }
        } catch {
            notice();
        } finally {
            busy = false;
            controls();
        }
    });
})();

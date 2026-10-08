/* Progressive enhancement for Time Entries. The canonical admin-post action owns all writes. */
(() => {
    'use strict';

    const host = document.querySelector('.cb-work-time-view--entries');
    if (!host) return;

    let busy = false;
    const currentList = () => host.querySelector('.cb-work-time-entries');
    const toggleSelector = 'a[data-cb-work-time-quick-edit-toggle]';

    function formatDuration(seconds) {
        const pad = value => String(value).padStart(2, '0');
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        return pad(hours) + ':' + pad(minutes) + ':' + pad(seconds % 60);
    }

    function updatePreview(form) {
        const input = name => form.elements.namedItem('time[' + name + ']')?.value || '';
        const output = form.querySelector('[data-cb-work-time-quick-edit-duration]');
        if (!output) return;

        const start = new Date(input('start_date') + 'T' + input('start_time'));
        const end = new Date(input('end_date') + 'T' + input('end_time'));
        const seconds = Math.floor((end.getTime() - start.getTime()) / 1000);

        // Advisory only: WordPress validates the site timezone and saves the final duration.
        output.textContent = Number.isFinite(seconds) && seconds > 0
            ? '~' + formatDuration(seconds)
            : '—';
    }

    function attachPreview(form) {
        if (!form || form.dataset.cbWorkPreviewBound === '1') return;
        form.dataset.cbWorkPreviewBound = '1';
        form.addEventListener('input', event => {
            if (event.target.matches('input[type="date"], input[type="time"]')) {
                updatePreview(form);
            }
        });
        form.addEventListener('change', event => {
            if (event.target.matches('input[type="date"], input[type="time"]')) {
                updatePreview(form);
            }
        });
    }

    function errorMessage() {
        return currentList()?.dataset.cbWorkTimeAsyncError || 'An error occurred. Please try again.';
    }

    function clearNotices() {
        host.querySelectorAll('.cb-work-time-request-notice').forEach(node => node.remove());
    }

    function report(target, serverNotice = null) {
        clearNotices();
        const notice = serverNotice
            ? document.importNode(serverNotice, true)
            : document.createElement('div');
        notice.classList.add('cb-work-time-request-notice');
        if (!serverNotice) {
            notice.classList.add('notice', 'notice-error', 'inline');
            const paragraph = document.createElement('p');
            paragraph.textContent = errorMessage();
            notice.append(paragraph);
        }
        notice.setAttribute('role', notice.classList.contains('notice-error') ? 'alert' : 'status');
        notice.tabIndex = -1;
        target.prepend(notice);
        notice.focus({ preventScroll: true });
    }

    function closeEditor(restoreFocus = false) {
        const row = host.querySelector('.cb-work-time-quick-edit-row');
        if (!row) return;
        const trigger = row.previousElementSibling?.querySelector(toggleSelector);
        const cancel = row.querySelector('.cb-work-time-quick-edit-actions a[href]');
        row.remove();
        if (trigger) {
            trigger.setAttribute('aria-expanded', 'false');
            trigger.removeAttribute('aria-controls');
        }
        if (cancel) {
            // Keep the current filters without reloading or adding a history entry.
            window.history.replaceState(window.history.state, '', cancel.href);
        }
        if (restoreFocus) trigger?.focus({ preventScroll: true });
    }

    async function readEntries(response) {
        const url = new URL(response.url, window.location.href);
        if (!response.ok || url.origin !== window.location.origin
            || !url.pathname.endsWith('/admin.php')
            || url.searchParams.get('view') !== 'entries') {
            throw new Error('Unexpected Time Entries response');
        }
        const html = await response.text();
        const page = new DOMParser().parseFromString(html, 'text/html');
        if (!page.querySelector('.cb-work-time-entries')) {
            throw new Error('Time Entries content missing');
        }
        return { page, url };
    }

    // Initial server-rendered deep links remain usable without JavaScript.
    host.querySelectorAll('[data-cb-work-time-quick-edit] form').forEach(attachPreview);

    host.addEventListener('click', async event => {
        const cancel = event.target.closest('.cb-work-time-quick-edit-actions a[href]');
        if (cancel && host.contains(cancel)) {
            if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
            event.preventDefault();
            if (!busy) {
                clearNotices();
                closeEditor(true);
            }
            return;
        }

        const trigger = event.target.closest(toggleSelector);
        if (!trigger || !host.contains(trigger)) return;
        if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        if (busy) return;

        const previous = host.querySelector('.cb-work-time-quick-edit-row');
        if (previous && previous.previousElementSibling === trigger.closest('tr')) {
            clearNotices();
            closeEditor(true);
            return;
        }
        clearNotices();
        closeEditor();
        busy = true;
        trigger.setAttribute('aria-busy', 'true');

        try {
            const response = await fetch(trigger.href, { credentials: 'same-origin' });
            const { page } = await readEntries(response);
            const editorRow = page.querySelector('.cb-work-time-quick-edit-row');
            const expectedId = new URL(trigger.href, window.location.href).searchParams.get('te_edit');
            const editor = editorRow?.querySelector('[data-cb-work-time-quick-edit]');
            if (!editor || editor.id !== 'cb-work-time-quick-edit-' + expectedId) {
                throw new Error('Requested editor unavailable');
            }
            const row = document.importNode(editorRow, true);
            trigger.closest('tr').after(row);
            trigger.setAttribute('aria-expanded', 'true');
            trigger.setAttribute('aria-controls', editor.id);
            attachPreview(row.querySelector('form'));
            window.history.replaceState(window.history.state, '', trigger.href);
            row.querySelector('input[type="date"]')?.focus({ preventScroll: true });
        } catch {
            report(currentList());
        } finally {
            trigger.removeAttribute('aria-busy');
            busy = false;
        }
    });

    host.addEventListener('submit', async event => {
        const form = event.target;
        if (!form.matches('[data-cb-work-time-quick-edit] form')) return;
        event.preventDefault();
        if (busy || !form.reportValidity()) return;

        busy = true;
        clearNotices();
        form.setAttribute('aria-busy', 'true');
        const submit = form.querySelector('button[type="submit"]');
        if (submit) submit.disabled = true;

        try {
            // Fetch follows the existing admin-post redirect. No second write API,
            // and all nonce, capability, revision and audit checks remain server-side.
            // Read the actual attribute: the WordPress hidden input named "action"
            // can shadow HTMLFormElement.action and become the fetch URL.
            const actionUrl = form.getAttribute('action');
            if (!actionUrl) throw new Error('Time Quick Edit action URL missing');
            const response = await fetch(actionUrl, {
                method: 'POST',
                body: new FormData(form),
                credentials: 'same-origin',
                redirect: 'follow'
            });
            const { page, url } = await readEntries(response);
            const outcome = url.searchParams.get('cb-work-notice');
            const serverNotice = page.querySelector('.cb-work-time-page > .notice');
            if (!outcome || !serverNotice) {
                throw new Error('Time Entries save outcome missing');
            }

            if (outcome === 'time-updated') {
                // Re-render from the authoritative server query: duration, totals,
                // row order and pagination may all change following a correction.
                const replacement = document.importNode(page.querySelector('.cb-work-time-entries'), true);
                const list = currentList();
                if (!list) throw new Error('Time Entries view unavailable');
                list.replaceWith(replacement);
                report(replacement, serverNotice);
                url.searchParams.delete('cb-work-notice');
                url.searchParams.delete('te_edit');
                window.history.replaceState(window.history.state, '', url.href);
            } else {
                // Preserve unsaved input when validation, authorization or the
                // optimistic revision guard rejects a change.
                report(form, serverNotice);
            }
        } catch {
            report(form);
        } finally {
            form.removeAttribute('aria-busy');
            if (submit) submit.disabled = false;
            busy = false;
        }
    });
})();

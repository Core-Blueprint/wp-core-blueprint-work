/* Core Blueprint Work: global Time HUD. Server owns all timer mutations. */
(() => {
    'use strict';

    const root = document.querySelector('[data-cb-time-hud]');
    const config = window.cbWorkTimerHud;
    if (!root || !config || !config.endpoint || !config.nonce) return;

    // Server-rendered redirect notices are useful once, not indefinitely.
    // Keep the current Time view, entry ID and other query parameters intact.
    if (document.querySelector('.cb-work-time-page')) {
        const address = new URL(window.location.href);
        if (address.searchParams.has('cb-work-notice')) {
            address.searchParams.delete('cb-work-notice');
            window.history.replaceState(window.history.state, '', address.pathname + address.search + address.hash);
        }
    }

    const toggle = root.querySelector('[data-cb-time-hud-toggle]');
    const panel = root.querySelector('[data-cb-time-hud-panel]');
    const closeButton = root.querySelector('[data-cb-time-hud-close]');
    const stopButton = root.querySelector('[data-cb-time-hud-stop]');
    const noteForm = root.querySelector('[data-cb-time-hud-note-form]');
    const noteInput = root.querySelector('[data-cb-time-hud-note]');
    const noteSave = root.querySelector('[data-cb-time-hud-note-save]');
    const feedback = root.querySelector('[data-cb-time-hud-feedback]');
    const title = root.querySelector('[data-cb-time-hud-title]');
    const detailTitle = root.querySelector('[data-cb-time-hud-detail-title]');
    const clocks = document.querySelectorAll('[data-cb-time-hud-clock], [data-cb-time-hud-elapsed], [data-cb-work-time-live]');

    let state = { active: false };
    let elapsedAtSync = 0;
    let syncedAt = 0;
    let busy = false;
    let noteDirty = false;
    let lastSync = 0;
    const text = (value) => String(value ?? '');

    const format = (seconds) => {
        const value = Math.max(0, Math.floor(seconds));
        return [Math.floor(value / 3600), Math.floor((value % 3600) / 60), value % 60]
            .map((part) => String(part).padStart(2, '0')).join(':');
    };

    const clock = () => {
        if (!state.active) return;
        const elapsed = elapsedAtSync + Math.max(0, (performance.now() - syncedAt) / 1000);
        clocks.forEach((node) => { node.textContent = format(elapsed); });
    };

    const announce = (message) => {
        feedback.textContent = text(message);
    };

    const close = (restoreFocus = true) => {
        if (panel.hidden) return true;
        if (noteDirty && !window.confirm(config.strings.discardNote)) return false;
        panel.hidden = true;
        toggle.setAttribute('aria-expanded', 'false');
        if (restoreFocus && !root.hidden) toggle.focus();
        return true;
    };

    const apply = (next, forceNote = false) => {
        if (!next || typeof next.active !== 'boolean') return;
        const oldId = Number(state.entryId || 0);
        const newId = Number(next.entryId || 0);
        state = next;
        root.hidden = !state.active;
        if (!state.active) {
            clocks.forEach((node) => { node.textContent = '00:00:00'; });
            // The page-level Time banner was server-rendered and must not remain
            // stale after another tab (or this HUD) completes the timer.
            document.querySelector('.cb-work-time-running-status')?.remove();
            panel.hidden = true;
            toggle.setAttribute('aria-expanded', 'false');
            noteDirty = false;
            return;
        }
        elapsedAtSync = Math.max(0, Number(next.serverEpoch) - Number(next.startedEpoch));
        syncedAt = performance.now();
        title.textContent = text(next.title);
        detailTitle.textContent = text(next.title);
        if (oldId !== newId || forceNote || !noteDirty) {
            noteInput.value = text(next.note);
            noteDirty = false;
        }
        clock();
    };

    const request = async (action, fields = {}) => {
        const form = new FormData();
        form.set('action', 'cb_work_timer_hud_' + action);
        form.set('nonce', config.nonce);
        Object.entries(fields).forEach(([key, value]) => { form.set(key, String(value)); });
        const response = await fetch(config.endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            body: form,
            cache: 'no-store'
        });
        const result = await response.json();
        if (!response.ok || !result.success) {
            throw new Error(result?.data?.message || config.strings.failed);
        }
        return result.data;
    };

    const sync = async () => {
        if (busy || document.visibilityState === 'hidden') return;
        try {
            const result = await request('state');
            apply(result);
            lastSync = Date.now();
        } catch {
            // Preserve the last known display without claiming a successful sync.
            announce(config.strings.syncFailed);
        }
    };

    const mutate = async (action, fields = {}) => {
        if (busy) return false;
        busy = true;
        stopButton.disabled = true;
        noteSave.disabled = true;
        announce('');
        try {
            const result = await request(action, fields);
            apply(result, true);
            lastSync = Date.now();
            if (action === 'note') {
                announce(config.strings.noteSaved);
            } else {
                // Timer-only view renders a server-side Stop form; refresh it
                // after a successful HUD Stop. Do not reload Manual Entry drafts.
                const timerStopForm = document.querySelector('.cb-work-time-view--timer input[name="action"][value="cb_work_stop_timer"]');
                if (timerStopForm) {
                    window.location.reload();
                    return true;
                }
                // On Work screens the shared Base Toast is authoritative.
                // Keep the existing HUD toast only as fallback on other admin screens.
                if (window.cbWorkToast?.showMessage(config.strings.stopped, 'success')) {
                    return true;
                }
                const toast = document.querySelector('[data-cb-time-hud-toast]');
                if (toast) {
                    toast.textContent = config.strings.stopped;
                    toast.hidden = false;
                    window.setTimeout(() => { toast.hidden = true; }, 5000);
                }
            }
            return true;
        } catch (error) {
            announce(error.message || config.strings.failed);
            return false;
        } finally {
            busy = false;
            stopButton.disabled = false;
            noteSave.disabled = false;
        }
    };

    toggle.addEventListener('click', () => {
        const opening = panel.hidden;
        if (!opening && !close()) return;
        if (opening) {
            panel.hidden = false;
            toggle.setAttribute('aria-expanded', 'true');
            noteInput.focus();
            sync();
        }
    });
    closeButton.addEventListener('click', () => close());
    root.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !panel.hidden) {
            event.preventDefault();
            close();
        }
    });
    document.addEventListener('pointerdown', (event) => {
        if (!panel.hidden && !root.contains(event.target)) close(false);
    });

    noteInput.addEventListener('input', () => { noteDirty = true; announce(''); });
    noteForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!state.active) return;
        await mutate('note', { entry_id: state.entryId, note: noteInput.value });
    });
    stopButton.addEventListener('click', async () => {
        if (!state.active) return;
        if (noteDirty) {
            announce(config.strings.saveBeforeStop);
            noteInput.focus();
            return;
        }
        if (!window.confirm(config.strings.stopConfirm)) return;
        await mutate('stop');
    });

    apply(config.initial || { active: false });
    window.setInterval(clock, 1000);
    window.setInterval(() => { if (Date.now() - lastSync > 55000) sync(); }, 60000);
    window.addEventListener('focus', sync);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') sync();
    });
    // An initial fetch reconciles changes made by other tabs after HTML rendered.
    sync();
})();

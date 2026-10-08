/* Progressive enhancement only. Server validation and duration remain authoritative. */
(() => {
    'use strict';

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

        // Browser-local calculation is advisory; WordPress computes the final
        // duration in its configured site timezone and validates it on save.
        output.textContent = Number.isFinite(seconds) && seconds > 0
            ? '~' + formatDuration(seconds)
            : '—';
    }

    document.querySelectorAll('[data-cb-work-time-quick-edit] form').forEach(form => {
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
    });
})();

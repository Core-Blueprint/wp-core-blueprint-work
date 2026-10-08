/**
 * Work feedback adapter for Base's public Toast Foundation.
 *
 * Base owns toast DOM, presentation, accessibility and lifecycle. Work owns
 * only which domain notices are transient and what semantic variant they use.
 * Server-rendered notices remain the no-JS fallback.
 */
import { toast } from '@cb-core/toast';

const SELECTOR = '[data-cb-work-toast]';
const VARIANTS = new Set(['success', 'error', 'warning', 'info']);

function showMessage(message, variant = 'info', options = {}) {
    const text = String(message || '').trim();
    if (!text) return false;
    const type = VARIANTS.has(variant) ? variant : 'info';
    toast(text, type, options);
    return true;
}

function showNotice(node) {
    if (!node?.matches?.(SELECTOR)) return false;
    const variant = node.getAttribute('data-cb-work-toast');
    const message = (node.querySelector('p')?.textContent || node.textContent || '').trim();
    return showMessage(message, variant, {
        // Operational errors and partial results must remain until dismissed.
        persistent: variant === 'error' || variant === 'warning'
    });
}

// Async editors are classic scripts. They may call the Work adapter only
// after it becomes available, otherwise retain their existing inline notice.
window.cbWorkToast = Object.freeze({ showNotice, showMessage });

let shown = false;
document.querySelectorAll(SELECTOR).forEach((node) => {
    if (!showNotice(node)) return;
    node.remove();
    shown = true;
});

// Do not repeat one-off redirect notifications after refresh. Query state
// for filters, edit IDs and other domains is intentionally untouched.
if (shown) {
    const url = new URL(window.location.href);
    for (const key of ['cb-work-notice', 'cb-work-quick-notice']) {
        url.searchParams.delete(key);
    }
    window.history.replaceState(window.history.state, '', url.href);
}

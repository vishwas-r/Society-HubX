/**
 * SHUBX Core Utilities
 * Shared between Admin App and Resident Dashboard.
 */
(function ($) {
    'use strict';

    /**
     * Show Global Toast
     * @param {string} msg 
     * @param {string} type 'success' | 'error'
     */
    window.SHUBXShowToast = function (msg, type = 'success') {
        const toastEl = document.getElementById('shubx-global-toast');
        const iconEl = document.getElementById('shubx-toast-icon');
        const msgEl = document.getElementById('shubx-toast-message');
        if (!toastEl || !msgEl || !iconEl) {
            console.warn('SHUBX: Toast elements missing from DOM');
            return;
        }

        // Reset classes
        toastEl.classList.remove('bg-success', 'bg-danger', 'text-white');
        iconEl.classList.remove('bi-check-circle-fill', 'bi-exclamation-triangle-fill');

        if (type === 'success') {
            toastEl.classList.add('bg-success', 'text-white');
            iconEl.classList.add('bi-check-circle-fill');
        } else {
            toastEl.classList.add('bg-danger', 'text-white');
            iconEl.classList.add('bi-exclamation-triangle-fill');
        }

        msgEl.textContent = msg;

        // Use Bootstrap API if available
        if (window.bootstrap && bootstrap.Toast) {
            const toast = new bootstrap.Toast(toastEl, { delay: 3000 });
            toast.show();
        } else {
            // Fallback: simple visibility
            toastEl.classList.add('show');
            setTimeout(() => toastEl.classList.remove('show'), 3000);
        }
    };

    /**
     * Global AJAX API Wrapper
     * @param {string} action WordPress AJAX action
     * @param {object} data   Payload data
     * @returns {Promise}
     */
    window.SHUBXApiRequest = function (action, data = {}) {
        return SHUBX.ajax({
            action: action,
            data: data
        });
    };

    /**
     * Debounce helper
     */
    window.SHUBXDebounce = function (func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    };

})(jQuery);

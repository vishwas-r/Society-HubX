/**
 * SGVX Core Utilities
 * Shared between Admin App and Resident Dashboard.
 */
(function ($) {
    'use strict';

    /**
     * Show Global Toast
     * @param {string} msg 
     * @param {string} type 'success' | 'error'
     */
    window.sgvxShowToast = function (msg, type = 'success') {
        const toastEl = document.getElementById('sgvx-global-toast');
        const iconEl = document.getElementById('sgvx-toast-icon');
        const msgEl = document.getElementById('sgvx-toast-message');
        if (!toastEl || !msgEl || !iconEl) {
            console.warn('SGVX: Toast elements missing from DOM');
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
    window.sgvxApiRequest = async function (action, data = {}) {
        let formData;

        if (data instanceof FormData) {
            formData = data;
        } else {
            formData = new FormData();
            for (const [key, val] of Object.entries(data)) {
                if (Array.isArray(val)) {
                    val.forEach(item => formData.append(key + '[]', item));
                } else if (val !== null && val !== undefined) {
                    formData.append(key, val);
                }
            }
        }

        if (!formData.has('action')) formData.append('action', action);

        // Auto-inject Nonce if missing
        if (!formData.has('_wpnonce')) {
            // Fallback to various data sources if global isn't set
            const nonce = (typeof sgvx51_nonce !== 'undefined') ? sgvx51_nonce : '';
            if (nonce) formData.append('_wpnonce', nonce);
        }

        try {
            const response = await fetch(ajaxurl, {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                const text = await response.text();
                throw new Error(text || 'Network response was not ok');
            }

            const result = await response.json();

            if (result.success) {
                if (result.data && result.data.message) {
                    window.sgvxShowToast(result.data.message, 'success');
                }
                return result.data || true;
            } else {
                const errorMsg = result.data && result.data.message ? result.data.message : 'Operation failed';
                window.sgvxShowToast(errorMsg, 'error');
                throw new Error(errorMsg);
            }
        } catch (error) {
            console.error('SGVX API Error:', error);
            window.sgvxShowToast(error.message, 'error');
            throw error;
        }
    };

    /**
     * Debounce helper
     */
    window.sgvxDebounce = function (func, wait) {
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

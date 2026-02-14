/**
 * SGVX Toast Notification System
 * Lightweight toast notification library for Society GovernX
 * 
 * Usage:
 * SGVX.toast.success('Operation completed!');
 * SGVX.toast.error('An error occurred');
 * SGVX.toast.warning('Please review');
 * SGVX.toast.info('New notification');
 */
(function () {
    'use strict';

    // Create SGVX namespace if it doesn't exist
    window.SGVX = window.SGVX || {};

    /**
     * Toast Notification API
     */
    window.SGVX.toast = {
        /**
         * Show success toast
         * @param {string} message Toast message
         * @param {number} duration Duration in ms (default: 3000)
         */
        success: function (message, duration = 3000) {
            this.show(message, 'success', duration);
        },

        /**
         * Show error toast
         * @param {string} message Toast message
         * @param {number} duration Duration in ms (default: 4000)
         */
        error: function (message, duration = 4000) {
            this.show(message, 'error', duration);
        },

        /**
         * Show warning toast
         * @param {string} message Toast message
         * @param {number} duration Duration in ms (default: 3500)
         */
        warning: function (message, duration = 3500) {
            this.show(message, 'warning', duration);
        },

        /**
         * Show info toast
         * @param {string} message Toast message
         * @param {number} duration Duration in ms (default: 3000)
         */
        info: function (message, duration = 3000) {
            this.show(message, 'info', duration);
        },

        /**
         * Show toast notification
         * @param {string} message Toast message
         * @param {string} type Toast type: success|error|warning|info
         * @param {number} duration Duration in ms
         */
        show: function (message, type = 'success', duration = 3000) {
            const toastEl = document.getElementById('sgvx-global-toast');
            const iconEl = document.getElementById('sgvx-toast-icon');
            const msgEl = document.getElementById('sgvx-toast-message');

            if (!toastEl || !msgEl || !iconEl) {
                console.warn('SGVX Toast: Toast elements missing from DOM');
                // Fallback to alert if toast not available
                alert(message);
                return;
            }

            // Reset all classes
            toastEl.classList.remove('bg-success', 'bg-danger', 'bg-warning', 'bg-info', 'text-white', 'text-dark');
            iconEl.classList.remove('bi-check-circle-fill', 'bi-exclamation-triangle-fill', 'bi-info-circle-fill', 'bi-exclamation-circle-fill');

            // Apply type-specific styling
            switch (type) {
                case 'success':
                    toastEl.classList.add('bg-success', 'text-white');
                    iconEl.classList.add('bi-check-circle-fill');
                    break;
                case 'error':
                    toastEl.classList.add('bg-danger', 'text-white');
                    iconEl.classList.add('bi-exclamation-circle-fill');
                    break;
                case 'warning':
                    toastEl.classList.add('bg-warning', 'text-dark');
                    iconEl.classList.add('bi-exclamation-triangle-fill');
                    break;
                case 'info':
                    toastEl.classList.add('bg-info', 'text-white');
                    iconEl.classList.add('bi-info-circle-fill');
                    break;
            }

            msgEl.textContent = message;

            // Use Bootstrap Toast API if available
            if (window.bootstrap && bootstrap.Toast) {
                const toast = new bootstrap.Toast(toastEl, { delay: duration });
                toast.show();
            } else {
                // Fallback: simple visibility
                toastEl.classList.add('show');
                setTimeout(() => toastEl.classList.remove('show'), duration);
            }
        }
    };

    // Maintain backward compatibility with old API
    window.sgvxShowToast = function (msg, type = 'success') {
        SGVX.toast.show(msg, type);
    };

})();

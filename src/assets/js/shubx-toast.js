/**
 * SHUBX Toast Notification System
 * Lightweight toast notification library for Society HubX
 * 
 * Usage:
 * SHUBX.toast.success('Operation completed!');
 * SHUBX.toast.error('An error occurred');
 * SHUBX.toast.warning('Please review');
 * SHUBX.toast.info('New notification');
 */
(function () {
    'use strict';

    // Create SHUBX namespace if it doesn't exist
    window.SHUBX = window.SHUBX || {};

    /**
     * Toast Notification API
     */
    window.SHUBX.toast = {
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
            const toastEl = document.getElementById('shubx-global-toast');
            const iconEl = document.getElementById('shubx-toast-icon');
            const msgEl = document.getElementById('shubx-toast-message');

            if (!toastEl || !msgEl || !iconEl) {
                console.warn('SHUBX Toast: Toast elements missing from DOM');
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
    window.SHUBXShowToast = function (msg, type = 'success') {
        SHUBX.toast.show(msg, type);
    };

})();

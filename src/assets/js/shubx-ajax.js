/**
 * SHUBX Centralized AJAX Handler
 * Provides automatic loading indicators and toast notifications
 * 
 * Usage:
 * SHUBX.ajax({
 *     action: 'shubx51_some_action',
 *     data: { key: 'value' },
 *     loadingButton: '#saveBtn',
 *     successMessage: 'Saved successfully!',
 *     onSuccess: function(response) { }
 * });
 */
(function ($) {
    'use strict';

    window.SHUBX = window.SHUBX || {};

    // Loading state management
    const loadingState = {
        activeRequests: 0,
        overlay: null,
        buttonStates: new Map()
    };

    /**
     * Show global loading overlay
     */
    function showGlobalLoader() {
        if (!loadingState.overlay) {
            loadingState.overlay = $(`
                <div id="shubx-loading-overlay" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 99999; display: flex; align-items: center; justify-content: center;">
                    <div style="background: white; padding: 2rem; border-radius: 1rem; box-shadow: 0 10px 40px rgba(0,0,0,0.3); text-align: center;">
                        <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-3 mb-0 fw-semibold">Processing...</p>
                    </div>
                </div>
            `);
            $('body').append(loadingState.overlay);
        }
        loadingState.activeRequests++;
        loadingState.overlay.fadeIn(200);
    }

    /**
     * Hide global loading overlay
     */
    function hideGlobalLoader() {
        loadingState.activeRequests = Math.max(0, loadingState.activeRequests - 1);
        if (loadingState.activeRequests === 0 && loadingState.overlay) {
            loadingState.overlay.fadeOut(200);
        }
    }

    /**
     * Show button loading state
     * @param {string|jQuery} button Button selector or jQuery object
     */
    function showButtonLoader(button) {
        const $btn = $(button);
        if (!$btn.length) return;

        // Save original state
        loadingState.buttonStates.set($btn[0], {
            html: $btn.html(),
            disabled: $btn.prop('disabled')
        });

        // Set loading state
        $btn.prop('disabled', true);
        $btn.html(`
            <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
            Loading...
        `);
    }

    /**
     * Hide button loading state
     * @param {string|jQuery} button Button selector or jQuery object
     */
    function hideButtonLoader(button) {
        const $btn = $(button);
        if (!$btn.length) return;

        const savedState = loadingState.buttonStates.get($btn[0]);
        if (savedState) {
            $btn.html(savedState.html);
            $btn.prop('disabled', savedState.disabled);
            loadingState.buttonStates.delete($btn[0]);
        }
    }

    /**
     * Centralized AJAX Request Handler
     * @param {object} options Configuration options
     * @param {string} options.action WordPress AJAX action
     * @param {object} options.data Request data
     * @param {string} options.loadingButton Button selector for button-specific loader
     * @param {boolean} options.showOverlay Show global loading overlay (default: auto)
     * @param {string} options.successMessage Success toast message
     * @param {string} options.errorMessage Fallback error message
     * @param {function} options.onSuccess Success callback
     * @param {function} options.onError Error callback
     * @param {function} options.beforeSend Before send callback
     * @param {function} options.complete Complete callback
     * @returns {Promise}
     */
    window.SHUBX.ajax = function (optionsOrAction, data, extra) {
        let options = {};

        // Support (action, data, extra) signature
        if (typeof optionsOrAction === 'string') {
            options = $.extend({
                action: optionsOrAction,
                data: data || {}
            }, extra || {});
        } else {
            // Support (options) signature
            options = optionsOrAction || {};
        }

        const config = $.extend({
            action: '',
            data: {},
            loadingButton: options.button || null, // Alias: button -> loadingButton
            showOverlay: null,
            successMessage: options.success || null, // Alias: success -> successMessage
            errorMessage: 'An error occurred',
            reload: false, // New: reload after success
            onSuccess: null,
            onError: null,
            beforeSend: null,
            complete: null
        }, options);

        // Auto-determine overlay: show if no specific button, or if explicitly requested
        const shouldShowOverlay = config.showOverlay !== null ? config.showOverlay : !config.loadingButton;

        return new Promise((resolve, reject) => {
            // Prepare data
            const requestData = (config.data instanceof FormData) ? config.data : $.extend({}, config.data);

            if (requestData instanceof FormData) {
                if (!requestData.has('action')) requestData.append('action', config.action);

                // Robust nonce detection for FormData
                if (!requestData.has('_wpnonce') || !requestData.get('_wpnonce')) {
                    const fallbackNonce = (typeof shubx51_nonce !== 'undefined' ? shubx51_nonce : '');
                    const providedNonce = (config.data && typeof config.data.get === 'function') ? config.data.get('_wpnonce') : (config.data ? config.data._wpnonce : null);
                    const finalNonce = providedNonce || fallbackNonce;

                    if (finalNonce) {
                        requestData.set('_wpnonce', finalNonce);
                    }
                }
            } else {
                requestData.action = config.action;
                if (!requestData._wpnonce) {
                    requestData._wpnonce = config.data._wpnonce || (typeof shubx51_nonce !== 'undefined' ? shubx51_nonce : '');
                }
            }

            // Show loading indicators
            if (config.beforeSend) {
                config.beforeSend();
            }

            if (shouldShowOverlay) {
                showGlobalLoader();
            }

            if (config.loadingButton) {
                showButtonLoader(config.loadingButton);
            }

            // Make AJAX request
            const ajaxOptions = {
                url: ajaxurl,
                type: 'POST',
                data: requestData,
                success: function (response) {
                    // Handle success
                    if (response.success) {
                        // Show success toast
                        if (config.successMessage) {
                            SHUBX.toast.success(config.successMessage);
                        } else if (response.data && response.data.message) {
                            SHUBX.toast.success(response.data.message);
                        }

                        // Call success callback
                        if (config.onSuccess) {
                            config.onSuccess(response.data, response);
                        }

                        // Auto-reload if requested
                        if (config.reload) {
                            setTimeout(() => window.location.reload(), 1000);
                        }

                        resolve(response.data);
                    } else {
                        // Handle error response
                        const errorMsg = (response.data && response.data.message) ? response.data.message : config.errorMessage;

                        // Only show toast if not suppressed
                        if (!config.suppressErrorToast) {
                            SHUBX.toast.error(errorMsg);
                        }

                        if (config.onError) {
                            config.onError(errorMsg, response);
                        }

                        reject(new Error(errorMsg));
                    }
                },
                error: function (xhr, status, error) {
                    // Handle network/server error
                    const errorMsg = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
                        ? xhr.responseJSON.data.message
                        : config.errorMessage;

                    // Only show toast if not suppressed
                    if (!config.suppressErrorToast) {
                        SHUBX.toast.error(errorMsg);
                    }

                    if (config.onError) {
                        config.onError(errorMsg, xhr);
                    }

                    reject(new Error(errorMsg));
                },
                complete: function () {
                    // Hide loading indicators
                    if (shouldShowOverlay) {
                        hideGlobalLoader();
                    }

                    if (config.loadingButton) {
                        hideButtonLoader(config.loadingButton);
                    }

                    if (config.complete) {
                        config.complete();
                    }
                }
            };

            // Specialized handling for FormData
            if (requestData instanceof FormData) {
                ajaxOptions.processData = false;
                ajaxOptions.contentType = false;
            }

            $.ajax(ajaxOptions);
        });
    };

})(jQuery);

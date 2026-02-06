/**
 * SGVX Notices JS
 * - AJAX publish via sgvxApiRequest
 * - Centralized delete confirmation
 * - Optimistic UI updates + toasts
 */
(function ($) {
    'use strict';

    // Module configuration (fetched at runtime)
    const Config = {
        nonce: null,
        deleteNonce: null,
        initialized: false
    };

    async function fetchModuleConfig() {
        if (Config.initialized) return;

        try {
            const response = await fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'sgvx51_get_module_config',
                    module: 'notices'
                }).toString()
            });

            if (!response.ok) throw new Error(`HTTP ${response.status}`);

            const result = await response.json();
            if (result.success && result.data) {
                Config.nonce = result.data.nonce || null;
                Config.deleteNonce = result.data.deleteNonce || null;
                Config.initialized = true;
            } else {
                console.error('Failed to fetch module config:', result.data?.message || 'Unknown error');
            }
        } catch (error) {
            console.error('Error fetching module config:', error);
        }
    }

    async function deleteNotice(id) {
        const modalEl = document.getElementById('deleteConfirmModal');
        const confirmBtn = document.getElementById('confirm-delete-btn');
        if (!modalEl || !confirmBtn) {
            if (!confirm('Permanently remove this notice from the board?')) return;
        }

        const modal = new bootstrap.Modal(modalEl);
        const newConfirmBtn = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);

        newConfirmBtn.addEventListener('click', async function () {
            try {
                await sgvxApiRequest('sgvx51_delete_notice', {
                    id: id,
                    _wpnonce: Config.deleteNonce
                });

                const el = document.querySelector(`.sgvx-notice-card[data-id="${id}"]`);
                if (el) {
                    el.style.opacity = '0.5';
                    setTimeout(() => el.remove(), 300);
                } else {
                    window.location.reload();
                }
            } catch (err) { }
            modal.hide();
        });

        modal.show();
    }

    $(function () {
        fetchModuleConfig().then(() => {
            // Submit notice form via AJAX
        const form = document.getElementById('notice-form');
        if (form) {
            form.addEventListener('submit', async function (e) {
                e.preventDefault();
                const submitBtn = form.querySelector('button[type="submit"]');
                const original = submitBtn ? submitBtn.innerHTML : '';
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Publishing...';
                }

                try {
                    const formData = new FormData(form);
                    const data = Object.fromEntries(formData.entries());

                    await sgvxApiRequest(data.action, data);

                    // On success, reset form and reload to show new notice
                    form.reset();
                    window.location.href = window.location.origin + window.location.pathname + '?page=sgvx51-notices&success=1';
                } catch (err) {
                    console.error('Notice publish failed', err);
                } finally {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = original;
                    }
                }
            });
        }

        // Delegated delete handler
        document.body.addEventListener('click', function (e) {
            const btn = e.target.closest('.js-delete-notice');
            if (btn) {
                deleteNotice(btn.dataset.id);
            }
        });
        });
    });

})(jQuery);

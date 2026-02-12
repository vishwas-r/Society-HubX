/**
 * SGVX Documents JS
 * - AJAX upload via sgvxApiRequest
 * - Approve/Delete using centralized modal and API wrapper
 * - Optimistic UI updates and toasts
 */
(function ($) {
    'use strict';

    // Module configuration (fetched at runtime)
    const Config = {
        nonce: null,
        deleteNonce: null,
        uploadNonce: null,
        approveNonce: null,
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
                    module: 'documents'
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

    async function approveDoc(docId) {
        try {
            await sgvxApiRequest('sgvx51_approve_doc', { doc_id: docId, _wpnonce: Config.nonce });
            const el = document.querySelector(`.sgvx-doc-card[data-id="${docId}"]`);
            if (el) {
                el.querySelectorAll('.position-absolute').forEach(b => b.remove());
                window.sgvxShowToast('Document approved', 'success');
                setTimeout(() => window.location.reload(), 400);
            } else {
                window.location.reload();
            }
        } catch (err) { }
    }

    async function deleteDoc(payload) {
        // payload: { id, flat, name, type }
        const modalEl = document.getElementById('deleteConfirmModal');
        const confirmBtn = document.getElementById('confirm-delete-btn');
        if (!modalEl || !confirmBtn) {
            if (!confirm('Permanently delete?')) return;
        }

        const modal = new bootstrap.Modal(modalEl);
        const newConfirmBtn = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);

        newConfirmBtn.addEventListener('click', async function () {
            try {
                const data = { flat: payload.flat, file: payload.name, type: payload.type };
                if (payload.id) data.doc_id = payload.id;
                await sgvxApiRequest('sgvx51_delete_doc', data);

                const el = payload.id ? document.querySelector(`.sgvx-doc-card[data-id="${payload.id}"]`) : null;
                if (el) {
                    el.style.opacity = '0.5';
                    setTimeout(() => el.remove(), 400);
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
            // Upload form AJAX submit
            const uploadForm = document.getElementById('upload-form');
            if (uploadForm) {
                uploadForm.addEventListener('submit', async function (e) {
                    e.preventDefault();
                    const submitBtn = uploadForm.querySelector('button[type="submit"]');
                    const original = submitBtn ? submitBtn.innerHTML : '';
                    if (submitBtn) { submitBtn.disabled = true; submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Uploading...'; }

                    try {
                        // We will send form via fetch because files need to be passed
                        const formData = new FormData(uploadForm);
                        // Ensure action is correct
                        formData.set('action', 'sgvx51_upload_doc');

                        // Determine nonce (prioritize Config.nonce if available)
                        const nonce = Config.nonce;
                        if (nonce) formData.set('_wpnonce', nonce);

                        const response = await fetch(ajaxurl, { method: 'POST', body: formData });
                        const text = await response.text();
                        let result = {};
                        try { result = JSON.parse(text); } catch (e) { }

                        if (result && result.success) {
                            window.sgvxShowToast('Upload started', 'success');
                            // close modal then reload
                            const modalEl = document.getElementById('uploadModal');
                            if (modalEl) {
                                const inst = bootstrap.Modal.getOrCreateInstance(modalEl);
                                if (inst) inst.hide();
                            }
                            setTimeout(() => window.location.reload(), 400);
                        } else {
                            window.sgvxShowToast('Upload failed', 'error');
                        }
                    } catch (err) {
                        console.error('Upload error', err);
                    } finally {
                        if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = original; }
                    }
                });
            }

            // Delegated actions: approve/delete
            document.body.addEventListener('click', function (e) {
                const approveBtn = e.target.closest('.js-approve-doc');
                if (approveBtn) {
                    approveDoc(approveBtn.dataset.id);
                }

                const delBtn = e.target.closest('.js-delete-doc');
                if (delBtn) {
                    const payload = {
                        id: delBtn.dataset.id || '',
                        flat: delBtn.dataset.flat || '',
                        name: delBtn.dataset.name || '',
                        type: delBtn.dataset.type || 'db'
                    };
                    deleteDoc(payload);
                }
            });
        });
    });

})(jQuery);

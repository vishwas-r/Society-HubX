/**
 * SNESTX Documents JS
 * - AJAX upload via SNESTXApiRequest
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
            const result = await SNESTX.ajax({
                action: 'snestx51_get_module_config',
                data: { module: 'documents' },
                showOverlay: false,
                suppressErrorToast: true
            });

            if (result) {
                Config.nonce = result.nonce || null;
                Config.deleteNonce = result.deleteNonce || null;
                Config.initialized = true;
            }
        } catch (error) {
            console.error('Error fetching module config:', error);
        }
    }

    function approveDoc(docId) {
        SNESTX.ajax({
            action: 'snestx51_approve_doc',
            data: { doc_id: docId, _wpnonce: Config.nonce },
            successMessage: 'Document approved!',
            reload: true
        });
    }

    function deleteDoc(payload) {
        // payload: { id, flat, name, type }
        const modalEl = document.getElementById('deleteConfirmModal');
        const confirmBtn = document.getElementById('confirm-delete-btn');
        if (!modalEl || !confirmBtn) {
            if (!confirm('Permanently delete?')) return;
        }

        const modal = new bootstrap.Modal(modalEl);
        const newConfirmBtn = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);

        newConfirmBtn.addEventListener('click', function () {
            const data = { flat: payload.flat, file: payload.name, type: payload.type };
            if (payload.id) data.doc_id = payload.id;

            SNESTX.ajax({
                action: 'snestx51_delete_doc',
                data: data,
                successMessage: 'Document deleted',
                onSuccess: function () {
                    const el = payload.id ? document.querySelector(`.snestx-doc-card[data-id="${payload.id}"]`) : null;
                    if (el) {
                        el.style.opacity = '0.5';
                        setTimeout(() => el.remove(), 400);
                    } else {
                        window.location.reload();
                    }
                }
            });
            modal.hide();
        });

        modal.show();
    }

    $(function () {
        fetchModuleConfig().then(() => {
            // Upload form AJAX submit
            const uploadForm = document.getElementById('upload-form');
            if (uploadForm) {
                uploadForm.addEventListener('submit', function (e) {
                    e.preventDefault();
                    const formData = new FormData(uploadForm);
                    formData.set('action', 'snestx51_upload_doc');
                    if (Config.nonce) {
                        formData.set('_wpnonce', Config.nonce);
                    }

                    SNESTX.ajax({
                        action: 'snestx51_upload_doc',
                        data: formData,
                        loadingButton: $(uploadForm).find('button[type="submit"]'),
                        successMessage: 'Document uploaded successfully!',
                        reload: true,
                        onSuccess: function () {
                            const modalEl = document.getElementById('uploadModal');
                            if (modalEl) {
                                const inst = bootstrap.Modal.getInstance(modalEl);
                                if (inst) inst.hide();
                            }
                        }
                    });
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

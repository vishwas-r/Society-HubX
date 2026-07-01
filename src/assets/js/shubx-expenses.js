/**
 * SHUBX Expenses JS
 * - Intercepts expense form submission (handles file upload via fetch)
 * - Handles approve and delete actions via centralized confirmation modal and SHUBXApiRequest
 * - Shows spinners and uses SHUBXShowToast for feedback
 */
(function ($) {
    'use strict';

    // Module configuration (fetched at runtime)
    const Config = {
        nonce: null,
        deleteNonce: null,
        approveNonce: null,
        initialized: false
    };

    async function fetchModuleConfig() {
        if (Config.initialized) return;

        try {
            const result = await SHUBX.ajax({
                action: 'shubx51_get_module_config',
                data: { module: 'expenses' },
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

    function approveExpense(id) {
        SHUBX.ajax({
            action: 'shubx51_approve_expense',
            data: { expense_id: id, _wpnonce: Config.nonce },
            successMessage: 'Expense approved successfully',
            reload: true
        });
    }

    function deleteExpense(id, date) {
        const modalEl = document.getElementById('deleteConfirmModal');
        const confirmBtn = document.getElementById('confirm-delete-btn');

        if (!modalEl || !confirmBtn) {
            if (!confirm('Delete this expense?')) return;
            SHUBX.ajax({
                action: 'shubx51_delete_expense',
                data: { id: id, date: date, _wpnonce: Config.deleteNonce },
                reload: true
            });
            return;
        }

        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        const newConfirmBtn = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);

        newConfirmBtn.addEventListener('click', function () {
            SHUBX.ajax({
                action: 'shubx51_delete_expense',
                data: { id: id, date: date, _wpnonce: Config.deleteNonce },
                successMessage: 'Expense deleted',
                onSuccess: function () {
                    const row = document.querySelector(`.js-delete-expense[data-id="${id}"]`)?.closest('tr');
                    if (row) {
                        row.style.opacity = '0.5';
                        setTimeout(() => row.remove(), 400);
                    } else {
                        window.location.reload();
                    }
                }
            });
            modal.hide();
        });

        modal.show();
    }

    // Intercept expense form (handles file upload)
    $(function () {
        fetchModuleConfig().then(() => {
            const form = document.getElementById('expense-form');
            if (form) {
                form.addEventListener('submit', function (e) {
                    e.preventDefault();
                    const formData = new FormData(form);
                    if (!formData.get('action')) formData.append('action', 'shubx51_add_expense');

                    SHUBX.ajax({
                        action: formData.get('action'),
                        data: formData,
                        loadingButton: $(form).find('button[type="submit"]'),
                        successMessage: 'Expense saved successfully!',
                        reload: true,
                        onSuccess: function () {
                            const modalEl = document.getElementById('expenseModal');
                            if (modalEl) {
                                const inst = bootstrap.Modal.getInstance(modalEl);
                                if (inst) inst.hide();
                            }
                        }
                    });
                });
            }

            // Delegated approve/delete handlers
            document.body.addEventListener('click', function (e) {
                const a = e.target.closest('.js-approve-expense');
                if (a) {
                    approveExpense(a.dataset.id);
                }

                const d = e.target.closest('.js-delete-expense');
                if (d) {
                    deleteExpense(d.dataset.id, d.dataset.date);
                }
            });
        });
    });

})(jQuery);

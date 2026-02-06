/**
 * SGVX Expenses JS
 * - Intercepts expense form submission (handles file upload via fetch)
 * - Handles approve and delete actions via centralized confirmation modal and sgvxApiRequest
 * - Shows spinners and uses sgvxShowToast for feedback
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
            const response = await fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'sgvx51_get_module_config',
                    module: 'expenses'
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

    async function approveExpense(id) {
        try {
            await sgvxApiRequest('sgvx51_approve_expense', { expense_id: id, _wpnonce: Config.nonce });
            window.sgvxShowToast('Expense approved', 'success');
            setTimeout(() => window.location.reload(), 400);
        } catch (err) { }
    }

    async function deleteExpense(id, date) {
        const modalEl = document.getElementById('deleteConfirmModal');
        const confirmBtn = document.getElementById('confirm-delete-btn');

        if (!modalEl || !confirmBtn) {
            if (!confirm('Delete this expense?')) return;
            // Fallback to direct deletion if modal doesn't exist
            try {
                await sgvxApiRequest('sgvx51_delete_expense', { id: id, date: date, _wpnonce: Config.deleteNonce });
                const row = document.querySelector(`.js-delete-expense[data-id="${id}"]`)?.closest('tr');
                if (row) {
                    row.style.opacity = '0.5';
                    setTimeout(() => row.remove(), 400);
                } else {
                    window.location.reload();
                }
            } catch (err) { }
            return;
        }

        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        const newConfirmBtn = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);

        newConfirmBtn.addEventListener('click', async function () {
            try {
                await sgvxApiRequest('sgvx51_delete_expense', { id: id, date: date, _wpnonce: Config.deleteNonce });
                const row = document.querySelector(`.js-delete-expense[data-id="${id}"]`)?.closest('tr');
                if (row) {
                    row.style.opacity = '0.5';
                    setTimeout(() => row.remove(), 400);
                } else {
                    window.location.reload();
                }
            } catch (err) { }
            modal.hide();
        });

        modal.show();
    }

    // Intercept expense form (handles file upload)
    $(function () {
        fetchModuleConfig().then(() => {
            const form = document.getElementById('expense-form');
            if (form) {
                form.addEventListener('submit', async function (e) {
                    e.preventDefault();
                    const btn = form.querySelector('button[type="submit"]');
                    const orig = btn ? btn.innerHTML : '';
                    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Saving...'; }

                    try {
                        const formData = new FormData(form);
                        // ensure action present
                        if (!formData.get('action')) formData.append('action', 'sgvx51_add_expense');

                        // attach nonce if localized
                        if (window.sgvxExpensesData && window.sgvxExpensesData.addNonce) formData.append('_wpnonce', window.sgvxExpensesData.addNonce);

                        const response = await fetch(ajaxurl, { method: 'POST', body: formData });
                        const text = await response.text();
                        let result = {};
                        try { result = JSON.parse(text); } catch (e) { }

                        if (result && result.success) {
                            window.sgvxShowToast(result.data && result.data.message ? result.data.message : 'Expense saved', 'success');
                            const modalEl = document.getElementById('expenseModal');
                            if (modalEl) {
                                const inst = bootstrap.Modal.getOrCreateInstance(modalEl);
                                if (inst) inst.hide();
                            }
                            setTimeout(() => window.location.reload(), 400);
                        } else {
                            window.sgvxShowToast('Failed to save expense', 'error');
                        }
                    } catch (err) {
                        console.error('Expense save error', err);
                    } finally {
                        if (btn) { btn.disabled = false; btn.innerHTML = orig; }
                    }
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

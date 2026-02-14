/**
 * Accounts Page JS - Invoice & Ledger interactions
 */
(function () {
    // Module configuration (fetched at runtime)
    const Config = {
        nonce: null,
        initialized: false
    };

    async function fetchModuleConfig() {
        if (Config.initialized) return;

        try {
            const result = await SGVX.ajax({
                action: 'sgvx51_get_module_config',
                data: { module: 'accounts' },
                showOverlay: false,
                suppressErrorToast: true
            });

            if (result) {
                Config.nonce = result.nonce || null;
                Config.initialized = true;
            }
        } catch (error) {
            console.error('Error fetching module config:', error);
        }
    }

    async function init() {
        // Initialize config first
        await fetchModuleConfig();

        // Delegated click handling
        document.addEventListener('click', function (e) {
            const btn = e.target.closest && e.target.closest('.js-edit-invoice, .js-record-payment, .js-open-receipt, .js-delete-invoice, .js-delete-payment, .js-approve-payment, .js-reject-payment');
            if (!btn) return;
            e.preventDefault();
            if (btn.classList.contains('js-edit-invoice')) return openEditInvoice(btn);
            if (btn.classList.contains('js-record-payment')) return openRecordPayment(btn);
            if (btn.classList.contains('js-open-receipt')) return openAdminReceipt(btn);
            if (btn.classList.contains('js-delete-invoice')) return deleteInvoice(btn);
            if (btn.classList.contains('js-delete-payment')) return deletePayment(btn);

            if (btn.classList.contains('js-approve-payment')) return handlePaymentApproval(btn, true);
            if (btn.classList.contains('js-reject-payment')) return handlePaymentApproval(btn, false);
        });

        function handlePaymentApproval(btn, isApprove) {
            const requestId = btn.getAttribute('data-id');
            const actionLabel = isApprove ? 'approve' : 'reject';

            if (!confirm(`Are you sure you want to ${actionLabel} this payment notification?`)) return;

            SGVX.ajax({
                action: isApprove ? 'sgvx51_approve_request' : 'sgvx51_reject_request',
                data: {
                    id: requestId,
                    _ajax_nonce: window.sgvx51RequestNonce
                },
                loadingButton: btn,
                successMessage: 'Payment ' + actionLabel + 'd successfully',
                reload: true
            });
        }

        // Form submissions (AJAX)
        const editForm = document.getElementById('edit-invoice-form');
        if (editForm) editForm.addEventListener('submit', handleEditSubmit);

        const paymentForm = document.getElementById('payment-form');
        if (paymentForm) paymentForm.addEventListener('submit', handlePaymentSubmit);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();

    function getModal(id) {
        if (!window._sgvx_modals) window._sgvx_modals = {};
        if (!window._sgvx_modals[id]) {
            const el = document.getElementById(id);
            if (!el) return null;
            window._sgvx_modals[id] = new bootstrap.Modal(el);
        }
        return window._sgvx_modals[id];
    }



    function openEditInvoice(btn) {
        try {
            const inv = JSON.parse(btn.getAttribute('data-invoice'));
            const form = document.getElementById('edit-invoice-form');
            if (!form) return;
            document.getElementById('editInvTitle').textContent = `Invoice #${String(inv.id).substr(-6)} (${inv.flat_no})`;
            form.querySelector('[name="invoice_id"]').value = inv.id;
            form.querySelector('[name="description"]').value = inv.description || '';
            form.querySelector('[name="amount"]').value = inv.amount || '';
            form.querySelector('[name="due_date"]').value = inv.due_date || '';

            const tbody = document.getElementById('edit-invoice-payments');
            const msg = document.getElementById('no-payments-msg');
            tbody.innerHTML = '';

            // Ensure payments is an array (it might be stored as JSON string)
            let payments = inv.payments;
            if (typeof payments === 'string') {
                try {
                    payments = JSON.parse(payments);
                } catch (e) {
                    payments = [];
                }
            }
            if (!Array.isArray(payments)) {
                payments = [];
            }

            if (payments.length > 0) {
                msg.classList.add('d-none');
                payments.forEach(p => {
                    const row = document.createElement('tr');
                    row.innerHTML = `<td class="px-3">${p.date}</td><td class="px-3 fw-bold">₹${parseFloat(p.amount).toFixed(2)}</td><td class="px-3 text-end"><button type="button" class="btn btn-sm text-danger js-delete-payment" data-invoice-id="${inv.id}" data-txn-id="${p.id}">×</button></td>`;
                    tbody.appendChild(row);
                });
            } else {
                msg.classList.remove('d-none');
            }

            const modal = getModal('editInvoiceModal');
            if (modal) modal.show();
        } catch (e) { console.error(e); }
    }

    function openRecordPayment(btn) {
        try {
            const inv = JSON.parse(btn.getAttribute('data-invoice'));
            // Ensure payments is an array (it might be stored as JSON string)
            let payments = inv.payments;
            if (typeof payments === 'string') {
                try {
                    payments = JSON.parse(payments);
                } catch (e) {
                    payments = [];
                }
            }
            if (!Array.isArray(payments)) {
                payments = [];
            }

            let paid = 0;
            if (payments.length > 0) {
                payments.forEach(p => paid += parseFloat(p.amount || 0));
            }
            const pending = parseFloat(inv.amount || 0) - paid;
            document.getElementById('paymentModalTitle').textContent = `Payment for ${inv.flat_no}`;
            document.getElementById('pay-amount').value = pending;

            // Set form values
            const form = document.getElementById('payment-form');
            if (form) {
                form.querySelector('[name="invoice_id"]').value = inv.id;
                // Set date to today if not set
                const dateInput = form.querySelector('[name="date"]');
                if (dateInput && !dateInput.value) {
                    dateInput.value = new Date().toISOString().split('T')[0];
                }
                // Set method to default if not set
                const methodSelect = form.querySelector('[name="method"]');
                if (methodSelect && !methodSelect.value) {
                    methodSelect.value = 'Bank Transfer';
                }
            }

            const modal = getModal('paymentModal');
            if (modal) modal.show();
        } catch (e) {
            console.error('Error opening payment modal:', e);
        }
    }

    function openAdminReceipt(btn) {
        try {
            const inv = JSON.parse(btn.getAttribute('data-invoice'));
            // Reuse existing global helper if present
            if (window.openAdminReceipt) return window.openAdminReceipt(btn);
            // Fallback simple rendering
            document.getElementById('adm-receipt-id').textContent = '#' + String(inv.id).substr(-6);
            document.getElementById('adm-receipt-name').textContent = inv.resident_name || 'Resident';
            document.getElementById('adm-receipt-flat').textContent = 'Flat ' + inv.flat_no;
            document.getElementById('adm-receipt-date').textContent = inv.date || inv.created_at || '';
            const tbody = document.getElementById('adm-receipt-items');
            tbody.innerHTML = '';
            let totalPaid = 0;

            // Ensure payments is an array (it might be stored as JSON string)
            let payments = inv.payments;
            if (typeof payments === 'string') {
                try {
                    payments = JSON.parse(payments);
                } catch (e) {
                    payments = [];
                }
            }
            if (!Array.isArray(payments)) {
                payments = [];
            }

            if (payments.length > 0) {
                payments.forEach(p => {
                    totalPaid += parseFloat(p.amount || 0);
                    const r = `<tr><td>${inv.description} <span class="text-muted small">(${p.date})</span></td><td class="text-end fw-bold">₹${parseFloat(p.amount).toLocaleString()}</td></tr>`;
                    tbody.insertAdjacentHTML('beforeend', r);
                });
            }
            document.getElementById('adm-receipt-subtotal').textContent = '₹' + parseFloat(inv.amount || 0).toLocaleString();
            document.getElementById('adm-receipt-total').textContent = '₹' + totalPaid.toLocaleString();
            const modal = getModal('receiptModal'); if (modal) modal.show();
        } catch (e) { console.error(e); }
    }

    function deleteInvoice(btn) {
        if (!confirm('Permanently delete this invoice?')) return;
        const id = btn.getAttribute('data-id');

        SGVX.ajax({
            action: 'sgvx51_delete_invoice',
            data: {
                id: id,
                _wpnonce: (window.sgvxAccountsData && window.sgvxAccountsData.deleteInvoiceNonce) ? window.sgvxAccountsData.deleteInvoiceNonce : undefined
            },
            successMessage: 'Invoice deleted',
            onSuccess: function () {
                const row = btn.closest('tr');
                if (row) row.remove();
            }
        });
    }

    function deletePayment(btn) {
        if (!confirm('Delete this payment?')) return;
        const invoiceId = btn.getAttribute('data-invoice-id');
        const txnId = btn.getAttribute('data-txn-id');

        SGVX.ajax({
            action: 'sgvx51_delete_payment',
            data: {
                invoice_id: invoiceId,
                txn_id: txnId,
                _wpnonce: (window.sgvxAccountsData && window.sgvxAccountsData.nonce) ? window.sgvxAccountsData.nonce : undefined
            },
            successMessage: 'Payment deleted',
            onSuccess: function () {
                const row = btn.closest('tr');
                if (row) row.remove();
            }
        });
    }

    function handleEditSubmit(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);

        SGVX.ajax({
            action: 'sgvx51_edit_invoice',
            data: formData,
            loadingButton: $(form).find('button[type="submit"]'),
            successMessage: 'Invoice updated successfully',
            reload: true
        });
    }

    function handlePaymentSubmit(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);

        if (!formData.get('amount') || !formData.get('method')) {
            SGVX.toast.warning('Please fill in all required fields');
            return;
        }

        SGVX.ajax({
            action: 'sgvx51_record_payment',
            data: formData,
            loadingButton: $(form).find('button[type="submit"]'),
            successMessage: 'Payment recorded successfully!',
            reload: true
        });
    }

    // Global modal functions
    window.openGenerateModal = function () {
        const modal = document.getElementById('generateModal');
        if (modal) {
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
        }
    };

    window.openAdhocModal = function () {
        const modal = document.getElementById('adhocModal');
        if (modal) {
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
        }
    };

    window.openEditInvoiceModal = function (btn) {
        const modal = document.getElementById('editInvoiceModal');
        if (modal && btn && btn.dataset.invoice) {
            try {
                const invoice = JSON.parse(btn.dataset.invoice);
                // Populate modal fields with invoice data if needed
                const bsModal = new bootstrap.Modal(modal);
                bsModal.show();
            } catch (e) {
                console.error('Error parsing invoice data:', e);
            }
        }
    };

    window.openPaymentModal = function (btn) {
        const modal = document.getElementById('paymentModal');
        if (modal && btn && btn.dataset.invoice) {
            try {
                const invoice = JSON.parse(btn.dataset.invoice);
                // Populate modal fields with invoice data if needed
                const bsModal = new bootstrap.Modal(modal);
                bsModal.show();
            } catch (e) {
                console.error('Error parsing invoice data:', e);
            }
        }
    };

    window.recordPayment = function (btn) {
        window.openPaymentModal(btn);
    };

    window.openReconcileModal = function () {
        const modal = document.getElementById('reconcileModal');
        if (modal) {
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
        }
    };

    window.editInvoice = function (btn) {
        window.openEditInvoiceModal(btn);
    };

})();

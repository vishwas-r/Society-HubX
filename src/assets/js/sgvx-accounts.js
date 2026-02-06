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
            const response = await fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'sgvx51_get_module_config',
                    module: 'accounts'
                }).toString()
            });

            if (!response.ok) throw new Error(`HTTP ${response.status}`);

            const result = await response.json();
            if (result.success && result.data) {
                Config.nonce = result.data.nonce || null;
                Config.initialized = true;
            } else {
                console.error('Failed to fetch module config:', result.data?.message || 'Unknown error');
            }
        } catch (error) {
            console.error('Error fetching module config:', error);
        }
    }

    async function init() {
        if (!window.sgvxApiRequest) {
            console.warn('sgvxApiRequest missing');
            return;
        }

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

        async function handlePaymentApproval(btn, isApprove) {
            const requestId = btn.getAttribute('data-id');
            const actionLabel = isApprove ? 'approve' : 'reject';

            if (!confirm(`Are you sure you want to ${actionLabel} this payment notification?`)) return;

            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

            const formData = new FormData();
            formData.append('action', isApprove ? 'sgvx51_approve_request' : 'sgvx51_reject_request');
            formData.append('id', requestId);
            formData.append('_ajax_nonce', window.sgvx51RequestNonce);

            try {
                const response = await fetch(ajaxurl, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    alert('Success: ' + result.data.message);
                    window.location.reload();
                } else {
                    alert('Error: ' + result.data.message);
                    btn.disabled = false;
                    btn.innerHTML = originalHtml;
                }
            } catch (err) {
                console.error(err);
                alert('An error occurred while processing the request.');
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
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

    // Admin-post helper for account actions (these handlers use admin-post.php)
    async function adminPostRequest(action, data = {}) {
        const formData = new FormData();
        formData.append('action', action);
        // Inject nonce if available
        if (data._wpnonce) formData.append('_wpnonce', data._wpnonce);
        else if (Config.nonce) formData.append('_wpnonce', Config.nonce);

        for (const [k, v] of Object.entries(data)) {
            if (k === 'action' || k === '_wpnonce') continue;
            formData.append(k, v);
        }

        // admin-post.php url derived from ajaxurl
        const adminPost = (typeof ajaxurl === 'string') ? ajaxurl.replace('admin-ajax.php', 'admin-post.php') : 'admin-post.php';

        const resp = await fetch(adminPost, { method: 'POST', body: formData });
        // Many admin_post handlers redirect — treat non-JSON as success and reload
        const text = await resp.text();
        try {
            const json = JSON.parse(text);
            if (json.success) {
                if (json.data && json.data.message) window.sgvxShowToast(json.data.message, 'success');
                return json.data || true;
            } else {
                const err = json.data && json.data.message ? json.data.message : 'Operation failed';
                window.sgvxShowToast(err, 'error');
                throw new Error(err);
            }
        } catch (e) {
            // Not JSON — assume redirect happened and server returned HTML. Treat as success and reload.
            window.location.reload();
            return true;
        }
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

    async function deleteInvoice(btn) {
        if (!confirm('Permanently delete this invoice?')) return;
        const id = btn.getAttribute('data-id');
        try {
            await adminPostRequest('sgvx51_delete_invoice', { id: id, _wpnonce: (window.sgvxAccountsData && window.sgvxAccountsData.deleteInvoiceNonce) ? window.sgvxAccountsData.deleteInvoiceNonce : undefined });
            // Remove row
            const row = btn.closest('tr'); if (row) row.remove();
        } catch (e) { /* adminPostRequest shows toast or reloads */ }
    }

    async function deletePayment(btn) {
        if (!confirm('Delete this payment?')) return;
        const invoiceId = btn.getAttribute('data-invoice-id');
        const txnId = btn.getAttribute('data-txn-id');
        try {
            await adminPostRequest('sgvx51_delete_payment', { invoice_id: invoiceId, txn_id: txnId, _wpnonce: (window.sgvxAccountsData && window.sgvxAccountsData.nonce) ? window.sgvxAccountsData.nonce : undefined });
            // Remove payment row
            const row = btn.closest('tr'); if (row) row.remove();
        } catch (e) { }
    }

    async function handleEditSubmit(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        // Ensure action is set
        formData.set('action', 'sgvx51_edit_invoice');
        // Provide nonce if available
        if (window.sgvxAccountsData && window.sgvxAccountsData.nonce) formData.set('_wpnonce', window.sgvxAccountsData.nonce);

        try {
            await adminPostRequest('sgvx51_edit_invoice', Object.fromEntries(formData.entries()));
            // successful - reload to reflect changes
            window.location.reload();
        } catch (err) { }
    }

    async function handlePaymentSubmit(e) {
        e.preventDefault();
        const form = e.target;
        const submitBtn = form.querySelector('button[type="submit"]');

        // Collect all form data
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        // Ensure all required fields are present
        if (!data.invoice_id || !data.amount || !data.method) {
            alert('Please fill in all required fields');
            return;
        }

        // Show loading state
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Processing...';
        }

        console.log('Payment submission data:', data);

        try {
            // Create proper form data for submission
            const params = new URLSearchParams();
            params.append('action', 'sgvx51_record_payment');
            params.append('invoice_id', data.invoice_id);
            params.append('amount', data.amount);
            params.append('date', data.date || new Date().toISOString().split('T')[0]);
            params.append('method', data.method);
            params.append('reference', data.reference || '');

            // Add nonce - check multiple sources
            let nonce = data._wpnonce;
            if (!nonce && window.sgvxAccountsData && window.sgvxAccountsData.nonce) {
                nonce = window.sgvxAccountsData.nonce;
            }
            if (!nonce && Config.nonce) {
                nonce = Config.nonce;
            }

            if (!nonce) {
                console.error('No nonce found for payment submission');
                alert('Security error: nonce not found');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Record Payment';
                }
                return;
            }

            params.append('_wpnonce', nonce);

            console.log('Submitting payment to admin-post.php');

            // Send to admin-post.php handler
            const postUrl = ajaxurl.replace('admin-ajax.php', 'admin-post.php');
            const response = await fetch(postUrl, {
                method: 'POST',
                body: params.toString(),
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                redirect: 'manual'
            });

            console.log('Payment submission response status:', response.status);

            if (response.status === 302 || response.status === 301) {
                // Redirect response - payment was recorded
                console.log('✓ Payment recorded successfully');
                alert('✅ Payment recorded successfully!');
                setTimeout(() => { window.location.reload(); }, 1000);
            } else if (response.ok) {
                // HTML or JSON response
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    const json = await response.json();
                    if (json.success) {
                        alert('✅ Payment recorded successfully!');
                        setTimeout(() => { window.location.reload(); }, 1000);
                    } else {
                        alert('❌ Error: ' + (json.data?.message || 'Failed to record payment'));
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = 'Record Payment';
                        }
                    }
                } else {
                    // HTML response - assume success and reload
                    console.log('Payment submitted, reloading...');
                    alert('✅ Payment recorded successfully!');
                    setTimeout(() => { window.location.reload(); }, 1000);
                }
            } else {
                alert('❌ Error: HTTP ' + response.status);
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Record Payment';
                }
            }
        } catch (err) {
            console.error('Payment submission error:', err);
            alert('Error recording payment: ' + err.message);
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Record Payment';
            }
        }
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

/**
 * Accounts Page JS - Invoice & Ledger interactions
 */
(function ($) {
    // Module configuration (fetched at runtime)
    const Config = {
        nonce: null,
        initialized: false
    };

    async function fetchModuleConfig() {
        if (Config.initialized) return;

        try {
            const result = await NAMMASOCIETY.ajax({
                action: 'nammasociety51_get_module_config',
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
            const btn = e.target.closest && e.target.closest('.js-edit-invoice, .js-record-payment, .js-open-receipt, .js-delete-invoice, .js-delete-payment, .js-approve-payment, .js-reject-payment, .js-view-tax-invoice');
            if (!btn) return;
            e.preventDefault();
            if (btn.classList.contains('js-view-tax-invoice')) return openTaxInvoice(btn);
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

            NAMMASOCIETY.ajax({
                action: isApprove ? 'nammasociety51_approve_request' : 'nammasociety51_reject_request',
                data: {
                    id: requestId,
                    _ajax_nonce: (window.nammasociety51RequestNonce || '')
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
        if (!window._NAMMASOCIETY_modals) window._NAMMASOCIETY_modals = {};
        if (!window._NAMMASOCIETY_modals[id]) {
            const el = document.getElementById(id);
            if (!el) return null;
            window._NAMMASOCIETY_modals[id] = new bootstrap.Modal(el);
        }
        return window._NAMMASOCIETY_modals[id];
    }

    function openTaxInvoice(btn) {
        const invId = btn.getAttribute('data-id');
        const modal = getModal('taxInvoiceModal');
        const container = document.getElementById('tax-invoice-container');
        if (!container) return;

        container.innerHTML = `
            <div class="text-center py-5 text-muted">
                <div class="spinner-border spinner-border-sm text-primary mb-2"></div>
                <p class="small m-0">Generating Statutory Tax Invoice...</p>
            </div>
        `;
        if (modal) modal.show();

        jQuery.ajax({
            url: ajaxurl,
            type: 'GET',
            data: {
                action: 'nammasociety51_get_tax_invoice',
                invoice_id: invId,
                _wpnonce: window.nammasociety51AccountNonce || ''
            },
            success: function (res) {
                if (!res || !res.success || !res.data) {
                    container.innerHTML = `<div class="alert alert-danger">Failed to load invoice details: ${(res && res.data && res.data.message) ? res.data.message : 'Unknown error'}</div>`;
                    return;
                }
                renderTaxInvoiceHtml(container, res.data);
            },
            error: function () {
                container.innerHTML = `<div class="alert alert-danger">Error fetching Tax Invoice. Please try again.</div>`;
            }
        });
    }

    function renderTaxInvoiceHtml(container, data) {
        const inv = data.invoice;
        const upi = data.upi || {};
        const total = parseFloat(data.total || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 });
        const base = parseFloat(data.base_amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 });
        const cgst = parseFloat(data.cgst || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 });
        const sgst = parseFloat(data.sgst || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 });

        container.innerHTML = `
            <div class="tax-invoice-sheet bg-white p-4 border rounded-3 text-dark">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-start border-bottom pb-4 mb-4">
                    <div>
                        <h4 class="fw-bold m-0 text-dark">${data.society_name}</h4>
                        <div class="small text-muted mt-1">${data.society_address}</div>
                        <div class="small fw-semibold text-secondary mt-1">
                            <span class="me-3">GSTIN: <strong class="text-dark font-monospace">${data.society_gstin}</strong></span>
                            <span>PAN: <strong class="text-dark font-monospace">${data.society_pan}</strong></span>
                        </div>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-3 py-1.5 rounded-pill fw-bold text-uppercase" style="font-size: 11px;">TAX INVOICE</span>
                        <div class="small text-muted font-monospace mt-2">#INV-${String(inv.id).substr(-6)}</div>
                        <div class="small text-muted">Date: ${inv.created_at ? inv.created_at.split(' ')[0] : 'Today'}</div>
                        <div class="small text-danger fw-semibold">Due: ${inv.due_date || 'N/A'}</div>
                    </div>
                </div>

                <!-- Billed To -->
                <div class="row g-3 bg-light p-3 rounded-3 mb-4">
                    <div class="col-sm-6">
                        <span class="small fw-bold text-secondary text-uppercase" style="font-size: 10px;">Billed To / Unit Owner</span>
                        <div class="fw-bold text-dark fs-6">${inv.resident_name || 'Resident'}</div>
                        <div class="small text-muted">Unit: <strong>${(inv.block ? inv.block + '-' : '') + inv.flat_no}</strong></div>
                    </div>
                    <div class="col-sm-6 text-sm-end">
                        <span class="small fw-bold text-secondary text-uppercase" style="font-size: 10px;">Service Period & SAC</span>
                        <div class="fw-bold text-dark font-monospace">SAC: ${data.sac_code}</div>
                        <div class="small text-muted">${data.sac_description}</div>
                    </div>
                </div>

                <!-- Table -->
                <div class="table-responsive mb-4">
                    <table class="table table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr class="small text-secondary fw-bold text-uppercase">
                                <th class="py-2">Description</th>
                                <th class="py-2 text-center">SAC Code</th>
                                <th class="py-2 text-end">Rate / Base (₹)</th>
                                <th class="py-2 text-end">CGST (9%)</th>
                                <th class="py-2 text-end">SGST (9%)</th>
                                <th class="py-2 text-end">Total Amount (₹)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark">${inv.description || 'Monthly Society Maintenance'}</div>
                                    <div class="small text-muted" style="font-size: 11px;">RWA Maintenance Charges for ${inv.month}</div>
                                </td>
                                <td class="text-center font-monospace">${data.sac_code}</td>
                                <td class="text-end font-monospace">₹${base}</td>
                                <td class="text-end font-monospace">₹${cgst}</td>
                                <td class="text-end font-monospace">₹${sgst}</td>
                                <td class="text-end font-monospace fw-bold text-dark">₹${total}</td>
                            </tr>
                        </tbody>
                        <tfoot class="table-light fw-bold">
                            <tr>
                                <td colspan="5" class="text-end text-uppercase">Total Payable (INR):</td>
                                <td class="text-end text-primary fs-6 font-monospace">₹${total}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Payment QR & Banking -->
                <div class="row g-4 align-items-center bg-light p-3 rounded-3 border">
                    <div class="col-sm-4 text-center border-end">
                        <img src="${upi.qr_url}" alt="UPI QR" class="rounded-2 shadow-sm border bg-white p-1" style="width: 140px; height: 140px;">
                        <div class="small fw-bold text-secondary mt-1" style="font-size: 10px;">Scan with GPay, PhonePe, Paytm</div>
                    </div>
                    <div class="col-sm-8 ps-sm-3">
                        <h6 class="fw-bold text-dark mb-2">Instant UPI & Direct Bank Transfer</h6>
                        <div class="small mb-1"><span class="text-secondary">VPA / UPI ID:</span> <strong class="font-monospace text-primary">${upi.upi_id}</strong></div>
                        <div class="small mb-1"><span class="text-secondary">Payee Name:</span> <strong class="text-dark">${upi.payee_name}</strong></div>
                        <div class="small mb-1"><span class="text-secondary">Payment Ref Note:</span> <code class="font-monospace text-dark">${upi.note}</code></div>
                        <div class="small mt-2 pt-2 border-top text-muted" style="font-size: 11px;">
                            <i class="bi bi-shield-check text-success me-1"></i>Computer-generated Tax Invoice under Rule 54 of CGST Rules 2017. No physical signature required.
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    window.printTaxInvoiceModal = function () {
        const printable = document.getElementById('tax-invoice-printable-area');
        if (!printable) return;
        const printWin = window.open('', '_blank', 'width=800,height=900');
        printWin.document.write(`
            <html>
                <head>
                    <title>Statutory Tax Invoice</title>
                    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
                    <style>
                        body { padding: 20px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
                        @media print { .no-print { display: none !important; } }
                    </style>
                </head>
                <body>
                    ${printable.innerHTML}
                    <script>
                        window.onload = function() { window.print(); window.close(); };
                    </script>
                </body>
            </html>
        `);
        printWin.document.close();
    };

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

        NAMMASOCIETY.ajax({
            action: 'nammasociety51_delete_invoice',
            data: {
                id: id,
                _wpnonce: (window.NAMMASOCIETYAccountsData && window.NAMMASOCIETYAccountsData.deleteInvoiceNonce) ? window.NAMMASOCIETYAccountsData.deleteInvoiceNonce : undefined
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

        NAMMASOCIETY.ajax({
            action: 'nammasociety51_delete_payment',
            data: {
                invoice_id: invoiceId,
                txn_id: txnId,
                _wpnonce: (window.NAMMASOCIETYAccountsData && window.NAMMASOCIETYAccountsData.nonce) ? window.NAMMASOCIETYAccountsData.nonce : undefined
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

        NAMMASOCIETY.ajax({
            action: 'nammasociety51_edit_invoice',
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
            NAMMASOCIETY.toast.warning('Please fill in all required fields');
            return;
        }

        NAMMASOCIETY.ajax({
            action: 'nammasociety51_record_payment',
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

})(jQuery);

// ====== RECEIPT FUNCTIONS & SEARCH & CHARTS & POLL SYNC ======

// Receipt Functions for Admin
window.openAdminReceipt = function (btn) {
    let invoiceId;
    try {
        const inv = JSON.parse(btn.getAttribute('data-invoice'));
        invoiceId = inv.id;
    } catch (e) {
        invoiceId = btn.getAttribute('data-invoice-id');
    }

    if (!invoiceId) {
        alert('Invoice ID not found');
        return;
    }

    // Prepare modal (clear old content and show loading)
    const receiptContent = document.getElementById('receipt-content');
    if (receiptContent) {
        receiptContent.innerHTML = `
            <div class="text-center py-5 w-100">
                <div class="spinner-border text-primary mb-3" role="status"></div>
                <div class="text-muted">Fetching receipt details...</div>
            </div>
        `;
    }

    // Show modal first (it will show the spinner)
    if (window.bootstrap && window.bootstrap.Modal) {
        const modalEl = document.getElementById('receiptModal');
        let modal = bootstrap.Modal.getInstance(modalEl);
        if (!modal) modal = new bootstrap.Modal(modalEl);
        modal.show();
    }

    // Make AJAX request to fetch receipt data
    const activeNonce = (typeof NAMMASOCIETY51AdminNonce !== 'undefined') ? NAMMASOCIETY51AdminNonce : '';
    const activeAjaxurl = (typeof ajaxurl !== 'undefined') ? ajaxurl : '';
    fetch(activeAjaxurl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=nammasociety51_get_receipt&invoice_id=' + encodeURIComponent(invoiceId) + '&nonce=' + activeNonce
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                populateReceiptModal(data.data);
            } else {
                if (receiptContent) {
                    receiptContent.innerHTML = `<div class="alert alert-danger mx-4 mt-4">${data.data.message || 'Error loading receipt'}</div>`;
                }
            }
        })
        .catch(error => {
            console.error('Receipt fetch error:', error);
            if (receiptContent) {
                receiptContent.innerHTML = `<div class="alert alert-danger mx-4 mt-4">Failed to fetch receipt data. Please check your connection.</div>`;
            }
        });
};

function populateReceiptModal(receiptData) {
    const receiptContent = document.getElementById('receipt-content');
    if (!receiptContent) return;

    // Reset flex classes and padding for A4
    receiptContent.className = 'receipt';
    receiptContent.style.minHeight = 'auto';

    // Calculate payment details
    let paymentRows = '';

    if (receiptData.payments && receiptData.payments.length > 0) {
        receiptData.payments.forEach(p => {
            const ref = p.reference || p.ref || '-';
            paymentRows += `
                <tr>
                    <td>${p.method || 'Payment'} <br><small class="text-muted">${p.date || ''}</small></td>
                    <td class="text-end fw-bold">₹${parseFloat(p.amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 })}</td>
                    <td class="text-muted small">${ref}</td>
                </tr>
            `;
        });
    }

    const invoiceAmount = parseFloat(receiptData.invoice_amount || 0);
    const totalPaid = parseFloat(receiptData.total_paid || 0);
    const balanceDue = parseFloat(receiptData.balance_due || 0);

    const statusClass = receiptData.status === 'paid' ? 'bg-success text-white' : (receiptData.status === 'partial' ? 'bg-warning text-dark' : 'bg-danger text-white');
    const statusText = receiptData.status === 'paid' ? 'FULLY PAID' : (receiptData.status === 'partial' ? 'PARTIALLY PAID' : 'UNPAID');

    receiptContent.innerHTML = `
        <!-- Header -->
        <div class="receipt-header-standard">
            <h2 class="fw-bold text-primary mb-1">${receiptData.society_name || 'Society Name'}</h2>
            <p class="text-muted mb-0">Payment Receipt <strong class="receipt-no">#${receiptData.receipt_number}</strong></p>
        </div>

        <!-- Info Grid -->
        <div class="receipt-grid">
            <div>
                <span class="receipt-label">Resident Name</span>
                <div class="receipt-value">${receiptData.resident_name}</div>
            </div>
            <div>
                <span class="receipt-label">Flat / Unit No.</span>
                <div class="receipt-value">${receiptData.flat_no}</div>
            </div>
            <div>
                <span class="receipt-label">Billing Period</span>
                <div class="receipt-value">${new Date(receiptData.invoice_month + '-02').toLocaleDateString('en-IN', { month: 'long', year: 'numeric' })}</div>
            </div>
            <div>
                <span class="receipt-label">Purpose</span>
                <div class="receipt-value">${receiptData.description || 'Society Maintenance'}</div>
            </div>
        </div>

        <!-- Payment Table -->
        <h5 class="fw-bold mb-3 mt-4">Transaction Details</h5>
        <table class="receipt-table">
            <thead>
                <tr>
                    <th>Method / Date</th>
                    <th class="text-end">Amount Paid</th>
                    <th>Reference ID</th>
                </tr>
            </thead>
            <tbody>
                ${paymentRows || '<tr><td colspan="3" class="py-4 text-center text-muted">No payments recorded</td></tr>'}
            </tbody>
        </table>

        <!-- Summary -->
        <div class="receipt-summary">
            <div class="summary-row">
                <span>Invoice Total</span>
                <span>₹${invoiceAmount.toLocaleString('en-IN', { minimumFractionDigits: 2 })}</span>
            </div>
            <div class="summary-row text-success fw-bold">
                <span>Total Received</span>
                <span>₹${totalPaid.toLocaleString('en-IN', { minimumFractionDigits: 2 })}</span>
            </div>
            <div class="summary-row grand-total">
                <span>Balance Due</span>
                <span>₹${balanceDue.toLocaleString('en-IN', { minimumFractionDigits: 2 })}</span>
            </div>
        </div>

        <!-- Status -->
        <div class="receipt-status-wrap">
            <span class="receipt-badge ${statusClass}">${statusText}</span>
        </div>

        <!-- Footer -->
        <div class="receipt-footer-standard">
            <p class="mb-1">This is a computer-generated document. It does not require a physical signature.</p>
            <p class="mb-0">Namma Society - Empowering Communities</p>
        </div>
    `;
}

window.downloadReceipt = function(event) {
    const e = event || window.event;
    const receiptElement = document.getElementById('receipt-content');
    if (!receiptElement || receiptElement.querySelector('.spinner-border')) {
        alert('Please wait for receipt to load fully.');
        return;
    }

    if (typeof html2canvas === 'undefined') {
        alert('Image generation library not loaded. Please try again in a few seconds.');
        return;
    }

    // Show loading state
    const btn = e ? e.target.closest('button') : null;
    const originalText = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Generating...';
    }

    // Use html2canvas to convert to image
    html2canvas(receiptElement, {
        scale: 2,
        logging: false,
        useCORS: true,
        allowTaint: true,
        backgroundColor: '#ffffff'
    }).then(canvas => {
        // Create download link
        const link = document.createElement('a');
        const receiptNumber = receiptElement.querySelector('.receipt-no')?.textContent || 'Receipt';
        link.href = canvas.toDataURL('image/png');
        link.download = `${receiptNumber.replace('#', '')}.png`;
        link.click();

        // Restore button
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }).catch(error => {
        console.error('Download error:', error);
        alert('Error generating receipt image. Please try again.');
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    });
};

// Invoice & Ledger Search Implementation
let invoiceFuse = null;
let ledgerFuse = null;

function applyAccountSearch() {
    const searchInput = document.getElementById('account-filter-search');
    const searchVal = searchInput ? searchInput.value.trim().toLowerCase() : '';
    
    // Determine which tab is active
    const invoiceTab = document.querySelector('[href="?page=nammasociety51-accounts&tab=invoices"]');
    const isInvoicesTab = invoiceTab && invoiceTab.classList.contains('active');
    
    if (isInvoicesTab) {
        // Search invoices
        if (!invoiceFuse && window.NAMMASOCIETYCreateFuse) {
            invoiceFuse = window.NAMMASOCIETYCreateFuse('.invoice-row');
        }

        const fuzzyMatches = searchVal && window.NAMMASOCIETYGetFuzzyMatches ? window.NAMMASOCIETYGetFuzzyMatches(invoiceFuse, searchVal) : null;

        document.querySelectorAll('.invoice-row').forEach(row => {
            const matchSearch = !searchVal || (fuzzyMatches && fuzzyMatches.has(row));

            if (matchSearch) {
                row.classList.remove('d-none');
                row.style.display = '';
            } else {
                row.classList.add('d-none');
            }
        });

        if (searchVal && fuzzyMatches) {
            console.log(`Invoice Search: Found ${fuzzyMatches.size} matches for "${searchVal}"`);
        }
    } else {
        // Search ledger
        if (!ledgerFuse && window.NAMMASOCIETYCreateFuse) {
            ledgerFuse = window.NAMMASOCIETYCreateFuse('.ledger-row');
        }

        const fuzzyMatches = searchVal && window.NAMMASOCIETYGetFuzzyMatches ? window.NAMMASOCIETYGetFuzzyMatches(ledgerFuse, searchVal) : null;

        document.querySelectorAll('.ledger-row').forEach(row => {
            const matchSearch = !searchVal || (fuzzyMatches && fuzzyMatches.has(row));

            if (matchSearch) {
                row.classList.remove('d-none');
                row.style.display = '';
            } else {
                row.classList.add('d-none');
            }
        });

        if (searchVal && fuzzyMatches) {
            console.log(`Ledger Search: Found ${fuzzyMatches.size} matches for "${searchVal}"`);
        }
    }
}

(function () {
    document.addEventListener('DOMContentLoaded', function () {
        const searchInput = document.getElementById('account-filter-search');
        if (searchInput) {
            searchInput.addEventListener('input', applyAccountSearch);
            searchInput.addEventListener('focus', function () {
                // Re-index on focus based on active tab
                const invoiceTab = document.querySelector('[href="?page=nammasociety51-accounts&tab=invoices"]');
                const isInvoicesTab = invoiceTab && invoiceTab.classList.contains('active');
                
                if (window.NAMMASOCIETYCreateFuse) {
                    if (isInvoicesTab) {
                        invoiceFuse = window.NAMMASOCIETYCreateFuse('.invoice-row');
                    } else {
                        ledgerFuse = window.NAMMASOCIETYCreateFuse('.ledger-row');
                    }
                }
            });
            
            // Clear search when switching tabs
            searchInput.value = '';
        }
    });
})();

// ===== CHART RENDERING =====
let cashFlowChart = null;
let categoryChart = null;
let collectionChart = null;
let currentChartView = 'monthly';

function initCharts() {
    if (!window.Chart || !window.NAMMASOCIETYAccountsChartData) {
        console.log('Chart.js or chart data not available');
        return;
    }

    renderCashFlowChart();
    renderCollectionChart();
    renderCategoryChart();
}

function renderCashFlowChart() {
    const container = document.getElementById("cashFlowChart");
    if (!container) return;

    const chartData = window.NAMMASOCIETYAccountsChartData.monthlyData;
    if (!chartData) return;

    const labels = [];
    const incomeData = [];
    const expenseData = [];

    // Process each month - show both income and expense as separate entries
    for (const [month, data] of Object.entries(chartData)) {
        labels.push(month);
        incomeData.push(data.income || 0);
        expenseData.push(data.expense || 0);
    }

    if (cashFlowChart) {
        cashFlowChart.destroy();
    }

    container.innerHTML = '';
    const canvas = document.createElement('canvas');
    container.appendChild(canvas);

    cashFlowChart = new Chart(canvas, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Income (₹)',
                    data: incomeData,
                    backgroundColor: '#10b981',
                    borderRadius: 4
                },
                {
                    label: 'Expense (₹)',
                    data: expenseData,
                    backgroundColor: '#ef4444',
                    borderRadius: 4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'Society Cash Flow (Income vs Expense)',
                    font: {
                        size: 16,
                        family: 'Inter, sans-serif'
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '₹' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });
}

function renderCollectionChart() {
    const container = document.getElementById("collectionChart");
    if (!container) return;

    const data = window.NAMMASOCIETYAccountsChartData.collectionData;
    if (!data) return;

    const total = data.paid + data.unpaid + data.partial;
    if (total === 0) return;

    if (collectionChart) {
        collectionChart.destroy();
    }

    container.innerHTML = '';
    const canvas = document.createElement('canvas');
    container.appendChild(canvas);

    collectionChart = new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: ['Paid', 'Partial', 'Unpaid'],
            datasets: [{
                data: [data.paid, data.partial, data.unpaid],
                backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const val = context.raw || 0;
                            const pct = Math.round((val / total) * 100);
                            return context.label + ': ' + val + ' (' + pct + '%)';
                        }
                    }
                }
            }
        }
    });
}

function renderCategoryChart() {
    const container = document.getElementById("expenseCategoryChart");
    if (!container) return;

    const data = window.NAMMASOCIETYAccountsChartData.categoryData;
    if (!data || Object.keys(data).length === 0) return;

    const labels = [];
    const values = [];

    for (const [cat, val] of Object.entries(data)) {
        labels.push(cat);
        values.push(val);
    }

    if (categoryChart) {
        categoryChart.destroy();
    }

    container.innerHTML = '';
    const canvas = document.createElement('canvas');
    container.appendChild(canvas);

    categoryChart = new Chart(canvas, {
        type: 'pie',
        data: {
            labels: labels,
            datasets: [{
                data: values,
                backgroundColor: [
                    '#6366f1', '#10b981', '#f59e0b', '#ef4444', 
                    '#3b82f6', '#8b5cf6', '#ec4899', '#14b8a6'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.label + ': ₹' + context.raw.toLocaleString();
                        }
                    }
                }
            }
        }
    });
}

function switchChartView(view) {
    currentChartView = view;

    const btnMonthly = document.getElementById('btn-chart-view-monthly');
    const btnYearly = document.getElementById('btn-chart-view-yearly');

    if (view === 'monthly') {
        if(btnMonthly) btnMonthly.classList.add('active');
        if(btnYearly) btnYearly.classList.remove('active');
    } else {
        if(btnYearly) btnYearly.classList.add('active');
        if(btnMonthly) btnMonthly.classList.remove('active');
    }

    renderCashFlowChart();
}

// Initialize charts on page load
document.addEventListener('DOMContentLoaded', function () {
    if (window.Chart) {
        initCharts();
    } else {
        console.error('Chart.js not loaded');
    }
    
    // --- Real-time Admin Sync (Optimistic UI) ---
    initAdminPaymentSync();
});

function initAdminPaymentSync() {
    let currentHash = null;
    let isPolling = false;
    
    async function pollState() {
        if (isPolling) return;
        isPolling = true;
        
        try {
            const formData = new URLSearchParams();
            formData.append('action', 'nammasociety51_poll_state_hash');
            const adminNonce = (typeof window.nammasociety51_admin_nonce !== 'undefined') ? window.nammasociety51_admin_nonce : '';
            formData.append('_wpnonce', adminNonce);
            
            const activeAjaxurl = (typeof window.ajaxurl !== 'undefined') ? window.ajaxurl : '';
            const req = await fetch(activeAjaxurl, {
                method: 'POST',
                body: formData
            });
            const res = await req.json();
            
            if (res.success && res.data && res.data.hash) {
                if (currentHash === null) {
                    currentHash = res.data.hash;
                } else if (currentHash !== res.data.hash) {
                    console.log('NAMMASOCIETY Admin: State Hash changed. Syncing UI...');
                    currentHash = res.data.hash;
                    await refreshAdminDashboard();
                }
            }
        } catch(e) {
            console.error('NAMMASOCIETY Admin Sync Error:', e);
        }
        
        isPolling = false;
        setTimeout(pollState, 4000); // 4 Seconds
    }
    
    async function refreshAdminDashboard() {
        try {
            const req = await fetch(window.location.href);
            if (!req.ok) return;
            const html = await req.text();
            const doc = new DOMParser().parseFromString(html, 'text/html');
            
            const currentContent = document.querySelector('.mb-5.px-1')?.parentNode;
            const newContent = doc.querySelector('.mb-5.px-1')?.parentNode;
            
            if (currentContent && newContent) {
                // Destroy old charts to prevent memory leak before replacing content
                if (cashFlowChart) cashFlowChart.destroy();
                if (categoryChart) categoryChart.destroy();
                if (collectionChart) collectionChart.destroy();
                
                // Replace HTML
                currentContent.innerHTML = newContent.innerHTML;
                
                // Update chart data from the new script block
                const scripts = doc.querySelectorAll('script');
                scripts.forEach(s => {
                    if (s.textContent.includes('NAMMASOCIETYAccountsChartData')) {
                        try {
                            eval(s.textContent); 
                            initCharts(); 
                        } catch(err) {}
                    }
                });
                
                if (window.NAMMASOCIETY && window.NAMMASOCIETY.toast) {
                    NAMMASOCIETY.toast.success('Live Update: Financials synced in real-time.', { icon: 'check-circle' });
                }
            }
        } catch(e) {
            console.error('NAMMASOCIETY Admin Refresh Error:', e);
        }
    }
    
    setTimeout(pollState, 2000);
}

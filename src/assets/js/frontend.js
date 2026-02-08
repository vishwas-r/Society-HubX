/**
 * Frontend Dashboard JS
 * Handles interactions for the resident dashboard using event delegation.
 * No window pollution - all handlers are scoped within IIFE.
 */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {

        // --- Initialize Bootstrap Tooltips ---
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        [...tooltipTriggerList].map(el => new bootstrap.Tooltip(el));

        // --- Helper: Toggle Bootstrap Modal ---
        function toggleBootstrapModal(modalId, show) {
            const m = document.getElementById(modalId);
            if (!m) return;
            const bsModal = bootstrap.Modal.getOrCreateInstance(m);
            if (show) bsModal.show();
            else bsModal.hide();
        }

        // --- 1. Main Tab Switching ---
        const tabs = ['home', 'notices', 'facilities', 'accounts', 'expenses', 'polls', 'community'];

        function activateTab(tabName) {
            const btnId = 'btn-tab-' + tabName;
            const btn = document.getElementById(btnId);
            if (!btn) return;

            const targetId = btn.getAttribute('data-tab-target');

            // Update button states
            document.querySelectorAll('.tab-btn').forEach(b => {
                b.classList.remove('active', 'text-primary', 'border-primary');
                b.classList.add('border-transparent', 'text-muted');
            });
            btn.classList.remove('border-transparent', 'text-muted');
            btn.classList.add('active', 'text-primary', 'border-primary');

            // Update content visibility
            document.querySelectorAll('.tab-content').forEach(c => {
                c.classList.add('d-none');
                c.classList.remove('d-block');
            });

            const targetEl = document.querySelector(targetId);
            if (targetEl) {
                targetEl.classList.remove('d-none');
                targetEl.classList.add('d-block');
            }

            // Update URL hash
            if (history.pushState) {
                const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + '#tab-' + tabName;
                window.history.pushState({ path: newUrl }, '', newUrl);
            }
        }

        // Bind tab click events
        tabs.forEach(t => {
            const btn = document.getElementById('btn-tab-' + t);
            if (btn) {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    activateTab(t);
                });
            }
        });

        // Auto-activate tab from URL hash or query param
        const hash = window.location.hash;
        if (hash && hash.startsWith('#tab-')) {
            const tabName = hash.replace('#tab-', '');
            if (tabs.includes(tabName)) activateTab(tabName);
        } else if (new URLSearchParams(window.location.search).has('ex_year')) {
            activateTab('expenses');
        }

        // --- 2. Sub-Tab Switching (Event Delegation) ---
        document.addEventListener('click', function (e) {
            const subTabBtn = e.target.closest('[data-subtab-target]');
            if (subTabBtn) {
                e.preventDefault();
                const subTabId = subTabBtn.getAttribute('data-subtab-target');
                switchSubTab(subTabId);
            }
        });

        function switchSubTab(subTabId) {
            // Hide all sub-tab content
            const contents = document.querySelectorAll('.sub-tab-content');
            contents.forEach(c => {
                c.classList.add('d-none');
                c.classList.remove('d-block');
            });

            // Show target sub-tab
            const target = document.getElementById('sub-tab-' + subTabId);
            if (target) {
                target.classList.remove('d-none');
                target.classList.add('d-block');
            }

            // Update nav-link active states
            const navLinks = document.querySelectorAll('.nav-tabs .nav-link');
            navLinks.forEach(link => {
                const linkTarget = link.getAttribute('data-subtab-target');
                if (linkTarget === subTabId) {
                    link.classList.add('active', 'text-primary');
                    link.classList.remove('text-secondary');
                } else {
                    link.classList.remove('active', 'text-primary');
                    link.classList.add('text-secondary');
                }
            });
        }

        // --- 3. Edit Handlers (Event Delegation) ---
        document.body.addEventListener('click', function (e) {
            // Family Edit
            const familyBtn = e.target.closest('.js-edit-family');
            if (familyBtn) {
                e.preventDefault();
                const data = JSON.parse(familyBtn.getAttribute('data-payload'));
                const m = document.getElementById('editFamilyModal');
                if (m) {
                    m.querySelector('[name="member_id"]').value = data.id || '';
                    if (m.querySelector('[name="name"]')) m.querySelector('[name="name"]').value = data.name || '';
                    if (m.querySelector('[name="relation"]')) m.querySelector('[name="relation"]').value = data.relation || '';
                    if (m.querySelector('[name="age"]')) m.querySelector('[name="age"]').value = data.age || '';
                    if (m.querySelector('[name="blood_group"]')) m.querySelector('[name="blood_group"]').value = data.blood_group || '';
                    if (m.querySelector('[name="phone"]')) m.querySelector('[name="phone"]').value = data.phone || '';
                    toggleBootstrapModal('editFamilyModal', true);
                }
                return;
            }

            // Help Edit
            const helpBtn = e.target.closest('.js-edit-help');
            if (helpBtn) {
                e.preventDefault();
                const data = JSON.parse(helpBtn.getAttribute('data-payload'));
                const m = document.getElementById('editHelpModal');
                if (m) {
                    m.querySelector('[name="help_id"]').value = data.id || '';
                    if (m.querySelector('[name="name"]')) m.querySelector('[name="name"]').value = data.name || '';
                    if (m.querySelector('[name="role"]')) m.querySelector('[name="role"]').value = data.role || '';
                    if (m.querySelector('[name="phone"]')) m.querySelector('[name="phone"]').value = data.phone || '';
                    if (m.querySelector('[name="sex"]')) m.querySelector('[name="sex"]').value = data.sex || '';
                    if (m.querySelector('[name="visiting_hours"]')) m.querySelector('[name="visiting_hours"]').value = data.visiting_hours || '';
                    toggleBootstrapModal('editHelpModal', true);
                }
                return;
            }

            // Vehicle Edit
            const vehicleBtn = e.target.closest('.js-edit-vehicle');
            if (vehicleBtn) {
                e.preventDefault();
                const data = JSON.parse(vehicleBtn.getAttribute('data-payload'));
                const m = document.getElementById('vehicleModal');
                if (m) {
                    m.querySelector('[name="action"]').value = 'sgvx51_edit_vehicle_frontend';
                    const idField = m.querySelector('[name="id"]') || m.querySelector('[name="vehicle_id"]');
                    if (idField) idField.value = data.id || '';
                    if (m.querySelector('[name="number"]')) m.querySelector('[name="number"]').value = data.number || '';
                    if (m.querySelector('[name="type"]')) m.querySelector('[name="type"]').value = data.type || '';
                    if (m.querySelector('[name="brand"]')) m.querySelector('[name="brand"]').value = data.brand || '';
                    if (m.querySelector('[name="model"]')) m.querySelector('[name="model"]').value = data.model || '';
                    toggleBootstrapModal('vehicleModal', true);
                }
                return;
            }

            // Vehicle Delete
            const delVehicleBtn = e.target.closest('.js-delete-vehicle-frontend');
            if (delVehicleBtn) {
                e.preventDefault();
                if (!confirm('Are you sure you want to deregister this vehicle?')) return;

                const id = delVehicleBtn.getAttribute('data-id');
                const nonce = delVehicleBtn.getAttribute('data-nonce');
                const originalText = delVehicleBtn.innerHTML;
                delVehicleBtn.disabled = true;
                delVehicleBtn.innerHTML = '...';

                const formData = new FormData();
                formData.append('action', 'sgvx51_delete_vehicle_frontend');
                formData.append('id', id);
                formData.append('_wpnonce', nonce);

                fetch(ajaxurl, {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Vehicle deregistration request submitted!');
                            location.reload();
                        } else {
                            alert('Error: ' + (data.data || 'Failed to delete vehicle'));
                            delVehicleBtn.disabled = false;
                            delVehicleBtn.innerHTML = originalText;
                        }
                    })
                    .catch(err => {
                        console.error('Delete error:', err);
                        alert('An error occurred.');
                        delVehicleBtn.disabled = false;
                        delVehicleBtn.innerHTML = originalText;
                    });
                return;
            }

            // Family Delete
            const delFamilyBtn = e.target.closest('.js-delete-family-frontend');
            if (delFamilyBtn) {
                e.preventDefault();
                handleDelete(delFamilyBtn, 'sgvx51_delete_family_frontend', 'Remove this member?');
                return;
            }

            // Help Delete
            const delHelpBtn = e.target.closest('.js-delete-help-frontend');
            if (delHelpBtn) {
                e.preventDefault();
                handleDelete(delHelpBtn, 'sgvx51_delete_daily_help_frontend', 'Remove this entry?');
                return;
            }

            /**
             * Generic Delete Handler
             */
            function handleDelete(btn, action, confirmMsg) {
                if (!confirm(confirmMsg)) return;

                const id = btn.getAttribute('data-id');
                const nonce = btn.getAttribute('data-nonce');
                const originalText = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '...';

                const formData = new FormData();
                formData.append('action', action);
                formData.append('id', id);
                formData.append('_wpnonce', nonce);

                fetch(ajaxurl, {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Request submitted successfully!');
                            location.reload();
                        } else {
                            alert('Error: ' + (data.data.message || data.data || 'Failed to delete'));
                            btn.disabled = false;
                            btn.innerHTML = originalText;
                        }
                    })
                    .catch(err => {
                        console.error('Delete error:', err);
                        alert('An error occurred.');
                        btn.disabled = false;
                        btn.innerHTML = originalText;
                    });
            }

            // View Receipt Button
            const receiptBtn = e.target.closest('[data-action="view-receipt"]');
            if (receiptBtn) {
                e.preventDefault();
                viewReceipt(receiptBtn);
                return;
            }

            // Community Directory Card
            const dirCard = e.target.closest('[data-action="open-directory"]');
            if (dirCard) {
                e.preventDefault();
                openDirectoryModal(dirCard);
                return;
            }
        });

        // --- 4. Payment Modal Actions ---
        document.addEventListener('click', function (e) {
            if (e.target.id === 'btn-close-payment' || e.target.id === 'btn-cancel-payment') {
                toggleBootstrapModal('sgvx51PaymentModal', false);
            }

            // Handle Pay Button Clicks to Open Modal
            const payBtn = e.target.closest('.js-btn-pay');
            if (payBtn) {
                // The modal is already triggered by data-bs-toggle="modal"
                // We just need to populate it
                const amount = payBtn.getAttribute('data-amount') || '';
                const invId = payBtn.getAttribute('data-invoice-id') || '';

                const form = document.getElementById('payment-confirmation-form');
                if (form) {
                    form.querySelector('[name="amount"]').value = amount;
                    form.querySelector('[name="invoice_id"]').value = invId;
                }
            }

            if (e.target.id === 'btn-confirm-payment') {
                e.preventDefault();
                submitPaymentConfirmation(e.target);
            }
        });

        function submitPaymentConfirmation(btn) {
            const form = document.getElementById('payment-confirmation-form');
            if (!form) return;

            // Validation
            const amount = form.querySelector('[name="amount"]').value;
            const ref = form.querySelector('[name="reference"]').value;
            if (!amount || !ref) {
                alert('Please fill in the Amount and Reference Number.');
                return;
            }

            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';

            const formData = new FormData(form);
            formData.append('action', 'sgvx51_submit_payment_request');
            formData.append('_ajax_nonce', sgvxDashboardData.nonce);

            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.data.message || 'Payment confirmation sent!');
                        toggleBootstrapModal('sgvx51PaymentModal', false);
                        location.reload(); // Refresh to show pending status if we implement it
                    } else {
                        alert('Error: ' + (data.data.message || 'Failed to submit request'));
                        btn.disabled = false;
                        btn.innerHTML = originalText;
                    }
                })
                .catch(error => {
                    console.error('Submission Error:', error);
                    alert('An error occurred. Please try again.');
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                });
        }

        // --- 5. Receipt View Function ---
        function viewReceipt(btn) {
            try {
                const inv = JSON.parse(btn.dataset.invoice);
                const modal = document.getElementById('sgvx-resident-receipt-modal');
                if (!modal) return;

                document.getElementById('receipt-id').textContent = '#' + (inv.id || '000000').substring(inv.id.length - 6);

                const dateVal = inv.date || inv.created_at || new Date();
                const d = new Date(dateVal);
                document.getElementById('receipt-date').textContent = isNaN(d.getTime()) ? 'N/A' : d.toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });

                document.getElementById('receipt-resident-name').textContent = inv.resident_name || 'Resident';
                document.getElementById('receipt-flat-no').textContent = inv.flat_no ? 'Flat ' + inv.flat_no : 'N/A';
                document.getElementById('receipt-desc').textContent = inv.description || 'Society Maintenance';

                let totalPaid = 0;
                let html = '';
                if (inv.payments) {
                    inv.payments.forEach(p => {
                        const amt = parseFloat(p.amount) || 0;
                        totalPaid += amt;
                        const ref = p.reference || p.ref || '-';
                        html += `<tr>
                            <td class="py-3 border-bottom border-light text-dark fw-medium">${p.method || 'Payment'} <span class="text-muted fw-normal ms-2 small">(${p.date || ''})</span></td>
                            <td class="py-3 border-bottom border-light text-end text-dark fw-bold">₹${amt.toLocaleString('en-IN', { minimumFractionDigits: 2 })}</td>
                            <td class="py-3 border-bottom border-light text-muted small">${ref}</td>
                        </tr>`;
                    });
                }
                const container = document.getElementById('receipt-payments');
                if (container) container.innerHTML = html || '<tr><td colspan="3" class="py-4 text-center text-muted">No payments found</td></tr>';

                document.getElementById('receipt-total').textContent = '₹' + totalPaid.toLocaleString('en-IN', { minimumFractionDigits: 2 });

                toggleBootstrapModal('sgvx-resident-receipt-modal', true);
            } catch (err) {
                console.error('viewReceipt Error:', err);
            }
        }

        // --- 6. Community Directory Modal ---
        function openDirectoryModal(card) {
            try {
                const data = JSON.parse(card.dataset.json);
                const m = document.getElementById('communityDetailModal');
                if (!m) {
                    console.error('communityDetailModal not found in DOM');
                    return;
                }

                // Fill basics
                document.getElementById('cdm-flat').textContent = data.flat_no;
                document.getElementById('cdm-owner').textContent = data.owner;
                document.getElementById('cdm-members').textContent = data.members;

                // Vehicles
                let vHtml = '';
                if (data.vehicles && data.vehicles.length) {
                    data.vehicles.forEach(v => {
                        const icon = v.type.toLowerCase() === 'bike' ? 'bi-bicycle' : 'bi-car-front';
                        vHtml += `<div class="p-2 border rounded border-light bg-light">
                            <div class="fw-bold small ms-1"><i class="${icon} me-2 text-primary"></i>${v.number}</div>
                            <div class="text-muted" style="font-size:10px; margin-left: 24px;">${v.brand || ''} ${v.type}</div>
                        </div>`;
                    });
                } else {
                    vHtml = '<div class="text-muted small italic">No vehicles</div>';
                }
                if (document.getElementById('cdm-vehicles')) {
                    document.getElementById('cdm-vehicles').innerHTML = vHtml;
                }

                // Help
                let hHtml = '';
                if (data.help && data.help.length) {
                    data.help.forEach(h => {
                        hHtml += `<div class="p-2 border rounded border-light bg-light">
                            <div class="fw-bold small ms-1"><i class="bi bi-person-badge me-2 text-primary"></i>${h.name}</div>
                            <div class="text-muted" style="font-size:10px; margin-left: 24px;">${h.role} • ${h.visiting_hours || ''}</div>
                        </div>`;
                    });
                } else {
                    hHtml = '<div class="text-muted small italic">No daily help registered</div>';
                }
                if (document.getElementById('cdm-help')) {
                    document.getElementById('cdm-help').innerHTML = hHtml;
                }

                toggleBootstrapModal('communityDetailModal', true);
            } catch (err) {
                console.error('openDirectoryModal Error:', err);
            }
        }

        // --- 7. Directory Search & Filter (Event Delegation) ---
        document.addEventListener('input', function (e) {
            if (e.target.id === 'dir-search') {
                filterDirectory(e.target.value);
            }
        });

        document.addEventListener('click', function (e) {
            const filterBtn = e.target.closest('[data-filter-type]');
            if (filterBtn) {
                e.preventDefault();
                const filterType = filterBtn.getAttribute('data-filter-type');
                filterDirectoryByType(filterType, filterBtn);
            }
        });

        function filterDirectory(query) {
            const q = query.toLowerCase();
            const cards = document.querySelectorAll('.dir-card');
            cards.forEach(c => {
                const text = c.dataset.json.toLowerCase();
                if (text.includes(q)) c.classList.remove('d-none');
                else c.classList.add('d-none');
            });
        }

        function filterDirectoryByType(filter, btn) {
            const cards = document.querySelectorAll('.dir-card');

            // Update button states
            document.querySelectorAll('.dir-filter-btn').forEach(b => {
                b.classList.remove('btn-dark', 'active');
                b.classList.add('btn-light', 'text-secondary');
            });
            if (btn) {
                btn.classList.add('btn-dark', 'active');
                btn.classList.remove('btn-light', 'text-secondary');
            }

            // Filter cards
            cards.forEach(c => {
                if (filter === 'all') {
                    c.classList.remove('d-none');
                } else if (filter === 'vehicle' && c.dataset.hasVehicle === '1') {
                    c.classList.remove('d-none');
                } else if (filter === 'help' && c.dataset.hasHelp === '1') {
                    c.classList.remove('d-none');
                } else {
                    c.classList.add('d-none');
                }
            });
        }

        // --- Receipt Functions (exposed globally) ---
        window.viewInvoiceReceipt = function (btn) {
            const invoiceId = btn.getAttribute('data-invoice-id');

            // Make AJAX request to fetch receipt data
            fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=sgvx51_get_receipt&invoice_id=' + encodeURIComponent(invoiceId) + '&nonce=' + sgvx51_nonce
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        populateReceiptModal(data.data);
                        const modal = new bootstrap.Modal(document.getElementById('receiptModal'));
                        modal.show();
                    } else {
                        alert('Error loading receipt: ' + (data.data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Receipt fetch error:', error);
                    alert('Error loading receipt. Please try again.');
                });
        };

        function populateReceiptModal(receiptData) {
            const receiptContent = document.getElementById('receipt-content');
            if (!receiptContent) return;

            // Calculate payment details
            let paymentRows = '';

            if (receiptData.payments && receiptData.payments.length > 0) {
                receiptData.payments.forEach(p => {
                    const ref = p.reference || p.ref || '-';
                    paymentRows += `
                        <tr>
                            <td class="py-3 border-bottom border-light text-dark fw-medium">${p.method || 'Payment'} <span class="text-muted fw-normal ms-2 small">(${p.date || ''})</span></td>
                            <td class="py-3 border-bottom border-light text-end text-dark fw-bold">₹${parseFloat(p.amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 })}</td>
                            <td class="py-3 border-bottom border-light text-muted small">${ref}</td>
                        </tr>
                    `;
                });
            }

            const invoiceAmount = parseFloat(receiptData.invoice_amount || 0);
            const totalPaid = parseFloat(receiptData.total_paid || 0);
            const balanceDue = parseFloat(receiptData.balance_due || 0);

            const statusClass = receiptData.status === 'paid' ? 'bg-success text-white' : (receiptData.status === 'partial' ? 'bg-warning text-dark' : 'bg-danger text-white');
            const statusText = receiptData.status === 'paid' ? 'FULLY PAID' : (receiptData.status === 'partial' ? 'PARTIALLY PAID' : 'UNPAID');

            // Reset flex classes and padding for A4
            receiptContent.className = 'receipt';
            receiptContent.style.minHeight = 'auto';

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
                        <div class="receipt-value">${new Date(receiptData.invoice_month + '-01').toLocaleDateString('en-IN', { month: 'long', year: 'numeric' })}</div>
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
                    <p class="mb-0">Society GoVernX - Empowering Communities</p>
                </div>
            `;
        }

        window.downloadReceipt = function () {
            const receiptElement = document.getElementById('receipt-content');
            if (!receiptElement) {
                alert('Receipt not found!');
                return;
            }

            // Show loading state
            const btn = event.target.closest('button');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Generating...';

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
                const receiptNumber = receiptElement.querySelector('.receipt-header-title strong')?.textContent || 'Receipt';
                link.href = canvas.toDataURL('image/png');
                link.download = `${receiptNumber}.png`;
                link.click();

                // Restore button
                btn.disabled = false;
                btn.innerHTML = originalText;
            }).catch(error => {
                console.error('Download error:', error);
                alert('Error generating receipt image. Please try again.');
                btn.disabled = false;
                btn.innerHTML = originalText;
            });
        };

        // --- 8. Vehicle Form Submission ---
        const vehicleForm = document.querySelector('#vehicleModal form');
        if (vehicleForm) {
            vehicleForm.addEventListener('submit', function (e) {
                e.preventDefault();
                handleFormSubmit(vehicleForm, 'sgvx51_add_vehicle_frontend', '#vehicleModal');
            });
        }

        // --- 9. Family Form Submission ---
        const familyForm = document.querySelector('#familyModal form');
        if (familyForm) {
            familyForm.addEventListener('submit', function (e) {
                e.preventDefault();
                handleFormSubmit(familyForm, 'sgvx51_add_family_frontend', '#familyModal');
            });
        }

        // --- 10. Help Form Submission ---
        const helpForm = document.querySelector('#helpModal form');
        if (helpForm) {
            helpForm.addEventListener('submit', function (e) {
                e.preventDefault();
                handleFormSubmit(helpForm, 'sgvx51_add_help_frontend', '#helpModal');
            });
        }

        // --- 11. Family Edit Form Submission ---
        const editFamilyForm = document.querySelector('#editFamilyModal form');
        if (editFamilyForm) {
            editFamilyForm.addEventListener('submit', function (e) {
                e.preventDefault();
                handleFormSubmit(editFamilyForm, 'sgvx51_edit_family_frontend', '#editFamilyModal');
            });
        }

        // --- 12. Help Edit Form Submission ---
        const editHelpForm = document.querySelector('#editHelpModal form');
        if (editHelpForm) {
            editHelpForm.addEventListener('submit', function (e) {
                e.preventDefault();
                handleFormSubmit(editHelpForm, 'sgvx51_edit_help_frontend', '#editHelpModal');
            });
        }

        /**
         * Generic Form Handler
         */
        function handleFormSubmit(form, defaultAction, modalId) {
            const btn = form.querySelector('button[type="submit"]');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';

            const formData = new FormData(form);
            // Force set the action to ensure we use the AJAX handler, overriding any legacy form input
            formData.set('action', defaultAction);

            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.data.message || 'Request submitted successfully!');
                        toggleBootstrapModal(modalId.replace('#', ''), false);
                        location.reload();
                    } else {
                        alert('Error: ' + (data.data.message || data.data || 'Failed to save'));
                        btn.disabled = false;
                        btn.innerHTML = originalText;
                    }
                })
                .catch(err => {
                    console.error('Save error:', err);
                    alert('An error occurred. Please try again.');
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                });
        }

    });
})();


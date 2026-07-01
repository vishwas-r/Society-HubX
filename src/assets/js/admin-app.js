
/**
 * Admin App JS
 * Handles interactions for the Society Govern X Admin App.
 */

(function () {
    // --- Initialization ---
    const initApp = () => {
        // 0. Centralized Toast Detection
        checkUrlForToasts();

        // 1. toggleModal Helper
        window.toggleModal = function (modalId, show) {
            const m = document.getElementById(modalId);
            if (m) {
                if (show) {
                    m.classList.remove('hidden');
                    m.style.zIndex = '9999';
                } else {
                    m.classList.add('hidden');
                    m.style.zIndex = '';
                }
            }
        };

        // 2. Generic Modal Dismissal
        document.addEventListener('click', function (e) {
            const dismiss = e.target.closest('[data-dismiss-modal]');
            if (dismiss) {
                e.preventDefault();
                const modal = dismiss.closest('.fixed.inset-0.z-50, .fixed.inset-0.z-\\[60\\]');
                if (modal) window.toggleModal(modal.id, false);
            }
        });

        // 3. Asset Management Logic
        const btnAddAsset = document.getElementById('btn-add-asset');
        if (btnAddAsset) {
            btnAddAsset.addEventListener('click', (e) => {
                e.preventDefault();
                window.toggleModal('addAssetModal', true);
            });
        }

        const editBtns = document.querySelectorAll('[id^="btn-edit-asset-"]');
        editBtns.forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                try {
                    const payload = this.getAttribute('data-payload');
                    const data = JSON.parse(payload);

                    const m = document.getElementById('editAssetModal');
                    if (!m) return;

                    m.querySelector('[name="asset_id"]').value = data.id;
                    m.querySelector('[name="name"]').value = data.name;
                    m.querySelector('[name="purchase_date"]').value = data.purchase_date || '';
                    m.querySelector('[name="warranty_expiry"]').value = data.warranty_expiry || '';
                    m.querySelector('[name="amc_provider"]').value = data.amc_provider || '';
                    m.querySelector('[name="amc_phone"]').value = data.amc_phone || '';
                    m.querySelector('[name="status"]').value = data.status || 'Active';

                    window.toggleModal('editAssetModal', true);
                } catch (err) { }
            });
        });

        // 4. Sidebar Toggle Logic
        const sidebar = document.getElementById('snestx-sidebar');
        const sidebarToggle = document.getElementById('snestx-sidebar-toggle');
        const sidebarClose = document.getElementById('snestx-sidebar-close');
        const sidebarBackdrop = document.getElementById('snestx-sidebar-backdrop');

        if (sidebar && sidebarToggle) {
            // Restore state from localStorage
            const isCollapsed = localStorage.getItem('SNESTX_sidebar_collapsed') === 'true';
            if (isCollapsed && window.innerWidth >= 992) {
                sidebar.classList.add('collapsed');
            }

            sidebarToggle.addEventListener('click', (e) => {
                e.preventDefault();
                if (window.innerWidth < 992) {
                    // Mobile: Toggle overlay
                    const isOpening = !sidebar.classList.contains('show');
                    sidebar.classList.toggle('show');
                    sidebarBackdrop.classList.toggle('show');
                    sidebarBackdrop.classList.toggle('d-none');

                    // Body lock
                    document.body.style.overflow = isOpening ? 'hidden' : '';
                } else {
                    // Desktop: Toggle collapse
                    sidebar.classList.toggle('collapsed');
                    localStorage.setItem('SNESTX_sidebar_collapsed', sidebar.classList.contains('collapsed'));
                }
            });
        }

        if (sidebarClose && sidebarBackdrop) {
            const closeSidebar = () => {
                sidebar.classList.remove('show');
                sidebarBackdrop.classList.remove('show');
                sidebarBackdrop.classList.add('d-none');
                document.body.style.overflow = '';
            };

            sidebarClose.addEventListener('click', closeSidebar);
            sidebarBackdrop.addEventListener('click', closeSidebar);
        }

        // 5. Global Chart Responsiveness (CanvasJS)
        window.addEventListener('resize', debounce(() => {
            if (window.SNESTXCharts && Array.isArray(window.SNESTXCharts)) {
                window.SNESTXCharts.forEach(chart => {
                    if (typeof chart.render === 'function') chart.render();
                });
            }
        }, 200));
    };

    // Helper: Debounce
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Robust Startup
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initApp);
    } else {
        initApp();
    }

    // --- Global Functions (Exposed to Window) ---

    // Note: switchTab, applyFilters, clearFilters are now handled locally in view templates
    // to avoid namespace collisions between Residents, Flats, and Vehicles.

    window.switchPage = function (page) {
        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('view', page);
        window.location.href = currentUrl.toString();
    };

    // --- Receipt Logic (Removed to avoid conflict with standardized accounts receipt) ---

    /**
     * Global AJAX API Wrapper - MOVED TO core.js
     */
    /*
    window.SNESTXApiRequest = async function (action, data = {}) { ... };
    */



    // --- Download Admin Receipt Logic (Removed) ---

    /**
     * Show Global Toast - MOVED TO core.js
     */
    /*
    window.SNESTXShowToast = function (msg, type = 'success') {
        ...
    };
    */

    /**
     * Automatic URL Notification Detector
     */
    function checkUrlForToasts() {
        const params = new URLSearchParams(window.location.search);

        // 1. Direct Message Parameter
        const msg = params.get('msg');
        const error = params.get('error');
        if (msg) return window.SNESTXShowToast(decodeURIComponent(msg.replace(/\+/g, ' ')), 'success');
        if (error) return window.SNESTXShowToast(decodeURIComponent(error.replace(/\+/g, ' ')), 'error');

        // 2. Status Code Parameter
        const status = params.get('status');
        const success = params.get('success');

        if (status || success) {
            const statusMap = {
                'added': 'Record added successfully',
                'updated': 'Changes saved successfully',
                'restored': 'Record restored from archive',
                'deleted': 'Record archived successfully',
                'history_deleted': 'Record permanently deleted',
                'imported': 'Data imported successfully',
                'success': 'Operation completed successfully',
                'error': 'Operation failed'
            };

            const statusCode = status || success;
            if (statusMap[statusCode]) {
                window.SNESTXShowToast(statusMap[statusCode], 'success');
            }
        }

        // 3. Clean URL (Production Grade) - Remove status/msg/error params
        if (params.has('status') || params.has('msg') || params.has('error') || params.has('success')) {
            const cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + (params.get('page') ? '?page=' + params.get('page') : '');
            window.history.replaceState({ path: cleanUrl }, '', cleanUrl);
        }
    }

    // --- Centralized Request Detail Logic ---
    document.addEventListener('click', function (e) {
        const viewBtn = e.target.closest('.js-view-request-detail');
        if (!viewBtn) return;

        e.preventDefault();
        const data = viewBtn.dataset;
        const modalEl = document.getElementById('requestDetailModal');
        if (!modalEl) return;

        let payload = {};
        let original = {};
        const module = data.module;
        const type = data.requestType;

        try {
            payload = JSON.parse(data.payload || '{}');
            original = JSON.parse(data.original || '{}');
        } catch (err) {
            console.error('SNESTX: Error parsing request payload', err, data.payload);
        }

        // Relocate to body if not already there to fix z-index/stacking context issues in WP Admin
        if (modalEl.parentElement !== document.body) {
            document.body.appendChild(modalEl);
        }

        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

        // 1. Setup Header & Icon
        const iconWrapper = document.getElementById('rd-icon-wrapper');
        const icon = document.getElementById('rd-icon');
        const title = document.getElementById('rd-title');
        const subtitle = document.getElementById('rd-subtitle');

        iconWrapper.className = 'rounded-3 d-flex align-items-center justify-content-center ';
        icon.className = 'bi fs-4 ';

        if (type === 'add' || type === 'upload') {
            iconWrapper.classList.add('bg-success', 'bg-opacity-10', 'text-success');
            icon.classList.add('bi-plus-circle');
        } else if (type === 'delete') {
            iconWrapper.classList.add('bg-danger', 'bg-opacity-10', 'text-danger');
            icon.classList.add('bi-trash');
        } else {
            iconWrapper.classList.add('bg-info', 'bg-opacity-10', 'text-info');
            icon.classList.add('bi-pencil-square');
        }

        title.textContent = (type.charAt(0).toUpperCase() + type.slice(1)) + ' ' + (module.replace('_', ' ').charAt(0).toUpperCase() + module.replace('_', ' ').slice(1));
        subtitle.textContent = `Requested by ${data.requester} on ${data.date}`;

        // 2. Populate Summary Grid with Integrated Comparison
        const grid = document.getElementById('rd-summary-grid');
        grid.innerHTML = '';

        const summaryFields = {
            'name': 'Full Name',
            'flat_no': 'Flat / Unit',
            'phone': 'Phone Number',
            'email': 'Email Address',
            'number': 'Vehicle Number',
            'brand': 'Vehicle Brand',
            'model': 'Vehicle Model',
            'role': 'Role / Occupation',
            'category': 'Request Category',
            'comments': 'Comments / Details',
            'resident_name': 'Resident Name',
            'invoice_id': 'Payment Towards',
            'amount': 'Invoice Amount',
            'method': 'Payment Method',
            'reference': 'Reference / UTR',
            'date': 'Transaction Date',
            'type': 'Type',
            'relation': 'Relation'
        };

        const skipKeys = ['id', 'resident_id', 'action', '_wpnonce', 'original_data', 'module', 'status'];

        // Sort keys to show important ones first (name, flat, category, then others, then comments last)
        const keys = Object.keys(payload).sort((a, b) => {
            const order = ['name', 'resident_name', 'flat_no', 'category', 'type', 'amount', 'method'];
            const idxA = order.indexOf(a);
            const idxB = order.indexOf(b);
            if (idxA !== -1 && idxB !== -1) return idxA - idxB;
            if (idxA !== -1) return -1;
            if (idxB !== -1) return 1;
            if (a === 'comments') return 1;
            if (b === 'comments') return -1;
            return a.localeCompare(b);
        });

        keys.forEach(key => {
            if (skipKeys.includes(key)) return;
            const newVal = payload[key];
            const oldVal = original[key];

            if (newVal !== undefined && newVal !== null && newVal !== '') {
                const col = document.createElement('div');
                // Give comments more space
                col.className = (key === 'comments' || String(newVal).length > 40) ? 'col-12' : 'col-md-6';

                let label = summaryFields[key] || key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                let valueHtml = `<div class="fw-bold text-dark">${newVal}</div>`;

                // If it's an edit and the value has changed, show comparison
                if (type === 'edit' && oldVal !== undefined && String(oldVal) !== String(newVal)) {
                    valueHtml = `
                        <div class="d-flex flex-column">
                            <span class="text-decoration-line-through text-danger small opacity-75">${oldVal || '(Empty)'}</span>
                            <span class="fw-bold text-success">${newVal}</span>
                        </div>
                    `;
                }

                col.innerHTML = `
                    <div class="bg-light p-3 rounded-3 border-0 h-100">
                        <div class="text-secondary small fw-bold text-uppercase mb-1" style="font-size: 10px;">${label}</div>
                        ${valueHtml}
                    </div>
                `;
                grid.appendChild(col);
            }
        });

        // 3. Setup Actions
        const approveBtn = document.getElementById('rd-approve-btn');
        const rejectBtn = document.getElementById('rd-reject-btn');

        approveBtn.dataset.id = data.id;
        rejectBtn.dataset.id = data.id;

        modal.show();
    });

    // --- Global Approval Handlers ---
    document.addEventListener('click', async function (e) {
        const approveBtn = e.target.closest('.js-approve-inline');
        const rejectBtn = e.target.closest('.js-reject-inline');

        if (approveBtn) {
            e.preventDefault();
            const id = approveBtn.dataset.id;
            if (!confirm('Approve this request?')) return;

            SNESTX.ajax({
                action: 'SNESTX51_approve_request',
                data: {
                    id: id,
                    _wpnonce: typeof snestx51RequestNonce !== 'undefined' ? snestx51RequestNonce : ''
                },
                loadingButton: approveBtn,
                successMessage: 'Request approved successfully!',
                reload: true
            });
        }

        if (rejectBtn) {
            e.preventDefault();
            const id = rejectBtn.dataset.id;
            const reason = prompt('Reason for rejection (optional):');
            if (reason === null) return; // Cancelled prompt

            SNESTX.ajax({
                action: 'SNESTX51_reject_request',
                data: {
                    id: id,
                    admin_note: reason,
                    _wpnonce: typeof snestx51RequestNonce !== 'undefined' ? snestx51RequestNonce : ''
                },
                loadingButton: rejectBtn,
                successMessage: 'Request rejected.',
                reload: true
            });
        }
    });

    /**
     * Bulk Action Logic
     */
    window.SNESTXBulkProcess = function (action) {
        const checkboxes = document.querySelectorAll('.snestx-bulk-checkbox:checked');
        if (checkboxes.length === 0) {
            SNESTX.toast.warning('Please select at least one item');
            return;
        }

        const ids = Array.from(checkboxes).map(cb => cb.value);
        let note = '';
        if (action === 'reject') {
            note = prompt('Reason for bulk rejection:');
            if (note === null) return;
        } else {
            if (!confirm(`Are you sure you want to approve ${ids.length} items?`)) return;
        }

        SNESTX.ajax({
            action: 'SNESTX51_bulk_process_requests',
            data: {
                ids: ids,
                bulk_action: action,
                note: note,
                _wpnonce: typeof snestx51RequestNonce !== 'undefined' ? snestx51RequestNonce : ''
            },
            successMessage: `Bulk ${action} processed successfully!`,
            reload: true
        });
    };

    // --- Bulk Checkbox Helpers ---
    document.addEventListener('change', function (e) {
        if (e.target.id === 'bulk-select-all') {
            const checkboxes = document.querySelectorAll('.snestx-bulk-checkbox:not(:disabled)');
            checkboxes.forEach(cb => cb.checked = e.target.checked);
            updateBulkToolbar();
        }

        if (e.target.classList.contains('snestx-bulk-checkbox')) {
            updateBulkToolbar();
        }
    });

    function updateBulkToolbar() {
        const checked = document.querySelectorAll('.snestx-bulk-checkbox:checked').length;
        const toolbar = document.querySelector('.snestx-bulk-actions');
        const countSpan = document.getElementById('selected-count');

        if (toolbar) {
            if (checked > 0) {
                toolbar.classList.remove('d-none');
                if (countSpan) countSpan.textContent = checked;
            } else {
                toolbar.classList.add('d-none');
            }
        }
    }

    // --- Startup ---
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initApp);
    } else {
        initApp();
    }
})();

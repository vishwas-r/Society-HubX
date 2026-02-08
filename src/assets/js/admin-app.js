
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
        const sidebar = document.getElementById('sgvx-sidebar');
        const sidebarToggle = document.getElementById('sgvx-sidebar-toggle');
        const sidebarClose = document.getElementById('sgvx-sidebar-close');
        const sidebarBackdrop = document.getElementById('sgvx-sidebar-backdrop');

        if (sidebar && sidebarToggle) {
            // Restore state from localStorage
            const isCollapsed = localStorage.getItem('sgvx_sidebar_collapsed') === 'true';
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
                    localStorage.setItem('sgvx_sidebar_collapsed', sidebar.classList.contains('collapsed'));
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
            if (window.sgvxCharts && Array.isArray(window.sgvxCharts)) {
                window.sgvxCharts.forEach(chart => {
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
     * Global AJAX API Wrapper
     * @param {string} action WordPress AJAX action
     * @param {object} data   Payload data
     * @returns {Promise}
     */
    window.sgvxApiRequest = async function (action, data = {}) {
        let formData;

        if (data instanceof FormData) {
            formData = data;
        } else {
            formData = new FormData();
            for (const [key, val] of Object.entries(data)) {
                if (Array.isArray(val)) {
                    val.forEach(item => formData.append(key + '[]', item));
                } else if (val !== null && val !== undefined) {
                    formData.append(key, val);
                }
            }
        }

        if (!formData.has('action')) formData.append('action', action);

        // 1. Determine the Nonce
        let finalNonce = formData.get('_wpnonce') || '';

        // 2. Auto-inject fallbacks if payload nonce is missing
        if (!finalNonce) {
            if (window.sgvxResidentsData) finalNonce = window.sgvxResidentsData.nonce;
            else if (window.sgvxFacilitiesData) finalNonce = window.sgvxFacilitiesData.nonce;
            else if (window.sgvxNoticesData) finalNonce = window.sgvxNoticesData.nonce;
            else if (window.sgvxDocumentsData) finalNonce = window.sgvxDocumentsData.nonce;
            else if (window.sgvxExpensesData) finalNonce = window.sgvxExpensesData.nonce;
            else if (window.sgvxAccountsData) finalNonce = window.sgvxAccountsData.nonce;
            else if (window.sgvxVehiclesData) finalNonce = window.sgvxVehiclesData.nonce;
            else if (window.sgvxFlatsData) finalNonce = window.sgvxFlatsData.nonce;
            else if (window.sgvxStaffData) finalNonce = window.sgvxStaffData.nonce;
        }

        if (finalNonce && !formData.has('_wpnonce')) {
            formData.append('_wpnonce', finalNonce);
        } else if (finalNonce && formData.has('_wpnonce') && !formData.get('_wpnonce')) {
            // If it's empty string, update it
            formData.set('_wpnonce', finalNonce);
        }

        try {
            const response = await fetch(ajaxurl, {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                const text = await response.text();
                throw new Error(text || 'Network response was not ok');
            }

            let result;
            const responseText = await response.text();
            try {
                result = JSON.parse(responseText);
            } catch (e) {
                console.error('SGVX JSON Parse Error. Raw Response:', responseText);
                throw new Error('Invalid JSON response from server. Check console for details.');
            }

            if (result.success) {
                if (result.data && result.data.message) {
                    window.sgvxShowToast(result.data.message, 'success');
                }
                return result.data || true;
            } else {
                const errorMsg = result.data && result.data.message ? result.data.message : 'Operation failed';
                window.sgvxShowToast(errorMsg, 'error');
                throw new Error(errorMsg);
            }
        } catch (error) {
            console.error('SGVX API Error:', error);
            window.sgvxShowToast(error.message, 'error');
            throw error;
        }
    };

    // --- Download Admin Receipt Logic (Removed) ---

    /**
     * Show Global Toast
     * @param {string} msg 
     * @param {string} type 'success' | 'error'
     */
    window.sgvxShowToast = function (msg, type = 'success') {
        const toastEl = document.getElementById('sgvx-global-toast');
        const iconEl = document.getElementById('sgvx-toast-icon');
        const msgEl = document.getElementById('sgvx-toast-message');
        if (!toastEl || !msgEl || !iconEl) return;

        // Reset classes
        toastEl.classList.remove('bg-success', 'bg-danger', 'text-white');
        iconEl.classList.remove('bi-check-circle-fill', 'bi-exclamation-triangle-fill');

        if (type === 'success') {
            toastEl.classList.add('bg-success', 'text-white');
            iconEl.classList.add('bi-check-circle-fill');
        } else {
            toastEl.classList.add('bg-danger', 'text-white');
            iconEl.classList.add('bi-exclamation-triangle-fill');
        }

        msgEl.textContent = msg;

        const toast = new bootstrap.Toast(toastEl, { delay: 3000 });
        toast.show();
    };

    /**
     * Automatic URL Notification Detector
     */
    function checkUrlForToasts() {
        const params = new URLSearchParams(window.location.search);

        // 1. Direct Message Parameter
        const msg = params.get('msg');
        const error = params.get('error');
        if (msg) return window.sgvxShowToast(decodeURIComponent(msg.replace(/\+/g, ' ')), 'success');
        if (error) return window.sgvxShowToast(decodeURIComponent(error.replace(/\+/g, ' ')), 'error');

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
                window.sgvxShowToast(statusMap[statusCode], 'success');
            }
        }

        // 3. Clean URL (Production Grade) - Remove status/msg/error params
        if (params.has('status') || params.has('msg') || params.has('error') || params.has('success')) {
            const cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + (params.get('page') ? '?page=' + params.get('page') : '');
            window.history.replaceState({ path: cleanUrl }, '', cleanUrl);
        }
    }

    // --- Global Approval Handlers ---
    document.addEventListener('click', async function (e) {
        const approveBtn = e.target.closest('.js-approve-inline');
        const rejectBtn = e.target.closest('.js-reject-inline');

        if (approveBtn) {
            e.preventDefault();
            const id = approveBtn.dataset.id;
            if (!confirm('Approve this request?')) return;

            approveBtn.disabled = true;
            approveBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';

            try {
                await window.sgvxApiRequest('sgvx51_approve_request', {
                    id: id,
                    _wpnonce: typeof sgvx51RequestNonce !== 'undefined' ? sgvx51RequestNonce : ''
                });
                // Row update logic: refresh page or remove row
                window.location.reload();
            } catch (err) {
                approveBtn.disabled = false;
                approveBtn.innerHTML = '<i class="bi bi-check-lg" style="font-size: 1.1rem;"></i>';
            }
        }

        if (rejectBtn) {
            e.preventDefault();
            const id = rejectBtn.dataset.id;
            const reason = prompt('Reason for rejection (optional):');
            if (reason === null) return; // Cancelled prompt

            rejectBtn.disabled = true;
            rejectBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';

            try {
                await window.sgvxApiRequest('sgvx51_reject_request', {
                    id: id,
                    admin_note: reason,
                    _wpnonce: typeof sgvx51RequestNonce !== 'undefined' ? sgvx51RequestNonce : ''
                });
                window.location.reload();
            } catch (err) {
                rejectBtn.disabled = false;
                rejectBtn.innerHTML = '<i class="bi bi-x-lg" style="font-size: 1.1rem;"></i>';
            }
        }
    });

    /**
     * Bulk Action Logic
     */
    window.sgvxBulkProcess = async function (action) {
        const checkboxes = document.querySelectorAll('.sgvx-bulk-checkbox:checked');
        if (checkboxes.length === 0) {
            window.sgvxShowToast('Please select at least one item', 'error');
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

        try {
            await window.sgvxApiRequest('sgvx51_bulk_process_requests', {
                ids: ids,
                bulk_action: action,
                note: note,
                _wpnonce: typeof sgvx51RequestNonce !== 'undefined' ? sgvx51RequestNonce : ''
            });
            window.location.reload();
        } catch (err) {
            // Error already handled by sgvxApiRequest toast
        }
    };

    // --- Bulk Checkbox Helpers ---
    document.addEventListener('change', function (e) {
        if (e.target.id === 'bulk-select-all') {
            const checkboxes = document.querySelectorAll('.sgvx-bulk-checkbox:not(:disabled)');
            checkboxes.forEach(cb => cb.checked = e.target.checked);
            updateBulkToolbar();
        }

        if (e.target.classList.contains('sgvx-bulk-checkbox')) {
            updateBulkToolbar();
        }
    });

    function updateBulkToolbar() {
        const checked = document.querySelectorAll('.sgvx-bulk-checkbox:checked').length;
        const toolbar = document.querySelector('.sgvx-bulk-actions');
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

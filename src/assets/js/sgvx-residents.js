(function ($) {
    'use strict';

    // Module configuration (fetched at runtime)
    const Config = {
        nonce: null,
        deleteNonce: null,
        restoreNonce: null,
        deleteHistoryNonce: null
    };

    // Namespace for internal state
    const State = {
        currentTab: 'all',
        residentModal: null,
        initialized: false,
        fuse: null
    };

    // --- Photo Preview ---
    window.previewImage = function (input) {
        const preview = document.getElementById('preview-admin');
        const icon = document.getElementById('icon-admin');

        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function (e) {
                if (preview) {
                    preview.src = e.target.result;
                    preview.classList.remove('d-none');
                }
                if (icon) icon.classList.add('d-none');
            };
            reader.readAsDataURL(input.files[0]);
        }
    };

    // --- Filter Logic ---
    function toggleFilters() {
        const section = document.getElementById('filter-section');
        if (section) {
            const bsCollapse = bootstrap.Collapse.getOrCreateInstance(section);
            bsCollapse.toggle();
        }
    }

    function applyFilters() {
        const searchInput = document.getElementById('filter-search');
        const typeFilter = document.getElementById('filter-type');
        const statusFilter = document.getElementById('filter-status');

        const searchVal = searchInput ? searchInput.value.trim().toLowerCase() : '';
        const typeVal = typeFilter ? typeFilter.value : 'all';
        const statusVal = statusFilter ? statusFilter.value : 'all';

        const rows = document.querySelectorAll('.resident-row');

        // Refresh Fuse if needed
        if (!State.fuse) {
            State.fuse = window.sgvxCreateFuse('.resident-row');
        }

        const fuzzyMatches = searchVal && window.sgvxGetFuzzyMatches ? window.sgvxGetFuzzyMatches(State.fuse, searchVal) : null;

        rows.forEach(row => {
            const type = row.dataset.type;
            const status = row.dataset.status;

            // Tab logic
            let matchesTab = false;
            const isArchived = (status === 'archived');
            const isPending = (status === 'pending' || status === 'rejected' || status === 'deletion_pending');

            if (isArchived) {
                matchesTab = (State.currentTab === 'archived');
            } else if (State.currentTab === 'pending') {
                matchesTab = isPending;
            } else {
                matchesTab = (State.currentTab === 'all') || (type === State.currentTab);
            }

            // Advanced Filters logic
            let matchesType = (typeVal === 'all') || (type === typeVal);
            let matchesStatus = (statusVal === 'all' && status !== 'archived') || (statusVal === 'archived' && status === 'archived');
            let matchesSearch = !searchVal || (fuzzyMatches && fuzzyMatches.has(row));

            if (matchesTab && matchesType && matchesStatus && matchesSearch) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    function clearFilters() {
        const searchInput = document.getElementById('filter-search');
        if (searchInput) {
            searchInput.value = '';
            applyFilters();
        }
    }

    function switchTab(btn) {
        const tab = btn.dataset.tab;
        State.currentTab = tab;

        // Update UI (Bootstrap active state)
        document.querySelectorAll('#residentsTabs .nav-link').forEach(b => {
            if (b.dataset.tab === tab) {
                b.classList.add('active', 'border-primary', 'text-primary');
                b.classList.remove('border-transparent', 'text-muted');
            } else {
                b.classList.remove('active', 'border-primary', 'text-primary');
                b.classList.add('border-transparent', 'text-muted');
            }
        });

        applyFilters();
    }

    // --- Modal Logic ---
    function openResidentModal() {
        if (!State.residentModal) {
            const el = document.getElementById('residentModal');
            if (el) State.residentModal = new bootstrap.Modal(el);
        }
        if (State.residentModal) State.residentModal.show();
    }

    function closeResidentModal() {
        const el = document.getElementById('residentModal');
        if (el) {
            const inst = bootstrap.Modal.getOrCreateInstance(el);
            if (inst) inst.hide();
        }
    }

    function resetResidentForm() {
        const form = document.getElementById('add-resident-form');
        if (!form) return;

        form.reset();

        const actionInput = form.querySelector('[name="action"]');
        if (actionInput) actionInput.value = 'sgvx51_add_resident';

        const idInput = form.querySelector('[name="resident_id"]');
        if (idInput) idInput.value = '';

        const title = document.getElementById('modal-title');
        if (title) title.textContent = 'Add New Resident';

        // Reset Photo Preview
        const preview = document.getElementById('preview-admin');
        const icon = document.getElementById('icon-admin');
        if (preview) {
            preview.src = '';
            preview.classList.add('d-none');
        }
        if (icon) icon.classList.remove('d-none');
    }

    function editResident(btn) {
        try {
            const r = JSON.parse(btn.dataset.resident);
            const form = document.getElementById('add-resident-form');
            if (!form) return;

            // Helper to safe set value
            const setVal = (name, val) => {
                const el = form.querySelector(`[name="${name}"]`);
                if (el) el.value = val;
            };

            setVal('name', r.name);
            setVal('flat_no', r.flat_no);
            setVal('type', r.type);
            setVal('phone', r.phone);
            setVal('email', r.email);
            setVal('dob', r.dob);
            setVal('blood_group', r.blood_group);
            setVal('role', r.roles || r.role || '');

            setVal('action', 'sgvx51_edit_resident');
            setVal('resident_id', r.id);

            const title = document.getElementById('modal-title');
            if (title) title.textContent = 'Edit Resident ' + r.name;

            // Set Photo Preview
            const preview = document.getElementById('preview-admin');
            const icon = document.getElementById('icon-admin');
            if (r.profile_photo) {
                if (preview) {
                    preview.src = r.profile_photo;
                    preview.classList.remove('d-none');
                }
                if (icon) icon.classList.add('d-none');
            } else {
                if (preview) {
                    preview.src = '';
                    preview.classList.add('d-none');
                }
                if (icon) icon.classList.remove('d-none');
            }

            openResidentModal();

        } catch (e) {
            console.error('Error parsing resident data', e);
        }
    }

    async function deleteResident(id) {
        const modalEl = document.getElementById('deleteConfirmModal');
        const confirmBtn = document.getElementById('confirm-delete-btn');
        if (!modalEl || !confirmBtn) return;

        const modal = new bootstrap.Modal(modalEl);

        // Remove old listeners to prevent double submit
        const newConfirmBtn = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);

        newConfirmBtn.addEventListener('click', async function () {
            try {
                await sgvxApiRequest('sgvx51_delete_resident', {
                    resident_id: id,
                    _wpnonce: Config.deleteNonce
                });

                // Immediate UI update: Hide the row
                const row = document.querySelector(`.js-delete-resident[data-id="${id}"]`).closest('tr');
                if (row) {
                    row.style.opacity = '0.5';
                    row.style.pointerEvents = 'none';
                    setTimeout(() => row.remove(), 500);
                }
            } catch (err) { }
            modal.hide();
        });

        modal.show();
    }

    async function restoreResident(id) {
        try {
            await sgvxApiRequest('sgvx51_restore_resident', {
                resident_id: id,
                _wpnonce: Config.restoreNonce
            });

            const row = document.querySelector(`.js-restore-resident[data-id="${id}"]`).closest('tr');
            if (row) {
                row.style.opacity = '0.5';
                row.style.pointerEvents = 'none';
                setTimeout(() => row.remove(), 500);
            }
        } catch (err) { }
    }

    async function deletePermanently(id) {
        const modalEl = document.getElementById('deleteConfirmModal');
        const confirmBtn = document.getElementById('confirm-delete-btn');
        const modalTitle = modalEl ? modalEl.querySelector('.modal-title') : null;
        const modalBody = modalEl ? modalEl.querySelector('.modal-text') : null;

        if (!modalEl || !confirmBtn) return;

        // Change text for permanent delete
        const originalTitle = modalTitle ? modalTitle.textContent : 'Delete Resident?';
        const originalBody = modalBody ? modalBody.textContent : 'This action cannot be undone.';

        if (modalTitle) modalTitle.textContent = 'Confirm Permanent Deletion';
        if (modalBody) modalBody.textContent = 'Are you sure you want to permanently delete this resident record from the archive? This action cannot be undone.';

        confirmBtn.classList.remove('btn-danger');
        confirmBtn.classList.add('btn-dark');

        const modal = new bootstrap.Modal(modalEl);
        const newConfirmBtn = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);

        newConfirmBtn.addEventListener('click', async function () {
            try {
                await sgvxApiRequest('sgvx51_delete_history', {
                    history_id: id,
                    _wpnonce: Config.deleteHistoryNonce
                });

                const row = document.querySelector(`.js-delete-permanent[data-id="${id}"]`).closest('tr');
                if (row) {
                    row.style.opacity = '0.5';
                    row.style.pointerEvents = 'none';
                    setTimeout(() => row.remove(), 500);
                }
            } catch (err) { }

            // Revert modal changes for next time
            if (modalTitle) modalTitle.textContent = originalTitle;
            if (modalBody) modalBody.textContent = originalBody;
            confirmBtn.classList.add('btn-danger');
            confirmBtn.classList.remove('btn-dark');
            modal.hide();
        });

        modal.show();
    }

    // --- Fetch Module Configuration ---
    async function fetchModuleConfig() {
        if (State.initialized) return;

        try {
            const response = await fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'sgvx51_get_module_config',
                    module: 'residents'
                }).toString()
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const result = await response.json();

            if (result.success && result.data) {
                Config.nonce = result.data.nonce || null;
                Config.deleteNonce = result.data.deleteNonce || null;
                Config.restoreNonce = result.data.restoreNonce || null;
                Config.deleteHistoryNonce = result.data.deleteHistoryNonce || null;
                State.initialized = true;
            } else {
                console.error('Failed to fetch module config:', result.data?.message || 'Unknown error');
            }
        } catch (error) {
            console.error('Error fetching module config:', error);
        }
    }

    // --- Init ---
    $(function () {
        // Initialize module configuration first
        fetchModuleConfig().then(() => {
            // Form Submission (AJAX)
            const form = document.getElementById('add-resident-form');
            if (form) {
                form.addEventListener('submit', async function (e) {
                    e.preventDefault();

                    const submitBtn = form.querySelector('button[type="submit"]');
                    const originalBtnText = submitBtn.innerHTML;
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Saving...';

                    try {
                        const formData = new FormData(form);

                        // roles are handled by the single select "role" in the form
                        // specialized roles[] logic is not needed for the current form structure

                        await sgvxApiRequest(formData.get('action'), formData);

                        closeResidentModal();
                        // Reload without status params
                        window.location.href = window.location.origin + window.location.pathname + '?page=sgvx51-residents';
                    } catch (err) {
                        console.error('Submit Error:', err);
                    } finally {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalBtnText;
                    }
                });
            }

            // Modal Hidden Event to Reset
            const modalEl = document.getElementById('residentModal');
            if (modalEl) {
                modalEl.addEventListener('hidden.bs.modal', function () {
                    resetResidentForm();
                });
            }

            // Real-time Search Listener
            const searchInput = document.getElementById('filter-search');
            if (searchInput) {
                searchInput.addEventListener('input', function () {
                    applyFilters();
                });
                searchInput.addEventListener('focus', function () {
                    State.fuse = window.sgvxCreateFuse('.resident-row');
                });
            }

            // Listener Delegation
            document.body.addEventListener('click', function (e) {
                const target = e.target;

                if (target.closest('.js-toggle-filters')) {
                    toggleFilters();
                }
                if (target.closest('.js-open-resident-modal')) {
                    openResidentModal();
                }
                if (target.closest('.js-apply-filters')) {
                    applyFilters();
                }
                if (target.closest('.js-clear-filters')) {
                    clearFilters();
                }

                // Edit Resident
                const editBtn = target.closest('.js-edit-resident');
                if (editBtn) {
                    editResident(editBtn);
                }

                // Delete Resident
                const delBtn = target.closest('.js-delete-resident');
                if (delBtn) {
                    deleteResident(delBtn.dataset.id);
                }

                // Restore Resident
                const restoreBtn = target.closest('.js-restore-resident');
                if (restoreBtn) {
                    restoreResident(restoreBtn.dataset.id);
                }

                // Permanent Delete
                const permDelBtn = target.closest('.js-delete-permanent');
                if (permDelBtn) {
                    deletePermanently(permDelBtn.dataset.id);
                }

                // Tabs
                const tabBtn = target.closest('#residentsTabs .nav-link');
                if (tabBtn) {
                    switchTab(tabBtn);
                }
            });
        });
    });

})(jQuery);

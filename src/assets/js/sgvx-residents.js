(function ($) {
    'use strict';

    // Module configuration (fetched at runtime)
    const Config = {
        nonce: null,
        deleteNonce: null,
        restoreNonce: null,
        moveToHistoryNonce: null,
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

            const isArchived = (status === 'archived');
            const isPending = (status === 'pending' || status === 'rejected' || status === 'deletion_pending');

            // 1. Tab Filtering (Top Tabs)
            let matchesTab = false;
            if (State.currentTab === 'archived') {
                matchesTab = isArchived;
            } else if (State.currentTab === 'pending') {
                matchesTab = isPending;
            } else if (State.currentTab === 'all') {
                matchesTab = !isArchived; // Don't show archived in "All" tab
            } else {
                matchesTab = (!isArchived && type === State.currentTab);
            }

            // 2. Advanced Filters (Dropdowns)
            let matchesType = (typeVal === 'all') || (type === typeVal);

            // Status dropdown logic: 
            // If "all" selected, show what makes sense for the current tab.
            let matchesStatus = false;
            if (statusVal === 'all') {
                matchesStatus = (State.currentTab === 'archived') ? isArchived : !isArchived;
            } else {
                matchesStatus = (status === statusVal);
            }

            // 3. Search Matching
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

            // Smart Flat matching
            const flatSelect = form.querySelector('[name="flat_no"]');
            if (flatSelect) {
                const targetVal = String(r.flat_no || '').trim();

                // Try direct value match first
                flatSelect.value = targetVal;

                // If direct value match failed, try matching against data-number or partial ID
                if (!flatSelect.value && targetVal) {
                    const options = Array.from(flatSelect.options);
                    const normalize = s => String(s || '').toLowerCase().replace(/[^a-z0-9]/g, '');
                    const nTarget = normalize(targetVal);

                    const match = options.find(opt => {
                        const optVal = String(opt.value || '').trim();
                        const optNum = String(opt.dataset.number || '').trim();
                        const optId = String(opt.dataset.id || '').trim();

                        const nOptVal = normalize(optVal);
                        const nOptNum = normalize(optNum);
                        const nOptId = normalize(optId);

                        return (
                            optVal === targetVal ||
                            optNum === targetVal ||
                            optId === targetVal ||
                            (nOptVal && nTarget === nOptVal) ||
                            (nOptNum && nTarget === nOptNum) ||
                            (nOptId && nTarget === nOptId) ||
                            (nOptVal && nTarget.includes(nOptVal)) ||
                            (nTarget && nOptVal.includes(nTarget)) ||
                            (nOptNum && nTarget.includes(nOptNum))
                        );
                    });
                    if (match) {
                        flatSelect.value = match.value;
                    }
                }
            }

            setVal('type', r.type);
            setVal('phone', r.phone);
            setVal('email', r.email);
            setVal('dob', r.dob);
            setVal('blood_group', r.blood_group);

            // Handle Role (try roles or role)
            const roleVal = r.roles || r.role || '';
            const roleSelect = form.querySelector('[name="role"]');
            if (roleSelect) roleSelect.value = roleVal;

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
        const modalTitle = modalEl ? modalEl.querySelector('.modal-title') : null;
        const modalBody = modalEl ? modalEl.querySelector('.modal-text') : null;

        if (!modalEl || !confirmBtn) return;

        if (modalTitle) modalTitle.textContent = 'Archive Resident?';
        if (modalBody) modalBody.textContent = 'The resident will be moved to the history log. You can restore them later.';

        confirmBtn.classList.add('btn-danger');
        confirmBtn.classList.remove('btn-dark');

        const modal = new bootstrap.Modal(modalEl);

        // Remove old listeners to prevent double submit
        const newConfirmBtn = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);

        newConfirmBtn.addEventListener('click', async function () {
            try {
                await SGVX.ajax('sgvx51_delete_resident', {
                    resident_id: id,
                    _wpnonce: Config.deleteNonce
                }, {
                    success: 'Resident archived successfully',
                    button: newConfirmBtn,
                    reload: true
                });
            } catch (err) {
                console.error('Delete Resident error:', err);
            }
            modal.hide();
        });

        modal.show();
    }

    async function restoreResident(id) {
        try {
            await SGVX.ajax('sgvx51_restore_resident', {
                resident_id: id,
                _wpnonce: Config.restoreNonce
            }, {
                success: 'Resident restored successfully',
                reload: true
            });
        } catch (err) {
            console.error('Restore Resident error:', err);
        }
    }

    async function deletePermanently(id, source = 'residents') {
        const modalEl = document.getElementById('deleteConfirmModal');
        const confirmBtn = document.getElementById('confirm-delete-btn');
        const modalTitle = modalEl ? modalEl.querySelector('.modal-title') : null;
        const modalBody = modalEl ? modalEl.querySelector('.modal-text') : null;

        if (!modalEl || !confirmBtn) return;

        // Change text for permanent delete
        if (modalTitle) modalTitle.textContent = 'Confirm Permanent Deletion';
        if (modalBody) modalBody.textContent = 'Are you sure you want to permanently delete this resident record? This will move it to the history table for auditing.';

        confirmBtn.classList.remove('btn-danger');
        confirmBtn.classList.add('btn-dark');

        const modal = new bootstrap.Modal(modalEl);
        const newConfirmBtn = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);

        newConfirmBtn.addEventListener('click', async function () {
            try {
                const action = (source === 'history') ? 'sgvx51_delete_history' : 'sgvx51_move_to_history';
                const nonce = (source === 'history') ? Config.deleteHistoryNonce : Config.moveToHistoryNonce;

                await SGVX.ajax(action, {
                    ...(source === 'history' ? { history_id: id } : { resident_id: id }),
                    _wpnonce: nonce
                }, {
                    success: (source === 'history') ? 'Record permanently purged' : 'Resident moved to history log',
                    button: newConfirmBtn,
                    reload: true
                });
            } catch (err) {
                console.error('Permanent Delete error:', err);
            }
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
                Config.moveToHistoryNonce = result.data.moveToHistoryNonce || null;
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

                        await SGVX.ajax(formData.get('action'), formData, {
                            success: (formData.get('action') === 'sgvx51_add_resident' && !Config.isAdmin) ? 'Update request submitted' : 'Resident saved successfully',
                            button: submitBtn
                        });

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
                    deletePermanently(permDelBtn.dataset.id, permDelBtn.dataset.source || 'residents');
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

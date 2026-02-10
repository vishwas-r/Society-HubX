/**
 * SGVX Staff Management JS
 */
(function ($) {
    'use strict';

    // Module configuration (fetched at runtime)
    const Config = {
        nonce: null,
        deleteNonce: null,
        initialized: false
    };

    let staffModal = null;
    let currentTab = 'all';
    let fuse = null;

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
                    module: 'staff'
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

    window.openStaffModal = function () {
        if (!staffModal) staffModal = new bootstrap.Modal(document.getElementById('staffModal'));
        resetStaffForm();
        staffModal.show();
    };

    window.closeStaffModal = function () {
        const el = document.getElementById('staffModal');
        if (el) {
            const inst = bootstrap.Modal.getOrCreateInstance(el);
            if (inst) inst.hide();
        }
    };

    // --- Filter Logic ---
    window.toggleStaffFilters = function () {
        const section = document.getElementById('staff-filter-section');
        if (section) {
            const bsCollapse = bootstrap.Collapse.getOrCreateInstance(section);
            bsCollapse.toggle();
        }
    };

    window.switchTab = function (tab) {
        currentTab = tab;
        $('.tab-btn').each(function () {
            const $btn = $(this);
            if ($btn.data('tab') === tab) {
                $btn.addClass('active border-primary text-primary fw-bold')
                    .removeClass('border-transparent text-muted fw-semibold');
            } else {
                $btn.removeClass('active border-primary text-primary fw-bold')
                    .addClass('border-transparent text-muted fw-semibold');
            }
        });
        applyFilters();
    };

    window.applyFilters = function () {
        const searchInput = document.getElementById('staff-search-input');
        const categoryFilter = document.getElementById('filter-staff-category');
        const statusFilter = document.getElementById('filter-staff-status');

        const searchVal = searchInput ? searchInput.value.trim().toLowerCase() : '';
        const categoryVal = categoryFilter ? categoryFilter.value : 'all';
        const statusVal = statusFilter ? statusFilter.value : 'all';

        if (!fuse && window.sgvxCreateFuse) {
            fuse = window.sgvxCreateFuse('.staff-row');
        }

        const fuzzyMatches = searchVal && window.sgvxGetFuzzyMatches ? window.sgvxGetFuzzyMatches(fuse, searchVal) : null;

        $('.staff-row').each(function () {
            const $row = $(this);
            const status = $row.data('status');
            const category = $row.data('category');

            // Tab logic
            let matchesTab = false;
            if (currentTab === 'archived') {
                matchesTab = (status === 'archived');
            } else if (currentTab === 'pending') {
                matchesTab = (status === 'pending');
            } else if (currentTab === 'approved') {
                matchesTab = (status === 'approved');
            } else if (currentTab === 'all') {
                matchesTab = (status !== 'archived');
            }

            // Advanced Filters logic
            let matchesCategory = (categoryVal === 'all') || (category === categoryVal);
            let matchesStatus = (statusVal === 'all' && status !== 'archived') || (statusVal === 'archived' && status === 'archived') || (status === statusVal);
            let matchesSearch = !searchVal || (fuzzyMatches && fuzzyMatches.has(this));

            if (matchesTab && matchesCategory && matchesStatus && matchesSearch) {
                $row.show();
            } else {
                $row.hide();
            }
        });
    };

    window.clearStaffFilters = function () {
        const searchInput = document.getElementById('staff-search-input');
        if (searchInput) searchInput.value = '';
        const catFilter = document.getElementById('filter-staff-category');
        if (catFilter) catFilter.value = 'all';
        const statusFilter = document.getElementById('filter-staff-status');
        if (statusFilter) statusFilter.value = 'all';
        applyFilters();
    };

    window.editStaff = function (staff) {
        if (!staffModal) staffModal = new bootstrap.Modal(document.getElementById('staffModal'));
        const $form = $('#add-staff-form');

        $form.find('[name="name"]').val(staff.name);
        $form.find('[name="role"]').val(staff.role);
        $form.find('[name="phone"]').val(staff.phone || '');
        $form.find('[name="sex"]').val(staff.sex || '');
        $form.find('[name="visiting_hours"]').val(staff.visiting_hours || '');
        $form.find('[name="profile_photo"]').val(staff.profile_photo || '');

        const preview = document.getElementById('current-doc-preview');
        if (preview) {
            if (staff.profile_photo) {
                preview.classList.remove('d-none');
                preview.querySelector('a').href = staff.profile_photo;
            } else {
                preview.classList.add('d-none');
            }
        }

        $form.find('[name="flat_no"]').val(staff.flat_no || '');
        $form.find('[name="category"]').val(staff.category || 'Support Staff');
        $form.find('[name="staff_id"]').val(staff.id);
        $form.find('[name="action"]').val('sgvx51_edit_staff');

        $('#staffModalTitle').text('Edit Staff: ' + staff.name);
        staffModal.show();
    };

    function resetStaffForm() {
        const $form = $('#add-staff-form');
        $form[0].reset();
        $form.find('[name="action"]').val('sgvx51_add_staff');
        $form.find('[name="profile_photo"]').val('');
        const preview = document.getElementById('current-doc-preview');
        if (preview) preview.classList.add('d-none');
        $('#staffModalTitle').text('Add New Staff');
    }

    window.deleteStaff = async function (id) {
        const modalEl = document.getElementById('deleteConfirmModal');
        const confirmBtn = document.getElementById('confirm-delete-btn');
        if (!modalEl || !confirmBtn) {
            if (!confirm('Are you sure you want to delete this staff member?')) return;
            return;
        }

        const modal = new bootstrap.Modal(modalEl);
        const newConfirmBtn = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);

        newConfirmBtn.addEventListener('click', async function () {
            try {
                await sgvxApiRequest('sgvx51_delete_staff', {
                    id: id,
                    _wpnonce: Config.deleteNonce
                });

                // Immediate UI update
                const row = document.querySelector(`.js-delete-staff[data-id="${id}"]`)?.closest('tr');
                if (row) {
                    row.style.opacity = '0.5';
                    row.style.pointerEvents = 'none';
                    setTimeout(() => {
                        row.remove();
                        window.location.reload();
                    }, 500);
                }
            } catch (err) { }
            modal.hide();
        });

        modal.show();
    };

    window.restoreStaff = async function (id) {
        try {
            await sgvxApiRequest('sgvx51_restore_staff', {
                id: id,
                _wpnonce: Config.nonce
            });
            window.location.reload();
        } catch (err) { }
    };

    // --- Init ---
    $(function () {
        fetchModuleConfig().then(() => {
            // Delegated edit / delete handlers
            $(document).on('click', '.js-edit-staff', function (e) {
                e.preventDefault();
                const payload = $(this).attr('data-staff');
                try {
                    const obj = JSON.parse(payload);
                    window.editStaff(obj);
                } catch (err) { console.error('Invalid staff payload', err); }
            });

            $(document).on('click', '.js-delete-staff', function (e) {
                e.preventDefault();
                const id = $(this).data('id');
                if (id) window.deleteStaff(id);
            });

            // Real-time Search Listener
            $('#staff-search-input').on('input', function () {
                applyFilters();
            }).on('focus', function () {
                if (window.sgvxCreateFuse) fuse = window.sgvxCreateFuse('.staff-row');
            });

            // Filter Buttons
            $(document).on('click', '.js-toggle-staff-filters', function (e) { e.preventDefault(); toggleStaffFilters(); });
            $(document).on('click', '.js-apply-staff-filters', function (e) { e.preventDefault(); applyFilters(); });
            $(document).on('click', '.js-clear-staff-filters', function (e) { e.preventDefault(); clearStaffFilters(); });

            // Tab Buttons
            $(document).on('click', '#staffTabs .tab-btn', function (e) {
                e.preventDefault();
                switchTab($(this).data('tab'));
            });

            const $form = $('#add-staff-form');
            if ($form.length) {
                $form.on('submit', async function (e) {
                    e.preventDefault();
                    const $btn = $form.find('button[type="submit"]');
                    const originalText = $btn.html();
                    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Saving...');

                    try {
                        const formData = new FormData($form[0]);
                        await sgvxApiRequest(formData.get('action'), formData);

                        closeStaffModal();
                        window.location.reload();
                    } catch (err) {
                        console.error('Staff Save Error:', err);
                    } finally {
                        $btn.prop('disabled', false).html(originalText);
                    }
                });
            }
        });
    });

})(jQuery);

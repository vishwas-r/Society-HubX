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

    window.switchTab = function (tab) {
        currentTab = tab;
        $('.tab-btn').each(function () {
            const $btn = $(this);
            if ($btn.data('tab') === tab) {
                $btn.addClass('active border-primary text-primary')
                    .removeClass('border-transparent text-muted');
            } else {
                $btn.removeClass('active border-primary text-primary')
                    .addClass('border-transparent text-muted');
            }
        });
        applyFilters();
    };

    window.applyFilters = function () {
        const searchInput = document.getElementById('staff-search-input');
        const searchVal = searchInput ? searchInput.value.trim().toLowerCase() : '';

        if (!fuse && window.sgvxCreateFuse) {
            fuse = window.sgvxCreateFuse('.staff-row');
        }

        const fuzzyMatches = searchVal && window.sgvxGetFuzzyMatches ? window.sgvxGetFuzzyMatches(fuse, searchVal) : null;

        $('.staff-row').each(function () {
            const $row = $(this);
            const status = $row.data('status');

            let matchTab = false;
            if (currentTab === 'archived') {
                matchTab = (status === 'archived');
            } else if (currentTab === 'all') {
                matchTab = (status !== 'archived');
            } else {
                matchTab = (status === currentTab);
            }

            let matchSearch = !searchVal || (fuzzyMatches && fuzzyMatches.has(this));

            if (matchTab && matchSearch) {
                $row.show();
            } else {
                $row.hide();
            }
        });
    };

    window.editStaff = function (staff) {
        if (!staffModal) staffModal = new bootstrap.Modal(document.getElementById('staffModal'));
        const $form = $('#add-staff-form');

        $form.find('[name="name"]').val(staff.name);
        $form.find('[name="role"]').val(staff.role);
        $form.find('[name="phone"]').val(staff.phone || '');
        $form.find('[name="sex"]').val(staff.sex || '');
        $form.find('[name="visiting_hours"]').val(staff.visiting_hours || '');
        $form.find('[name="flat_no"]').val(staff.flat_no || '');
        $form.find('[name="staff_id"]').val(staff.id);
        $form.find('[name="action"]').val('sgvx51_edit_staff');

        $('#staffModalTitle').text('Edit Staff: ' + staff.name);
        staffModal.show();
    };

    function resetStaffForm() {
        const $form = $('#add-staff-form');
        $form[0].reset();
        $form.find('[name="action"]').val('sgvx51_add_staff');
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

            const $form = $('#add-staff-form');
            if ($form.length) {
                $form.on('submit', async function (e) {
                    e.preventDefault();
                    const $btn = $form.find('button[type="submit"]');
                    const originalText = $btn.html();
                    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Saving...');

                    try {
                        const formData = new FormData($form[0]);
                        const data = Object.fromEntries(formData.entries());

                        await sgvxApiRequest(data.action, data);

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

/**
 * SHUBX Staff Management JS
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
            const result = await SHUBX.ajax({
                action: 'shubx51_get_module_config',
                data: { module: 'staff' },
                showOverlay: false,
                suppressErrorToast: true // Silent fetch for config
            });

            if (result) {
                Config.nonce = result.nonce || null;
                Config.deleteNonce = result.deleteNonce || null;
                Config.initialized = true;
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

        // View Toggling
        if (currentTab === 'report') {
            $('#staff-directory-view, #staff-filter-section, .shubx-bulk-actions, .js-toggle-staff-filters, #addStaff, .staff-search-wrapper').hide();
            $('#staff-report-view').removeClass('d-none').show();
            // Automatically fetch if not fetched yet (optional)
            return;
        } else {
            $('#staff-report-view').hide();
            $('#staff-directory-view, #addStaff').show();
            if ($('#staff-filter-section').hasClass('show')) {
                // Let collapse handle it
            }
        }

        if (!fuse && window.SHUBXCreateFuse) {
            fuse = window.SHUBXCreateFuse('.staff-row');
        }

        const fuzzyMatches = searchVal && window.SHUBXGetFuzzyMatches ? window.SHUBXGetFuzzyMatches(fuse, searchVal) : null;

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

    window.previewStaffImage = function (input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function (e) {
                $('#staff-preview').attr('src', e.target.result).removeClass('d-none');
                $('#staff-icon').addClass('d-none');
            };
            reader.readAsDataURL(input.files[0]);
        }
    };

    window.editStaff = function (staff) {
        if (!staffModal) staffModal = new bootstrap.Modal(document.getElementById('staffModal'));
        const $form = $('#add-staff-form');

        $form.find('[name="name"]').val(staff.name);
        $form.find('[name="role"]').val(staff.role);
        $form.find('[name="phone"]').val(staff.phone || '');
        $form.find('[name="sex"]').val(staff.sex || '');
        $form.find('[name="visiting_hours"]').val(staff.visiting_hours || '');
        $form.find('[name="profile_photo"]').val(''); // Clear file input
        // Handle Profile Photo Preview
        if (staff.profile_photo) {
            $('#staff-preview').attr('src', staff.profile_photo).removeClass('d-none');
            $('#staff-icon').addClass('d-none');
        } else {
            $('#staff-preview').addClass('d-none');
            $('#staff-icon').removeClass('d-none');
        }

        // Handle ID Proof Preview (Strict: only id_proof)
        const idProofUrl = staff.id_proof;

        const docPreview = document.getElementById('current-doc-preview');
        if (docPreview) {
            if (idProofUrl) {
                docPreview.classList.remove('d-none');
                docPreview.querySelector('a').href = idProofUrl;
            } else {
                docPreview.classList.add('d-none');
            }
        }

        const served_flats = staff.flats_served ? (Array.isArray(staff.flats_served) ? staff.flats_served : JSON.parse(staff.flats_served)) : [];
        $form.find('[name="flats_served[]"]').val(served_flats);

        $form.find('[name="category"]').val(staff.category || 'Support Staff');
        $form.find('[name="staff_id"]').val(staff.id);
        $form.find('[name="action"]').val('shubx51_edit_staff');

        $('#staffModalTitle').text('Edit Staff: ' + staff.name);
        staffModal.show();
    };

    function resetStaffForm() {
        const $form = $('#add-staff-form');
        $form[0].reset();
        $form.find('[name="action"]').val('shubx51_add_staff');
        $form.find('[name="flats_served[]"]').val([]); // Clear multi-select
        $form.find('[name="staff_id"]').val('');

        // Reset Photo Preview
        $('#staff-preview').attr('src', '').addClass('d-none');
        $('#staff-icon').removeClass('d-none');

        const docPreview = document.getElementById('current-doc-preview');
        if (docPreview) docPreview.classList.add('d-none');
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
            SHUBX.ajax({
                action: 'shubx51_delete_staff',
                data: {
                    id: id,
                    _wpnonce: Config.deleteNonce
                },
                successMessage: 'Staff member archived successfully',
                onSuccess: function () {
                    const row = document.querySelector(`.js-delete-staff[data-id="${id}"]`)?.closest('tr');
                    if (row) {
                        row.style.opacity = '0.5';
                        row.style.pointerEvents = 'none';
                        setTimeout(() => {
                            row.remove();
                            window.location.reload();
                        }, 500);
                    }
                }
            });
            modal.hide();
        });

        modal.show();
    };

    window.restoreStaff = async function (id) {
        SHUBX.ajax({
            action: 'shubx51_restore_staff',
            data: {
                id: id,
                _wpnonce: Config.nonce
            },
            successMessage: 'Staff member restored!',
            reload: true
        });
    };

    window.markAttendance = function (id) {
        let attendanceModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('attendanceModal'));
        document.getElementById('attendance-staff-id').value = id;
        document.getElementById('attendance-form').reset();
        attendanceModal.show();
    };

    window.raiseConcern = function (id) {
        let concernModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('concernModal'));
        document.getElementById('concern-staff-id').value = id;
        document.getElementById('concern-form').reset();
        concernModal.show();
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

            $(document).on('click', '.js-mark-attendance', function (e) {
                e.preventDefault();
                const id = $(this).data('id');
                if (id) window.markAttendance(id);
            });

            $(document).on('click', '.js-raise-concern', function (e) {
                e.preventDefault();
                const id = $(this).data('id');
                if (id) window.raiseConcern(id);
            });

            // Real-time Search Listener
            $('#staff-search-input').on('input', function () {
                applyFilters();
            }).on('focus', function () {
                if (window.SHUBXCreateFuse) fuse = window.SHUBXCreateFuse('.staff-row');
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
                $form.on('submit', function (e) {
                    e.preventDefault();
                    const action = $form.find('[name="action"]').val();
                    const formData = new FormData($form[0]);

                    SHUBX.ajax({
                        action: action,
                        data: formData,
                        loadingButton: $form.find('button[type="submit"]'),
                        successMessage: 'Staff details saved successfully',
                        reload: true,
                        onSuccess: function () {
                            closeStaffModal();
                        }
                    });
                });
            }

            const $attendanceForm = $('#attendance-form');
            if ($attendanceForm.length) {
                $attendanceForm.on('submit', function (e) {
                    e.preventDefault();
                    const action = $attendanceForm.find('[name="action"]').val();
                    const formData = new FormData($attendanceForm[0]);

                    SHUBX.ajax({
                        action: action,
                        data: formData,
                        loadingButton: $attendanceForm.find('button[type="submit"]'),
                        successMessage: 'Attendance saved successfully',
                        reload: true
                    });
                });
            }

            const $concernForm = $('#concern-form');
            if ($concernForm.length) {
                $concernForm.on('submit', function (e) {
                    e.preventDefault();
                    const action = $concernForm.find('[name="action"]').val();
                    const formData = new FormData($concernForm[0]);

                    SHUBX.ajax({
                        action: action,
                        data: formData,
                        loadingButton: $concernForm.find('button[type="submit"]'),
                        successMessage: 'Concern submitted successfully',
                        reload: true
                    });
                });
            }
        });
    });
    
    // --- Attendance Report Generation ---
    window.fetchAttendanceReport = function() {
        const month = document.getElementById('attendance-month').value;
        if (!month) {
            alert('Please select a month');
            return;
        }
        
        const btn = event.currentTarget;
        const origText = btn.innerText;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Generating...';
        btn.disabled = true;

        const data = new FormData();
        data.append('action', 'shubx51_get_attendance_report');
        data.append('_wpnonce', document.getElementById('shubx51_staff_nonce') ? document.getElementById('shubx51_staff_nonce').value : shubxAdminData.nonce);
        data.append('month', month);

        fetch(shubxAdminData.ajaxUrl, {
            method: 'POST',
            body: data
        })
        .then(res => res.json())
        .then(res => {
            btn.innerHTML = origText;
            btn.disabled = false;
            
            if (res.success && res.data.report) {
                const tbody = document.getElementById('attendance-report-body');
                if (res.data.report.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-muted">No attendance data found for the selected month.</td></tr>';
                    return;
                }

                let html = '';
                res.data.report.forEach(r => {
                    const totalPayable = r.present + r.leave; // Simple formula: Present + Leave days are payable. Absent is not.
                    html += `
                        <tr>
                            <td class="ps-3 ps-md-5">
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center text-secondary me-3 shadow-sm border border-white" style="width: 40px; height: 40px;">
                                        <i class="bi bi-person-fill fs-5"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark">${r.name}</div>
                                        <div class="small text-muted font-monospace">${r.id}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 px-md-4">
                                <div class="fw-semibold text-dark">${r.role}</div>
                                <div class="small text-muted">${r.category}</div>
                            </td>
                            <td class="px-3 px-md-4">
                                <span class="badge bg-success bg-opacity-10 text-success fw-bold px-3 py-2 rounded-pill fs-6">${r.present}</span>
                            </td>
                            <td class="px-3 px-md-4">
                                <span class="badge bg-danger bg-opacity-10 text-danger fw-bold px-3 py-2 rounded-pill fs-6">${r.absent}</span>
                            </td>
                            <td class="px-3 px-md-4">
                                <span class="badge bg-warning bg-opacity-10 text-warning fw-bold px-3 py-2 rounded-pill fs-6">${r.leave}</span>
                            </td>
                            <td class="pe-3 pe-md-5 text-end">
                                <div class="fw-bold fs-5 text-dark">${totalPayable}</div>
                                <div class="small text-muted">Days</div>
                            </td>
                        </tr>
                    `;
                });
                tbody.innerHTML = html;
            } else {
                alert(res.data?.message || 'Error generating report');
            }
        })
        .catch(err => {
            console.error('Error fetching report', err);
            btn.innerHTML = origText;
            btn.disabled = false;
            alert('A network error occurred.');
        });
    }

})(jQuery);

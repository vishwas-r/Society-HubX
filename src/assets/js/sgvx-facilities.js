/**
 * SGVX Facilities Management JS
 * Brings Facilities view in line with Residents UI patterns:
 * - Delegated event handling
 * - AJAX form submit via sgvxApiRequest
 * - Centralized delete/confirm modal usage
 * - Real-time search for facility list
 */
(function ($) {
    'use strict';

    // Module configuration (fetched at runtime)
    const Config = {
        nonce: null,
        deleteNonce: null,
        initialized: false
    };

    const State = {
        facilityModal: null
    };

    async function fetchModuleConfig() {
        if (Config.initialized) return;

        try {
            const result = await SGVX.ajax({
                action: 'sgvx51_get_module_config',
                data: { module: 'facilities' },
                showOverlay: false,
                suppressErrorToast: true
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

    function openFacilityModal() {
        if (!State.facilityModal) State.facilityModal = new bootstrap.Modal(document.getElementById('facilityModal'));
        resetFacilityForm();
        State.facilityModal.show();
    }

    function closeFacilityModal() {
        const el = document.getElementById('facilityModal');
        if (el) {
            const inst = bootstrap.Modal.getOrCreateInstance(el);
            if (inst) inst.hide();
        }
    }

    function resetFacilityForm() {
        const form = document.getElementById('facility-form');
        if (!form) return;
        form.reset();
        form.querySelector('[name="action"]').value = 'sgvx51_add_facility';
        form.querySelector('[name="facility_id"]').value = '';
        const title = document.getElementById('form-title');
        if (title) title.textContent = 'Define New Amenity';
        const cancel = document.getElementById('cancel-edit-btn');
        if (cancel) cancel.classList.add('d-none');
        const submit = form.querySelector('button[type="submit"]');
        if (submit) submit.textContent = 'Save Configuration';
        const card = form.closest('.card');
        if (card) card.classList.remove('border-indigo-500', 'border');
    }

    function populateFacilityForm(data) {
        const form = document.getElementById('facility-form');
        if (!form) return;
        form.querySelector('[name="action"]').value = 'sgvx51_edit_facility';
        form.querySelector('[name="facility_id"]').value = data.id || '';
        form.querySelector('[name="name"]').value = data.name || '';
        form.querySelector('[name="rate"]').value = data.rate || 0;
        form.querySelector('[name="rate_unit"]').value = data.rate_unit || 'Hour';
        form.querySelector('[name="max_hours"]').value = data.max_hours || '';
        form.querySelector('[name="rules"]').value = data.rules || '';

        const title = document.getElementById('form-title');
        if (title) title.textContent = 'Modify Facility';
        const submit = form.querySelector('button[type="submit"]');
        if (submit) submit.textContent = 'Update Amenity';
        const cancel = document.getElementById('cancel-edit-btn');
        if (cancel) cancel.classList.remove('d-none');
        const card = form.closest('.card');
        if (card) card.classList.add('border-indigo-500', 'border');

        if (!State.facilityModal) State.facilityModal = new bootstrap.Modal(document.getElementById('facilityModal'));
        State.facilityModal.show();
    }

    function deleteFacility(id) {
        const modalEl = document.getElementById('deleteConfirmModal');
        const confirmBtn = document.getElementById('confirm-delete-btn');
        if (!modalEl || !confirmBtn) {
            if (!confirm('Delete facility?')) return;
        }

        const modal = modalEl ? new bootstrap.Modal(modalEl) : null;
        if (modal) {
            const newConfirmBtn = confirmBtn.cloneNode(true);
            confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);

            newConfirmBtn.addEventListener('click', function () {
                SGVX.ajax({
                    action: 'sgvx51_delete_facility',
                    data: { id: id, _wpnonce: Config.deleteNonce },
                    successMessage: 'Facility deleted successfully',
                    onSuccess: function () {
                        const row = document.querySelector(`.js-delete-facility[data-id="${id}"]`)?.closest('.list-group-item');
                        if (row) {
                            row.style.opacity = '0.5';
                            row.style.pointerEvents = 'none';
                            setTimeout(() => { row.remove(); }, 400);
                        }
                    }
                });
                modal.hide();
            });

            modal.show();
        } else {
            // Fallback
            if (!confirm('Delete facility?')) return;
            SGVX.ajax({
                action: 'sgvx51_delete_facility',
                data: { id: id, _wpnonce: Config.deleteNonce },
                reload: true
            });
        }
    }

    // --- Init ---
    $(function () {
        // Initialize module configuration first
        fetchModuleConfig().then(() => {
            // Open Add modal
            document.body.addEventListener('click', function (e) {
                const target = e.target;
                if (target.closest('.js-open-facility-modal')) {
                    openFacilityModal();
                }

                const editBtn = target.closest('.js-edit-facility');
                if (editBtn) {
                    try {
                        const payload = editBtn.getAttribute('data-facility');
                        const data = JSON.parse(payload);
                        populateFacilityForm(data);
                    } catch (err) { }
                }

                const delBtn = target.closest('.js-delete-facility');
                if (delBtn) {
                    deleteFacility(delBtn.dataset.id);
                }
            });

            // Facility Form AJAX submit
            const form = document.getElementById('facility-form');
            if (form) {
                form.addEventListener('submit', function (e) {
                    e.preventDefault();
                    const action = form.querySelector('[name="action"]').value;
                    const formData = new FormData(form);

                    SGVX.ajax({
                        action: action,
                        data: formData,
                        loadingButton: form.querySelector('button[type="submit"]'),
                        successMessage: 'Facility configuration saved!',
                        reload: true,
                        onSuccess: function () {
                            closeFacilityModal();
                        }
                    });
                });
            }

            // Reset form when modal hidden (if modal exists)
            const modalEl = document.getElementById('facilityModal');
            if (modalEl) {
                modalEl.addEventListener('hidden.bs.modal', function () {
                    resetFacilityForm();
                });
            }

            // Live search for facility list
            const searchInput = document.querySelector('#facility-list-search');
            if (searchInput) {
                searchInput.addEventListener('input', function () {
                    const q = this.value.toLowerCase();
                    document.querySelectorAll('.list-group-item[data-search]').forEach(item => {
                        const s = (item.dataset.search || '').toLowerCase();
                        item.style.display = (!q || s.includes(q)) ? '' : 'none';
                    });
                });
            }

            // Live search for booking list
            const bookingSearch = document.querySelector('#bookingSearch');
            if (bookingSearch) {
                bookingSearch.addEventListener('input', function () {
                    const q = this.value.toLowerCase();
                    document.querySelectorAll('.booking-row').forEach(row => {
                        const s = (row.dataset.search || '').toLowerCase();
                        row.style.display = (!q || s.includes(q)) ? '' : 'none';
                    });
                });
            }
        });
    });

})(jQuery);

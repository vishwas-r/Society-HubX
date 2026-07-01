/**
 * SNESTX Facilities & Bookings Management JS
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
        bookingModal: null,
        deleteBookingModal: null,
        deleteFacilityModal: null,
        facilityToDelete: null,
        bookingToDelete: null
    };

    async function fetchModuleConfig() {
        if (Config.initialized) return;

        try {
            const result = await SNESTX.ajax({
                action: 'SNESTX51_get_module_config',
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

    // --- Facility Pricing Toggle ---
    window.togglePricing = function (isFree) {
        const fields = document.getElementById('pricing-fields');
        if (fields) {
            if (isFree == '1') {
                fields.classList.add('d-none');
            } else {
                fields.classList.remove('d-none');
            }
        }
    };

    // --- Reset Forms ---
    window.resetFacilityForm = function () {
        const form = document.getElementById('facility-form');
        if (!form) return;
        form.reset();
        form.querySelector('[name="action"]').value = 'SNESTX51_add_facility';
        form.querySelector('[name="facility_id"]').value = '';
        form.querySelector('[name="booking_required"]').checked = true;
        
        const title = document.getElementById('form-title');
        if (title) title.textContent = 'Define New Amenity';
        const cancel = document.getElementById('cancel-edit-btn');
        if (cancel) cancel.classList.add('d-none');
        const submit = form.querySelector('button[type="submit"]');
        if (submit) submit.textContent = 'Save Configuration';

        // Reset Pricing fields
        const pricePaid = document.getElementById('pricePaid');
        if (pricePaid) pricePaid.checked = true;
        window.togglePricing('0');
    };

    window.resetBookingForm = function () {
        const form = document.getElementById('booking-form');
        if (!form) return;
        form.reset();
        form.querySelector('[name="action"]').value = 'SNESTX51_book_facility';
        form.querySelector('[name="booking_id"]').value = '';
        
        const modalTitle = form.closest('.modal-content')?.querySelector('h5');
        if (modalTitle) modalTitle.textContent = 'Facility Booking';
        
        const statusContainer = document.getElementById('booking-status-container');
        if (statusContainer) statusContainer.classList.add('d-none');
    };

    // --- Booking Modal Actions ---
    window.openBookingModal = function () {
        if (!State.bookingModal) {
            const el = document.getElementById('bookingModal');
            if (el) State.bookingModal = new bootstrap.Modal(el);
        }
        window.resetBookingForm();
        if (State.bookingModal) State.bookingModal.show();
    };

    // --- Init ---
    $(function () {
        fetchModuleConfig().then(() => {
            // 1. Edit Facility click handler
            $(document).on('click', '.js-edit-facility', function (e) {
                e.preventDefault();
                const payload = $(this).attr('data-facility');
                if (!payload) return;
                
                try {
                    const data = JSON.parse(payload);
                    const form = document.getElementById('facility-form');
                    if (!form) return;

                    form.querySelector('[name="action"]').value = 'SNESTX51_edit_facility';
                    form.querySelector('[name="facility_id"]').value = data.id || '';
                    form.querySelector('[name="name"]').value = data.name || '';

                    // Pricing logic
                    const rate = parseFloat(data.rate || 0);
                    if (rate === 0) {
                        const priceFree = document.getElementById('priceFree');
                        if (priceFree) priceFree.checked = true;
                        window.togglePricing('1');
                        form.querySelector('[name="rate"]').value = 0;
                    } else {
                        const pricePaid = document.getElementById('pricePaid');
                        if (pricePaid) pricePaid.checked = true;
                        window.togglePricing('0');
                        form.querySelector('[name="rate"]').value = rate;
                    }

                    form.querySelector('[name="rate_unit"]').value = data.rate_unit || 'Hour';
                    form.querySelector('[name="max_hours"]').value = data.max_hours || '';
                    form.querySelector('[name="booking_required"]').checked = (data.booking_required != 0);
                    form.querySelector('[name="rules"]').value = data.rules || '';

                    document.getElementById('form-title').textContent = 'Modify Amenity Settings';
                    document.getElementById('submit-btn').textContent = 'Update Policy';
                    document.getElementById('cancel-edit-btn').classList.remove('d-none');

                    form.scrollIntoView({ behavior: 'smooth' });
                } catch (err) {
                    console.error('Error parsing facility data', err);
                }
            });

            // 2. Delete Facility click handler
            $(document).on('click', '.js-delete-facility', function (e) {
                e.preventDefault();
                State.facilityToDelete = $(this).data('id');
                if (!State.deleteFacilityModal) {
                    const el = document.getElementById('deleteConfirmModal');
                    if (el) State.deleteFacilityModal = new bootstrap.Modal(el);
                }
                if (State.deleteFacilityModal) State.deleteFacilityModal.show();
            });

            // Confirm Delete Facility handler
            $('#confirm-delete-btn').on('click', function () {
                if (!State.facilityToDelete) return;

                SNESTX.ajax({
                    action: 'SNESTX51_delete_facility',
                    data: {
                        id: State.facilityToDelete,
                        _wpnonce: Config.deleteNonce
                    },
                    successMessage: 'Facility deleted successfully',
                    reload: true
                });
            });

            // 3. Edit Booking click handler
            $(document).on('click', '.js-edit-booking', function (e) {
                e.preventDefault();
                const payload = $(this).attr('data-booking');
                if (!payload) return;

                try {
                    const data = JSON.parse(payload);
                    const form = document.getElementById('booking-form');
                    if (!form) return;

                    form.querySelector('[name="action"]').value = 'SNESTX51_edit_booking';
                    form.querySelector('[name="booking_id"]').value = data.id || '';
                    form.querySelector('[name="facility_id"]').value = data.facility_id || '';
                    form.querySelector('[name="resident_id"]').value = data.resident_id || '';
                    form.querySelector('[name="start_time"]').value = (data.start_time || '').replace(' ', 'T').substring(0, 16);
                    form.querySelector('[name="end_time"]').value = (data.end_time || '').replace(' ', 'T').substring(0, 16);
                    form.querySelector('[name="status"]').value = data.status || 'confirmed';

                    const modalTitle = form.closest('.modal-content')?.querySelector('h5');
                    if (modalTitle) modalTitle.textContent = 'Modify Reservation';

                    const statusContainer = document.getElementById('booking-status-container');
                    if (statusContainer) statusContainer.classList.remove('d-none');

                    if (!State.bookingModal) {
                        const el = document.getElementById('bookingModal');
                        if (el) State.bookingModal = new bootstrap.Modal(el);
                    }
                    if (State.bookingModal) State.bookingModal.show();
                } catch (err) {
                    console.error('Error parsing booking data', err);
                }
            });

            // 4. Delete Booking click handler
            $(document).on('click', '.js-delete-booking', function (e) {
                e.preventDefault();
                State.bookingToDelete = $(this).data('id');
                if (!State.deleteBookingModal) {
                    const el = document.getElementById('deleteBookingConfirmModal');
                    if (el) State.deleteBookingModal = new bootstrap.Modal(el);
                }
                if (State.deleteBookingModal) State.deleteBookingModal.show();
            });

            // Confirm Delete Booking handler
            $('#confirm-delete-booking-btn').on('click', function () {
                if (!State.bookingToDelete) return;

                const form = document.createElement('form');
                form.method = 'POST';
                form.action = ajaxurl.replace('admin-ajax.php', 'admin-post.php'); // Clean fallback to admin-post.php path

                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'SNESTX51_delete_booking';

                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'id';
                idInput.value = State.bookingToDelete;

                const nonceInput = document.createElement('input');
                nonceInput.type = 'hidden';
                nonceInput.name = '_wpnonce';
                nonceInput.value = Config.nonce;

                form.appendChild(actionInput);
                form.appendChild(idInput);
                form.appendChild(nonceInput);
                document.body.appendChild(form);
                form.submit();
            });

            // 5. Live search for facility list
            $('#facility-list-search').on('input', function () {
                const q = this.value.toLowerCase();
                document.querySelectorAll('#facilityContainer .list-group-item').forEach(item => {
                    const s = (item.dataset.search || '').toLowerCase();
                    item.style.display = (!q || s.includes(q)) ? '' : 'none';
                });
            });

            // 6. Live search for booking list
            $('#bookingSearch').on('input', function () {
                const q = this.value.toLowerCase();
                document.querySelectorAll('.booking-row').forEach(row => {
                    const s = (row.dataset.search || '').toLowerCase();
                    row.style.display = (!q || s.includes(q)) ? '' : 'none';
                });
            });
        });
    });

})(jQuery);

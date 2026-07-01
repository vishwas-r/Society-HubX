/**
 * SHUBX Vehicles Management JS
 */
(function ($) {
    'use strict';

    // Module configuration (fetched at runtime)
    const Config = {
        nonce: null,
        deleteNonce: null,
        initialized: false
    };

    let vehicleModal = null;
    let currentTab = 'all';
    let fuse = null;

    async function fetchModuleConfig() {
        if (Config.initialized) return;

        try {
            const result = await SHUBX.ajax({
                action: 'shubx51_get_module_config',
                data: { module: 'vehicles' },
                showOverlay: false, // Silent fetch for config
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

    window.openVehicleModal = function () {
        if (!vehicleModal) vehicleModal = new bootstrap.Modal(document.getElementById('vehicleModal'));
        resetVehicleForm();
        vehicleModal.show();
    };

    window.closeVehicleModal = function () {
        const el = document.getElementById('vehicleModal');
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
        const searchInput = document.getElementById('filter-search');
        const searchVal = searchInput ? searchInput.value.trim().toLowerCase() : '';

        if (!fuse && window.SHUBXCreateFuse) {
            fuse = window.SHUBXCreateFuse('.vehicle-row');
        }

        const fuzzyMatches = searchVal && window.SHUBXGetFuzzyMatches ? window.SHUBXGetFuzzyMatches(fuse, searchVal) : null;

        $('.vehicle-row').each(function () {
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

    window.editVehicle = function (vehicle) {
        if (!vehicleModal) vehicleModal = new bootstrap.Modal(document.getElementById('vehicleModal'));
        const $form = $('#add-vehicle-form');

        $form.find('[name="number"]').val(vehicle.number);
        $form.find('[name="type"]').val(vehicle.type);
        $form.find('[name="brand"]').val(vehicle.brand || '');
        $form.find('[name="model"]').val(vehicle.model || '');
        $form.find('[name="sticker"]').val(vehicle.sticker || '');
        $form.find('[name="flat_no"]').val(vehicle.flat_no || '');
        $form.find('[name="vehicle_id"]').val(vehicle.id);
        $form.find('[name="action"]').val('shubx51_edit_vehicle');

        $('#vehicleModalTitle').text('Edit Vehicle: ' + vehicle.number);
        vehicleModal.show();
    };

    function resetVehicleForm() {
        const $form = $('#add-vehicle-form');
        $form[0].reset();
        $form.find('[name="action"]').val('shubx51_add_vehicle');
        $('#vehicleModalTitle').text('Add New Vehicle');
    }

    window.deleteVehicle = async function (id) {
        const modalEl = document.getElementById('deleteConfirmModal');
        const confirmBtn = document.getElementById('confirm-delete-btn');
        if (!modalEl || !confirmBtn) {
            if (!confirm('Are you sure you want to delete this vehicle?')) return;
            return;
        }

        const modal = new bootstrap.Modal(modalEl);
        const newConfirmBtn = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);

        newConfirmBtn.addEventListener('click', async function () {
            SHUBX.ajax({
                action: 'shubx51_delete_vehicle',
                data: {
                    id: id,
                    _wpnonce: Config.deleteNonce
                },
                successMessage: 'Vehicle archived successfully',
                onSuccess: function () {
                    const row = document.querySelector(`.js-delete-vehicle[data-id="${id}"]`)?.closest('tr');
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

    window.restoreVehicle = async function (id) {
        SHUBX.ajax({
            action: 'shubx51_restore_vehicle',
            data: {
                id: id,
                _wpnonce: Config.nonce
            },
            successMessage: 'Vehicle restored successfully!',
            reload: true
        });
    };

    // --- Init ---
    $(function () {
        fetchModuleConfig().then(() => {
            // Delegated edit / delete handlers (data attributes)
            $(document).on('click', '.js-edit-vehicle', function (e) {
                e.preventDefault();
                const payload = $(this).attr('data-vehicle');
                try {
                    const obj = JSON.parse(payload);
                    window.editVehicle(obj);
                } catch (err) { console.error('Invalid vehicle payload', err); }
            });

            $(document).on('click', '.js-delete-vehicle', function (e) {
                e.preventDefault();
                const id = $(this).data('id');
                if (id) window.deleteVehicle(id);
            });

            // Add Vehicle Button Listener
            $('#addVehicle').on('click', function (e) {
                e.preventDefault();
                window.openVehicleModal();
            });

            const $form = $('#add-vehicle-form');
            if ($form.length) {
                $form.on('submit', function (e) {
                    e.preventDefault();
                    const action = $form.find('[name="action"]').val();
                    const formData = new FormData($form[0]);

                    SHUBX.ajax({
                        action: action,
                        data: formData,
                        loadingButton: $form.find('button[type="submit"]'),
                        successMessage: 'Vehicle details saved successfully',
                        reload: true,
                        onSuccess: function () {
                            closeVehicleModal();
                        }
                    });
                });
            }
            // Real-time Search
            $('#filter-search').on('input', function () {
                applyFilters();
            }).on('focus', function () {
                if (window.SHUBXCreateFuse) fuse = window.SHUBXCreateFuse('.vehicle-row');
            });
        });
    });

})(jQuery);

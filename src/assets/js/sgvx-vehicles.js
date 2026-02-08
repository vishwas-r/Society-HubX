/**
 * SGVX Vehicles Management JS
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
            const response = await fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'sgvx51_get_module_config',
                    module: 'vehicles'
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

        if (!fuse && window.sgvxCreateFuse) {
            fuse = window.sgvxCreateFuse('.vehicle-row');
        }

        const fuzzyMatches = searchVal && window.sgvxGetFuzzyMatches ? window.sgvxGetFuzzyMatches(fuse, searchVal) : null;

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
        $form.find('[name="action"]').val('sgvx51_edit_vehicle');

        $('#vehicleModalTitle').text('Edit Vehicle: ' + vehicle.number);
        vehicleModal.show();
    };

    function resetVehicleForm() {
        const $form = $('#add-vehicle-form');
        $form[0].reset();
        $form.find('[name="action"]').val('sgvx51_add_vehicle');
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
            try {
                await sgvxApiRequest('sgvx51_delete_vehicle', {
                    id: id,
                    _wpnonce: Config.deleteNonce
                });

                // Immediate UI update: Hide the row from active view
                const row = document.querySelector(`.js-delete-vehicle[data-id="${id}"]`)?.closest('tr');
                if (row) {
                    row.style.opacity = '0.5';
                    row.style.pointerEvents = 'none';
                    setTimeout(() => {
                        row.remove();
                        // No reload needed if we just hide it, but since tabs rely on DOM, better to refresh or just hide.
                        // For completeness:
                        window.location.reload();
                    }, 500);
                }
            } catch (err) { }
            modal.hide();
        });

        modal.show();
    };

    window.restoreVehicle = async function (id) {
        try {
            await sgvxApiRequest('sgvx51_restore_vehicle', {
                id: id,
                _wpnonce: Config.nonce
            });
            window.location.reload();
        } catch (err) { }
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
                $form.on('submit', async function (e) {
                    e.preventDefault();
                    const $btn = $form.find('button[type="submit"]');
                    const originalText = $btn.html();
                    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Saving...');

                    try {
                        const formData = new FormData($form[0]);
                        const data = Object.fromEntries(formData.entries());

                        await sgvxApiRequest(data.action, data);

                        closeVehicleModal();
                        window.location.reload();
                    } catch (err) {
                        console.error('Vehicle Save Error:', err);
                    } finally {
                        $btn.prop('disabled', false).html(originalText);
                    }
                });
            }
            // Real-time Search
            $('#filter-search').on('input', function () {
                applyFilters();
            }).on('focus', function () {
                if (window.sgvxCreateFuse) fuse = window.sgvxCreateFuse('.vehicle-row');
            });
        });
    });

})(jQuery);

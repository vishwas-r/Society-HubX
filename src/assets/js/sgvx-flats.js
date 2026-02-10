/**
 * SGVX Flats Management JS
 */
(function ($) {
    'use strict';

    // Module configuration (fetched at runtime)
    const Config = {
        nonce: null,
        deleteNonce: null,
        initialized: false
    };

    let flatModal = null;
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
                    module: 'flats'
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

    window.openFlatModal = function () {
        if (!flatModal) flatModal = new bootstrap.Modal(document.getElementById('flatModal'));
        resetFlatForm();
        flatModal.show();
    };

    window.closeFlatModal = function () {
        const el = document.getElementById('flatModal');
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
            fuse = window.sgvxCreateFuse('.flat-row');
        }

        const fuzzyMatches = searchVal && window.sgvxGetFuzzyMatches ? window.sgvxGetFuzzyMatches(fuse, searchVal) : null;

        $('.flat-row').each(function () {
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

    window.editFlat = function (flat) {
        if (!flatModal) flatModal = new bootstrap.Modal(document.getElementById('flatModal'));
        const $form = $('#add-flat-form');
        $form.find('[name="block"]').val(flat.block);
        $form.find('[name="flat_number"]').val(flat.flat_number);
        $form.find('[name="type"]').val(flat.type);
        $form.find('[name="floor"]').val(flat.floor);
        $form.find('[name="sq_foot"]').val(flat.sq_foot || '');
        $form.find('[name="parking_slot"]').val(flat.parking_slot || '');
        $form.find('[name="status"]').val(flat.status || 'vacant');
        $form.find('[name="parking_status"]').val(flat.parking_status || 'available');
        $form.find('[name="action"]').val('sgvx51_edit_flat');
        $form.find('[name="flat_id"]').val(flat.id || '');

        $('#flatModalTitle').text('Edit Unit: ' + flat.id);
        flatModal.show();
    };

    function resetFlatForm() {
        const $form = $('#add-flat-form');
        $form[0].reset();
        $form.find('[name="action"]').val('sgvx51_add_flat');
        $('#flatModalTitle').text('Add New Unit');
    }

    window.deleteFlat = async function (id) {
        const modalEl = document.getElementById('deleteConfirmModal');
        const confirmBtn = document.getElementById('confirm-delete-btn');
        if (!modalEl || !confirmBtn) {
            if (!confirm('Are you sure you want to delete Unit ' + id + '?')) return;
            // Fallback to legacy if no modern modal
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = ajaxurl;
            const fields = {
                action: 'sgvx51_delete_flat',
                flat_id: id,
                _wpnonce: window.sgvxFlatsData.nonce // Note: Check if we need a specific delete nonce
            };
            // ... (rest of legacy form submit if needed)
            return;
        }

        const modal = new bootstrap.Modal(modalEl);
        const newConfirmBtn = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);

        newConfirmBtn.addEventListener('click', async function () {
            try {
                await sgvxApiRequest('sgvx51_delete_flat', {
                    flat_id: id,
                    _wpnonce: window.sgvxFlatsData.deleteNonce || window.sgvxFlatsData.nonce
                });

                const row = document.querySelector(`.js-delete-flat[data-id="${id}"]`)?.closest('tr');
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

    window.restoreFlat = async function (id) {
        try {
            await sgvxApiRequest('sgvx51_restore_flat', {
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
            $(document).on('click', '.js-edit-flat', function (e) {
                e.preventDefault();
                const payload = $(this).attr('data-flat');
                try {
                    const obj = JSON.parse(payload);
                    window.editFlat(obj);
                } catch (err) { console.error('Invalid flat payload', err); }
            });

            $(document).on('click', '.js-delete-flat', function (e) {
                e.preventDefault();
                const id = $(this).data('id');
                if (id) window.deleteFlat(id);
            });

            // Real-time Search
            $('#filter-search').on('input', function () {
                applyFilters();
            }).on('focus', function () {
                if (window.sgvxCreateFuse) fuse = window.sgvxCreateFuse('.flat-row');
            });

            const $form = $('#add-flat-form');
            if ($form.length) {
                $form.on('submit', async function (e) {
                    e.preventDefault();
                    const $btn = $form.find('button[type="submit"]');
                    const originalText = $btn.html();
                    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Saving...');

                    try {
                        const formData = new FormData($form[0]);
                        const data = Object.fromEntries(formData.entries());

                        console.log('SGVX Flat Form Submit:', {
                            action: data.action,
                            block: data.block,
                            flat_number: data.flat_number,
                            type: data.type,
                            status: data.status,
                            parking_status: data.parking_status,
                            floor: data.floor,
                            parking_slot: data.parking_slot
                        });

                        const resp = await sgvxApiRequest(data.action, data);
                        console.log('SGVX Save response:', resp);

                        // If the server reports no rows affected, inform the user.
                        const rows = resp && (typeof resp.rows_affected !== 'undefined') ? resp.rows_affected : null;

                        // Edit action: if rows === 0 -> notify; if rows > 0 -> close modal and reload table
                        if (data.action === 'sgvx51_edit_flat') {
                            if (rows === 0) {
                                console.warn('No changes detected for this flat (rows_affected=0).');
                                alert('Save completed: No changes detected. The values you submitted match existing values.');
                            } else {
                                closeFlatModal();
                                window.location.reload();
                            }
                        } else {
                            // For add or other actions, reload on success so new row appears
                            if (resp) {
                                closeFlatModal();
                                window.location.reload();
                            }
                        }
                    } catch (err) {
                        console.error('Flat Save Error:', err);
                    } finally {
                        $btn.prop('disabled', false).html(originalText);
                    }
                });
            }
        });
    });

})(jQuery);

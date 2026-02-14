/**
 * SGVX Flats Management JS
 */
(function ($) {
    'use strict';

    // Module configuration (fetched at runtime)
    const Config = {
        nonce: null,
        deleteNonce: null,
        hardDeleteNonce: null,
        initialized: false
    };

    let flatModal = null;
    let currentTab = 'all';
    let fuse = null;

    async function fetchModuleConfig() {
        if (Config.initialized) return;

        try {
            const result = await SGVX.ajax({
                action: 'sgvx51_get_module_config',
                data: { module: 'flats' },
                showOverlay: false,
                suppressErrorToast: true
            });

            if (result) {
                Config.nonce = result.nonce || null;
                Config.deleteNonce = result.deleteNonce || null;
                Config.hardDeleteNonce = result.hardDeleteNonce || null;
                Config.initialized = true;
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

    window.deleteFlat = function (id) {
        const modalEl = document.getElementById('deleteConfirmModal');
        const confirmBtn = document.getElementById('confirm-delete-btn');
        const modalTitle = modalEl ? modalEl.querySelector('.modal-title') : null;
        const modalBody = modalEl ? modalEl.querySelector('.modal-text') : null;

        if (!modalEl || !confirmBtn) return;

        // Set labels for Archive
        if (modalTitle) modalTitle.textContent = 'Archive Unit?';
        if (modalBody) modalBody.textContent = 'This unit will be moved to the archive registry.';

        confirmBtn.classList.add('btn-danger');
        confirmBtn.classList.remove('btn-dark');

        const modal = new bootstrap.Modal(modalEl);

        // Remove old listeners to prevent double submit
        const newConfirmBtn = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);

        newConfirmBtn.addEventListener('click', function () {
            SGVX.ajax({
                action: 'sgvx51_delete_flat',
                data: {
                    flat_id: id,
                    _wpnonce: Config.deleteNonce
                },
                loadingButton: newConfirmBtn,
                successMessage: 'Flat archived successfully',
                reload: true,
                onSuccess: () => modal.hide()
            });
        });

        modal.show();
    };

    window.hardDeleteFlat = function (id) {
        const modalEl = document.getElementById('deleteConfirmModal');
        const confirmBtn = document.getElementById('confirm-delete-btn');
        const modalTitle = modalEl ? modalEl.querySelector('.modal-title') : null;
        const modalBody = modalEl ? modalEl.querySelector('.modal-text') : null;

        if (!modalEl || !confirmBtn) return;

        // Set labels for Hard Delete
        if (modalTitle) modalTitle.textContent = 'Permanently Delete?';
        if (modalBody) modalBody.textContent = 'This action is IRREVERSIBLE. Are you sure you want to completely remove this unit?';

        confirmBtn.classList.remove('btn-danger');
        confirmBtn.classList.add('btn-dark');

        const modal = new bootstrap.Modal(modalEl);

        // Remove old listeners
        const newConfirmBtn = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);

        newConfirmBtn.addEventListener('click', function () {
            SGVX.ajax({
                action: 'sgvx51_hard_delete_flat',
                data: {
                    flat_id: id,
                    _wpnonce: Config.hardDeleteNonce
                },
                loadingButton: newConfirmBtn,
                successMessage: 'Flat deleted permanently',
                reload: true,
                onSuccess: () => modal.hide()
            });
        });

        modal.show();
    };

    window.restoreFlat = function (id) {
        SGVX.ajax({
            action: 'sgvx51_restore_flat',
            data: {
                flat_id: id,
                _wpnonce: Config.nonce
            },
            successMessage: 'Flat restored successfully',
            reload: true
        });
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

            $(document).on('click', '.js-restore-flat', function (e) {
                e.preventDefault();
                const id = $(this).data('id');
                if (id) window.restoreFlat(id);
            });

            $(document).on('click', '.js-hard-delete-flat', function (e) {
                e.preventDefault();
                const id = $(this).data('id');
                if (id) window.hardDeleteFlat(id);
            });

            // Real-time Search
            $('#filter-search').on('input', function () {
                applyFilters();
            }).on('focus', function () {
                if (window.sgvxCreateFuse) fuse = window.sgvxCreateFuse('.flat-row');
            });

            if ($form.length) {
                $form.on('submit', function (e) {
                    e.preventDefault();
                    const formData = new FormData($form[0]);
                    const action = formData.get('action');

                    SGVX.ajax({
                        action: action,
                        data: formData,
                        loadingButton: $form.find('button[type="submit"]'),
                        onSuccess: function (resp) {
                            const rows = resp && (typeof resp.rows_affected !== 'undefined') ? resp.rows_affected : null;

                            if (action === 'sgvx51_edit_flat' && rows === 0) {
                                SGVX.toast.info('Save completed: No changes detected.');
                            } else {
                                closeFlatModal();
                                window.location.reload();
                            }
                        }
                    });
                });
            }
        });
    });

})(jQuery);

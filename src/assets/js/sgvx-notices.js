/**
 * SGVX Notices JS (Modernized)
 * Handles AJAX CRUD, Tabs, Pinned Logic, and TinyMCE.
 */
(function ($) {
    'use strict';

    const Config = {
        nonce: null,
        deleteNonce: null,
        initialized: false
    };

    async function fetchModuleConfig() {
        if (Config.initialized) return;
        try {
            const result = await sgvxApiRequest('sgvx51_get_module_config', { module: 'notices' });
            if (result) {
                Config.nonce = result.nonce;
                Config.deleteNonce = result.deleteNonce;
                Config.initialized = true;
            }
        } catch (error) {
            console.error('Failed to fetch notice config:', error);
        }
    }

    // --- Modal Logic ---
    let noticeModal = null;
    window.openNoticeModal = function (data = null) {
        if (!noticeModal) noticeModal = new bootstrap.Modal(document.getElementById('noticeModal'));

        const form = document.getElementById('modern-notice-form');
        form.reset();
        document.getElementById('n-id').value = '';
        document.getElementById('noticeModalLabel').textContent = data ? 'Edit Announcement' : 'Broadcast Announcement';

        // TinyMCE Reset
        if (window.tinymce && tinymce.get('notice_editor')) {
            tinymce.get('notice_editor').setContent(data ? (data.content || '') : '');
        } else {
            document.getElementById('notice_editor').value = data ? (data.content || '') : '';
        }

        if (data) {
            document.getElementById('n-id').value = data.id;
            document.getElementById('n-title').value = data.title;
            document.getElementById('n-urgency').value = data.urgency;
            document.getElementById('n-audience').value = data.audience;
            document.getElementById('n-expiry').value = data.expiry_date || '';
            document.getElementById('n-status').value = data.status || 'published';
            document.getElementById('n-pinned').checked = parseInt(data.is_pinned) === 1;
        }

        noticeModal.show();
    };

    // --- Tab Switching ---
    function switchTab(target) {
        $('.notice-tab-btn').removeClass('active text-primary border-primary').addClass('text-muted border-transparent');
        $(`.notice-tab-btn[data-tab="${target}"]`).addClass('active text-primary border-primary').removeClass('text-muted border-transparent');

        $('.notice-pane').addClass('d-none');
        $(`#pane-${target}`).removeClass('d-none');
    }

    // --- CRUD Actions ---
    async function saveNotice(e) {
        e.preventDefault();
        const form = e.target;
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;

        // Sync TinyMCE to Textarea
        if (window.tinymce && tinymce.get('notice_editor')) {
            document.getElementById('notice_editor').value = tinymce.get('notice_editor').getContent();
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';

        try {
            const formData = new FormData(form);
            formData.append('_wpnonce', Config.nonce);

            const isUpdate = formData.get('id');
            const action = isUpdate ? 'sgvx51_update_notice' : 'sgvx51_add_notice';

            await sgvxApiRequest(action, formData);

            noticeModal.hide();
            setTimeout(() => window.location.reload(), 500);
        } catch (err) {
            console.error('Save failed:', err);
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    }

    async function deleteNotice(id) {
        if (!confirm('Permanently remove this announcement? This cannot be undone.')) return;
        try {
            await sgvxApiRequest('sgvx51_delete_notice', { id, _wpnonce: Config.deleteNonce });
            $(`.sgvx-notice-card[data-id="${id}"]`).fadeOut(400, function () { $(this).remove(); });
        } catch (err) { }
    }

    async function togglePin(id, pinned) {
        try {
            await sgvxApiRequest('sgvx51_toggle_pin', { id, pinned, _wpnonce: Config.nonce });
            window.location.reload();
        } catch (err) { }
    }

    // --- Initialization ---
    $(function () {
        fetchModuleConfig();

        // Form Submit
        $('#modern-notice-form').on('submit', saveNotice);

        // Tab Clicks
        $('.notice-tab-btn').on('click', function () {
            switchTab($(this).data('tab'));
        });

        // Delegate Edit/Pin/Delete
        $(document).on('click', '.js-edit-notice', async function (e) {
            e.preventDefault();
            const id = $(this).data('id');
            const card = $(`.sgvx-notice-card[data-id="${id}"]`);

            // Fetch fresh data from server or use attributes? Fetching is safer.
            try {
                const data = await sgvxApiRequest('sgvx51_get_notice', { id, _wpnonce: Config.nonce });
                openNoticeModal(data);
            } catch (err) { }
        });

        $(document).on('click', '.js-toggle-pin', function (e) {
            e.preventDefault();
            togglePin($(this).data('id'), $(this).data('pinned'));
        });

        $(document).on('click', '.js-delete-notice', function (e) {
            e.preventDefault();
            deleteNotice($(this).data('id'));
        });

        // Searching & Filtering
        const searchInput = document.getElementById('noticeSearch');
        const urgencyFilter = document.getElementById('urgencyFilter');

        function applyFilters() {
            const q = searchInput.value.toLowerCase();
            const urg = urgencyFilter.value.toLowerCase();

            $('.sgvx-notice-card').each(function () {
                const text = $(this).data('search') || '';
                const cardUrg = $(this).data('urgency') || '';

                const matchesSearch = text.includes(q);
                const matchesUrgency = urg === 'all' || cardUrg === urg;

                if (matchesSearch && matchesUrgency) $(this).removeClass('d-none');
                else $(this).addClass('d-none');
            });
        }

        if (searchInput) searchInput.addEventListener('keyup', applyFilters);
        if (urgencyFilter) urgencyFilter.addEventListener('change', applyFilters);
    });

})(jQuery);

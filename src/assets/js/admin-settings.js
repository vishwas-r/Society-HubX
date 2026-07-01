/**
 * Admin Settings JS
 * Handles Tab Switching and QR Code Upload.
 */
(function ($) {
    'use strict';

    // --- 1. Tab Switching (Immediate Global) ---
    window.switchSettingsTab = function (tab) {
        $('.settings-tab-pane').addClass('hidden');
        $('#tab-content-' + tab).removeClass('hidden');

        $('#snestx-settings-tabs button').removeClass('active border-primary text-primary')
            .addClass('border-transparent text-muted');

        $('#tab-btn-' + tab).removeClass('border-transparent text-muted')
            .addClass('active border-primary text-primary');

        // Persistence: Update URL without reloading
        const url = new URL(window.location);
        url.searchParams.set('tab', tab);
        window.history.replaceState({}, '', url);
    };

    $(function () {
        // --- 0. Tab Persistence on Load ---
        const urlParams = new URLSearchParams(window.location.search);
        const activeTab = urlParams.get('tab');
        if (activeTab) {
            window.switchSettingsTab(activeTab);
        }

        // --- 0.1 URL Cleanup (Remove migration/sync stats after display) ---
        if (urlParams.has('migration_done') || urlParams.has('reset_done') || urlParams.has('export_done')) {
            setTimeout(() => {
                const cleanUrl = new URL(window.location);
                cleanUrl.searchParams.delete('migration_done');
                cleanUrl.searchParams.delete('reset_done');
                cleanUrl.searchParams.delete('export_done');
                cleanUrl.searchParams.delete('stats');
                window.history.replaceState({}, '', cleanUrl);
            }, 3000); // 3 seconds grace period to see the stats
        }

        // --- 2. QR Upload (Media Library) ---
        const $btnUpload = $('#btn-upload-qr');
        const $btnRemove = $('#btn-remove-qr');
        const $inputQr = $('#snestx51_bank_qr');
        const $preview = $('#qr-preview-container');

        if ($btnUpload.length) {
            $btnUpload.on('click', function (e) {
                e.preventDefault();

                // Professional Check: Is wp.media available?
                if (typeof wp === 'undefined' || !wp.media) {
                    SNESTX.toast.error('WordPress Media Library not loaded properly. Please refresh the page.');
                    return;
                }

                const mediaUploader = wp.media({
                    title: 'Select UPI QR Code',
                    button: { text: 'Use this QR Code' },
                    multiple: false
                });

                mediaUploader.on('select', function () {
                    const attachment = mediaUploader.state().get('selection').first().toJSON();
                    $inputQr.val(attachment.url);
                    $preview.html(`<img src="${attachment.url}" class="max-w-full max-h-full object-contain">`);
                    $btnRemove.removeClass('hidden');
                });

                mediaUploader.open();
            });
        }

        if ($btnRemove.length) {
            $btnRemove.on('click', function () {
                $inputQr.val('');
                $preview.html('<span class="text-[10px] text-slate-400">No QR Code</span>');
                $(this).addClass('hidden');
            });
        }
    });

})(jQuery);

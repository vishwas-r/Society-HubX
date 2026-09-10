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

        $('#shubx-settings-tabs button').removeClass('active border-primary text-primary')
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
        const $inputQr = $('#shubx51_bank_qr');
        const $preview = $('#qr-preview-container');

        if ($btnUpload.length) {
            $btnUpload.on('click', function (e) {
                e.preventDefault();

                // Professional Check: Is wp.media available?
                if (typeof wp === 'undefined' || !wp.media) {
                    SHUBX.toast.error('WordPress Media Library not loaded properly. Please refresh the page.');
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

        // --- 4. Module Enable / Disable Toggle ---
        $(document).on('change', '.module-toggle-switch', function () {
            const $switch = $(this);
            const moduleSlug = $switch.data('module');
            const isEnabled = $switch.is(':checked') ? 1 : 0;
            const nonce = $('#shubx51_module_toggle_nonce').val() || '';
            const $badge = $('.status-badge-' + moduleSlug);
            const $alert = $('#module-toggle-alert');

            $switch.prop('disabled', true);

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'shubx51_toggle_module',
                    module: moduleSlug,
                    status: isEnabled,
                    nonce: nonce
                },
                success: function (res) {
                    $switch.prop('disabled', false);
                    if (res && res.success) {
                        if (isEnabled) {
                            $badge.removeClass('bg-secondary bg-opacity-10 text-muted')
                                  .addClass('bg-success bg-opacity-10 text-success')
                                  .text('Active');
                        } else {
                            $badge.removeClass('bg-success bg-opacity-10 text-success')
                                  .addClass('bg-secondary bg-opacity-10 text-muted')
                                  .text('Disabled');
                        }
                        $alert.removeClass('d-none alert-danger').addClass('alert-success')
                              .html('<i class="bi bi-check-circle-fill me-2"></i>' + (res.data.message || 'Module status updated successfully.'));
                        setTimeout(function() { $alert.addClass('d-none'); }, 3500);
                    } else {
                        $switch.prop('checked', !isEnabled);
                        $alert.removeClass('d-none alert-success').addClass('alert-danger')
                              .html('<i class="bi bi-exclamation-triangle-fill me-2"></i>' + (res.data ? res.data.message : 'Failed to update module status.'));
                    }
                },
                error: function (xhr) {
                    $switch.prop('disabled', false);
                    $switch.prop('checked', !isEnabled);
                    const msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : 'Network error updating module status.';
                    $alert.removeClass('d-none alert-success').addClass('alert-danger')
                          .html('<i class="bi bi-exclamation-triangle-fill me-2"></i>' + msg);
                }
            });
        });
    });

    // --- 3. Color Palette & Theme Selection Helpers ---
    window.shubxSelectPalette = function (key) {
        $('.shubx-palette-radio').each(function () {
            this.checked = (this.value === key);
        });
        $('.shubx-palette-card').removeClass('border-2 border-primary shadow-sm').addClass('border-light');
        const $selectedRadio = $('#palette-radio-' + key);
        if ($selectedRadio.length) {
            $selectedRadio.closest('.shubx-palette-card').addClass('border-2 border-primary shadow-sm').removeClass('border-light');
        }
        $('.shubx-palette-check').addClass('d-none');
        if ($selectedRadio.length) {
            $selectedRadio.closest('.shubx-palette-card').find('.shubx-palette-check').removeClass('d-none');
        }

        // Live update DOM
        document.documentElement.setAttribute('data-shubx-palette', key);
        const root = document.getElementById('shubx51-app-root');
        if (root) root.setAttribute('data-shubx-palette', key);

        // Set 1-year Cookie for PHP SSR
        document.cookie = "shubx_palette=" + encodeURIComponent(key) + "; path=/; max-age=31536000; SameSite=Lax";
    };

    window.shubxSelectDefaultTheme = function (mode) {
        if (typeof window.shubxApplyTheme === 'function') {
            window.shubxApplyTheme(mode);
        } else {
            document.documentElement.setAttribute('data-bs-theme', mode);
            const root = document.getElementById('shubx51-app-root');
            if (root) root.setAttribute('data-bs-theme', mode);
            document.cookie = "shubx_theme=" + encodeURIComponent(mode) + "; path=/; max-age=31536000; SameSite=Lax";
        }
    };

})(jQuery);

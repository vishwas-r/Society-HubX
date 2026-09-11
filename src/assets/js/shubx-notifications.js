jQuery(document).ready(function ($) {
    // 1. Initialize Modals
    const channelModalNode = document.getElementById('shubx-channel-modal');
    const templateModalNode = document.getElementById('shubx-template-modal');

    const channelModal = channelModalNode ? new bootstrap.Modal(channelModalNode) : null;
    const templateModal = templateModalNode ? new bootstrap.Modal(templateModalNode) : null;

    const $channelForm = $('#shubx-channel-form');
    const $templateForm = $('#shubx-template-form');
    const $fieldsContainer = $('#shubx-channel-settings-fields');

    // 2. Channel Configuration
    $('.shubx-configure-channel').on('click', function () {
        const channel = $(this).data('channel');
        $('#shubx-modal-channel-name').text(channel.charAt(0).toUpperCase() + channel.slice(1));
        $('#shubx-modal-channel-slug').val(channel);

        // Fetch current config via SHUBX.ajax
        SHUBX.ajax({
            action: 'shubx51_get_channel_config',
            data: {
                channel: channel,
                _ajax_nonce: shubx51RequestNonce
            },
            onSuccess: function (data) {
                if (channelModal) {
                    renderSettingsFields(channel, data);
                    channelModal.show();
                }
            }
        });
    });

    function renderSettingsFields(channel, config) {
        $fieldsContainer.empty();
        let html = '';

        if (channel === 'email') {
            html = `
                <div class="mb-3">
                    <label class="form-label small fw-bold text-slate-700">Delivery Method</label>
                    <select class="form-select rounded-3" id="shubx-email-method" name="config[method]">
                        <option value="wp_mail" ${config.method === 'wp_mail' ? 'selected' : ''}>WordPress Default (wp_mail)</option>
                        <option value="gmail" ${config.method === 'gmail' ? 'selected' : ''}>Gmail API (OAuth2)</option>
                        <option value="smtp" ${config.method === 'smtp' ? 'selected' : ''}>Custom SMTP</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-slate-700">Sender Name</label>
                    <input type="text" class="form-control rounded-3" name="config[from_name]" value="${config.from_name || ''}" placeholder="Society HubX">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-slate-700">Sender Email</label>
                    <input type="email" class="form-control rounded-3" name="config[from_email]" value="${config.from_email || ''}" placeholder="noreply@society.com">
                </div>

                <!-- Gmail API Config Fields -->
                <div id="shubx-email-config-gmail" class="shubx-email-sub-config mt-3 p-3 border rounded-3 bg-light" style="display: none;">
                    <h6 class="fw-bold mb-3 small text-primary"><i class="bi bi-google me-2"></i>Gmail OAuth2 Settings</h6>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-slate-700">Gmail Client ID</label>
                        <input type="text" class="form-control rounded-3" name="config[gmail_client_id]" value="${config.gmail_client_id || ''}" placeholder="client-id.apps.googleusercontent.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-slate-700">Gmail Client Secret</label>
                        <input type="password" class="form-control rounded-3" name="config[gmail_client_secret]" value="${config.gmail_client_secret || ''}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-slate-700">Authorized Redirect URI</label>
                        <input type="text" class="form-control rounded-3 bg-light text-muted" value="${(typeof shubx51NotificationsVars !== 'undefined' ? shubx51NotificationsVars.ajaxUrl : ajaxurl)}?action=shubx51_gmail_oauth_callback" readonly>
                    </div>
                </div>

                <!-- Custom SMTP Config Fields -->
                <div id="shubx-email-config-smtp" class="shubx-email-sub-config mt-3 p-3 border rounded-3 bg-light" style="display: none;">
                    <h6 class="fw-bold mb-3 small text-primary"><i class="bi bi-envelope-check me-2"></i>Custom SMTP Settings</h6>
                    <div class="row g-2 mb-3">
                        <div class="col-md-8">
                            <label class="form-label small fw-bold text-slate-700">SMTP Host</label>
                            <input type="text" class="form-control rounded-3" name="config[smtp_host]" value="${config.smtp_host || ''}" placeholder="smtp.mailtrap.io">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-slate-700">SMTP Port</label>
                            <input type="number" class="form-control rounded-3" name="config[smtp_port]" value="${config.smtp_port || 587}">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-slate-700">Encryption</label>
                        <select class="form-select rounded-3" name="config[smtp_encryption]">
                            <option value="tls" ${config.smtp_encryption === 'tls' ? 'selected' : ''}>TLS (Recommended)</option>
                            <option value="ssl" ${config.smtp_encryption === 'ssl' ? 'selected' : ''}>SSL</option>
                            <option value="none" ${config.smtp_encryption === 'none' ? 'selected' : ''}>None</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-slate-700">SMTP Username</label>
                        <input type="text" class="form-control rounded-3" name="config[smtp_user]" value="${config.smtp_user || ''}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-slate-700">SMTP Password</label>
                        <input type="password" class="form-control rounded-3" name="config[smtp_pass]" value="${config.smtp_pass || ''}">
                    </div>
                </div>
            `;
        } else if (channel === 'whatsapp') {
            html = `
                <div class="mb-3">
                    <label class="form-label small fw-bold text-slate-700">Twilio Account SID</label>
                    <input type="text" class="form-control rounded-3" name="config[sid]" value="${config.sid || ''}">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-slate-700">Twilio Auth Token</label>
                    <input type="password" class="form-control rounded-3" name="config[token]" value="${config.token || ''}">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-slate-700">Twilio Phone Number (from)</label>
                    <input type="text" class="form-control rounded-3" name="config[from_number]" value="${config.from_number || ''}" placeholder="+123456789">
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label small fw-bold text-slate-700">Monthly Budget ($)</label>
                        <input type="number" step="0.01" class="form-control rounded-3" name="config[monthly_budget]" value="${config.monthly_budget || 50}">
                    </div>
                </div>
            `;
        } else if (channel === 'inapp') {
            html = `<p class="text-slate-500 small">In-App notifications are delivered to the resident dashboard. No additional configuration required.</p>`;
                } else if (channel === 'push') {
            html = `
                <div class="card border-0 bg-primary bg-opacity-10 rounded-4 p-3 mb-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="p-3 bg-white rounded-circle shadow-sm text-primary fs-4">
                            <i class="bi bi-broadcast-pin"></i>
                        </div>
                        <div>
                            <div class="d-flex align-items-center gap-2">
                                <h6 class="fw-bold text-slate-900 mb-0">NammaSociety Cloud Push Relay</h6>
                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1 rounded-pill small">
                                    <i class="bi bi-check-circle-fill me-1"></i>Active &amp; Connected
                                </span>
                            </div>
                            <p class="x-small text-slate-600 mb-0 mt-1">
                                Push notifications are dispatched automatically through the centralized NammaSociety Universal Gateway. Resident devices registered via the mobile app connect seamlessly without requiring local Firebase credentials or Google Cloud setup.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="p-3 bg-light rounded-4 border border-light mt-3">
                    <div class="fw-bold small text-dark mb-1 d-flex align-items-center">
                        <i class="bi bi-send-fill text-primary me-2"></i>Test Push Notification Dispatch
                    </div>
                    <p class="x-small text-muted mb-3">
                        Dispatch a live test notification to verify delivery to resident mobile devices and the in-app notification center.
                    </p>
                    <div class="input-group input-group-sm mb-2">
                        <input type="text" id="shubx-modal-test-flat" class="form-control" placeholder="Target Flat (Optional, e.g. A-101 or leave empty for all)">
                        <button type="button" class="btn btn-primary fw-bold" id="btn-modal-send-test-push">
                            <i class="bi bi-send me-1"></i>Send Test Alert
                        </button>
                    </div>
                    <div id="shubx-modal-test-status" class="x-small"></div>
                </div>
            `;
        }

        $fieldsContainer.html(html);

        // Bind dynamic visibility trigger for delivery methods
        if (channel === 'email') {
            const toggleEmailMethodFields = () => {
                const method = $('#shubx-email-method').val();
                $('.shubx-email-sub-config').hide();
                if (method === 'gmail') {
                    $('#shubx-email-config-gmail').show();
                } else if (method === 'smtp') {
                    $('#shubx-email-config-smtp').show();
                }
            };
            $('#shubx-email-method').on('change', toggleEmailMethodFields);
            toggleEmailMethodFields(); // Run initially
                } else if (channel === 'push') {
            $('#btn-modal-send-test-push').on('click', function () {
                const $btn = $(this);
                const flatNo = $('#shubx-modal-test-flat').val();
                $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Dispatching...');
                $('#shubx-modal-test-status').html('<span class="text-muted"><i class="bi bi-arrow-repeat spin me-1"></i>Dispatching test alert via Central Relay...</span>');

                const nonceVal = (typeof shubxAdmin !== 'undefined' && shubxAdmin.fcm_nonce) ? shubxAdmin.fcm_nonce :
                                 ((typeof shubxAdmin !== 'undefined' && shubxAdmin.nonce) ? shubxAdmin.nonce :
                                 ((typeof shubx51RequestNonce !== 'undefined') ? shubx51RequestNonce : ''));

                $.ajax({
                    url: (typeof shubx51NotificationsVars !== 'undefined' ? shubx51NotificationsVars.ajaxUrl : (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php')),
                    type: 'POST',
                    data: {
                        action: 'shubx51_send_test_push',
                        target_flat: flatNo,
                        nonce: nonceVal
                    },
                    success: function (res) {
                        $btn.prop('disabled', false).html('<i class="bi bi-send me-1"></i>Send Test Alert');
                        if (res.success) {
                            const icon = res.data && res.data.warning ? 'bi-info-circle-fill text-info' : 'bi-check-circle-fill text-success';
                            const textClass = res.data && res.data.warning ? 'text-primary' : 'text-success';
                            $('#shubx-modal-test-status').html('<span class="' + textClass + ' fw-bold"><i class="bi ' + icon + ' me-1"></i>' + (res.data.message || 'Test alert recorded!') + '</span>');
                        } else {
                            $('#shubx-modal-test-status').html('<span class="text-danger fw-bold"><i class="bi bi-exclamation-triangle-fill me-1"></i>' + (res.data && res.data.message ? res.data.message : 'Error sending test push.') + '</span>');
                        }
                    },
                    error: function (xhr) {
                        $btn.prop('disabled', false).html('<i class="bi bi-send me-1"></i>Send Test Alert');
                        const msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message)
                            ? xhr.responseJSON.data.message
                            : 'Network error occurred.';
                        $('#shubx-modal-test-status').html('<span class="text-danger fw-bold"><i class="bi bi-x-circle me-1"></i>' + msg + '</span>');
                    }
                });
            });
        }
    }

    $channelForm.on('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(this);

        SHUBX.ajax({
            action: 'shubx51_save_channel_config',
            data: formData,
            successMessage: 'Channel configuration saved!',
            reload: true,
            onSuccess: function () {
                if (channelModal) channelModal.hide();
            }
        });
    });

    // 3. Channel Toggles
    $('.shubx-channel-toggle').on('change', function () {
        const channel = $(this).data('channel');
        const active = $(this).is(':checked') ? 1 : 0;

        SHUBX.ajax({
            action: 'shubx51_toggle_channel',
            data: {
                channel: channel,
                active: active,
                _ajax_nonce: shubx51RequestNonce
            }
        });
    });

    // 4. Event Mapping
    $('.shubx-mapping-toggle').on('change', function () {
        const event = $(this).data('event');
        const channel = $(this).data('channel');

        SHUBX.ajax({
            action: 'shubx51_update_event_mapping',
            data: {
                event: event,
                channel: channel,
                enabled: $(this).is(':checked') ? 1 : 0,
                _ajax_nonce: shubx51RequestNonce
            }
        });
    });

    // 5. Template Editing
    $('.shubx-edit-template').on('click', function () {
        const id = $(this).data('id');

        SHUBX.ajax({
            action: 'shubx51_get_template',
            data: {
                id: id,
                _ajax_nonce: shubx51RequestNonce
            },
            onSuccess: function (tpl) {
                if (templateModal) {
                    $('#shubx-template-id').val(tpl.id);
                    $('#shubx-template-event-name').text(tpl.event_slug.replace(/_/g, ' '));
                    $('#shubx-template-subject').val(tpl.subject);
                    $('#shubx-template-content').val(tpl.content);

                    // Show/Hide subject based on channel
                    if (tpl.channel === 'whatsapp' || tpl.channel === 'inapp') {
                        $('.subject-field').hide();
                    } else {
                        $('.subject-field').show();
                    }

                    templateModal.show();
                }
            }
        });
    });

    $templateForm.on('submit', function (e) {
        e.preventDefault();
        const formData = Object.fromEntries(new FormData(this));

        SHUBX.ajax({
            action: 'shubx51_save_template',
            data: formData,
            successMessage: 'Notification template saved!',
            reload: true,
            onSuccess: function () {
                if (templateModal) templateModal.hide();
            }
        });
    });
});

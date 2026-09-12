jQuery(document).ready(function ($) {
    // 1. Initialize Modals
    const channelModalNode = document.getElementById('nammasociety-channel-modal');
    const templateModalNode = document.getElementById('nammasociety-template-modal');

    const channelModal = channelModalNode ? new bootstrap.Modal(channelModalNode) : null;
    const templateModal = templateModalNode ? new bootstrap.Modal(templateModalNode) : null;

    const $channelForm = $('#nammasociety-channel-form');
    const $templateForm = $('#nammasociety-template-form');
    const $fieldsContainer = $('#nammasociety-channel-settings-fields');

    // 2. Channel Configuration
    $('.nammasociety-configure-channel').on('click', function () {
        const channel = $(this).data('channel');
        $('#nammasociety-modal-channel-name').text(channel.charAt(0).toUpperCase() + channel.slice(1));
        $('#nammasociety-modal-channel-slug').val(channel);

        // Fetch current config via NAMMASOCIETY.ajax
        NAMMASOCIETY.ajax({
            action: 'nammasociety51_get_channel_config',
            data: {
                channel: channel,
                _ajax_nonce: nammasociety51RequestNonce
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
                    <select class="form-select rounded-3" id="nammasociety-email-method" name="config[method]">
                        <option value="wp_mail" ${config.method === 'wp_mail' ? 'selected' : ''}>WordPress Default (wp_mail)</option>
                        <option value="gmail" ${config.method === 'gmail' ? 'selected' : ''}>Gmail API (OAuth2)</option>
                        <option value="smtp" ${config.method === 'smtp' ? 'selected' : ''}>Custom SMTP</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-slate-700">Sender Name</label>
                    <input type="text" class="form-control rounded-3" name="config[from_name]" value="${config.from_name || ''}" placeholder="Namma Society">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-slate-700">Sender Email</label>
                    <input type="email" class="form-control rounded-3" name="config[from_email]" value="${config.from_email || ''}" placeholder="noreply@society.com">
                </div>

                <!-- Gmail API Config Fields -->
                <div id="nammasociety-email-config-gmail" class="nammasociety-email-sub-config mt-3 p-3 border rounded-3 bg-light" style="display: none;">
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
                        <input type="text" class="form-control rounded-3 bg-light text-muted" value="${(typeof nammasociety51NotificationsVars !== 'undefined' ? nammasociety51NotificationsVars.ajaxUrl : ajaxurl)}?action=nammasociety51_gmail_oauth_callback" readonly>
                    </div>
                </div>

                <!-- Custom SMTP Config Fields -->
                <div id="nammasociety-email-config-smtp" class="nammasociety-email-sub-config mt-3 p-3 border rounded-3 bg-light" style="display: none;">
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
                        <input type="text" id="nammasociety-modal-test-flat" class="form-control" placeholder="Target Flat (Optional, e.g. A-101 or leave empty for all)">
                        <button type="button" class="btn btn-primary fw-bold" id="btn-modal-send-test-push">
                            <i class="bi bi-send me-1"></i>Send Test Alert
                        </button>
                    </div>
                    <div id="nammasociety-modal-test-status" class="x-small"></div>
                </div>
            `;
        }

        $fieldsContainer.html(html);

        // Bind dynamic visibility trigger for delivery methods
        if (channel === 'email') {
            const toggleEmailMethodFields = () => {
                const method = $('#nammasociety-email-method').val();
                $('.nammasociety-email-sub-config').hide();
                if (method === 'gmail') {
                    $('#nammasociety-email-config-gmail').show();
                } else if (method === 'smtp') {
                    $('#nammasociety-email-config-smtp').show();
                }
            };
            $('#nammasociety-email-method').on('change', toggleEmailMethodFields);
            toggleEmailMethodFields(); // Run initially
                } else if (channel === 'push') {
            $('#btn-modal-send-test-push').on('click', function () {
                const $btn = $(this);
                const flatNo = $('#nammasociety-modal-test-flat').val();
                $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Dispatching...');
                $('#nammasociety-modal-test-status').html('<span class="text-muted"><i class="bi bi-arrow-repeat spin me-1"></i>Dispatching test alert via Central Relay...</span>');

                const nonceVal = (typeof nammasocietyAdmin !== 'undefined' && nammasocietyAdmin.fcm_nonce) ? nammasocietyAdmin.fcm_nonce :
                                 ((typeof nammasocietyAdmin !== 'undefined' && nammasocietyAdmin.nonce) ? nammasocietyAdmin.nonce :
                                 ((typeof nammasociety51RequestNonce !== 'undefined') ? nammasociety51RequestNonce : ''));

                $.ajax({
                    url: (typeof nammasociety51NotificationsVars !== 'undefined' ? nammasociety51NotificationsVars.ajaxUrl : (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php')),
                    type: 'POST',
                    data: {
                        action: 'nammasociety51_send_test_push',
                        target_flat: flatNo,
                        nonce: nonceVal
                    },
                    success: function (res) {
                        $btn.prop('disabled', false).html('<i class="bi bi-send me-1"></i>Send Test Alert');
                        if (res.success) {
                            const icon = res.data && res.data.warning ? 'bi-info-circle-fill text-info' : 'bi-check-circle-fill text-success';
                            const textClass = res.data && res.data.warning ? 'text-primary' : 'text-success';
                            $('#nammasociety-modal-test-status').html('<span class="' + textClass + ' fw-bold"><i class="bi ' + icon + ' me-1"></i>' + (res.data.message || 'Test alert recorded!') + '</span>');
                        } else {
                            $('#nammasociety-modal-test-status').html('<span class="text-danger fw-bold"><i class="bi bi-exclamation-triangle-fill me-1"></i>' + (res.data && res.data.message ? res.data.message : 'Error sending test push.') + '</span>');
                        }
                    },
                    error: function (xhr) {
                        $btn.prop('disabled', false).html('<i class="bi bi-send me-1"></i>Send Test Alert');
                        const msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message)
                            ? xhr.responseJSON.data.message
                            : 'Network error occurred.';
                        $('#nammasociety-modal-test-status').html('<span class="text-danger fw-bold"><i class="bi bi-x-circle me-1"></i>' + msg + '</span>');
                    }
                });
            });
        }
    }

    $channelForm.on('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(this);

        NAMMASOCIETY.ajax({
            action: 'nammasociety51_save_channel_config',
            data: formData,
            successMessage: 'Channel configuration saved!',
            reload: true,
            onSuccess: function () {
                if (channelModal) channelModal.hide();
            }
        });
    });

    // 3. Channel Toggles
    $('.nammasociety-channel-toggle').on('change', function () {
        const channel = $(this).data('channel');
        const active = $(this).is(':checked') ? 1 : 0;

        NAMMASOCIETY.ajax({
            action: 'nammasociety51_toggle_channel',
            data: {
                channel: channel,
                active: active,
                _ajax_nonce: nammasociety51RequestNonce
            }
        });
    });

    // 4. Event Mapping
    $('.nammasociety-mapping-toggle').on('change', function () {
        const event = $(this).data('event');
        const channel = $(this).data('channel');

        NAMMASOCIETY.ajax({
            action: 'nammasociety51_update_event_mapping',
            data: {
                event: event,
                channel: channel,
                enabled: $(this).is(':checked') ? 1 : 0,
                _ajax_nonce: nammasociety51RequestNonce
            }
        });
    });

    // 5. Template Editing
    $('.nammasociety-edit-template').on('click', function () {
        const id = $(this).data('id');

        NAMMASOCIETY.ajax({
            action: 'nammasociety51_get_template',
            data: {
                id: id,
                _ajax_nonce: nammasociety51RequestNonce
            },
            onSuccess: function (tpl) {
                if (templateModal) {
                    $('#nammasociety-template-id').val(tpl.id);
                    $('#nammasociety-template-event-name').text(tpl.event_slug.replace(/_/g, ' '));
                    $('#nammasociety-template-subject').val(tpl.subject);
                    $('#nammasociety-template-content').val(tpl.content);

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

        NAMMASOCIETY.ajax({
            action: 'nammasociety51_save_template',
            data: formData,
            successMessage: 'Notification template saved!',
            reload: true,
            onSuccess: function () {
                if (templateModal) templateModal.hide();
            }
        });
    });

    // 6. Registered Devices & Push Telemetry
    let registeredDevicesCache = [];

    function getAjaxUrl() {
        if (typeof nammasociety51NotificationsVars !== 'undefined' && nammasociety51NotificationsVars.ajaxUrl) {
            return nammasociety51NotificationsVars.ajaxUrl;
        }
        if (typeof ajaxurl !== 'undefined') {
            return ajaxurl;
        }
        return '/wp-admin/admin-ajax.php';
    }

    function getRequestNonce() {
        if (typeof nammasociety51NotificationsVars !== 'undefined' && nammasociety51NotificationsVars.nonce) {
            return nammasociety51NotificationsVars.nonce;
        }
        if (typeof nammasociety51RequestNonce !== 'undefined') {
            return nammasociety51RequestNonce;
        }
        return '';
    }

    function fetchRegisteredDevices() {
        const $tbody = $('#nammasociety-devices-table-body');
        const $refreshBtn = $('#btn-refresh-devices');
        if (!$tbody.length) return;

        $refreshBtn.prop('disabled', true).find('i').addClass('spin');
        if (!registeredDevicesCache.length) {
            $tbody.html(`
                <tr>
                    <td colspan="6" class="text-center py-5 text-muted">
                        <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                        Loading registered mobile devices...
                    </td>
                </tr>
            `);
        }

        $.ajax({
            url: getAjaxUrl(),
            type: 'POST',
            data: {
                action: 'nammasociety51_get_registered_devices',
                _ajax_nonce: getRequestNonce()
            },
            success: function(res) {
                $refreshBtn.prop('disabled', false).find('i').removeClass('spin');
                if (res.success && res.data) {
                    registeredDevicesCache = res.data.devices || [];
                    renderKpis(res.data.kpi);
                    renderDevicesTable(registeredDevicesCache);
                } else {
                    $tbody.html(`
                        <tr>
                            <td colspan="6" class="text-center py-4 text-danger">
                                <i class="bi bi-exclamation-triangle me-1"></i> Failed to load devices: ${(res.data && res.data.message) || 'Unknown error'}
                            </td>
                        </tr>
                    `);
                }
            },
            error: function() {
                $refreshBtn.prop('disabled', false).find('i').removeClass('spin');
                $tbody.html(`
                    <tr>
                        <td colspan="6" class="text-center py-4 text-danger">
                            <i class="bi bi-x-circle me-1"></i> Network error loading registered devices.
                        </td>
                    </tr>
                `);
            }
        });
    }

    function renderKpis(kpi) {
        if (!kpi) return;
        $('#nammasociety-kpi-total-devices').text(kpi.total_devices || 0);
        $('#nammasociety-kpi-active-devices').text(kpi.active_devices || 0);
        $('#nammasociety-kpi-total-pushes').text(kpi.total_pushes || 0);
        $('#nammasociety-kpi-total-societies').text(kpi.total_societies || 1);
        $('#nammasociety-device-count-badge').html(`<i class="bi bi-phone me-1"></i> ${kpi.total_devices || 0} Connected`);
    }

    function renderDevicesTable(devices) {
        const $tbody = $('#nammasociety-devices-table-body');
        if (!$tbody.length) return;

        if (!devices || devices.length === 0) {
            $tbody.html(`
                <tr>
                    <td colspan="6" class="text-center py-5 text-muted">
                        <div class="mb-2"><i class="bi bi-phone-vibrate text-muted" style="font-size: 2.5rem;"></i></div>
                        <div class="fw-bold text-dark mb-1">No Mobile Devices Registered Yet</div>
                        <p class="x-small text-muted mb-0">Devices register automatically when residents or admins log into the Namma Society mobile app.</p>
                    </td>
                </tr>
            `);
            return;
        }

        let html = '';
        devices.forEach(function(d) {
            const isAndroid = (d.platform === 'android');
            const platformIcon = isAndroid 
                ? '<i class="bi bi-android2 text-success fs-5"></i>' 
                : '<i class="bi bi-apple text-dark fs-5"></i>';
            const deviceTitle = d.device_model ? d.device_model : (d.device_name || 'Mobile Device');

            html += `
                <tr id="device-row-${d.id}">
                    <td class="ps-4 py-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="p-2 rounded-3 bg-light d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                                ${platformIcon}
                            </div>
                            <div>
                                <div class="fw-bold text-dark">${deviceTitle}</div>
                                <div class="x-small text-muted">
                                    <span class="text-capitalize">${d.platform}</span> • App v${d.app_version}
                                    ${d.ip_address ? ' • <span class="font-monospace">' + d.ip_address + '</span>' : ''}
                                </div>
                                <div class="mt-1">
                                    <span class="badge bg-light text-secondary font-monospace" style="font-size: 9px;">${d.token_preview}</span>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td class="py-3">
                        <div class="d-flex align-items-center gap-1 mb-1">
                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary-subtle fw-bold" style="font-size: 10px;">#${d.society_id}</span>
                            <span class="fw-bold text-dark small">${d.society_name}</span>
                        </div>
                        <div class="x-small text-muted">
                            <i class="bi bi-door-open me-1"></i>${d.unit_display}
                        </div>
                    </td>
                    <td class="py-3">
                        <div class="fw-bold text-dark">${d.display_name}</div>
                        <div class="x-small text-muted">
                            ${d.user_login ? '@' + d.user_login : (d.user_email || 'Resident')}
                        </div>
                    </td>
                    <td class="py-3">
                        <div class="d-flex flex-column gap-1">
                            <div>
                                <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle fw-bold" style="font-size: 11px;">
                                    <i class="bi bi-send-check me-1"></i><span class="device-pushes-count" data-id="${d.id}">${d.total_pushes_sent}</span> Dispatched
                                </span>
                            </div>
                            <div class="x-small text-muted">
                                <i class="bi bi-clock-history me-1"></i>${d.last_dispatched_at ? d.last_dispatched_diff : 'Never sent'}
                            </div>
                            ${d.last_push_title ? '<div class="x-small text-truncate text-secondary" style="max-width: 170px;" title="' + d.last_push_title + '"><i class="bi bi-chat-left-quote me-1"></i>' + d.last_push_title + '</div>' : ''}
                        </div>
                    </td>
                    <td class="py-3">
                        <div class="small text-slate-700">${d.last_seen}</div>
                        <div>
                            ${d.is_active ? '<span class="badge bg-success-subtle text-success border border-success-subtle x-small">Active</span>' : '<span class="badge bg-secondary-subtle text-secondary x-small">Inactive</span>'}
                        </div>
                    </td>
                    <td class="pe-4 py-3 text-end text-nowrap">
                        <button type="button" class="btn btn-sm btn-outline-primary rounded-3 btn-ping-device me-1 shadow-none" data-id="${d.id}" data-name="${deviceTitle}" title="Dispatch Test Push to this device">
                            <i class="bi bi-send-fill me-1"></i>Test Ping
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger rounded-3 btn-delete-device shadow-none" data-id="${d.id}" data-name="${deviceTitle}" title="Remove device">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        });

        $tbody.html(html);
    }

    // Refresh button
    $('#btn-refresh-devices').on('click', function() {
        fetchRegisteredDevices();
    });

    // Client-side search / filter
    $('#nammasociety-devices-search').on('input', function() {
        const query = $(this).val().toLowerCase().trim();
        if (!query) {
            renderDevicesTable(registeredDevicesCache);
            return;
        }

        const filtered = registeredDevicesCache.filter(function(d) {
            return (
                (d.display_name && d.display_name.toLowerCase().includes(query)) ||
                (d.user_login && d.user_login.toLowerCase().includes(query)) ||
                (d.device_name && d.device_name.toLowerCase().includes(query)) ||
                (d.device_model && d.device_model.toLowerCase().includes(query)) ||
                (d.society_name && d.society_name.toLowerCase().includes(query)) ||
                (d.society_id && String(d.society_id).toLowerCase().includes(query)) ||
                (d.unit_display && d.unit_display.toLowerCase().includes(query)) ||
                (d.flat_no && d.flat_no.toLowerCase().includes(query)) ||
                (d.platform && d.platform.toLowerCase().includes(query))
            );
        });

        renderDevicesTable(filtered);
    });

    // Test Ping Action
    $(document).on('click', '.btn-ping-device', function() {
        const $btn = $(this);
        const deviceId = $btn.data('id');
        const deviceName = $btn.data('name') || 'Device';
        const $feedback = $('#nammasociety-ping-feedback');

        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Pinging...');
        $feedback.slideUp();

        $.ajax({
            url: getAjaxUrl(),
            type: 'POST',
            data: {
                action: 'nammasociety51_ping_device',
                device_id: deviceId,
                title: '🔔 Namma Society Ping: ' + deviceName,
                body: 'Direct diagnostic test notification received at ' + new Date().toLocaleTimeString() + '!',
                _ajax_nonce: getRequestNonce()
            },
            success: function(res) {
                $btn.prop('disabled', false).html('<i class="bi bi-send-fill me-1"></i>Test Ping');
                if (res.success) {
                    $feedback.html(`
                        <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm border-0 d-flex align-items-center gap-2 m-0 p-3">
                            <i class="bi bi-check-circle-fill fs-5 text-success"></i>
                            <div>
                                <strong>Ping Dispatched!</strong> Notification was successfully routed to <em>${deviceName}</em>.
                            </div>
                            <button type="button" class="btn-close shadow-none" data-bs-dismiss="alert"></button>
                        </div>
                    `).slideDown();

                    // Increment local counter badge
                    const $countEl = $(`.device-pushes-count[data-id="${deviceId}"]`);
                    if ($countEl.length) {
                        const current = parseInt($countEl.text(), 10) || 0;
                        $countEl.text(current + 1);
                    }
                    // Auto-refresh roster in 1.5s to pull full telemetry
                    setTimeout(fetchRegisteredDevices, 1500);
                } else {
                    $feedback.html(`
                        <div class="alert alert-danger alert-dismissible fade show rounded-4 shadow-sm border-0 d-flex align-items-center gap-2 m-0 p-3">
                            <i class="bi bi-exclamation-triangle-fill fs-5 text-danger"></i>
                            <div>
                                <strong>Ping Failed:</strong> ${(res.data && res.data.message) || 'Unknown error'}
                            </div>
                            <button type="button" class="btn-close shadow-none" data-bs-dismiss="alert"></button>
                        </div>
                    `).slideDown();
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).html('<i class="bi bi-send-fill me-1"></i>Test Ping');
                const errMsg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) || 'Network error occurred.';
                $feedback.html(`
                    <div class="alert alert-danger alert-dismissible fade show rounded-4 shadow-sm border-0 d-flex align-items-center gap-2 m-0 p-3">
                        <i class="bi bi-x-circle-fill fs-5 text-danger"></i>
                        <div><strong>Network Error:</strong> ${errMsg}</div>
                        <button type="button" class="btn-close shadow-none" data-bs-dismiss="alert"></button>
                    </div>
                `).slideDown();
            }
        });
    });

    // Delete Device Action
    $(document).on('click', '.btn-delete-device', function() {
        const $btn = $(this);
        const deviceId = $btn.data('id');
        const deviceName = $btn.data('name') || 'Device';

        if (!confirm(`Are you sure you want to remove ${deviceName} from the registered push tokens? The app will need to reconnect to receive pushes.`)) {
            return;
        }

        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

        $.ajax({
            url: getAjaxUrl(),
            type: 'POST',
            data: {
                action: 'nammasociety51_delete_registered_device',
                device_id: deviceId,
                _ajax_nonce: getRequestNonce()
            },
            success: function(res) {
                if (res.success) {
                    $(`#device-row-${deviceId}`).fadeOut(300, function() {
                        $(this).remove();
                        fetchRegisteredDevices();
                    });
                } else {
                    alert((res.data && res.data.message) || 'Failed to remove device.');
                    $btn.prop('disabled', false).html('<i class="bi bi-trash"></i>');
                }
            },
            error: function() {
                alert('Network error removing device.');
                $btn.prop('disabled', false).html('<i class="bi bi-trash"></i>');
            }
        });
    });

    // Auto-load devices when Registered Devices accordion is opened
    $('#collapseDevices').on('show.bs.collapse', function() {
        fetchRegisteredDevices();
    });

    // Also trigger initial load if communication tab is active on load or clicked
    $('#tab-btn-communication').on('click', function() {
        setTimeout(fetchRegisteredDevices, 200);
    });

    if ($('#accordion-registered-devices').length) {
        setTimeout(fetchRegisteredDevices, 500);
    }
});

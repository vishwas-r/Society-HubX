jQuery(document).ready(function ($) {
    // 1. Initialize Modals
    const channelModalNode = document.getElementById('sgvx-channel-modal');
    const templateModalNode = document.getElementById('sgvx-template-modal');

    const channelModal = channelModalNode ? new bootstrap.Modal(channelModalNode) : null;
    const templateModal = templateModalNode ? new bootstrap.Modal(templateModalNode) : null;

    const $channelForm = $('#sgvx-channel-form');
    const $templateForm = $('#sgvx-template-form');
    const $fieldsContainer = $('#sgvx-channel-settings-fields');

    // 2. Channel Configuration
    $('.sgvx-configure-channel').on('click', function () {
        const channel = $(this).data('channel');
        $('#sgvx-modal-channel-name').text(channel.charAt(0).toUpperCase() + channel.slice(1));
        $('#sgvx-modal-channel-slug').val(channel);

        // Fetch current config via SGVX.ajax
        SGVX.ajax({
            action: 'sgvx51_get_channel_config',
            data: {
                channel: channel,
                _ajax_nonce: sgvx51RequestNonce
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
                    <select class="form-select rounded-3" name="config[method]">
                        <option value="wp_mail" ${config.method === 'wp_mail' ? 'selected' : ''}>WordPress Default (wp_mail)</option>
                        <option value="gmail" ${config.method === 'gmail' ? 'selected' : ''}>Gmail API (OAuth2)</option>
                        <option value="smtp" ${config.method === 'smtp' ? 'selected' : ''}>Custom SMTP</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-slate-700">Sender Name</label>
                    <input type="text" class="form-control rounded-3" name="config[from_name]" value="${config.from_name || ''}" placeholder="Society GoVernX">
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
        }

        $fieldsContainer.html(html);
    }

    $channelForm.on('submit', function (e) {
        e.preventDefault();
        const formData = $(this).serializeArray();

        SGVX.ajax({
            action: 'sgvx51_save_channel_config',
            data: formData,
            successMessage: 'Channel configuration saved!',
            reload: true,
            onSuccess: function () {
                if (channelModal) channelModal.hide();
            }
        });
    });

    // 3. Channel Toggles
    $('.sgvx-channel-toggle').on('change', function () {
        const channel = $(this).data('channel');
        const active = $(this).is(':checked') ? 1 : 0;

        SGVX.ajax({
            action: 'sgvx51_toggle_channel',
            data: {
                channel: channel,
                active: active,
                _ajax_nonce: sgvx51RequestNonce
            }
        });
    });

    // 4. Event Mapping
    $('.sgvx-mapping-toggle').on('change', function () {
        const event = $(this).data('event');
        const channel = $(this).data('channel');

        SGVX.ajax({
            action: 'sgvx51_update_event_mapping',
            data: {
                event: event,
                channel: channel,
                enabled: $(this).is(':checked') ? 1 : 0,
                _ajax_nonce: sgvx51RequestNonce
            }
        });
    });

    // 5. Template Editing
    $('.sgvx-edit-template').on('click', function () {
        const id = $(this).data('id');

        SGVX.ajax({
            action: 'sgvx51_get_template',
            data: {
                id: id,
                _ajax_nonce: sgvx51RequestNonce
            },
            onSuccess: function (tpl) {
                if (templateModal) {
                    $('#sgvx-template-id').val(tpl.id);
                    $('#sgvx-template-event-name').text(tpl.event_slug.replace(/_/g, ' '));
                    $('#sgvx-template-subject').val(tpl.subject);
                    $('#sgvx-template-content').val(tpl.content);

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
        const formData = $(this).serializeArray();

        SGVX.ajax({
            action: 'sgvx51_save_template',
            data: formData,
            successMessage: 'Notification template saved!',
            reload: true,
            onSuccess: function () {
                if (templateModal) templateModal.hide();
            }
        });
    });
});

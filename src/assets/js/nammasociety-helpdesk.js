/**
 * NAMMASOCIETY Helpdesk & Field Operations JS
 */
(function ($) {
    'use strict';

    let currentTab = 'all';
    let newTicketModal = null;
    let assignModal = null;
    let verifyOtpModal = null;
    let detailModal = null;

    function getNonce() {
        return typeof nammasociety51HelpdeskVars !== 'undefined' ? nammasociety51HelpdeskVars.nonce : (document.querySelector('input[name="_wpnonce"]') ? document.querySelector('input[name="_wpnonce"]').value : '');
    }

    // --- Tab Switching ---
    window.switchHelpdeskTab = function (tab) {
        currentTab = tab;
        $('.helpdesk-tab-btn').each(function () {
            const $btn = $(this);
            if ($btn.data('tab') === tab) {
                $btn.addClass('active border-primary text-primary fw-bold')
                    .removeClass('border-transparent text-muted fw-semibold');
            } else {
                $btn.removeClass('active border-primary text-primary fw-bold')
                    .addClass('border-transparent text-muted fw-semibold');
            }
        });
        applyHelpdeskFilters();
    };

    // --- Filter & Search ---
    window.applyHelpdeskFilters = function () {
        const searchVal = $('#helpdesk-search-input').val().trim().toLowerCase();
        const categoryVal = $('#filter-category').val().toLowerCase();
        const priorityVal = $('#filter-priority').val().toLowerCase();

        $('.ticket-row').each(function () {
            const $row = $(this);
            const status = $row.data('status');
            const category = $row.data('category');
            const priority = $row.data('priority');
            const search = $row.data('search');

            let matchesTab = false;
            if (currentTab === 'all') {
                matchesTab = true;
            } else if (currentTab === 'open') {
                matchesTab = (status === 'open' || status === 'assigned');
            } else if (currentTab === 'in_progress') {
                matchesTab = (status === 'in_progress');
            } else if (currentTab === 'resolved') {
                matchesTab = (status === 'resolved');
            } else if (currentTab === 'closed') {
                matchesTab = (status === 'closed');
            }

            let matchesCat = (categoryVal === 'all') || (category === categoryVal);
            let matchesPri = (priorityVal === 'all') || (priority === priorityVal);
            let matchesSearch = !searchVal || (search && search.indexOf(searchVal) !== -1);

            if (matchesTab && matchesCat && matchesPri && matchesSearch) {
                $row.show();
            } else {
                $row.hide();
            }
        });
    };

    $(document).on('change', '#ticket-flat', function () {
        const sel = $(this).find(':selected');
        $('#ticket-block').val(sel.data('block') || '');
    });

    // --- Modal Helpers ---
    window.openNewTicketModal = function () {
        const el = document.getElementById('newTicketModal');
        if (!el) return;
        newTicketModal = bootstrap.Modal.getOrCreateInstance(el);
        document.getElementById('new-ticket-form').reset();
        $('#ticket-block').val('');
        newTicketModal.show();
    };

    window.openAssignModal = function (ticketId, ticketNum, currentTechId) {
        const el = document.getElementById('assignModal');
        if (!el) return;
        assignModal = bootstrap.Modal.getOrCreateInstance(el);
        document.getElementById('assign-ticket-id').value = ticketId;
        document.getElementById('assign-ticket-num').textContent = ticketNum;
        if (currentTechId) {
            document.getElementById('assign-tech-select').value = currentTechId;
        } else {
            document.getElementById('assign-tech-select').value = '';
        }
        assignModal.show();
    };

    window.openVerifyOtpModal = function (ticketId, ticketNum) {
        const el = document.getElementById('verifyOtpModal');
        if (!el) return;
        verifyOtpModal = bootstrap.Modal.getOrCreateInstance(el);
        document.getElementById('otp-ticket-id').value = ticketId;
        document.getElementById('otp-ticket-num').textContent = ticketNum;
        document.getElementById('closure-otp-input').value = '';
        verifyOtpModal.show();
    };

    // --- View Timeline & Details ---
    window.viewTicketTimeline = function (ticketId) {
        const el = document.getElementById('ticketDetailModal');
        if (!el) return;
        detailModal = bootstrap.Modal.getOrCreateInstance(el);

        $('#reply-ticket-id').val(ticketId);
        $('#reply-message').val('');
        $('#detail-timeline').html('<div class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm" role="status"></span> Loading conversation...</div>');

        detailModal.show();

        NAMMASOCIETY.ajax({
            action: 'nammasociety51_helpdesk_get_details',
            data: {
                ticket_id: ticketId,
                _wpnonce: getNonce()
            },
            showOverlay: false,
            onSuccess: function (res) {
                if (res && res.ticket) {
                    const t = res.ticket;
                    $('#detail-ticket-number').text(t.ticket_number);
                    $('#detail-ticket-subject').text(t.subject);
                    $('#detail-ticket-desc').text(t.description || 'No additional description provided.');
                    
                    let flatDisplay = t.flat_no || 'Common Area';
                    if (t.flat_no && t.flat_no !== 'Common Area') {
                        const cleanB = String(t.block || '').replace(/^block\s*/i, '').trim();
                        if (cleanB && !flatDisplay.toLowerCase().includes(cleanB.toLowerCase())) {
                            flatDisplay = cleanB + ' - ' + flatDisplay;
                        }
                    }
                    $('#detail-ticket-flat').text(flatDisplay);
                    $('#detail-ticket-category').text(t.category || 'General');
                    $('#detail-ticket-tech').text(t.technician_name || 'Unassigned');
                    $('#detail-ticket-sla').text(t.sla_text || 'None');

                    // Status badge
                    let badgeClass = 'bg-warning text-warning';
                    if (t.status === 'in_progress') badgeClass = 'bg-info text-info';
                    else if (t.status === 'resolved') badgeClass = 'bg-primary text-primary';
                    else if (t.status === 'closed') badgeClass = 'bg-success text-success';
                    $('#detail-ticket-status-badge').html(`<span class="badge ${badgeClass} bg-opacity-10 border px-2.5 py-1 rounded-pill fw-bold text-uppercase" style="font-size: 10px;">${t.status}</span>`);

                    // OTP display
                    if (t.closure_otp && (t.status === 'resolved' || t.status === 'in_progress' || t.status === 'closed')) {
                        $('#detail-otp-val').text(t.closure_otp);
                        $('#detail-otp-banner').removeClass('d-none');
                    } else {
                        $('#detail-otp-banner').addClass('d-none');
                    }

                    // Photos
                    if (t.photos_list && t.photos_list.length > 0) {
                        let photoHtml = '';
                        t.photos_list.forEach(p => {
                            photoHtml += `<a href="${p}" target="_blank" class="rounded-3 overflow-hidden border shadow-sm" style="width: 60px; height: 60px; display: inline-block;">
                                <img src="${p}" class="w-100 h-100 object-fit-cover" alt="Photo">
                            </a>`;
                        });
                        $('#detail-photos-list').html(photoHtml);
                        $('#detail-photos-container').removeClass('d-none');
                    } else {
                        $('#detail-photos-container').addClass('d-none');
                    }

                    // Timeline replies
                    let timelineHtml = '';
                    if (res.replies && res.replies.length > 0) {
                        res.replies.forEach(r => {
                            const isInternal = parseInt(r.is_internal_note, 10) === 1;
                            const bubbleClass = isInternal ? 'bg-warning bg-opacity-10 border-warning border-opacity-50 text-dark' : 'bg-light border text-dark';
                            const badgeNote = isInternal ? '<span class="badge bg-warning text-dark border border-warning px-2 py-0.5 rounded-pill me-1" style="font-size: 9px;"><i class="bi bi-lock-fill"></i> Internal Note</span>' : '';

                            timelineHtml += `
                                <div class="p-3 rounded-3 border ${bubbleClass}">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="fw-bold small text-dark">${r.author_name} ${badgeNote}</span>
                                        <span class="text-muted" style="font-size: 10px;">${r.formatted_time}</span>
                                    </div>
                                    <div class="small">${r.message}</div>
                                </div>
                            `;
                        });
                    } else {
                        timelineHtml = '<div class="text-center py-3 text-muted small">No replies or updates yet. Add one below.</div>';
                    }
                    $('#detail-timeline').html(timelineHtml);
                }
            }
        });
    };

    // --- Quick Status Change ---
    window.quickStatusChange = function (ticketId, status) {
        NAMMASOCIETY.ajax({
            action: 'nammasociety51_helpdesk_update_status',
            data: {
                ticket_id: ticketId,
                status: status,
                _wpnonce: getNonce()
            },
            successMessage: 'Ticket status updated to ' + status.toUpperCase(),
            reload: true
        });
    };

    // --- Delete Ticket ---
    window.deleteTicket = function (ticketId) {
        if (!confirm('Are you sure you want to delete this ticket?')) return;
        NAMMASOCIETY.ajax({
            action: 'nammasociety51_helpdesk_delete_ticket',
            data: {
                ticket_id: ticketId,
                _wpnonce: getNonce()
            },
            successMessage: 'Ticket deleted successfully',
            reload: true
        });
    };

    // --- Init ---
    $(function () {
        $('#helpdesk-search-input').on('input', applyHelpdeskFilters);
        $('#filter-category, #filter-priority').on('change', applyHelpdeskFilters);

        $(document).on('click', '#helpdeskTabs .helpdesk-tab-btn', function (e) {
            e.preventDefault();
            switchHelpdeskTab($(this).data('tab'));
        });

        // New Ticket Form Submit
        const $newForm = $('#new-ticket-form');
        if ($newForm.length) {
            $newForm.on('submit', function (e) {
                e.preventDefault();
                const formData = new FormData($newForm[0]);

                NAMMASOCIETY.ajax({
                    action: 'nammasociety51_helpdesk_create',
                    data: formData,
                    loadingButton: $newForm.find('button[type="submit"]'),
                    successMessage: 'Ticket raised successfully',
                    reload: true,
                    onSuccess: function () {
                        if (newTicketModal) newTicketModal.hide();
                    }
                });
            });
        }

        // Assign Technician Form Submit
        const $assignForm = $('#assign-technician-form');
        if ($assignForm.length) {
            $assignForm.on('submit', function (e) {
                e.preventDefault();
                const formData = new FormData($assignForm[0]);

                NAMMASOCIETY.ajax({
                    action: 'nammasociety51_helpdesk_assign',
                    data: formData,
                    loadingButton: $assignForm.find('button[type="submit"]'),
                    successMessage: 'Technician assigned successfully',
                    reload: true,
                    onSuccess: function () {
                        if (assignModal) assignModal.hide();
                    }
                });
            });
        }

        // Verify OTP Form Submit
        const $otpForm = $('#verify-otp-form');
        if ($otpForm.length) {
            $otpForm.on('submit', function (e) {
                e.preventDefault();
                const formData = new FormData($otpForm[0]);

                NAMMASOCIETY.ajax({
                    action: 'nammasociety51_helpdesk_verify_otp',
                    data: formData,
                    loadingButton: $otpForm.find('button[type="submit"]'),
                    successMessage: 'OTP verified! Ticket closed successfully',
                    reload: true,
                    onSuccess: function () {
                        if (verifyOtpModal) verifyOtpModal.hide();
                    }
                });
            });
        }

        // Add Reply Form Submit
        const $replyForm = $('#add-reply-form');
        if ($replyForm.length) {
            $replyForm.on('submit', function (e) {
                e.preventDefault();
                const ticketId = $('#reply-ticket-id').val();
                const formData = new FormData($replyForm[0]);

                NAMMASOCIETY.ajax({
                    action: 'nammasociety51_helpdesk_add_reply',
                    data: formData,
                    loadingButton: $replyForm.find('button[type="submit"]'),
                    successMessage: 'Reply posted',
                    onSuccess: function () {
                        $('#reply-message').val('');
                        $('#internalNoteCheck').prop('checked', false);
                        window.viewTicketTimeline(ticketId);
                    }
                });
            });
        }
    });

})(jQuery);

(function ($) {
    'use strict';

    // State
    let expensesChart = null;
    let paymentChart = null;
    let dirFuse = null;
    let facFuse = null;
    let bookingFuse = null;
    let dirActiveFilter = 'all';

    // --- Chart Logic ---
    function initCharts() {
        if (!window.CanvasJS || !window.sgvxDashboardData) {
            return;
        }

        // 1. Society Expense Trend
        const expenseContainer = document.getElementById('expensesChart');
        if (expenseContainer && window.sgvxDashboardData.expenseChartData) {
            const expenseData = window.sgvxDashboardData.expenseChartData;
            const dps = [];

            for (const [label, y] of Object.entries(expenseData)) {
                dps.push({ label: label, y: y });
            }

            if (dps.length > 0) {
                expensesChart = new CanvasJS.Chart("expensesChart", {
                    animationEnabled: true,
                    theme: "light2",
                    title: {
                        text: "Monthly Society Expense Trend",
                        fontSize: 16,
                        fontFamily: "Inter, sans-serif"
                    },
                    axisY: {
                        title: "Amount (₹)",
                        includeZero: true,
                        prefix: "₹",
                        valueFormatString: "#,##,##0"
                    },
                    data: [{
                        type: "column",
                        color: "#6366f1",
                        indexLabel: "{y}",
                        yValueFormatString: "₹#,##,##0",
                        dataPoints: dps
                    }]
                });

                // Only render if tab is visible
                const expensesTab = document.getElementById('tab-expenses');
                if (expensesTab && !expensesTab.classList.contains('d-none')) {
                    expensesChart.render();
                }
            }
        }

        // 2. Resident Payment History
        const paymentContainer = document.getElementById('paymentHistoryChart');
        if (paymentContainer && window.sgvxDashboardData.paymentHistory) {
            const paymentData = window.sgvxDashboardData.paymentHistory;
            const dps = [];

            for (const [label, y] of Object.entries(paymentData)) {
                dps.push({ label: label, y: y });
            }

            if (dps.length > 0) {
                paymentChart = new CanvasJS.Chart("paymentHistoryChart", {
                    animationEnabled: true,
                    theme: "light2",
                    title: {
                        text: "My Payment History",
                        fontSize: 16,
                        fontFamily: "Inter, sans-serif"
                    },
                    axisY: {
                        title: "Amount (₹)",
                        includeZero: true,
                        prefix: "₹",
                        valueFormatString: "#,##,##0"
                    },
                    data: [{
                        type: "area",
                        color: "#10b981",
                        markerSize: 8,
                        yValueFormatString: "₹#,##,##0",
                        dataPoints: dps
                    }]
                });

                // Render immediately (usually home tab is visible)
                paymentChart.render();
            }
        }
    }

    // --- Poll Logic ---
    function togglePollEdit(e) {
        e.preventDefault();
        const btn = e.currentTarget;
        const pollId = btn.dataset.pollId;
        const showForm = btn.dataset.showForm === 'true';

        const formDiv = document.getElementById('poll-vote-form-' + pollId);
        const resultsDiv = document.getElementById('poll-results-' + pollId);

        if (formDiv && resultsDiv) {
            if (showForm) {
                formDiv.classList.remove('d-none');
                resultsDiv.classList.add('d-none');
            } else {
                formDiv.classList.add('d-none');
                resultsDiv.classList.remove('d-none');
            }
        }
    }

    // --- Event Listeners ---
    document.addEventListener('DOMContentLoaded', function () {
        // Init Charts
        initCharts();

        // Hook into Tab Switching for Chart Re-render
        const btnExpenses = document.getElementById('btn-tab-expenses');
        if (btnExpenses) {
            btnExpenses.addEventListener('click', function () {
                setTimeout(function () {
                    if (expensesChart) {
                        expensesChart.render();
                    }
                }, 100);
            });
        }

        const btnHome = document.getElementById('btn-tab-home');
        if (btnHome) {
            btnHome.addEventListener('click', function () {
                setTimeout(function () {
                    if (paymentChart) {
                        paymentChart.render();
                    }
                }, 100);
            });
        }

        const btnAccounts = document.getElementById('btn-tab-accounts');
        if (btnAccounts) {
            btnAccounts.addEventListener('click', function () {
                setTimeout(function () {
                    if (paymentChart) {
                        paymentChart.render();
                    }
                }, 100);
            });
        }

        // Global Event Delegation for Dynamic Elements
        document.body.addEventListener('click', function (e) {
            // Poll Edit Toggles
            const pollToggle = e.target.closest('.js-toggle-poll-edit');
            if (pollToggle) {
                togglePollEdit({ ...e, currentTarget: pollToggle, preventDefault: () => e.preventDefault() });
            }
        });

        // Hook into modal hidden events to reset forms
        const familyModal = document.getElementById('familyModal');
        if (familyModal) {
            familyModal.addEventListener('hidden.bs.modal', window.resetFamilyModal);
        }

        const vehicleModal = document.getElementById('vehicleModal');
        if (vehicleModal) {
            vehicleModal.addEventListener('hidden.bs.modal', window.resetVehicleModal);
        }

        const helpModal = document.getElementById('helpModal');
        if (helpModal) {
            helpModal.addEventListener('hidden.bs.modal', window.resetHelpModal);
        }

        // Community Directory Listeners
        initDirectoryCardListeners();
        initDirectoryFilterListeners();

        const dirSearch = document.getElementById('dir-search');
        if (dirSearch) {
            dirSearch.addEventListener('input', window.filterDirectory);
            dirSearch.addEventListener('focus', function () {
                if (window.sgvxCreateFuse) dirFuse = window.sgvxCreateFuse('.dir-card');
            });
        }

        // Facility Search (Available Facilities)
        const facilitySearch = document.getElementById('facility-dashboard-search');
        if (facilitySearch) {
            facilitySearch.addEventListener('input', function () {
                const val = this.value.trim().toLowerCase();
                if (!facFuse && window.sgvxCreateFuse) {
                    facFuse = window.sgvxCreateFuse('.facility-card');
                }

                const matches = val && window.sgvxGetFuzzyMatches ? window.sgvxGetFuzzyMatches(facFuse, val) : null;

                document.querySelectorAll('.facility-card').forEach(card => {
                    const isMatch = !val || (matches && matches.has(card));
                    if (isMatch) {
                        card.classList.remove('d-none');
                        card.classList.add('d-flex');
                    } else {
                        card.classList.add('d-none');
                        card.classList.remove('d-flex');
                    }
                });
            });
            facilitySearch.addEventListener('focus', function () {
                if (window.sgvxCreateFuse) facFuse = window.sgvxCreateFuse('.facility-card');
            });
        }

        // Booking Search (My Bookings)
        const bookingSearch = document.getElementById('booking-dashboard-search');
        if (bookingSearch) {
            bookingSearch.addEventListener('input', function () {
                const val = this.value.trim().toLowerCase();
                if (!bookingFuse && window.sgvxCreateFuse) {
                    bookingFuse = window.sgvxCreateFuse('.booking-dash-row');
                }

                const matches = val && window.sgvxGetFuzzyMatches ? window.sgvxGetFuzzyMatches(bookingFuse, val) : null;

                document.querySelectorAll('.booking-dash-row').forEach(row => {
                    const isMatch = !val || (matches && matches.has(row));
                    if (isMatch) {
                        row.classList.remove('d-none');
                    } else {
                        row.classList.add('d-none');
                    }
                });
            });
            bookingSearch.addEventListener('focus', function () {
                if (window.sgvxCreateFuse) bookingFuse = window.sgvxCreateFuse('.booking-dash-row');
            });
        }
    });

    // --- Helper to open Modal (Tailwind/Custom) ---
    function openModal(modalId) {
        const m = document.getElementById(modalId);
        if (m) m.classList.remove('d-none');
    }

    // --- Reset Family Modal for Add ---
    window.resetFamilyModal = function () {
        const form = document.querySelector('#familyModal form');
        if (!form) return;

        // Reset form fields
        form.reset();

        // Remove hidden ID and type fields if they exist
        const idInput = form.querySelector('input[name="member_id"]');
        if (idInput) idInput.remove();

        const typeInput = form.querySelector('input[name="type"]');
        if (typeInput) typeInput.remove();

        // Reset action to add
        const actionInput = form.querySelector('input[name="action"]');
        if (actionInput) actionInput.value = 'sgvx51_add_family';

        // Ensure main _wpnonce points to add nonce
        const addNonceInput = form.querySelector('input[name="_wpnonce_add_family"]');
        if (addNonceInput) {
            let mainNonce = form.querySelector('input[name="_wpnonce"]');
            if (!mainNonce) {
                mainNonce = document.createElement('input');
                mainNonce.type = 'hidden';
                mainNonce.name = '_wpnonce';
                form.appendChild(mainNonce);
            }
            mainNonce.value = addNonceInput.value || '';
        }

        // Reset title and button
        const modalTitle = form.querySelector('.modal-title');
        if (modalTitle) modalTitle.innerText = 'Add Family Member';

        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) submitBtn.innerText = 'Add Member';
    };

    // --- Edit Handlers ---
    function handleEditFamily(btn) {
        const payload = JSON.parse(btn.dataset.payload);
        const form = document.querySelector('#familyModal form');

        // Populate inputs
        form.querySelector('[name="name"]').value = payload.name;
        form.querySelector('[name="relation"]').value = payload.relation;
        form.querySelector('[name="age"]').value = payload.age || '';

        // Optional fields
        const bloodGroupField = form.querySelector('[name="blood_group"]');
        if (bloodGroupField) bloodGroupField.value = payload.blood_group || '';

        const phoneField = form.querySelector('[name="phone"]');
        if (phoneField) phoneField.value = payload.phone || '';

        // Set action for edit
        form.querySelector('[name="action"]').value = 'sgvx51_edit_family';

        // Ensure member ID input exists (expected by handler as member_id)
        let idInput = form.querySelector('input[name="member_id"]');
        if (!idInput) {
            idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'member_id';
            form.appendChild(idInput);
        }
        idInput.value = payload.id;

        // Swap main _wpnonce value to the edit nonce so check_admin_referer passes
        const editNonceInput = form.querySelector('input[name="_wpnonce_edit_family"]');
        if (editNonceInput) {
            let mainNonce = form.querySelector('input[name="_wpnonce"]');
            if (!mainNonce) {
                mainNonce = document.createElement('input');
                mainNonce.type = 'hidden';
                mainNonce.name = '_wpnonce';
                form.appendChild(mainNonce);
            }
            mainNonce.value = editNonceInput.value || '';
        }

        // Ensure Type input exists
        let typeInput = form.querySelector('input[name="type"]');
        if (!typeInput) {
            typeInput = document.createElement('input');
            typeInput.type = 'hidden';
            typeInput.name = 'type';
            form.appendChild(typeInput);
        }
        typeInput.value = 'family'; // Force type family

        // Change Title
        const modalTitle = form.querySelector('.modal-title');
        if (modalTitle) modalTitle.innerText = 'Edit Family Member';


        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) submitBtn.innerText = 'Update';

        // Open modal using Bootstrap API
        const modalEl = document.getElementById('familyModal');
        if (modalEl) {
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
        }
    }

    function handleEditHelp(btn) {
        try {
            const payload = JSON.parse(btn.dataset.payload);
            const form = document.querySelector('#helpModal form');

            // Populate
            form.querySelector('[name="name"]').value = payload.name || '';
            form.querySelector('[name="role"]').value = payload.role || 'Maid';
            form.querySelector('[name="phone"]').value = payload.phone || '';

            const categoryField = form.querySelector('[name="category"]');
            if (categoryField) categoryField.value = payload.category || 'Support Staff';

            const sexField = form.querySelector('[name="sex"]');
            if (sexField) sexField.value = payload.sex || 'Male';

            const vhField = form.querySelector('[name="visiting_hours"]');
            if (vhField) vhField.value = payload.visiting_hours || '';

            const docUrlField = form.querySelector('[name="document_url"]');
            if (docUrlField) docUrlField.value = payload.document_url || '';

            const preview = document.getElementById('current-help-doc-preview');
            if (preview) {
                if (payload.document_url) {
                    preview.classList.remove('d-none');
                    preview.querySelector('a').href = payload.document_url;
                } else {
                    preview.classList.add('d-none');
                }
            }

            // Set Action
            form.querySelector('[name="action"]').value = 'sgvx51_edit_daily_help';

            // Set ID
            let idInput = form.querySelector('[name="help_id"]');
            if (!idInput) {
                idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'help_id';
                form.appendChild(idInput);
            }
            idInput.value = payload.id;

            const modalTitle = form.querySelector('.modal-title');
            if (modalTitle) modalTitle.innerText = 'Edit Daily Help';

            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) submitBtn.innerText = 'Update Help';

            // Open modal using Bootstrap API
            const modalEl = document.getElementById('helpModal');
            if (modalEl) {
                const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                modal.show();
            }
        } catch (e) { console.error('handleEditHelp Error:', e); }
    }

    // Helper to reset help modal
    window.resetHelpModal = function () {
        const form = document.querySelector('#helpModal form');
        if (!form) return;
        form.reset();
        form.querySelector('[name="action"]').value = 'sgvx51_add_daily_help';
        const idInput = form.querySelector('[name="help_id"]');
        if (idInput) idInput.value = '';
        const docUrlInput = form.querySelector('[name="document_url"]');
        if (docUrlInput) docUrlInput.value = '';

        const preview = document.getElementById('current-help-doc-preview');
        if (preview) preview.classList.add('d-none');

        const modalTitle = form.querySelector('.modal-title');
        if (modalTitle) modalTitle.innerText = 'Add Daily Help';
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) submitBtn.innerText = 'Save Help';
    };

    function handleEditVehicle(btn) {
        const payload = JSON.parse(btn.dataset.payload);
        const form = document.querySelector('#vehicleModal form');

        form.querySelector('[name="number"]').value = payload.number;
        form.querySelector('[name="type"]').value = payload.type;
        form.querySelector('[name="brand"]').value = payload.brand;
        form.querySelector('[name="model"]').value = payload.model;

        form.querySelector('[name="action"]').value = 'sgvx51_edit_vehicle_frontend';
        form.querySelector('[name="vehicle_id"]').value = payload.id;

        let modal = document.querySelector('#vehicleModal');
        modal.querySelector('.modal-title').innerText = 'Edit Vehicle';
        modal.querySelector('button[type="submit"]').innerText = 'Update';
        openModal('vehicleModal');
    }

    // --- Reset Vehicle Modal for Add ---
    window.resetVehicleModal = function () {
        const form = document.querySelector('#vehicleModal form');
        if (!form) return;

        // Reset form fields
        form.reset();

        // Clear vehicle_id field
        const idInput = form.querySelector('input[name="vehicle_id"]');
        if (idInput) idInput.value = '';

        // Reset action to add
        const actionInput = form.querySelector('input[name="action"]');
        if (actionInput) actionInput.value = 'sgvx51_add_vehicle_frontend';

        // Reset title and button
        const modal = document.querySelector('#vehicleModal');
        const modalTitle = modal.querySelector('.modal-title');
        if (modalTitle) modalTitle.innerText = 'Register Vehicle';

        const submitBtn = modal.querySelector('button[type="submit"]');
        if (submitBtn) submitBtn.innerText = 'Request Registration';
    };

    // --- Global Click Handler for Edits ---
    document.body.addEventListener('click', function (e) {
        let btn = e.target.closest('.js-edit-family');
        if (btn) { e.preventDefault(); handleEditFamily(btn); return; }

        btn = e.target.closest('.js-edit-help');
        if (btn) { e.preventDefault(); handleEditHelp(btn); return; }

        btn = e.target.closest('.js-edit-vehicle');
        if (btn) { e.preventDefault(); handleEditVehicle(btn); return; }
    });

    // Optional: Hook into global window.switchTab if it exists (legacy support)
    const originalSwitchTab = window.switchTab;
    if (typeof originalSwitchTab === 'function') {
        window.switchTab = function (tab, btn) {
            originalSwitchTab(tab, btn);
            if ((tab === 'tab-expenses' || tab === '#tab-expenses') && expensesChart) {
                setTimeout(function () { expensesChart.render(); }, 50);
            }
        };
    }

    // --- Directory Card Click Handler (Community Tab) ---
    function initDirectoryCardListeners() {
        const directoryGrid = document.getElementById('directory-grid');
        if (!directoryGrid) return;

        directoryGrid.addEventListener('click', function (e) {
            const dirCard = e.target.closest('.dir-card');
            if (dirCard) {
                e.preventDefault();
                openDirModal(dirCard);
            }
        });
    }

    // --- Open Directory Modal ---
    window.openDirModal = function (card) {
        if (!card || !card.dataset.json) return;

        try {
            const data = JSON.parse(card.dataset.json);
            const modal = document.getElementById('communityDetailModal');
            if (!modal) return;

            // Populate modal with data
            document.getElementById('cdm-flat').textContent = data.flat_no || '';
            document.getElementById('cdm-owner').textContent = data.owner || '';
            document.getElementById('cdm-members').textContent = data.members || '0';

            // Populate vehicles
            const vehiclesDiv = document.getElementById('cdm-vehicles');
            if (vehiclesDiv) {
                vehiclesDiv.innerHTML = '';
                if (data.vehicles && data.vehicles.length > 0) {
                    data.vehicles.forEach(function (v) {
                        const badge = document.createElement('div');
                        badge.className = 'badge bg-primary-subtle text-primary border border-primary-subtle fw-normal d-flex align-items-center gap-1';
                        badge.innerHTML = '<i class="bi bi-car-front" style="font-size:12px;"></i> ' + (v.number || 'N/A');
                        vehiclesDiv.appendChild(badge);
                    });
                } else {
                    vehiclesDiv.innerHTML = '<span class="text-muted small">None registered</span>';
                }
            }

            // Populate help staff
            const helpDiv = document.getElementById('cdm-help');
            if (helpDiv) {
                helpDiv.innerHTML = '';
                if (data.help && data.help.length > 0) {
                    data.help.forEach(function (h) {
                        const badge = document.createElement('div');
                        badge.className = 'badge bg-warning-subtle text-warning border border-warning-subtle fw-normal d-flex align-items-center gap-1';
                        badge.innerHTML = '<i class="bi bi-person-badge" style="font-size:12px;"></i> ' + (h.name || 'N/A') + ' (' + (h.role || 'Staff') + ')';
                        helpDiv.appendChild(badge);
                    });
                } else {
                    helpDiv.innerHTML = '<span class="text-muted small">None registered</span>';
                }
            }

            // Show modal using Bootstrap
            const bsModal = bootstrap.Modal.getOrCreateInstance(modal);
            bsModal.show();
        } catch (e) {
            console.error('Error opening directory modal:', e);
        }
    };

    // --- Consolidated Community Filter/Search ---
    window.applyCommunityFilters = function () {
        const searchInput = document.getElementById('dir-search');
        const searchTerm = searchInput ? searchInput.value.trim().toLowerCase() : '';
        const dirCards = document.querySelectorAll('.dir-card');

        if (!dirFuse && window.sgvxCreateFuse) {
            dirFuse = window.sgvxCreateFuse('.dir-card');
        }

        const fuzzyMatches = searchTerm && window.sgvxGetFuzzyMatches ? window.sgvxGetFuzzyMatches(dirFuse, searchTerm) : null;

        const query = searchTerm.toLowerCase();

        dirCards.forEach(function (card) {
            // 1. Check Search Match
            const matchesSearch = !searchTerm || (fuzzyMatches && fuzzyMatches.has(card));

            // 2. Check Tab Filter Match
            let matchesFilter = true;
            if (dirActiveFilter === 'vehicle') {
                matchesFilter = (card.dataset.hasVehicle === '1');
            } else if (dirActiveFilter === 'help') {
                matchesFilter = (card.dataset.hasHelp === '1');
            }

            const finalMatch = !!(matchesSearch && matchesFilter);

            // Apply visibility - Use Bootstrap d-none for consistency
            if (finalMatch) {
                card.classList.remove('d-none');
            } else {
                card.classList.add('d-none');
            }

        });

        if (searchTerm && fuzzyMatches) {
            console.log(`applyCommunityFilters: Found ${fuzzyMatches.size} matches for "${searchTerm}"`);
        }
    };

    window.filterDirFilter = function (filter) {
        dirActiveFilter = filter;
        const filterBtns = document.querySelectorAll('.dir-filter-btn');

        // Update button states
        filterBtns.forEach(function (btn) {
            if (btn.dataset.filter === filter) {
                btn.classList.remove('btn-light', 'text-secondary');
                btn.classList.add('btn-dark', 'active');
            } else {
                btn.classList.add('btn-light', 'text-secondary');
                btn.classList.remove('btn-dark', 'active');
            }
        });

        applyCommunityFilters();
    };

    // --- Directory Search Handler ---
    window.filterDirectory = function () {
        applyCommunityFilters();
    };

    // --- Directory Filter Button Listeners ---
    function initDirectoryFilterListeners() {
        const dirFiltersContainer = document.getElementById('dir-filters');
        if (!dirFiltersContainer) return;

        dirFiltersContainer.addEventListener('click', function (e) {
            const filterBtn = e.target.closest('.dir-filter-btn');
            if (filterBtn && filterBtn.dataset.filter) {
                e.preventDefault();
                filterDirFilter(filterBtn.dataset.filter);
            }
        });
    }

})(jQuery);
